<?php

declare(strict_types=1);

$arguments = $_SERVER['argv'] ?? [];
$report = simplexml_load_file($arguments[1] ?? 'coverage/clover.xml');
$metrics = $report === false ? null : ($report->xpath('/coverage/project/metrics')[0] ?? null);
if (!$metrics instanceof SimpleXMLElement || (int) $metrics['statements'] <= 0) {
    fwrite(STDERR, "Missing or empty coverage report.\n");
    exit(1);
}
$coverage = 100 * (int) $metrics['coveredstatements'] / (int) $metrics['statements'];
$minimum = (float) ($arguments[2] ?? 80);
fwrite(STDOUT, sprintf("Backend line coverage %.2f%%; required %.2f%%\n", $coverage, $minimum));
exit($coverage >= $minimum ? 0 : 1);
