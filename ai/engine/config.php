<?php

return [
    'ollama_url' => 'http://127.0.0.1:11434',

    'models' => [
    'code' => 'deepseek-coder:6.7b',
    'architecture' => 'qwen3:8b',
    'embedding' => 'nomic-embed-text:latest',
],

    'project_root' => '/home/sergeniyrus/www/daodes',

    'rules_path' => '/home/sergeniyrus/www/daodes/ai/rules',

    'max_context_files' => 40,

    'ignore_paths' => [
        'vendor',
        'node_modules',
        'storage',
        '.git',
        'bootstrap/cache',
    ],
];
