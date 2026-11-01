
<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI access only.\n");
}

require_once __DIR__ . '/apply_patch.php';

$id = $argv[1] ?? null;

if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/', $id)) {
    fwrite(STDERR, "Invalid or missing patch ID.\n");
    exit(1);
}

try {

    echo "Starting approved patch application...\n";
    echo "Patch ID: {$id}\n";

    PatchApplier::applyApprovedById($id);

    echo "DONE.\n";

    exit(0);

} catch (Throwable $e) {

    fwrite(
        STDERR,
        "PATCH APPLY ERROR: " . $e->getMessage() . "\n"
    );

    exit(1);
}
