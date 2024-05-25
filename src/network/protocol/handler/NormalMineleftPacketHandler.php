<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol\handler;

use Lyrica0954\Mineleft\network\MineleftSession;
use Lyrica0954\Mineleft\network\protocol\PacketPlayerViolation;
use Lyrica0954\Mineleft\player\PlayerSessionManager;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\CorrectPlayerMovePredictionPacket;
use pocketmine\Server;

class NormalMineleftPacketHandler implements IMineleftPacketHandler {

	public function __construct(
		private readonly MineleftSession $session
	) {
	}

	public function handlePlayerViolation(PacketPlayerViolation $packet): void {

		$player = Server::getInstance()->getPlayerByUUID($packet->playerUuid);

		if ($player !== null) {
			$playerSession = PlayerSessionManager::getSession($player);

			Server::getInstance()->broadcastMessage("Violation {$player->getName()}: $packet->message ({$packet->level->displayName()})");

			$pk = CorrectPlayerMovePredictionPacket::create(
				$player->getPosition(),
				new Vector3(0, 1, 0),
				true,
				$playerSession->getTickId(),
				CorrectPlayerMovePredictionPacket::PREDICTION_TYPE_PLAYER
			);

			// $player->getNetworkSession()->sendDataPacket($pk);
		}
	}
}
