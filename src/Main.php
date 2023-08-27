<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft;

use Lyrica0954\Mineleft\client\MineleftClient;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

class Main extends PluginBase {

	protected MineleftClient $client;


	protected function onEnable(): void {
		$this->client = new MineleftClient($this->getServer(), "127.0.0.1", 19170, $this->getLogger());

		$this->client->start();

		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask($this->client->tick(...)), 1);
		$this->getServer()->getPluginManager()->registerEvents($this->client->getListener(), $this);
	}

}
