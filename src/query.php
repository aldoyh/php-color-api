<?php declare(strict_types=1);

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

require_once __DIR__ . '/color.php';

// $MyDB = new mysqli(getenv('DB_HOST') != false ? getenv('DB_HOST') : null, getenv('DB_USERNAME'), getenv('DB_PASSWORD'), "colors", getenv('DB_PORT') != false ? getenv('DB_PORT') : null, getenv('DB_SOCKET') != false ? getenv('DB_SOCKET') : null);
$conn = new mysqli("127.0.0.1", "root", "", "colorsdb", 3306);

if ($MyDB->connect_errno) {
    error_log("Failed to connect to MySQL: (" . $MyDB->connect_errno . ") " . $MyDB->connect_error);
}

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'color' => [
            'type' => $colorType,
            'args' => [
                'id' => Type::nonNull(Type::int()),
            ],
            'resolve' => function ($rootValue, array $args) {
                global $MyDB;
                $stmt = $MyDB->prepare("SELECT * FROM colors WHERE id = ?");
                $stmt->bind_param("i", $args['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                return $row;
            }
        ],
        'colors' => [
            'type' => Type::listOf($colorType),
            'args' => [
                'limit' => Type::int(),
                'offset' => Type::int(),
            ],
            'resolve' => function ($rootValue, array $args, $context) {
                global $MyDB;

                if (array_key_exists('offset', $args) && array_key_exists('limit', $args)) {
                    $stmt = $MyDB->prepare("SELECT * FROM colors ORDER BY id DESC LIMIT ? OFFSET ?");
                    $stmt->bind_param("ii", $args["limit"] , $args['offset']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $rows = $result->fetch_all(MYSQLI_ASSOC);
                    return $rows;
                }
                if (array_key_exists('limit', $args)) {
                    $stmt = $MyDB->prepare("SELECT * FROM colors ORDER BY id DESC LIMIT ?");
                    $stmt->bind_param("i", $args["limit"]);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $rows = $result->fetch_all(MYSQLI_ASSOC);
                    return $rows;
                }
                if (array_key_exists('offset', $args)) {
                    $stmt = $MyDB->prepare("SELECT * FROM colors ORDER BY id DESC LIMIT 30 OFFSET ?");
                    $stmt->bind_param("i", $args['offset']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $rows = $result->fetch_all(MYSQLI_ASSOC);
                    return $rows;
                }
                $stmt = $MyDB->prepare("SELECT * FROM colors ORDER BY id DESC LIMIT 30");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $rows = $result->fetch_all(MYSQLI_ASSOC);
                    return $rows;

            }
        ]
    ],
]);