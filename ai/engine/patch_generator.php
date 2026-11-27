<?php

require_once __DIR__ . '/ollama_client.php';

class PatchGenerator
{
    public static function generate(
        $analysis,
        array $config,
        array $context = [],
        string $task = '',
        string $rules = ''
    ): string {
        if ($context === []) {
            throw new RuntimeException('Генератору патча не передан контекст исходных файлов.');
        }

        $analysisText = is_array($analysis)
            ? (string) ($analysis['response'] ?? '')
            : (string) $analysis;

        $analysisText = self::cutText($analysisText, (int) ($config['max_analysis_bytes'] ?? 3500));
        $task = self::cutText(trim($task), (int) ($config['max_task_chars'] ?? 700));
        $rules = self::cutText(trim($rules), (int) ($config['max_generator_rules_bytes'] ?? 1500));

        if (trim($analysisText) === '') {
            throw new RuntimeException('Архитектурный анализ пуст. Генерация патча отменена.');
        }

        $contextJson = json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($contextJson === false) {
            throw new RuntimeException('Не удалось сериализовать исходники для генератора патча.');
        }

        $maxContextJsonBytes = (int) ($config['max_generator_context_bytes'] ?? 10000);
        if (strlen($contextJson) > $maxContextJsonBytes) {
            throw new RuntimeException(
                'Контекст исходников превышает лимит генератора: ' . strlen($contextJson) .
                ' байт, лимит ' . $maxContextJsonBytes . ' байт.'
            );
        }

        $allowedPaths = [];
        foreach ($context as $item) {
            if (!isset($item['path'], $item['content']) || !is_string($item['path']) || !is_string($item['content'])) {
                throw new RuntimeException('Контекст содержит файл неизвестного формата.');
            }

            $allowedPaths[] = str_replace('\\', '/', $item['path']);
        }

        $allowedPaths = array_values(array_unique($allowedPaths));

        $prompt = "You are the patch-generation stage of the DAODES AI Engine.\n" .
            "Generate a minimal, real patch using ONLY the exact source files supplied below.\n\n" .
            "NON-NEGOTIABLE RULES:\n" .
            "1. You may modify existing files listed in ALLOWED FILE PATHS only.\n" .
            "2. Do not create, delete, rename, or invent files.\n" .
            "3. Read the supplied file contents and make changes against those exact contents.\n" .
            "4. Never replace PHP source with JSON, placeholder text, examples, or unrelated content.\n" .
            "5. Preserve existing functionality, routes, public method names, and variable names unless the task explicitly requires a change.\n" .
            "6. Do not change generated files, dependencies, lock files, environment files, or build artifacts.\n" .
            "7. Return a valid unified Git diff with one complete diff block per changed file.\n" .
            "8. Every block must contain: diff --git, ---, +++, and at least one @@ hunk.\n" .
            "9. Do not use Markdown fences, explanations, summaries, or text before/after the diff.\n" .
            "10. If no safe, concrete change is justified by the task and supplied source, return exactly NO_SAFE_PATCH.\n\n" .
            "USER TASK:\n" . $task . "\n\n" .
            "ARCHITECTURE ANALYSIS:\n" . $analysisText . "\n\n" .
            "PROJECT RULES:\n" . $rules . "\n\n" .
            "ALLOWED FILE PATHS:\n" . implode("\n", $allowedPaths) . "\n\n" .
            "EXACT SOURCE FILES (JSON; each item has path and content):\n" . $contextJson . "\n\n" .
            "Return only the unified diff or NO_SAFE_PATCH.";

        $result = OllamaClient::generate(
            $prompt,
            $config['models']['code'],
            $config,
            'code'
        );

        if (($result['done_reason'] ?? null) === 'length') {
            throw new RuntimeException(
                'Генерация diff достигла лимита токенов. Неполный патч отклонён.'
            );
        }

        $diff = trim((string) ($result['response'] ?? ''));

        if ($diff === 'NO_SAFE_PATCH') {
            throw new RuntimeException('AI Engine не обнаружил безопасных изменений для указанной задачи.');
        }

        if ($diff === '') {
            throw new RuntimeException('Модель генерации вернула пустой diff.');
        }

        $maxDiffBytes = (int) ($config['max_diff_bytes'] ?? 12000);
        if (strlen($diff) > $maxDiffBytes) {
            throw new RuntimeException(
                'Сгенерированный diff превышает допустимый размер: ' . strlen($diff) . ' байт.'
            );
        }

        self::validateGeneratedDiff($diff, $allowedPaths);

        return $diff . "\n";
    }

    private static function validateGeneratedDiff(string $diff, array $allowedPaths): void
    {
        if (str_contains($diff, '```') || preg_match('/^diff--git\b/m', $diff)) {
            throw new RuntimeException('Diff содержит Markdown-обрамление или некорректный заголовок.');
        }

        if (!str_starts_with($diff, 'diff --git ')) {
            throw new RuntimeException('Ответ модели не начинается с заголовка unified diff.');
        }

        $blocks = preg_split('/(?=^diff --git )/m', $diff, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($blocks) || $blocks === []) {
            throw new RuntimeException('Не удалось разделить ответ на diff-блоки.');
        }

        $allowed = array_fill_keys($allowedPaths, true);
        $seen = [];

        foreach ($blocks as $block) {
            $lines = preg_split('/\r?\n/', trim($block));
            if (!$lines || !preg_match('/^diff --git a\/([^\s]+) b\/([^\s]+)$/', $lines[0], $header)) {
                throw new RuntimeException('Обнаружен некорректный заголовок diff --git.');
            }

            $oldPath = $header[1];
            $newPath = $header[2];

            if ($oldPath !== $newPath) {
                throw new RuntimeException('Переименование файлов генератором запрещено.');
            }

            $path = str_replace('\\', '/', $newPath);
            if (!isset($allowed[$path])) {
                throw new RuntimeException('Модель пытается изменить файл, которого нет в контексте: ' . $path);
            }

            if (isset($seen[$path])) {
                throw new RuntimeException('В diff повторяется один и тот же файл: ' . $path);
            }
            $seen[$path] = true;

            $oldHeaders = preg_grep('/^--- /', $lines) ?: [];
            $newHeaders = preg_grep('/^\+\+\+ /', $lines) ?: [];
            $hunks = preg_grep('/^@@ /', $lines) ?: [];

            if (count($oldHeaders) !== 1 || count($newHeaders) !== 1 || count($hunks) < 1) {
                throw new RuntimeException('Diff-блок не содержит корректные заголовки файлов и hunk.');
            }

            if (
                trim(reset($oldHeaders)) !== '--- a/' . $path ||
                trim(reset($newHeaders)) !== '+++ b/' . $path
            ) {
                throw new RuntimeException('Пути в diff --git и ---/+++ не совпадают: ' . $path);
            }

            if (preg_match('/^(?:new file mode|deleted file mode|old mode|new mode|rename from|rename to|copy from|copy to|GIT binary patch)\b/m', $block)) {
                throw new RuntimeException('Создание, удаление, переименование, смена режима и бинарные патчи запрещены.');
            }
        }
    }

    private static function cutText(string $value, int $maxBytes): string
    {
        if ($maxBytes <= 0 || strlen($value) <= $maxBytes) {
            return $value;
        }

        if (function_exists('mb_strcut')) {
            return mb_strcut($value, 0, $maxBytes, 'UTF-8');
        }

        return substr($value, 0, $maxBytes);
    }
}
