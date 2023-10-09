<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol\handler;

use Lyrica0954\Mineleft\network\MineleftSession;
use Lyrica0954\Mineleft\network\protocol\PacketPlayerViolation;
use pocketmine\Server;

class NormalMineleftPacketHandler implements IMineleftPacketHandler {

	public function __construct(
		private readonly MineleftSession $session
	) {
	}

	public function handlePlayerViolation(PacketPlayerViolation $packet): void {
		$this->session->getLogger()->info("Violation {$packet->playerUuid}: $packet->message ({$packet->level->displayName()})");

		Server::getInstance()->broadcastMessage("Violation {$packet->playerUuid}: $packet->message ({$packet->level->displayName()})");
	}
}
