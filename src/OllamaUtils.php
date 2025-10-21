<?php
// src/OllamaUtils.php
namespace Frontify\ColorApi;

class OllamaUtils
{
    /**
     * Check if a model exists in the local Ollama instance
     * @param string $model
     * @param string $endpoint
     * @return bool
     */
    public static function modelExists($model, $endpoint = 'http://localhost:11434/api/tags')
    {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        if ($response === false) {
            return false;
        }
        curl_close($ch);
        $json = json_decode($response, true);
        if (!isset($json['models']) || !is_array($json['models'])) {
            return false;
        }
        foreach ($json['models'] as $m) {
            if (isset($m['name']) && strtolower($m['name']) === strtolower($model)) {
                return true;
            }
        }
        return false;
    }
}
