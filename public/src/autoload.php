<?php
declare(strict_types=1);

// Manual autoload for the src/ classes (no Composer dependency).
// Order matters: interfaces first, then implementations, then dependents.

$srcDir = __DIR__;

require_once $srcDir . '/BucketStrategy.php';
require_once $srcDir . '/ThresholdBucketStrategy.php';
require_once $srcDir . '/LineParser.php';
require_once $srcDir . '/MbnetLineParser.php';
require_once $srcDir . '/GameDefinition.php';
require_once $srcDir . '/DrawAnalysis.php';
require_once $srcDir . '/ProfileDescriber.php';
require_once $srcDir . '/MetricCalculator.php';
require_once $srcDir . '/MetricTextProvider.php';
require_once $srcDir . '/DrawRepository.php';
require_once $srcDir . '/CoOccurrenceRepository.php';
require_once $srcDir . '/AnalysisConfig.php';
require_once $srcDir . '/GameRegistry.php';
require_once $srcDir . '/GameKit.php';
