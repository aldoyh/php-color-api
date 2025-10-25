<?php
// src/OllamaClient.php
namespace Frontify\ColorApi;

class OllamaClient
{
    private $endpoint;
    private $model;

    public function __construct($endpoint = 'http://localhost:11434/api/generate', $model = 'llama3.1')
    {
        $this->endpoint = $endpoint;
        $this->model = $model;
        error_log("OllamaClient initialized: model=$model, endpoint=$endpoint");
    }

    /**
     * Generate a color theme using Ollama LLM
     * @param string $prompt
     * @return string Raw model output
     * @throws \Exception
     */
    public function generateTheme($prompt)
    {
        error_log("OllamaClient::generateTheme() called with model={$this->model}");
        $data = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false
        ];
        $maxAttempts = 3;
        $lastError = null;
        $payload = json_encode($data);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                error_log("[Attempt $attempt/$maxAttempts] Calling Ollama endpoint: {$this->endpoint}");
                $ch = curl_init($this->endpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                // Timeouts to avoid hanging requests
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 120);
                // Don't fail silently on HTTP >= 400 because we want the body for debugging
                curl_setopt($ch, CURLOPT_FAILONERROR, false);

                $response = curl_exec($ch);
                $curlErr = curl_error($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($response === false || $curlErr) {
                    $lastError = "[Attempt $attempt] Curl error: $curlErr";
                    error_log($lastError);
                    // exponential backoff (max ~8s)
                    usleep(min(8000000, 500000 * (1 << ($attempt - 1))));
                    continue;
                }

                error_log("[Attempt $attempt] Ollama returned HTTP $httpCode, response length=" . strlen($response));

                // If non-2xx, log and retry a couple times
                if ($httpCode < 200 || $httpCode >= 300) {
                    $lastError = "[Attempt $attempt] HTTP $httpCode from Ollama: " . substr($response, 0, 500);
                    error_log($lastError);
                    usleep(min(8000000, 500000 * (1 << ($attempt - 1))));
                    continue;
                }

                // Try flexible parsing: 'response', 'outputs', 'text', or raw string
                $json = json_decode($response, true);
                if (is_array($json)) {
                    if (isset($json['response'])) {
                        $result = $json['response'];
                        error_log("[Attempt $attempt] SUCCESS: extracted 'response' field, length=" . strlen($result));
                        return $result;
                    }
                    if (isset($json['text'])) {
                        $result = $json['text'];
                        error_log("[Attempt $attempt] SUCCESS: extracted 'text' field, length=" . strlen($result));
                        return $result;
                    }
                    if (isset($json['outputs']) && is_array($json['outputs'])) {
                        // Concatenate textual outputs if present
                        $collected = [];
                        foreach ($json['outputs'] as $out) {
                            if (is_string($out)) {
                                $collected[] = $out;
                            } elseif (is_array($out) && isset($out['content'])) {
                                if (is_string($out['content'])) {
                                    $collected[] = $out['content'];
                                } elseif (is_array($out['content'])) {
                                    $collected[] = json_encode($out['content']);
                                }
                            }
                        }
                        if (!empty($collected)) {
                            $result = implode("\n", $collected);
                            error_log("[Attempt $attempt] SUCCESS: extracted from 'outputs', length=" . strlen($result));
                            return $result;
                        }
                    }
                }

                // If we reach here, try to treat response as plain text
                if (is_string($response) && trim($response) !== '') {
                    error_log("[Attempt $attempt] SUCCESS: treating response as plain text, length=" . strlen($response));
                    return $response;
                }

                $lastError = "[Attempt $attempt] Could not parse Ollama response: " . substr($response ?? '', 0, 500);
                error_log($lastError);
                usleep(min(8000000, 500000 * (1 << ($attempt - 1))));
            } catch (\Throwable $e) {
                $lastError = "[Attempt $attempt] Exception: " . $e->getMessage();
                error_log($lastError);
                usleep(min(8000000, 500000 * (1 << ($attempt - 1))));
            }
        }

        // Final fallback: try file_get_contents (stream) once
        try {
            error_log("Attempting fallback with file_get_contents...");
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'timeout' => 120,
                    'content' => $payload
                ]
            ];
            $context = stream_context_create($opts);
            $resp = @file_get_contents($this->endpoint, false, $context);
            if ($resp !== false) {
                error_log("Fallback file_get_contents succeeded, response length=" . strlen($resp));
                // attempt parsing
                $j = json_decode($resp, true);
                if (is_array($j)) {
                    if (isset($j['response'])) return $j['response'];
                    if (isset($j['text'])) return $j['text'];
                }
                if (is_string($resp) && trim($resp) !== '') return $resp;
            }
        } catch (\Throwable $e) {
            error_log('Ollama fallback error: ' . $e->getMessage());
        }

        error_log("OllamaClient::generateTheme() FAILED after $maxAttempts attempts. Last error: $lastError");
        throw new \Exception('Ollama theme generation failed after ' . $maxAttempts . ' attempts. ' . $lastError);
    }
}
