<?php

/**
 * Initializes necessary config files after
 * `composer install` is called.
 */

declare(strict_types=1);

$sep = DIRECTORY_SEPARATOR;
$configDir = realpath(__DIR__ . $sep . '..' . $sep . 'config');

$phpstanConfig = $configDir . $sep . 'phpstan.neon';
if (!file_exists($phpstanConfig)) {
	file_put_contents($phpstanConfig, <<<'EOT'
	includes:
		- phpstan.neon.dist
	EOT . "\n");
}
