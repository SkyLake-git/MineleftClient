<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use pocketmine\utils\BinaryStream;

class PacketSetPlayerFlags extends MineleftPacket {

	public int $profileRuntimeId;

	public int $flags;

	public function getProtocolId(): int {
		return ProtocolIds::SET_PLAYER_FLAGS;
	}

	public function encode(BinaryStream $out): void {
		$out->putInt($this->profileRuntimeId);
		$out->putLong($this->flags);
	}

	public function decode(BinaryStream $in): void {
		$this->profileRuntimeId = $in->getInt();
		$this->flags = $in->getLong();
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
