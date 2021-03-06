<?php

namespace ride\library\router;


/**
 * Generic router implementation
 */
class GenericRouter extends AbstractRouter {

    /**
     * Gets a route from the route definitions for the requested path
     * @param string $method Method of the request
     * @param string $path Path of the request without trailing / and query
     * string
     * @param string $baseUrl Base URL of the request
     * @return RouterResult
     */
    protected function getRouteFromPath($method, $path, $baseUrl = null) {
        $alias = $this->routeContainer->getAliasByAlias($path);
        if ($alias) {
            $path = $alias->getPath();
        }

        $path = new Route($path, 'callback');
        $pathTokens = $path->getPathTokens();

        $allowedMethods = array();
        $resultRoute = null;
        $resultArguments = null;

        $routes = $this->routeContainer->getRoutes();
        foreach ($routes as $route) {
            $routeTokens = $route->getPathTokens();

            $routeArguments = $this->matchTokens($pathTokens, $routeTokens, $route->isDynamic());
            if ($routeArguments === false) {
                // no match
                continue;
            }

            if ($resultRoute && count($resultArguments) < count($routeArguments)) {
                // less arguments of the current result, more static routes have priority
                continue;
            }

            if ($resultRoute && !$route->isMethodAllowed($method)) {
                // method is not allowed
                $allowedMethods = array_merge($allowedMethods, $route->getAllowedMethods());

                continue;
            }

            $routeBaseUrl = $route->getBaseUrl();
            if ($baseUrl && $routeBaseUrl && $routeBaseUrl != $baseUrl) {
                // base URL defined but not the incoming base URL
                continue;
            }

            if ($resultRoute && $resultRoute->getPath() == $route->getPath() && $resultRoute->getBaseUrl() == $routeBaseUrl && $resultRoute->isMethodAllowed($method)) {
                // already matched a route with this method, path and baseUrl
                continue;
            }

            $resultRoute = $route;
            $resultArguments = $routeArguments;
        }

        $result = new RouterResult();

        if (!$resultRoute) {
            return $result;
        }

        if ($resultRoute->isMethodAllowed($method)) {
            $route = clone $resultRoute;
            if ($resultArguments) {
                $route->setArguments($resultArguments);
            }

            $result->setRoute($route);
        } else {
            $allowedMethods = array_merge($allowedMethods, $resultRoute->getAllowedMethods());

            $result->setAllowedMethods($allowedMethods);
        }

        return $result;
    }

    /**
     * Matches the tokens of the path with the tokens of a route
     * @param array $pathTokens Tokens of the path
     * @param array $routeTokens Tokens of a route
     * @param boolean $isDynamic Flag to see if it is a dynamic route
     * @return boolean|array False when no match, an array when matched with
     * the matched arguments
     */
    protected function matchTokens(array $pathTokens, array $routeTokens, $isDynamic) {
        $arguments = array();

        $numPathTokens = count($pathTokens);
        $numRouteTokens = count($routeTokens);

        if ($numPathTokens < $numRouteTokens) {
            return false;
        }

        foreach ($routeTokens as $index => $routeToken) {
            $parameterName = substr($routeToken, 1, -1);
            $isParameter = $routeToken == '%' . $parameterName . '%';

            if ($isParameter) {
                $arguments[$parameterName] = $pathTokens[$index];

                continue;
            }

            if ($routeToken != $pathTokens[$index]) {
                return false;
            }
        }

        if (!$isDynamic) {
            if ($numPathTokens != $numRouteTokens) {
                return false;
            }

            return $arguments;
        }

        $index = $numRouteTokens;
        while (isset($pathTokens[$index])) {
            $arguments[] = $pathTokens[$index];
            $index++;
        }

        return $arguments;
    }

}
