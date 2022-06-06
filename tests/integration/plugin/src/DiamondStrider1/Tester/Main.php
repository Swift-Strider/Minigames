<?php

declare(strict_types=1);

namespace DiamondStrider1\Tester;

use Exception;
use pocketmine\plugin\PluginBase;
use SOFe\AwaitGenerator\Await as ShadedAwait;
use SOFe\AwaitStd\AwaitStd;

final class Main extends PluginBase
{
	private AwaitStd $awaitStd;

	public function onEnable(): void
	{
		ShadedAwait::f2c(function () {
			$tests = require $this->getDataFolder() . 'tests.php';
			foreach ($tests() as $name => $test) {
				$this->getLogger()->notice("Running Test: $name");
				try {
					yield from $test();
				} catch (Exception $e) {
					$this->getLogger()->critical("Test Failed: $name");
					$this->getLogger()->logException($e);
					$this->getServer()->shutdown();
				}
			}
			$this->getLogger()->notice("All Tests Succeeded!");
			$this->getServer()->shutdown();
		});
	}

	private static self $instance;

	public static function getInstance(): self
	{
		return self::$instance;
	}

	public function onLoad(): void
	{
		self::$instance = $this;
		$this->awaitStd = AwaitStd::init($this);
	}

	public function getAwaitStd(): AwaitStd
	{
		return $this->awaitStd;
	}
}

class_alias(ShadedAwait::class, 'SOFe\AwaitGenerator\Await');
