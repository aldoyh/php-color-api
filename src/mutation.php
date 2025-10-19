<?php declare(strict_types=1);

namespace Frontify\ColorApi;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

require_once __DIR__ . '/../src/color.php';
require_once __DIR__ . '/../src/ColorUtils.php';

$mutationType = new ObjectType([
    'name' => 'Mutation',
    'fields' => [
        'saveColor' => [
            'type' => $colorType,
            'args' => [
                'name' => Type::nonNull(Type::string()),
                'hex' => Type::nonNull(Type::string())
            ],
            'resolve' => function ($root, $args) {
                $utils = new ColorUtils();

                $hex = $utils->normalizeHex($args['hex']);
                if (!$utils->isValidHex($hex)) {
                    throw new \Exception('Invalid hex color format');
                }

                $rgb = $utils->hexToRgb($hex);
                $hsl = $utils->rgbToHsl($rgb['r'], $rgb['g'], $rgb['b']);

                $pdo = getDatabase();
                $stmt = $pdo->prepare("INSERT INTO colors (name, hex, rgb, hsl) VALUES (:name, :hex, :rgb, :hsl)");

                $rgbStr = sprintf('%d, %d, %d', $rgb['r'], $rgb['g'], $rgb['b']);
                $hslStr = sprintf('%.0f, %.1f%%, %.1f%%', $hsl['h'], $hsl['s'] * 100, $hsl['l'] * 100);

                $executed = $stmt->execute([
                    ':name' => $args['name'],
                    ':hex' => $hex,
                    ':rgb' => $rgbStr,
                    ':hsl' => $hslStr
                ]);

                if (!$executed) {
                    $errorInfo = $stmt->errorInfo();
                    throw new \Exception('Failed to save color: ' . ($errorInfo[2] ?? 'unknown'));
                }

                return [
                    'hex' => $hex,
                    'rgb' => $rgbStr,
                    'hsl' => $hslStr,
                    'name' => $args['name']
                ];
            }
        ]
    ]
]);
