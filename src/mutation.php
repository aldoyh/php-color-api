<?php declare(strict_types=1);

namespace Frontify\ColorApi;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

use function Frontify\ColorApi\sql;

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
                'name' => Type::nonNull(Type::string()),
                'value' => Type::nonNull(Type::string()),
            ],
            'resolve' => function ($rootValue, array $args) {
                // Validate input
                if (empty($args['name'])) {
                    throw new \Exception('Color name cannot be empty');
                }
                if (!preg_match('/^#[a-f0-9]{6}$/i', $args['value'])) {
                    throw new \Exception('Invalid color value. Must be a valid hex color code.');
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
        ],
        'generateColorTheme' => [
            'type' => Type::nonNull(Type::listOf($colorType)),
            'args' => [
                'group' => Type::string(),
            ],
            'resolve' => function ($root, array $args) {
                // Validate group color if provided
                if (isset($args['group']) && !preg_match('/^#[a-f0-9]{6}$/i', $args['group'])) {
                    throw new \Exception('Invalid group color. Must be a valid hex color code.');
                }

                $colors = [];
                $baseColor = $args['group'] ?? sprintf('#%06X', mt_rand(0, 0xFFFFFF));
                $baseRgb = hex2rgbArray($baseColor);
                $hsl = rgbToHsl($baseRgb['r'], $baseRgb['g'], $baseRgb['b']);

                // Generate a harmonious color palette using different color relationships
                $colorRelationships = [
                    'base' => 0,        // Base color
                    'complementary' => 180, // Direct complement
                    'analogous1' => 30,    // Analogous color 1
                    'analogous2' => -30,   // Analogous color 2
                    'triadic1' => 120,     // Triadic color 1
                    'triadic2' => 240      // Triadic color 2
                ];

                $i = 1;
                foreach ($colorRelationships as $name => $hueOffset) {
                    $hue = fmod($hsl['h'] + $hueOffset, 360);
                    $rgb = hslToRgb($hue, $hsl['s'], $hsl['l']);
                    $hex = rgbToHex([$rgb['r'], $rgb['g'], $rgb['b']]);

                    $colors[] = [
                        'id' => $i++,
                        'name' => ucfirst($name) . ' Color',
                        'hex' => $hex,
                        'rgb' => hex2rgb($hex),
                        'created' => date('Y-m-d H:i:s')
                    ];
                }

                return $colors;
            }
        ],
        'saveColor' => [
            'type' => $colorType,
            'args' => [
                'name' => Type::nonNull(Type::string()),
                'hex' => Type::nonNull(Type::string())
            ],
            'resolve' => function ($root, $args) {
                $query = sprintf(
                    "INSERT INTO colors (name, hex) VALUES ('%s', '%s')",
                    $args['name'],
                    $args['hex']
                );
                $result = sql($query);
                return [
                    'id' => $result['id'],
                    'name' => $args['name'],
                    'hex' => $args['hex'],
                    'created' => date('Y-m-d H:i:s')
                ];
            }
        ]
    ]
]);

/**
 * Convert hex color to RGB string
 * @param string $hex Hex color code (e.g. #ffffff)
 * @return string RGB color string (e.g. "rgb(255,255,255)")
 */
function hex2rgb($hex)
{
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "rgb($r,$g,$b)";
}

/**
 * Convert hex color to RGB array
 * @param string $hex Hex color code (e.g. #ffffff)
 * @return array RGB color array (e.g. ['r' => 255, 'g' => 255, 'b' => 255])
 */
function hex2rgbArray($hex)
{
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return ['r' => $r, 'g' => $g, 'b' => $b];
}

/**
 * Convert RGB array to hex color
 * @param array $rgb RGB color array
 * @return string Hex color code
 */
function rgbToHex($rgb)
{
    return sprintf("#%02x%02x%02x", $rgb[0], $rgb[1], $rgb[2]);
}

/**
 * Convert RGB to HSL color space
 * @param int $r Red value (0-255)
 * @param int $g Green value (0-255)
 * @param int $b Blue value (0-255)
 * @return array HSL color array
 */
function rgbToHsl($r, $g, $b)
{
    $r /= 255;
    $g /= 255;
    $b /= 255;
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $h = 0;
    $s = 0;
    $l = ($max + $min) / 2;

    if ($max == $min) {
    } else {
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        switch ($max) {
            case $r:
                $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) * 60;
                break;
            case $g:
                $h = (($b - $r) / $d + 2) * 60;
                break;
            case $b:
                $h = (($r - $g) / $d + 4) * 60;
                break;
        }
    }
    return ['h' => round($h), 's' => $s, 'l' => $l];
}

function hslToRgb($h, $s, $l)
{
    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;
    $r = 0;
    $g = 0;
    $b = 0;
    if ($h < 60) {
        $r = $c;
        $g = $x;
        $b = 0;
    } elseif ($h < 120) {
        $r = $x;
        $g = $c;
        $b = 0;
    } elseif ($h < 180) {
        $r = 0;
        $g = $c;
        $b = $x;
    } elseif ($h < 240) {
        $r = 0;
        $g = $x;
        $b = $c;
    } elseif ($h < 300) {
        $r = $x;
        $g = 0;
        $b = $c;
    } else {
        $r = $c;
        $g = 0;
        $b = $x;
    }
    return ['r' => round(($r + $m) * 255), 'g' => round(($g + $m) * 255), 'b' => round(($b + $m) * 255)];
}
