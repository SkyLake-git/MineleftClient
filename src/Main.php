<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft;

use Lyrica0954\Mineleft\client\LatencyHandler;
use Lyrica0954\Mineleft\client\MineleftClient;
use Lyrica0954\Mineleft\player\PlayerSessionManager;
use pocketmine\event\EventPriority;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Binary;
use RuntimeException;

class Main extends PluginBase {

	protected static LatencyHandler $latencyHandler;

	private static ?self $instance;

	protected MineleftClient $client;

	/**
	 * @return LatencyHandler
	 */
	public static function getLatencyHandler(): LatencyHandler {
		return self::$latencyHandler;
	}

	/**
	 * @return Main
	 */
	public static function getInstance(): Main {
		return self::$instance ?? throw new RuntimeException("Plugin not loaded");
	}

	protected function onLoad(): void {
		self::$instance = $this;
		self::$latencyHandler = new LatencyHandler();

		printf("%.50f\n", Binary::readLFloat(Binary::writeLFloat(0.2)));
	}

	protected function onEnable(): void {
		$this->client = new MineleftClient($this->getServer(), "127.0.0.1", 19170, $this->getLogger());
		PlayerSessionManager::initClient($this->client);

		$this->client->start();

		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask($this->client->tick(...)), 1);
		$this->getServer()->getPluginManager()->registerEvents($this->client->getListener(), $this);
		$this->getServer()->getPluginManager()->registerEvent(
			DataPacketReceiveEvent::class,
			self::$latencyHandler->onDataPacketReceive(...),
			EventPriority::LOWEST,
			$this,
			true
		);
	}

}
