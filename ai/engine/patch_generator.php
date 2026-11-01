
<?php

class PatchGenerator
{
    public static function generate(array $analysis, array $config): array
    {
        $prompt = <<<PROMPT
You are a senior Laravel 11 software engineer.

Convert the supplied analysis into a REAL unified Git diff patch.

STRICT RULES:
- Preserve the existing project architecture.
- Use Laravel 11 and the existing PHP project conventions.
- Do not introduce Python, external scripts or unrelated dependencies.
- Do not modify routes, function names, class names or variable names unless explicitly required by the analysis.
- Do not invent files or project functionality.
- Do not output explanations, comments outside the patch, Markdown or code fences.
- Output ONLY a valid unified Git diff.
- Include file paths in the diff.
- Use standard Git diff format.
- If no safe patch can be generated, return an empty response.

ANALYSIS:
PROMPT;

        $prompt .= "\n" . json_encode(
            $analysis,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $result = self::callOllama(
            $prompt,
            $config['models']['code'],
            $config
        );

        $diff = self::extractDiff($result['response'] ?? '');

        if ($diff === '') {
            throw new RuntimeException(
                'Ollama did not return a valid unified diff.'
            );
        }

        return [
            'diff' => $diff,
            'model' => $config['models']['code'],
            'generated_at' => date('Y-m-d H:i:s'),
            'project_root' => $config['project_root'],
        ];
    }

    private static function callOllama(
        string $prompt,
        string $model,
        array $config
    ): array {
        $url = rtrim($config['ollama_url'], '/') . '/api/generate';

        $ch = curl_init($url);

        if ($ch === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 300,
        ]);

        $response = curl_exec($ch);

        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException(
                'Ollama connection failed: ' . $curlError
            );
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(
                'Ollama returned HTTP ' . $httpCode . ': ' . $response
            );
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'Invalid JSON response from Ollama.'
            );
        }

        if (!isset($decoded['response'])) {
            throw new RuntimeException(
                'Ollama response field is missing.'
            );
        }

        return $decoded;
    }

    private static function extractDiff(string $response): string
    {
        $response = trim($response);

        if ($response === '') {
            return '';
        }

        // Убираем Markdown-обёртку, если модель её всё-таки добавила.
        $response = preg_replace(
            '/^\s*```(?:diff|patch)?\s*/i',
            '',
            $response
        );

        $response = preg_replace(
            '/\s*```\s*$/',
            '',
            $response
        );

        $response = trim($response);

        // Находим начало настоящего unified diff.
        if (preg_match('/^(?:diff --git |--- )/m', $response, $matches, PREG_OFFSET_CAPTURE)) {
            $response = substr(
                $response,
                $matches[0][1]
            );
        } else {
            return '';
        }

        $response = trim($response);

        $hasGitHeader = str_starts_with($response, 'diff --git ');

        $hasUnifiedHeader =
            preg_match('/^--- .+$/m', $response) &&
            preg_match('/^\+\+\+ .+$/m', $response);

        if (!$hasGitHeader && !$hasUnifiedHeader) {
            return '';
        }

        // Патч должен содержать хотя бы одну секцию изменений.
        if (
            !preg_match('/^@@ /m', $response) &&
            !preg_match('/^new file mode /m', $response) &&
            !preg_match('/^deleted file mode /m', $response)
        ) {
            return '';
        }

        return $response . "\n";
    }
}
