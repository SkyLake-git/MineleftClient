<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client\processor;

use Lyrica0954\Mineleft\client\MineleftClient;
use Lyrica0954\Mineleft\network\protocol\PacketSetPlayerAttribute;
use Lyrica0954\Mineleft\player\PlayerSession;
use pocketmine\entity\Attribute;
use pocketmine\network\mcpe\protocol\types\entity\Attribute as NetworkAttribute;

class PlayerAttributeProcessor {
	/**
	 * @param MineleftClient $client
	 * @param PlayerSession $session
	 * @param NetworkAttribute[] $attributes
	 * @return void
	 */
	public static function process(MineleftClient $client, PlayerSession $session, array $attributes): void {
		$attributesMap = [];

		foreach ($attributes as $attr) {
			$attributesMap[$attr->getId()] = $attr;
		}

		$movementSpeed = ($attributesMap[Attribute::MOVEMENT_SPEED] ?? null)?->getCurrent();

		if ($movementSpeed === null) {
			return;
		}

		$packet = new PacketSetPlayerAttribute();
		$packet->playerUuid = $session->getUuid();
		$packet->movementSpeed = $movementSpeed;

		$client->getSession()->sendPacket($packet);
	}
}
