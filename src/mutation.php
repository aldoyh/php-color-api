<?php declare(strict_types=1);

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

require_once __DIR__ . '/../src/color.php';

$successType = new ObjectType([
    'name' => 'Success',
    'fields' => [
        'success' => Type::boolean(),
    ]
]);

$mutationType = new ObjectType([
    'name' => 'Mutation',
    'fields' => [
        'addColor' => [
            'type' => $colorType,
            'args' => [
                'name' => Type::string(),
                'value' => Type::string(),
            ],
            'resolve' => function ($rootValue, array $args) {
                // check if the $args['value'] is a valid hex color
                if (!preg_match('/^#[a-f0-9]{6}$/i', $args['value'])) {
                    return null;
                }
                //Needs to be rewritten as aprepared statement for production
                $insert = sql("INSERT INTO colors (name, value) VALUES ('" . $args['name'] . "', '" . $args['value'] . "')"); 
                
                $colors = sql("SELECT * FROM colors WHERE id = '" . $insert["id"] . "'");
                if (count($colors['data']) === 0) {
                    return null;
                }
                return $colors['data'];
            }
        ],
        'updateColor' => [
            'type' => $colorType,
            'args' => [
                'id' => Type::nonNull(Type::int()),
                'name' => Type::string(),
                'value' => Type::string(),
            ],
            'resolve' => function ($rootValue, array $args) {
                if (!preg_match('/^#[a-f0-9]{6}$/i', $args['value'])) {
                    return null;
                }
                //Needs to be rewritten as aprepared statement for production
                $query = "UPDATE colors SET";

                if (array_key_exists('name', $args)) {
                    $query .= " name =' " . $args['name'] . "'";
                }
                if (array_key_exists('name', $args) && array_key_exists('value', $args)) {
                    $query .= ",";
                }
                if (array_key_exists('value', $args)) {
                    $query .= " value = '" . $args['value'] . "'";
                }
                $query .= " WHERE id = '" . $args['id'] . "'";
                sql($query);
                $colors = sql("SELECT * FROM colors WHERE id = '" . $args['id'] . "'");
                if (!array_key_exists('data', $colors) || count($colors['data']) === 0) {
                    return null;
                }
                return $colors['data'];
            }
        ],
        'removeColor' => [
            'type' => $successType,
            'args' => [
                'id' => Type::int(),
            ],
            'resolve' => function ($rootValue, array $args) {
                //Needs to be rewritten as aprepared statement for production
                $sql = sql("DELETE FROM colors WHERE id = '" . $args['id'] . "'", true);
                return [
                    "success" => $sql["success"],
                ];
            }
        ]
    ]
]);