<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client\processor;

use Lyrica0954\Mineleft\client\MineleftClient;
use Lyrica0954\Mineleft\network\protocol\PacketSetPlayerMotion;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;

class PlayerMotionProcessor {

	public static function process(MineleftClient $client, NetworkSession $session, Vector3 $motion): void {
		$packet = new PacketSetPlayerMotion();
		$packet->playerUuid = $session->getPlayerInfo()->getUuid();
		$packet->motion = $motion;

		$client->getSession()->sendPacket($packet);
	}
}
