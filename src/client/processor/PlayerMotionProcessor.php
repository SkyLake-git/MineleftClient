<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client\processor;

use Lyrica0954\Mineleft\client\MineleftClient;
use Lyrica0954\Mineleft\network\protocol\PacketSetPlayerMotion;
use Lyrica0954\Mineleft\player\PlayerSession;
use pocketmine\math\Vector3;

class PlayerMotionProcessor {

	public static function process(MineleftClient $client, PlayerSession $session, Vector3 $motion): void {
		$packet = new PacketSetPlayerMotion();
		$packet->playerUuid = $session->getUuid();
		$packet->motion = $motion;

		$client->getSession()->sendPacket($packet);
	}
}
