<?php

declare(strict_types=1);

/*
 * Fails when line coverage of the pure layers (Domain, Application) drops below a threshold.
 * Usage: php tests/coverage-gate.php <clover.xml> <min-percent>
 */

$arguments = $_SERVER['argv'] ?? [];
$cloverFile = is_array($arguments) ? ($arguments[1] ?? null) : null;
$minimum = is_array($arguments) ? ($arguments[2] ?? null) : null;
if (!is_string($cloverFile) || !is_numeric($minimum)) {
    fwrite(\STDERR, "Usage: php tests/coverage-gate.php <clover.xml> <min-percent>\n");
    exit(2);
}

$clover = simplexml_load_file($cloverFile);
if (false === $clover) {
    fwrite(\STDERR, "Cannot read {$cloverFile}\n");
    exit(2);
}

$covered = 0;
$total = 0;
$missed = [];
$files = $clover->xpath('//file');
foreach (is_array($files) ? $files : [] as $file) {
    $path = (string) $file['name'];
    if (1 !== preg_match('#/src/[A-Za-z]+/(Domain|Application)/#', $path)) {
        continue;
    }
    $metrics = $file->metrics;
    if (null === $metrics) {
        continue;
    }
    $statements = (int) $metrics['statements'];
    $coveredStatements = (int) $metrics['coveredstatements'];
    $total += $statements;
    $covered += $coveredStatements;
    if ($coveredStatements < $statements) {
        $missed[] = sprintf('  %s (%d/%d)', preg_replace('#^.*/src/#', 'src/', $path), $coveredStatements, $statements);
    }
}

$percent = 0 === $total ? 100.0 : 100 * $covered / $total;
printf("Domain + Application line coverage: %.1f%% (%d/%d, minimum %s%%)\n", $percent, $covered, $total, $minimum);

if ($percent < (float) $minimum) {
    fwrite(\STDERR, "Below threshold. Not fully covered:\n".implode("\n", $missed)."\n");
    exit(1);
}
