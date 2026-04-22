#!/usr/bin/env php
<?php
require_once __DIR__ . '/lib/Migrator.php';
$cmd = $argv[1] ?? 'status';
$mig = new Migrator();
try {
    switch ($cmd) {
        case 'up':
            $applied = $mig->up();
            if (empty($applied)) { echo "No pending migrations.\n"; exit(0); }
            echo "Applied:\n";
            foreach ($applied as $a) echo "    $a\n";
            exit(0);
        case 'down':
            $version = $argv[2] ?? null;
            if (!$version) { fwrite(STDERR, "Usage: php migrate.php down <version>\n"); exit(1); }
            $mig->down($version);
            echo "Rolled back $version\n";
            exit(0);
        case 'status':
            $rows = $mig->status();
            if (empty($rows)) { echo "(no migrations found)\n"; exit(0); }
            printf("%-18s %-14s %-40s %s\n", 'VERSION', 'STATUS', 'NAME', 'APPLIED_AT');
            echo str_repeat('-', 100) . "\n";
            foreach ($rows as $r) {
                printf("%-18s %-14s %-40s %s\n",
                    $r['version'], $r['status'], substr($r['name'], 0, 40), $r['applied_at'] ?? '-');
            }
            exit(0);
        default:
            fwrite(STDERR, "Usage: php migrate.php [status|up|down <version>]\n");
            exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
