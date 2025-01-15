<?php

declare(strict_types=1);

namespace Frontify\ColorApi;

use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use mysqli;

// Database configuration
const DB_CONFIG = [
    'host' => '127.0.0.1',
    'username' => 'root',
    'password' => '',
    'database' => 'colorsdb',
    'port' => 3306
];

$conn = new mysqli(
    DB_CONFIG['host'],
    DB_CONFIG['username'],
    DB_CONFIG['password']
);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "CREATE DATABASE IF NOT EXISTS colorsdb";

if ($conn->query($sql) === true) {
    error_log("Database created successfully");
} else {
    echo "Error creating database: " . $conn->error;
}

// Create colors table if not exists
$sql = "CREATE TABLE IF NOT EXISTS colorsdb.colors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    hex VARCHAR(7) NOT NULL,
    rgb VARCHAR(20),
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) !== true) {
    die("Error creating table: " . $conn->error);
}

require_once __DIR__ . '/query.php';
require_once __DIR__ . '/mutation.php';

function sql($query)
{
    $conn = new \mysqli(
        DB_CONFIG['host'],
        DB_CONFIG['username'],
        DB_CONFIG['password'],
        DB_CONFIG['database'],
        DB_CONFIG['port']
    );

    if ($conn->connect_error) {
        return [
            'success' => false,
            'error' => $conn->connect_error
        ];
    }

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

return $schema;
