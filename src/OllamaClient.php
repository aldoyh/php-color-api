<?php
// src/OllamaClient.php
namespace Frontify\ColorApi;

class OllamaClient
{
    private $endpoint;
    private $model;

    public function __construct($endpoint = 'http://localhost:11434/api/generate', $model = 'llama3')
    {
        $this->endpoint = $endpoint;
        $this->model = $model;
    }

    /**
     * Generate a color theme using Ollama LLM
     * @param string $prompt
     * @return string Raw model output
     */
    public function generateTheme($prompt)
    {
        $data = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false
        ];
        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);
        if ($response === false) {
            throw new \Exception('Ollama request failed: ' . curl_error($ch));
        }
        curl_close($ch);
        $json = json_decode($response, true);
        if (!isset($json['response'])) {
            error_log('Ollama raw response: ' . $response);
            throw new \Exception('Invalid Ollama response: ' . $response);
        }
        return $json['response'];
    }
}
