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
        "pattern" => "index",
        "controller" => "home",
        "action" => "index"
    ),
    array(
        "pattern" => "privacypolicy",
        "controller" => "home",
        "action" => "privacypolicy"
    ),
    array(
        "pattern" => "termsofservice",
        "controller" => "home",
        "action" => "termsofservice"
    ),
    array(
        "pattern" => "refundspolicy",
        "controller" => "home",
        "action" => "refundspolicy"
    ),
    array(
        "pattern" => "pricing",
        "controller" => "home",
        "action" => "pricing"
    ),
    array(
        "pattern" => "adformats",
        "controller" => "home",
        "action" => "adformats"
    ),
    array(
        "pattern" => "contact",
        "controller" => "auth",
        "action" => "contact"
    ),
    array(
        "pattern" => "home",
        "controller" => "home",
        "action" => "index"
    ),
    array(
        "pattern" => "faqs",
        "controller" => "home",
        "action" => "faqs"
    )
);

// add defined routes
foreach ($routes as $route) {
    $router->addRoute(new Framework\Router\Route\Simple($route));
}

// unset globals
unset($routes);
