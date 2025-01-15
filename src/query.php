<?php declare(strict_types=1);

namespace Frontify\ColorApi;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

require_once __DIR__ . '/color.php';

$dbconn = new \mysqli("127.0.0.1", "root", "", "colorsdb", 3306);

if ($dbconn->connect_errno) {
    error_log("Failed to connect to MySQL: (" . $dbconn->connect_errno . ") " . $dbconn->connect_error);
}

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'color' => [
            'type' => $colorType,
            'args' => [
                'id' => Type::nonNull(Type::id()),
            ],
            'resolve' => function ($rootValue, array $args) {
                global $dbconn;
                $stmt = $dbconn->prepare("SELECT * FROM colors WHERE id = ?");
                $stmt->bind_param("i", $args['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                return $row;
            }
        ],
        'colors' => [
            'type' => Type::listOf($colorType),
            'resolve' => function () {
                $result = sql("SELECT * FROM colors ORDER BY created DESC");
                return isset($result['data']) ? [$result['data']] : [];
            }
        ]
    ],
]);