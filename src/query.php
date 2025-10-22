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
        // Get info for a single color
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

        // Generate a palette from a base color and mode
        'palette' => [
            'type' => $paletteType,
            'args' => [
                'baseColor' => Type::nonNull(Type::string()),
                'mode' => Type::string(),
                'count' => Type::int()
            ],
            'resolve' => function ($root, $args) {
                $utils = new ColorUtils();
                $baseColor = $utils->normalizeHex($args['baseColor']);
                $mode = $args['mode'] ?? 'complementary';
                $count = $args['count'] ?? 5;
                if (!$utils->isValidHex($baseColor)) {
                    throw new \Exception('Invalid base color');
                }
                $count = max(2, min(12, $count));
                // Only 'shades' and 'tints' support count, others are fixed by mode
                if (in_array($mode, ['shades', 'tints'])) {
                    $baseRgb = $utils->hexToRgb($baseColor);
                    $baseHsl = $utils->rgbToHsl($baseRgb['r'], $baseRgb['g'], $baseRgb['b']);
                    $palette = [];
                    for ($i = 0; $i < $count; $i++) {
                        $lightness = ($mode === 'shades')
                            ? max(0, $baseHsl['l'] - ($i * 0.15))
                            : min(1, $baseHsl['l'] + ($i * 0.15));
                        $rgb = $utils->hslToRgb($baseHsl['h'], $baseHsl['s'], $lightness);
                        $palette[] = $utils->getColorInfo($utils->rgbToHex($rgb['r'], $rgb['g'], $rgb['b']));
                    }
                } else {
                    $palette = $utils->generatePalette($baseColor, $mode);
                }
                if (empty($palette)) {
                    throw new \Exception('Palette generation failed');
                }
                return [ 'colors' => $palette ];
            }
        ],

        // AI-powered theme generation
        'generateTheme' => [
            'type' => Type::listOf($colorType),
            'args' => [
                'prompt' => Type::nonNull(Type::string()),
                'model' => Type::string()
            ],
            'resolve' => function ($root, $args) {
                require_once __DIR__ . '/OllamaClient.php';
                require_once __DIR__ . '/OllamaUtils.php';
                $userPrompt = trim($args['prompt']);
                $model = $args['model'] ?? 'llama3';
                // Confirm model exists in Ollama
                if (!\Frontify\ColorApi\OllamaUtils::modelExists($model)) {
                    throw new \Exception("Ollama model '$model' not found. Please install it with 'ollama pull $model'.");
                }
                // Improved system prompt for better color theme generation
                $prompt = "You are a world-class color palette designer. Generate a JSON array of 5-7 beautiful, visually harmonious color hex codes for this theme: '" . $userPrompt . "'. Only output the array, no explanation.";
                $ollama = new \Frontify\ColorApi\OllamaClient('http://localhost:11434/api/generate', $model);
                $utils = new ColorUtils();
                try {
                    $output = $ollama->generateTheme($prompt);
                } catch (\Exception $e) {
                    // Provide a helpful error message to the client and log details
                    error_log('Ollama generation error: ' . $e->getMessage());
                    throw new \Exception('AI generation failed: ' . $e->getMessage());
                }
                // Try to extract JSON array of hex codes first
                $hexes = [];
                if (preg_match('/\[([^\]]+)\]/', $output, $jsonMatch)) {
                    $jsonStr = '[' . $jsonMatch[1] . ']';
                    $arr = @json_decode($jsonStr, true);
                    if (is_array($arr)) {
                        $hexes = $arr;
                    }
                }
                // Fallback: extract hex codes from text
                if (empty($hexes)) {
                    preg_match_all('/#?[0-9a-fA-F]{6}\b/', $output, $matches);
                    $hexes = $matches[0] ?? [];
                }
                $hexes = array_map(function($h) use ($utils) {
                    return $utils->normalizeHex($h);
                }, $hexes);
                $colors = [];
                foreach ($hexes as $hex) {
                    if ($utils->isValidHex($hex)) {
                        $colors[] = $utils->getColorInfo($hex);
                    }
                }
                if (empty($colors)) {
                    throw new \Exception('No valid colors found in AI output.');
                }
                // Try to persist generated theme, but don't fail the request if DB write errors
                try {
                    $pdo = getDatabase();
                    $stmt = $pdo->prepare('INSERT INTO ai_themes (prompt, model, colors_json) VALUES (:prompt, :model, :colors_json)');
                    $stmt->execute([
                        ':prompt' => $userPrompt,
                        ':model' => $model,
                        ':colors_json' => json_encode($colors)
                    ]);
                } catch (\Exception $e) {
                    error_log('Failed to save AI theme: ' . $e->getMessage());
                    // continue and return colors despite DB failure
                }
                return $colors;
            }
        ],

        // List all saved colors
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