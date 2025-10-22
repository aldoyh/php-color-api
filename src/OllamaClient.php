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
        // If the requested model isn't available, try to pick an available model automatically
        $this->model = $model;
        try {
            if (!\Frontify\ColorApi\OllamaUtils::modelExists($this->model)) {
                $available = \Frontify\ColorApi\OllamaUtils::getAvailableModels();
                if (!empty($available)) {
                    $old = $this->model;
                    $this->model = $available[0];
                    error_log("OllamaClient: requested model '$old' not found, falling back to '{$this->model}'");
                } else {
                    error_log("OllamaClient: requested model '{$this->model}' not found and no available models returned");
                }
            }
        } catch (\Throwable $e) {
            // If any error occurs during model check, keep the original model and continue
            error_log('OllamaClient model check error: ' . $e->getMessage());
        }
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
        $maxAttempts = 3;
        $lastError = null;
        $payload = json_encode($data);
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
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

            // If non-2xx, log and retry a couple times
            if ($httpCode < 200 || $httpCode >= 300) {
                $lastError = "[Attempt $attempt] HTTP $httpCode from Ollama: " . substr($response, 0, 2000);
                error_log($lastError);
                usleep(min(8000000, 500000 * (1 << ($attempt - 1))));
                continue;
            }

            // Try flexible parsing: 'response', 'outputs', 'text', or raw string
            $json = json_decode($response, true);
            if (is_array($json)) {
                if (isset($json['response'])) {
                    return $json['response'];
                }
                if (isset($json['text'])) {
                    return $json['text'];
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
                        return implode("\n", $collected);
                    }
                }
                // If array but no known keys, attempt to find 'response' nested or return first string value
                array_walk_recursive($json, function ($v) use (&$lastError, &$json): void {
                    // noop - just to iterate
                });
            }

            // If we reach here, try to treat response as plain text
            if (is_string($response) && trim($response) !== '') {
                return $response;
            }

            $lastError = "[Attempt $attempt] Unparseable Ollama response: " . substr($response ?? '', 0, 2000);
            error_log($lastError);
            usleep(250000 * $attempt);
        }

        // Final fallback: try file_get_contents (stream) once
        try {
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'timeout' => 8,
                    'content' => $payload
                ]
            ];
            $context = stream_context_create($opts);
            $resp = @file_get_contents($this->endpoint, false, $context);
            if ($resp !== false) {
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

        throw new \Exception('Ollama theme generation failed after ' . $maxAttempts . ' attempts. Last error: ' . $lastError);
    }
}
