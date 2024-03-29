<?php

declare(strict_types=1);

use Laminas\Diactoros\Response;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Laminas\HttpHandlerRunner\RequestHandlerRunner;
use Laminas\Stratigility\Middleware\NotFoundHandler;
use Laminas\Stratigility\MiddlewarePipe;

use function Laminas\Stratigility\middleware;
use function Laminas\Stratigility\path;

// Delegate static file requests back to the PHP built-in webserver
if (PHP_SAPI === 'cli-server' && $_SERVER['SCRIPT_FILENAME'] !== __FILE__) {
    return false;
}

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

/**
 * Self-called anonymous function that creates its own scope and keeps the global namespace clean.
 */
(function () {

    $app    = new MiddlewarePipe();

    // Landing page
    $app->pipe(middleware(function ($req, $handler) {
        if (! in_array($req->getUri()->getPath(), ['/', ''], true)) {
            return $handler->handle($req);
        }

        $response = new Response();
        $response->getBody()->write('Hello world!');

        return $response;
    }));

    // Another page
    $app->pipe(path('/foo', middleware(function ($req, $handler) {
        $response = new Response();
        $response->getBody()->write('FOO!');

        return $response;
    })));

    // 404 handler
    $app->pipe(new NotFoundHandler(function () {
        return new Response();
    }));


    $server = new RequestHandlerRunner(
        $app,
        new SapiEmitter(),
        static function () {
            return ServerRequestFactory::fromGlobals();
        },
        static function (\Throwable $e) {
            $response = (new ResponseFactory())->createResponse(500);
            $response->getBody()->write(sprintf(
                'An error occurred: %s',
                $e->getMessage
            ));
            return $response;
        }
    );

    $server->run();

})();
