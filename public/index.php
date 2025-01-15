<?php declare(strict_types=1);

use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/schema.php';

// Handle CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// GraphQL endpoint
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/graphql') {
    header('Content-Type: application/json');

    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $rawInput = file_get_contents('php://input');
            if (!$rawInput) {
                throw new Exception('Missing POST data');
            }
            $input = json_decode($rawInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON');
            }
        } else {
            $input = $_GET;
        }

        $query = $input['query'] ?? null;
        if (!$query) {
            throw new Exception('Missing query');
        }

        $config = ServerConfig::create()
            ->setSchema($schema)
            ->setDebugFlag(1); // Changed from true to 1

        $server = new StandardServer($config);
        $server->handleRequest(GraphQL\Server\OperationParams::create($input));
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'errors' => [
                ['message' => $e->getMessage()]
            ]
        ]);
    }
    exit;
}

// HTML endpoint
error_reporting(0);

// load color Schema
$colors = json_decode(file_get_contents(__DIR__ . '/../colors.json'), true);
$colorsHtml = '';
foreach ($colors['colors'] as $color) {
    $colorsHtml .= <<<HTML
    <div class="color-box" style="background-color: {$color['hex']}" onclick="copyColor('{$color['hex']}')">
        <span class="color-name">{$color['name']}</span>
        <span class="color-hex">{$color['hex']}</span>
    </div>
    HTML;
}

$homeTemplate = file_get_contents(__DIR__ . '/../templates/home.html');
$homeTemplate = str_replace('{{colors}}', $colorsHtml, $homeTemplate);
echo $homeTemplate;
