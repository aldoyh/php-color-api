<?php

declare(strict_types=1);

namespace Frontify\ColorApi;

use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

require_once __DIR__ . '/query.php';
require_once __DIR__ . '/mutation.php';

function sql($query)
{
    // $conn = new mysqli(getenv('DB_HOST') != false ? getenv('DB_HOST') : null, getenv('DB_USERNAME'), getenv('DB_PASSWORD'), "colors", getenv('DB_PORT') != false ? getenv('DB_PORT') : null, getenv('DB_SOCKET') != false ? getenv('DB_SOCKET') : null);
    $conn = new \mysqli(
        "localhost",
        "root",
        "",
        "colorsdb",
        3306
    );
    $result = $conn->query($query);
    if ($result === true) {
        $affected_rows = $conn->affected_rows;
        $last_id = $conn->insert_id;
        $conn->close();
        return [
            'success' => $affected_rows > 0,
            'id' => $last_id,
        ];
    } elseif ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        return [
            'success' => true,
            'data' => $data,
        ];
    } else {
        return [
            'success' => false,
        ];
    }
}

$schema = new Schema([
    'query' => $queryType,
    'mutation' => $mutationType,
    'debug' => true,
]);
