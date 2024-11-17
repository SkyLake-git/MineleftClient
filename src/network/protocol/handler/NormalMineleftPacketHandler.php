<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol\handler;

use Lyrica0954\Mineleft\network\MineleftSession;
use Lyrica0954\Mineleft\network\protocol\PacketCorrectMovement;
use Lyrica0954\Mineleft\network\protocol\PacketPlayerViolation;
use Lyrica0954\Mineleft\network\protocol\PacketSimulationFrameDebug;
use Lyrica0954\Mineleft\player\PlayerProfileManager;
use pocketmine\Server;

class NormalMineleftPacketHandler implements IMineleftPacketHandler {

	public function __construct(
		private readonly MineleftSession $session
	) {
	}

	public function handleCorrectMovement(PacketCorrectMovement $packet): void {
		$playerProfile = PlayerProfileManager::getSessionById($packet->profileRuntimeId);

		$playerProfile?->queueCorrectMovement($packet);
	}

	public function handlePlayerViolation(PacketPlayerViolation $packet): void {

		$playerProfile = PlayerProfileManager::getSessionById($packet->profileRuntimeId);
		Server::getInstance()->broadcastMessage("Violation {$playerProfile->getPlayer()->getName()}: $packet->message ({$packet->level->displayName()})");
	}

	public function handleSimulationFrameDebug(PacketSimulationFrameDebug $packet): void {
		$playerProfile = PlayerProfileManager::getSessionById($packet->profileRuntimeId);

		if ($playerProfile !== null) {
			$posDiff = round($packet->position->distance($packet->clientPosition), 6);
			$deltaDiff = round($packet->delta->distance($packet->clientDelta), 6);
			if ($posDiff <= 1e-7) {
				$posDiff = 0;
			}

			if ($deltaDiff <= 1e-7) {
				$deltaDiff = 0;
			}
			$processingDelay = $playerProfile->getTickId() - $packet->frame;
			$playerProfile->getPlayer()->sendActionBarMessage("§7---------- Mineleft ----------\n§qProcessing Delay: §c$processingDelay F\n§qPosition Dist§7: §c$posDiff   §qDelta Dist§7: §c$deltaDiff");
		}
	}
}
