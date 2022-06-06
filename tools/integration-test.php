<?php

/**
 * Runs integration tests for
 * this plugin.
 */

declare(strict_types=1);

function download($file, $url): void
{
	file_put_contents($file, file_get_contents($url));
}

function run(string $command): int
{
	echo "\t>> $command\n";
	exec($command, $output, $code);
	return $code;
}

function cp_dir($src, $dst)
{
	$dir = opendir($src);
	@mkdir($dst);
	while (($file = readdir($dir))) {
		if (($file != '.') && ($file != '..')) {
			if (is_dir($src . '/' . $file)) {
				cp_dir($src . '/' . $file, $dst . '/' . $file);
			} else {
				copy($src . '/' . $file, $dst . '/' . $file);
			}
		}
	}
	closedir($dir);
}

function rm_dir($dir) {
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
			if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
				rm_dir($dir. DIRECTORY_SEPARATOR .$object);
			else
				unlink($dir. DIRECTORY_SEPARATOR .$object);
			}
		}
		rmdir($dir);
	}
}

$exitCode = 0;

$testsLocation = 'tests/integration/cases';
$sources = ['src', 'plugin.yml', 'resources'];
$sourcesString = implode(',', $sources);
$php = 'php -dphar.readonly=0';
$pluginName = 'Minigames';
$pocketmineVersion = '4.4.0';
$pocketmine = 'build/PocketMine-MP.phar';
$consoleScript = 'build/ConsoleScript.php';
$fakePlayer = 'build/FakePlayer.phar';
$testerPlugin = 'build/Tester.phar';
$awaitGenerator = 'build/AwaitGenerator.phar';
$awaitStd = 'build/AwaitStd.phar';

@mkdir('build');
download($pocketmine, "https://github.com/pmmp/PocketMine-MP/releases/download/$pocketmineVersion/PocketMine-MP.phar");
download($consoleScript, 'https://github.com/pmmp/DevTools/raw/master/src/ConsoleScript.php');
download($awaitGenerator, 'https://poggit.pmmp.io/r/167694/await-generator_dev-81.phar');
download($awaitStd, 'https://poggit.pmmp.io/r/177586/await-std_dev-23.phar');
download($fakePlayer, 'https://poggit.pmmp.io/r/180628/FakePlayer_pr-8.phar');

echo "! Building $pluginName plugin...\n";
@unlink("build/$pluginName.phar");
run("$php $consoleScript --make $sourcesString --relative . --out build/$pluginName.phar");

echo "! Building Tester plugin\n";
@unlink($testerPlugin);
run("$php $consoleScript --make src,plugin.yml --relative tests/integration/plugin/ --out $testerPlugin");
run("$php $awaitGenerator $testerPlugin");
run("$php $awaitStd $testerPlugin");

foreach (new DirectoryIterator($testsLocation) as $file) {
	if ($file->getFilename()[0] === '.' || !$file->isDir()) continue;

	$name = $file->getFilename();
	$data = $file->getPathname() . "/data";

	echo "! Compiling data directory...\n";
	rm_dir('build/data');
	@mkdir('build/data/plugins', 0777, true);

	foreach ([
		'banned-ips.txt', 'banned-players.txt',
		'ops.txt', 'whitelist.txt',
	] as $file) {
		touch("build/data/$file");
	}

	cp_dir('tests/integration/common/data', 'build/data');
	cp_dir($data, 'build/data');
	copy("build/$pluginName.phar", "build/data/plugins/$pluginName.phar");
	copy($testerPlugin, 'build/data/plugins/Tester.phar');
	copy($fakePlayer, 'build/data/plugins/FakePlayer.phar');

	echo "! Starting PocketMine-MP...\n";
	$cmd = 'php ' . realpath($pocketmine);
	$proc_fds = [
		["pipe", "r"],
		["pipe", "w"],
		["pipe", "w"]
	];
	$process = proc_open($cmd, $proc_fds, $pipes, realpath("./build/data/"));
	$testPassed = false;
	if (!is_resource($process)) {
		echo "~ Failed to open pocketmine server process! ~";
		exit(1);
	}
	$kill = null;
	$r = [$pipes[1]];
	$w = null;
	$e = null;
	while (null === $kill || microtime(true) - $kill < 3) {
		if (
			null === $kill ||
			stream_select(
				$r, $w, $e,
				(int) ceil(3 + $kill - microtime(true)), 0
			) === 1
		) {
			$line = fgets($pipes[1]);
			if ($line === false) {
				break;
			}
			echo $line;
			if (str_contains($line, "All Tests Succeeded!")) {
				$testPassed = true;
			}
			if (
				str_contains($line, "[Server thread/ERROR]") ||
				str_contains($line, "[Server thread/EMERGENCY]") ||
				str_contains($line, "[Server thread/CRITICAL]") ||
				str_contains($line, "Test Failed")
			) {
				$kill = microtime(true);
			}
		}
	}
	if (null !== $kill) {
		echo "! Killing process early!\n";
		proc_terminate($process);
	} else {
		proc_close($process);
	}

	echo "! Done\n";
	if ($testPassed) {
		echo "$ Test Succeeded!\n";
	} else {
		echo "$ Test Failed!\n";
		$exitCode = 1;
	}
}

exit($exitCode);
