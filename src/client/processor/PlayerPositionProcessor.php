<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client\processor;

use Lyrica0954\Mineleft\client\MineleftClient;
use Lyrica0954\Mineleft\network\protocol\PacketPlayerTeleport;
use Lyrica0954\Mineleft\player\PlayerSession;
use pocketmine\math\Vector3;

class PlayerPositionProcessor {

	public static function process(MineleftClient $client, PlayerSession $session, Vector3 $position): void {
		$packet = new PacketPlayerTeleport();
		$packet->worldName = $session->getPlayer()->getWorld()->getFolderName();
		$packet->playerUuid = $session->getUuid();
		$packet->position = $position;

		$client->getSession()->sendPacket($packet);
	}
}