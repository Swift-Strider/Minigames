<?php

declare(strict_types=1);

use DiamondStrider1\Tester\Main;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerJoinEvent;

return function () {
	$tester = Main::getInstance();
	$server = $tester->getServer();
	yield "let players join" => function () use ($tester, $server) {
		if (count($server->getOnlinePlayers()) !== 3) {
			$evFilter = fn($_) => count($server->getOnlinePlayers()) === 3;
			yield from $tester->getAwaitStd()->awaitEvent(
				PlayerJoinEvent::class, $evFilter, false, EventPriority::MONITOR, false
			);
		}
	};
};
