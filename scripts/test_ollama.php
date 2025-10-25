<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/OllamaClient.php';
require_once __DIR__ . '/../src/OllamaUtils.php';

use Frontify\ColorApi\OllamaClient;
use Frontify\ColorApi\OllamaUtils;

echo "=== OllamaClient Direct Test ===\n\n";

echo "1. Getting available models...\n";
$models = OllamaUtils::getAvailableModels();
echo "Available models: " . json_encode($models) . "\n\n";

if (empty($models)) {
    echo "ERROR: No models available. Please run 'ollama pull gemma3' or similar.\n";
    exit(1);
}

echo "2. Creating OllamaClient with first available model: {$models[0]}\n";
$client = new OllamaClient('http://localhost:11434/api/generate', $models[0]);

echo "3. Calling generateTheme() with test prompt...\n";
$testPrompt = "Modern dashboard with cool blues and warm accents";
try {
    $output = $client->generateTheme($testPrompt);
    echo "SUCCESS!\n";
    echo "Output length: " . strlen($output) . " characters\n";
    echo "Output preview: " . substr($output, 0, 300) . "...\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

