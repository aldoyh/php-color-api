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

        // AI-powered theme generation (drastically improved)
        'generateTheme' => [
            'type' => new ObjectType([
                'name' => 'AIThemeResult',
                'fields' => [
                    'colors' => Type::listOf($colorType),
                    'metadata' => new ObjectType([
                        'name' => 'AIThemeMetadata',
                        'fields' => [
                            'model' => Type::string(),
                            'rawResponse' => Type::string(),
                            'extractionMethod' => Type::string(),
                            'error' => Type::string(),
                        ]
                    ])
                ]
            ]),
            'args' => [
                'prompt' => Type::nonNull(Type::string()),
                'model' => Type::string()
            ],
            'resolve' => function ($root, $args) {
                error_log("=== generateTheme resolver START ===");
                $metadata = [
                    'model' => $args['model'] ?? 'llama3.1',
                    'rawResponse' => '',
                    'extractionMethod' => '',
                    'error' => ''
                ];
                try {
                    require_once __DIR__ . '/OllamaClient.php';
                    require_once __DIR__ . '/OllamaUtils.php';

                    $userPrompt = trim($args['prompt'] ?? '');
                    $model = $args['model'] ?? 'llama3.1';

                    error_log("generateTheme: userPrompt='$userPrompt', model='$model'");

                    if (empty($userPrompt)) {
                        throw new \Exception('Prompt cannot be empty');
                    }

                    // Model check and fallback
                    try {
                        error_log("Checking if model '$model' exists...");
                        if (!\Frontify\ColorApi\OllamaUtils::modelExists($model)) {
                            error_log("Model check: '$model' not found. Attempting auto-fallback...");
                            $available = \Frontify\ColorApi\OllamaUtils::getAvailableModels();
                            if (!empty($available)) {
                                $oldModel = $model;
                                $model = $available[0];
                                error_log("Model fallback: '$oldModel' -> '$model'");
                                $metadata['model'] = $model;
                            } else {
                                error_log("WARNING: No available models returned from Ollama. Will attempt with '$model' anyway.");
                            }
                        } else {
                            error_log("Model check: '$model' found");
                        }
                    } catch (\Throwable $e) {
                        error_log("Model check exception (non-blocking): " . $e->getMessage());
                    }

                    // Advanced prompt engineering
                    $systemPrompt = "You are a world-class color palette designer. Generate a JSON array of 5-7 beautiful, visually harmonious color hex codes for this theme: '" . $userPrompt . "'. Only output the array, no explanation. If you cannot generate a JSON array, output a list of hex codes separated by spaces.";
                    error_log("Initializing OllamaClient with model=$model");
                    $ollama = new \Frontify\ColorApi\OllamaClient('http://localhost:11434/api/generate', $model);
                    $utils = new ColorUtils();

                    error_log("Calling ollama->generateTheme()...");
                    $output = $ollama->generateTheme($systemPrompt);
                    $metadata['rawResponse'] = $output;
                    error_log("Ollama response received, length=" . strlen($output));

                    // Try to extract JSON array of hex codes first
                    $hexes = [];
                    $extractionMethod = '';
                    if (preg_match('/\[([^\]]+)\]/', $output, $jsonMatch)) {
                        $jsonStr = '[' . $jsonMatch[1] . ']';
                        $arr = @json_decode($jsonStr, true);
                        if (is_array($arr)) {
                            $hexes = $arr;
                            $extractionMethod = 'json-array';
                            error_log("Extracted hex array from JSON, count=" . count($hexes));
                        }
                    }

                    // Fallback: extract hex codes from text
                    if (empty($hexes)) {
                        preg_match_all('/#?[0-9a-fA-F]{6}\b/', $output, $matches);
                        $hexes = $matches[0] ?? [];
                        $extractionMethod = 'regex';
                        error_log("Extracted hex codes via regex, count=" . count($hexes));
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

                    error_log("Valid colors extracted, count=" . count($colors));
                    $metadata['extractionMethod'] = $extractionMethod;

                    if (empty($colors)) {
                        $metadata['error'] = 'No valid colors found in AI output.';
                        throw new \Exception('No valid colors found in AI output: ' . substr($output, 0, 200));
                    }

                    // Try to persist generated theme, but don't fail the request if DB write errors
                    try {
                        error_log("Attempting to save theme to database...");
                        $pdo = getDatabase();
                        $stmt = $pdo->prepare('INSERT INTO ai_themes (prompt, model, colors_json) VALUES (:prompt, :model, :colors_json)');
                        $stmt->execute([
                            ':prompt' => $userPrompt,
                            ':model' => $model,
                            ':colors_json' => json_encode($colors)
                        ]);
                        error_log("Theme saved to database");
                    } catch (\Exception $e) {
                        error_log('WARNING: Failed to save AI theme to DB: ' . $e->getMessage());
                        $metadata['error'] = 'DB save failed: ' . $e->getMessage();
                    }

                    error_log("=== generateTheme resolver SUCCESS ===");
                    return [
                        'colors' => $colors,
                        'metadata' => $metadata
                    ];

                } catch (\Throwable $e) {
                    error_log("=== generateTheme resolver EXCEPTION ===");
                    error_log("Exception type: " . get_class($e));
                    error_log("Exception message: " . $e->getMessage());
                    error_log("Exception file: " . $e->getFile() . ":" . $e->getLine());
                    error_log("Exception trace: " . $e->getTraceAsString());
                    $metadata['error'] = $e->getMessage();
                    return [
                        'colors' => [],
                        'metadata' => $metadata
                    ];
                }
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