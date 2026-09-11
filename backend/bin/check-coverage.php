<?php

declare(strict_types=1);

$file = $argv[1] ?? 'coverage/clover.xml';
$minimum = (float) ($argv[2] ?? 80);
$xml = simplexml_load_file($file);
if ($xml === false) {
    throw new RuntimeException('Unable to read Clover coverage report.');
}
$metrics = $xml->project->metrics;
$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percentage = $statements === 0 ? 0.0 : ($covered / $statements) * 100;
fwrite(STDOUT, sprintf("Statement coverage: %.2f%%\n", $percentage));
exit($percentage >= $minimum ? 0 : 1);
