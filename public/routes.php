<?php

// define routes

$routes = array(
    array(
        "pattern" => "logout",
        "controller" => "auth",
        "action" => "logout"
    ),
    array(
        "pattern" => "login",
        "controller" => "auth",
        "action" => "login"
    ),
    array(
        "pattern" => "register",
        "controller" => "publisher",
        "action" => "register"
    ),
    array(
        "pattern" => "index",
        "controller" => "auth",
        "action" => "login"
    ),
    array(
        "pattern" => "api/affiliate/:id/earning",
        "controller" => "api",
        "action" => "earning"
    )
);

// add defined routes
foreach ($routes as $route) {
    $router->addRoute(new Framework\Router\Route\Simple($route));
}

// unset globals
unset($routes);
