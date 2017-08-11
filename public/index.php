<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

chdir(dirname(__DIR__));

require 'vendor/autoload.php';

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    $r->addRoute('GET', '/users', 'get_all_users_handler');
    // {id} must be a number (\d+)
    $r->addRoute('GET', '/user/{id:\d+}', 'get_user_handler');
    // The /{title} suffix is optional
    $r->addRoute('GET', '/articles/{id:\d+}[/{title}]', 'get_article_handler');
});
    
    // Fetch method and URI from somewhere
    $httpMethod = $_SERVER['REQUEST_METHOD'];


    $uri = $_SERVER['REQUEST_URI'];
    $queryString = '';
    if (strpos($uri, '?')) {
        list($uri, $queryString) = explode('?', $uri);
    }
    
    $uri = rawurldecode($uri);
    $routeInfo = $dispatcher->dispatch($httpMethod, $uri);
    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            
            $uri = rtrim($uri, '/');
            if (substr($uri, -4) != '.php') {
                $uri .= '/index.php';
            }
            
            if (file_exists(getcwd().$uri)) {
                $scriptPath = getcwd().$uri;
                chdir(dirname(getcwd().$uri));
                $_SERVER['PHP_SELF'] = $uri;
                if ($queryString) {
                    $_SERVER['PHP_SELF'] .= '?'.$queryString;
                }
                
                include $scriptPath;
            }
            // ... 404 Not Found
            break;
        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            $allowedMethods = $routeInfo[1];
            // ... 405 Method Not Allowed
            break;
        case FastRoute\Dispatcher::FOUND:
//             $handler = $routeInfo[1];
//             $vars = $routeInfo[2];
            // ... call $handler with $vars
            break;
    }
