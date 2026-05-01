<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteControllerMethodTest extends TestCase
{
    public function test_all_controller_routes_point_to_existing_methods(): void
    {
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();

            if ($action === 'Closure') {
                continue;
            }

            if (str_contains($action, '@')) {
                [$controller, $method] = explode('@', $action, 2);
            } else {
                $controller = $action;
                $method = '__invoke';
            }

            $routeLabel = sprintf(
                '%s %s [%s]',
                implode('|', $route->methods()),
                $route->uri(),
                $route->getName() ?: 'unnamed'
            );

            $this->assertTrue(
                class_exists($controller),
                "Route {$routeLabel} uses missing controller {$controller}."
            );

            $this->assertTrue(
                method_exists($controller, $method),
                "Route {$routeLabel} uses missing method {$controller}@{$method}."
            );
        }
    }
}
