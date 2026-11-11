<?php

class OllamaClient
{
    public static function ensureModelAvailable(string $model, array $config): void
    {
        $url = rtrim((string) ($config['ollama_url'] ?? ''), '/') . '/api/tags';
        $response = self::request($url, null, $config, 20);
        $decoded = json_decode($response['body'], true);

        if ($response['http_code'] < 200 || $response['http_code'] >= 300 || !is_array($decoded)) {
            throw new RuntimeException('Не удалось получить список моделей Ollama. HTTP ' . $response['http_code']);
        }

        $available = [];
        foreach (($decoded['models'] ?? []) as $item) {
            if (isset($item['name']) && is_string($item['name'])) {
                $available[] = $item['name'];
            }
        }

        if (!in_array($model, $available, true)) {
            throw new RuntimeException(
                'Модель Ollama не установлена: ' . $model . '. Установленные модели: ' .
                ($available ? implode(', ', $available) : 'список пуст')
            );
        }
    }

    public static function generate(string $prompt, string $model, array $config, string $purpose = 'code'): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP-расширение cURL не установлено.');
        }

        $baseUrl = rtrim((string) ($config['ollama_url'] ?? ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('Не задан адрес Ollama.');
        }

        $options = $config['ollama_options'] ?? [];
        $options['num_ctx'] = min(4096, max(1024, (int) ($options['num_ctx'] ?? 4096)));
        $options['num_batch'] = min(128, max(32, (int) ($options['num_batch'] ?? 128)));
        $options['num_predict'] = (int) ($config['ollama_num_predict'][$purpose] ?? 1200);

        $requestData = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false,
            'keep_alive' => $config['ollama_keep_alive'] ?? 0,
            'options' => $options,
        ];

        // Qwen3: отключаем thinking, чтобы итоговый текст возвращался в response.
        if ($purpose === 'architecture') {
            $requestData['think'] = false;
        }

        $payload = json_encode(
            $requestData,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($payload === false) {
            throw new RuntimeException('Не удалось сформировать запрос к Ollama.');
        }

        $response = self::request($baseUrl . '/api/generate', $payload, $config);
        $body = $response['body'];
        $httpCode = $response['http_code'];

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('Ollama вернула HTTP ' . $httpCode . ': ' . substr($body, 0, 1500));
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !isset($decoded['response']) || !is_string($decoded['response'])) {
            throw new RuntimeException('Ollama вернула ответ неизвестного формата: ' . substr($body, 0, 1000));
        }

        if (trim($decoded['response']) === '') {
            throw new RuntimeException(
                'Ollama вернула пустой response. Модель: ' . $model .
                ', purpose: ' . $purpose .
                ', done_reason: ' . ($decoded['done_reason'] ?? 'unknown')
            );
        }

        return $decoded;
    }

    private static function request(string $url, ?string $payload, array $config, ?int $timeout = null): array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Не удалось инициализировать cURL.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => (int) ($config['ollama_connect_timeout'] ?? 10),
            CURLOPT_TIMEOUT => $timeout ?? (int) ($config['ollama_timeout'] ?? 600),
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ];

        if ($payload !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $payload;
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }

        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException('Ошибка соединения с Ollama: ' . $error);
        }

        return [
            'body' => $body,
            'http_code' => $httpCode,
        ];
    }
}
