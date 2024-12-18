<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\player\debug;

use Logger;
use Lyrica0954\Mineleft\client\MineleftClient;
use Lyrica0954\Mineleft\network\protocol\PacketSimulationFrameDebug;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\player\Player;
use PrefixedLogger;

class PlayerDebuggingProfile {

	private Logger $logger;

	private MineleftDebugOption $option;

	private ?MineleftDebugVirtualEntity $virtualEntity;

	public function __construct(
		private readonly MineleftClient $mineleftClient,
		private readonly Player         $player
	) {
		$this->option = new MineleftDebugOption(false);
		$this->logger = new PrefixedLogger($this->mineleftClient->getSession()->getLogger(), "Debugging: {$this->player->getName()}");
		$this->virtualEntity = null;
	}

	/**
	 * @return MineleftDebugOption
	 */
	public function getOption(): MineleftDebugOption {
		return $this->option;
	}

	public function despawnSimulatingEntity(): void {
		if ($this->virtualEntity === null) {
			return;
		}
		$this->virtualEntity->despawnFrom($this->player);
		$this->virtualEntity = null;
	}

	public function isSimulationEnabled(): bool {
		return $this->option->simulation;
	}

	public function handleSimulationFrameDebug(PacketSimulationFrameDebug $packet): void {
		if (!$this->option->simulation) {
			return;
		}

		if ($this->virtualEntity === null) {
			$this->spawnSimulatingEntity();
		}

		$this->virtualEntity->broadcastMovement($packet->position, $packet->yaw, $packet->pitch);
	}

	public function spawnSimulatingEntity(): void {
		if ($this->virtualEntity !== null) {
			return;
		}
		$this->virtualEntity = new MineleftDebugVirtualEntity($this->player->getName(), TypeConverter::getInstance()->getSkinAdapter()->toSkinData($this->player->getSkin()), $this->player->getPosition());
		$this->virtualEntity->spawnTo($this->player);
	}
}
