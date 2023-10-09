<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client\processor;

use Lyrica0954\Mineleft\client\MineleftClient;
use Lyrica0954\Mineleft\network\protocol\PacketSetPlayerFlags;
use Lyrica0954\Mineleft\network\protocol\types\PlayerFlags;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;

class PlayerFlagsProcessor {

	public static function process(MineleftClient $client, NetworkSession $session): void {
		$packet = new PacketSetPlayerFlags();
		$packet->playerUuid = $session->getPlayerInfo()->getUuid();
		$packet->flags = 0;

		$stored = $client->getActorStateStore($session)->getMetadata($session->getPlayer()->getId());

		if (self::hasGenericFlag($stored, EntityMetadataFlags::SPRINTING))
			$packet->flags |= (1 << PlayerFlags::SPRINTING);
		if (self::hasGenericFlag($stored, EntityMetadataFlags::SNEAKING))
			$packet->flags |= (1 << PlayerFlags::SNEAKING);
		if (self::hasGenericFlag($stored, EntityMetadataFlags::IMMOBILE)) {
			$packet->flags |= (1 << PlayerFlags::IMMOBILE);
		}

		$client->getSession()->sendPacket($packet);
	}

	public static function hasGenericFlag(EntityMetadataCollection $collection, int $flagId): ?bool {
		$propertyId = self::getPropertyIdFromGenericFlag($flagId);
		$list = $collection->getAll();
		$flagSetProp = $list[$propertyId] ?? null;
		if ($flagSetProp instanceof LongMetadataProperty) {
			$flags = $flagSetProp->getValue();

			return ($flags & (1 << $flagId)) !== 0;
		} else {
			return null;
		}
	}

	public static function getPropertyIdFromGenericFlag(int $flagId): int {
		return $flagId >= 64 ? EntityMetadataProperties::FLAGS2 : EntityMetadataProperties::FLAGS;
	}

}
