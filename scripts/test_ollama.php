<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/OllamaClient.php';

use Frontify\ColorApi\OllamaClient;

$client = new OllamaClient('http://localhost:11434/api/generate', 'llama3');
$prompt = "Test palette: modern dashboard, muted blues and warm accents";
try {
    $out = $client->generateTheme($prompt);
    echo "SUCCESS:\n";
    echo $out . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
