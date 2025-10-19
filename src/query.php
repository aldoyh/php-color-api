<?php declare(strict_types=1);

namespace Frontify\ColorApi;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

require_once __DIR__ . '/color.php';
require_once __DIR__ . '/ColorUtils.php';

$paletteType = new ObjectType([
    'name' => 'Palette',
    'fields' => [
        'colors' => Type::nonNull(Type::listOf($colorType))
    ]
]);

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'colorInfo' => [
            'type' => $colorType,
            'args' => [
                'hex' => Type::string(),
                'name' => Type::string()
            ],
            'resolve' => function ($root, $args) {
                $utils = new ColorUtils();
                $hex = null;

                if (isset($args['hex'])) {
                    $hex = $utils->normalizeHex($args['hex']);
                    if (!$utils->isValidHex($hex)) {
                        throw new \Exception('Invalid hex color');
                    }
                } elseif (isset($args['name'])) {
                    $hex = $utils->getColorByName($args['name']);
                    if (!$hex) {
                        throw new \Exception('Color name not found');
                    }
                } else {
                    throw new \Exception('Provide either hex or name');
                }

                return $utils->getColorInfo($hex);
            }
        ],
        'palette' => [
            'type' => $paletteType,
            'args' => [
                'baseColor' => Type::nonNull(Type::string()),
                'mode' => Type::string()
            ],
            'resolve' => function ($root, $args) {
                $utils = new ColorUtils();
                $baseColor = $utils->normalizeHex($args['baseColor']);
                $mode = $args['mode'] ?? 'complementary';

                if (!$utils->isValidHex($baseColor)) {
                    throw new \Exception('Invalid base color');
                }

                $colors = $utils->generatePalette($baseColor, $mode);

                return [
                    'colors' => $colors
                ];
            }
        ],
        'allColors' => [
            'type' => Type::nonNull(Type::listOf($colorType)),
            'resolve' => function () {
                $pdo = getDatabase();
                $stmt = $pdo->query("SELECT hex, name, rgb, hsl FROM colors ORDER BY created DESC LIMIT 100");

                $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

                $colors = [];
                foreach ($rows as $row) {
                    $colors[] = [
                        'hex' => $row['hex'] ?? null,
                        'name' => $row['name'] ?? null,
                        'rgb' => $row['rgb'] ?? null,
                        'hsl' => $row['hsl'] ?? null
                    ];
                }

                return $colors;
            }
        ]
    ]
]);