
<?php

$config = require __DIR__ . '/config.php';

require __DIR__ . '/context_builder.php';
require __DIR__ . '/rule_engine.php';
require __DIR__ . '/analyzer.php';
require __DIR__ . '/patch_generator.php';
require __DIR__ . '/approval_queue.php';

$projectRoot = $config['project_root'];

if (!is_dir($projectRoot)) {
    die("Project root does not exist.\n");
}

chdir($projectRoot);

$task = $argv[1] ?? 'Analyze project structure';

try {

    echo "Building context...\n";

    $context = ContextBuilder::build(
        $projectRoot,
        $config
    );

    echo "Loading rules...\n";

    $rules = RuleEngine::loadRules(
        $config['rules_path']
    );

    echo "Running analysis...\n";

    $analysis = Analyzer::analyze(
        $context,
        $rules,
        $task,
        $config
    );

    echo "Generating patch...\n";

    $patch = PatchGenerator::generate(
        $analysis,
        $config
    );

    if (empty($patch['diff'])) {
        throw new RuntimeException(
            'Generated patch is empty.'
        );
    }

    echo "Adding patch to approval queue...\n";

    $queueItem = ApprovalQueue::add($patch);

    // Сохраняем старый файл для совместимости.
    $legacyFile = $projectRoot . '/ai_last_patch.txt';

    file_put_contents(
        $legacyFile,
        json_encode(
            $patch,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        ),
        LOCK_EX
    );

    echo "\n";
    echo "====================================\n";
    echo "AI PATCH GENERATED\n";
    echo "====================================\n";

    echo "Patch ID: " . $queueItem['id'] . "\n";
    echo "Status: " . $queueItem['status'] . "\n";
    echo "SHA256: " . $queueItem['sha256'] . "\n";

    echo "Patch saved to approval queue.\n";
    echo "Waiting for administrator approval.\n";

} catch (Throwable $e) {

    fwrite(
        STDERR,
        "\nAI ERROR: " . $e->getMessage() . "\n"
    );

    exit(1);
}
