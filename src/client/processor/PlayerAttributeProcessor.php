<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client\processor;

use Lyrica0954\Mineleft\client\MineleftClient;
use Lyrica0954\Mineleft\network\MineleftSession;
use Lyrica0954\Mineleft\network\protocol\PacketSetPlayerAttribute;
use pocketmine\entity\Attribute;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\entity\Attribute as NetworkAttribute;

class PlayerAttributeProcessor {
	/**
	 * @param MineleftSession $mineleftSession
	 * @param NetworkSession $session
	 * @param NetworkAttribute[] $attributes
	 * @return void
	 */
	public static function process(MineleftClient $client, NetworkSession $session, array $attributes): void {
		$attributesMap = [];

		foreach ($attributes as $attr) {
			$attributesMap[$attr->getId()] = $attr;
		}

		$movementSpeed = ($attributesMap[Attribute::MOVEMENT_SPEED] ?? null)?->getCurrent();

		if ($movementSpeed === null) {
			return;
		}

		$packet = new PacketSetPlayerAttribute();
		$packet->playerUuid = $session->getPlayerInfo()->getUuid();
		$packet->movementSpeed = $movementSpeed;

		$client->getSession()->sendPacket($packet);
	}
}
