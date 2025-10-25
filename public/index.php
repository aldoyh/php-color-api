<?php declare(strict_types=1);

use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/schema.php';

// Handle CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// GraphiQL for local development
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/graphiql') {
    header('Content-Type: text/html');
    echo file_get_contents(__DIR__ . '/../templates/graphiql.html');
    exit;
}

// GraphQL endpoint
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/graphql') {
    header('Content-Type: application/json');
    error_log("=== GraphQL REQUEST ===");

    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $rawInput = file_get_contents('php://input');
            $input = $rawInput ? json_decode($rawInput, true) : null;
            if ($rawInput && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON: ' . json_last_error_msg());
            }
            // If no JSON body, fall back to $_POST
            if ($input === null) {
                $input = $_POST;
            }
        } else {
            $input = $_GET;
        }

        error_log("GraphQL input: " . json_encode($input));

        $config = ServerConfig::create()
            ->setSchema($schema)
            ->setDebugFlag(1);

        $server = new StandardServer($config);
        ob_start();
        $server->handleRequest(GraphQL\Server\OperationParams::create($input ?? []));
        $output = ob_get_clean();
        error_log("GraphQL output: " . $output);
        echo $output;

    } catch (Exception $e) {
        error_log("GraphQL exception: " . $e->getMessage());
        error_log("Exception trace: " . $e->getTraceAsString());
        http_response_code(200); // GraphQL errors should return 200 with errors in body
        echo json_encode([
            'errors' => [
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ]
        ]);
    } catch (Throwable $e) {
        error_log("GraphQL throwable: " . $e->getMessage());
        error_log("Throwable trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'errors' => [
                [
                    'message' => 'Internal server error: ' . $e->getMessage(),
                    'type' => get_class($e)
                ]
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
