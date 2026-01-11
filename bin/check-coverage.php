#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CI Helper Script: Code Coverage Threshold Checker
 *
 * Usage: php bin/check-coverage.php <path-to-clover.xml> <threshold-percent>
 */

$inputFile = $argv[1] ?? 'var/coverage/clover.xml';
$threshold = (float) ($argv[2] ?? 55.0);

if (!file_exists($inputFile)) {
    fwrite(STDERR, sprintf("\033[31m[ERROR] Coverage file not found at: %s\033[0m\n", $inputFile));
    exit(1);
}

echo sprintf("Reading coverage report from: %s\n", $inputFile);

try {
    // Suppress warnings for simplexml loading to handle them as exceptions if needed,
    // though strict mode usually handles this.
    $xml = simplexml_load_file($inputFile);

    if ($xml === false) {
        throw new RuntimeException('Failed to parse XML file.');
    }

    // XPath to metrics
    $metrics = $xml->xpath('//project/metrics');

    if (!$metrics || !isset($metrics[0])) {
        throw new RuntimeException('Could not find <metrics> element in Clover XML.');
    }

    $metric = $metrics[0];
    $coveredMethods = (int) $metric['coveredmethods'];
    $totalMethods   = (int) $metric['methods'];

    if ($totalMethods === 0) {
        fwrite(STDERR, "\033[33m[WARN] No methods found in project. Coverage is 0%.
");
        $percentage = 0.0;
    } else {
        $percentage = round(($coveredMethods / $totalMethods) * 100, 2);
    }

    echo sprintf("Coverage: %.2f%% (%d/%d methods)\n", $percentage, $coveredMethods, $totalMethods);

    if ($percentage < $threshold) {
        fwrite(STDERR, sprintf(
            "\033[31m[FAIL] Coverage %.2f%% is below the required threshold of %.2f%%.\033[0m\n",
            $percentage,
            $threshold
        ));
        exit(1);
    }

    echo sprintf("\033[32m[PASS] Coverage %.2f%% meets the threshold of %.2f%%.\033[0m\n", $percentage, $threshold);
    exit(0);

} catch (Throwable $e) {
    fwrite(STDERR, sprintf("\033[31m[ERROR] An error occurred: %s\033[0m\n", $e->getMessage()));
    exit(1);
}
