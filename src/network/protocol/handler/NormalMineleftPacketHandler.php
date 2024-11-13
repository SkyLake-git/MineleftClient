<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol\handler;

use Lyrica0954\Mineleft\network\MineleftSession;
use Lyrica0954\Mineleft\network\protocol\PacketCorrectMovement;
use Lyrica0954\Mineleft\network\protocol\PacketPlayerViolation;
use Lyrica0954\Mineleft\network\protocol\PacketSimulationFrameDebug;
use Lyrica0954\Mineleft\player\PlayerSessionManager;
use pocketmine\Server;

class NormalMineleftPacketHandler implements IMineleftPacketHandler {

	public function __construct(
		private readonly MineleftSession $session
	) {
	}

	public function handleCorrectMovement(PacketCorrectMovement $packet): void {
		$player = Server::getInstance()->getPlayerByUUID($packet->playerUuid);

		if ($player !== null) {
			$playerSession = PlayerSessionManager::getSession($player);
			$playerSession->queueCorrectMovement($packet);
		}
	}

	public function handlePlayerViolation(PacketPlayerViolation $packet): void {

		$player = Server::getInstance()->getPlayerByUUID($packet->playerUuid);

		if ($player !== null) {
			$playerSession = PlayerSessionManager::getSession($player);

			Server::getInstance()->broadcastMessage("Violation {$player->getName()}: $packet->message ({$packet->level->displayName()})");
		}
	}

	public function handleSimulationFrameDebug(PacketSimulationFrameDebug $packet): void {
		$player = Server::getInstance()->getPlayerByUUID($packet->playerUuid);

		if ($player !== null) {
			$playerSession = PlayerSessionManager::getSession($player);
			$posDiff = round($packet->position->distance($packet->clientPosition), 6);
			$deltaDiff = round($packet->delta->distance($packet->clientDelta), 6);
			if ($posDiff <= 1e-7) {
				$posDiff = 0;
			}

			if ($deltaDiff <= 1e-7) {
				$deltaDiff = 0;
			}
			$processingDelay = $playerSession->getTickId() - $packet->frame;
			$player->sendActionBarMessage("§7---------- Mineleft ----------\n§qProcessing Delay: §c$processingDelay F\n§qPosition Dist§7: §c$posDiff   §qDelta Dist§7: §c$deltaDiff");
		}
	}
}
