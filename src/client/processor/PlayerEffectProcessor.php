<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client\processor;

use Lyrica0954\Mineleft\client\MineleftClient;
use Lyrica0954\Mineleft\network\protocol\PacketPlayerEffect;
use Lyrica0954\Mineleft\network\protocol\types\Effect;
use Lyrica0954\Mineleft\player\PlayerSession;
use pocketmine\data\bedrock\EffectIds;
use pocketmine\network\mcpe\protocol\MobEffectPacket;
use RuntimeException;

class PlayerEffectProcessor {
	public static function process(MineleftClient $client, PlayerSession $session, int $effectId, int $effectAmplifier, int $mode): void {
		$netEffect = match ($effectId) {
			EffectIds::JUMP_BOOST => Effect::JUMP_BOOST,
			default => null
		};

		$netMode = match ($mode) {
			MobEffectPacket::EVENT_ADD => PacketPlayerEffect::MODE_ADD,
			MobEffectPacket::EVENT_MODIFY => PacketPlayerEffect::MODE_MODIFY,
			MobEffectPacket::EVENT_REMOVE => PacketPlayerEffect::MODE_REMOVE,
			default => throw new RuntimeException("Invalid event")
		};

		if ($netEffect === null) {
			return;
		}

		$pk = new PacketPlayerEffect();
		$pk->effect = $netEffect;
		$pk->amplifier = $effectAmplifier;
		$pk->playerUuid = $session->getUuid();
		$pk->mode = $netMode;

		$client->getSession()->sendPacket($pk);
	}
}
