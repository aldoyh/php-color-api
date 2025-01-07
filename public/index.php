<?php declare(strict_types=1);

/* (c) Copyright Frontify Ltd., all rights reserved. */

use GraphQL\Server\StandardServer;

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../src/schema.php';

// header("Access-Control-Allow-Origin: *");
// header("Content-Type: application/json; charset=UTF-8");
// header("Access-Control-Allow-Methods: POST, GET");
// header("Access-Control-Max-Age: 3600");
// header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    return 0;
} else {
    $server = new StandardServer([
        'schema' => $schema,
    ]);
    $server->handleRequest();
    // echo $server->executePsrRequest($request);

    return 0;
}
