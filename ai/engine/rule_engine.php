<?php

class RuleEngine
{
    public static function loadRules(string $rulesPath): string
    {
        if (!is_dir($rulesPath)) {
            throw new RuntimeException('Каталог правил AI Engine не найден: ' . $rulesPath);
        }

        $files = glob(rtrim($rulesPath, DIRECTORY_SEPARATOR) . '/*.md') ?: [];
        sort($files, SORT_STRING);

        $rules = [];

        foreach ($files as $file) {
            if (!is_file($file) || !is_readable($file)) {
                continue;
            }

            $content = file_get_contents($file);

            if ($content !== false && trim($content) !== '') {
                $rules[] = "# " . basename($file) . "\n" . $content;
            }
        }

        return implode("\n\n", $rules);
    }
}
