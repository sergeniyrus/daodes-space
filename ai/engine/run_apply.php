<?php

$projectRoot = dirname(__DIR__, 2);

require_once $projectRoot . '/vendor/autoload.php';
require_once __DIR__ . '/apply_patch.php';

$patchFile = $argv[1] ?? null;

if (!$patchFile) {
    fwrite(STDERR, "Usage: php ai/engine/run_apply.php /absolute/path/to/patch.json\n");
    exit(1);
}

try {
    $queued = PatchApplier::apply($patchFile);

    echo "Patch queued for approval: " . $queued['id'] . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, "Queue error: " . $exception->getMessage() . PHP_EOL);
    exit(1);
}
