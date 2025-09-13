<?php

require __DIR__ . '/../vendor/autoload.php';

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;

use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\Http\PSR7Worker;
use Slim\Factory\AppFactory;

// Create new RoadRunner worker from global environment
$worker = Worker::create();

// Create common PSR-17 HTTP factory
$factory = new Psr17Factory();

//
// Create PSR-7 worker and pass:
//  - RoadRunner worker
//  - PSR-17 ServerRequestFactory
//  - PSR-17 StreamFactory
//  - PSR-17 UploadFilesFactory
//
$psr7 = new PSR7Worker($worker, $factory, $factory, $factory);

$app = AppFactory::create();

$app->get('/kenny', function (ServerRequest $request, Response $response, $args) {
    $response->getBody()->write("Hello world Kenny!");
    return $response;
});
while (true) {
    try {
        $request = $psr7->waitRequest();
    } catch (\Throwable $e) {
        // Although the PSR-17 specification clearly states that there can be
        // no exceptions when creating a request, however, some implementations
        // may violate this rule. Therefore, it is recommended to process the 
        // incoming request for errors.
        //
        // Send "Bad Request" response.
        $psr7->respond(new Response(400));
        continue;
    }

    try {
        // Here is where the call to your application code will be located. 
        // For example:
        //
        $response = $app->handle($request);
        //
        // Reply by the 200 OK response
        $psr7->respond($response);
    } catch (\Throwable $e) {
        // In case of any exceptions in the application code, you should handle
        // them and inform the client about the presence of a server error.
        //
        // Reply by the 500 Internal Server Error response
        $psr7->respond(new Response(500, [], $e->getMessage()));

        // Additionally, we can inform the RoadRunner that the processing 
        // of the request failed.
        $worker->error((string)$e);
    }
}