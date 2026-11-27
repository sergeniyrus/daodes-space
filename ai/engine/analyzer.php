<?php

require_once __DIR__ . '/ollama_client.php';

class Analyzer
{
    public static function analyze($context, $rules, $task, $config): array
    {
        $contextJson = json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($contextJson === false) {
            throw new RuntimeException('Не удалось сериализовать контекст проекта.');
        }

        // Не обрезаем JSON посередине: повреждённый контекст опаснее отказа анализа.
        $maxPromptContext = (int) ($config['max_analyzer_context_bytes'] ?? 8000);
        if (strlen($contextJson) > $maxPromptContext) {
            throw new RuntimeException(
                'Контекст для архитектурного анализа превышает лимит: ' .
                strlen($contextJson) . ' байт, лимит ' . $maxPromptContext . ' байт.'
            );
        }

        $rules = self::cutText(
            (string) $rules,
            (int) ($config['max_rules_bytes'] ?? 2500)
        );
        $task = self::cutText(
            (string) $task,
            (int) ($config['max_task_chars'] ?? 700)
        );

        $prompt = "You are DAODES AI ENGINE. Be concise and base every suggestion only on supplied files.\n\n" .
            "TASK:\n{$task}\n\nRULES:\n{$rules}\n\nPROJECT CONTEXT (JSON):\n{$contextJson}\n\n" .
            "Return concise sections: problem analysis, risks, Laravel 11-safe solution, exact files, patch plan. " .
            "Do not claim code was changed. Do not output shell commands. " .
            "If the supplied context does not prove a concrete problem, say that no safe change is justified.";

        $result = OllamaClient::generate(
            $prompt,
            $config['models']['architecture'],
            $config,
            'architecture'
        );

        if (($result['done_reason'] ?? null) === 'length') {
            throw new RuntimeException(
                'Архитектурный анализ достиг лимита токенов и мог быть обрезан. ' .
                'Патч не будет генерироваться на основе неполного анализа.'
            );
        }

        return $result;
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
