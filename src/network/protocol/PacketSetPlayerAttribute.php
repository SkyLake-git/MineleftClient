<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use pocketmine\utils\BinaryStream;

class PacketSetPlayerAttribute extends MineleftPacket {

	public int $profileRuntimeId;

	public float $movementSpeed;

	public function getProtocolId(): int {
		return ProtocolIds::SET_PLAYER_ATTRIBUTE;
	}

	public function encode(BinaryStream $out): void {
		$out->putInt($this->profileRuntimeId);
		$out->putFloat($this->movementSpeed);
	}

	public function decode(BinaryStream $in): void {
		$this->profileRuntimeId = $in->getInt();
		$this->movementSpeed = $in->getFloat();
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
