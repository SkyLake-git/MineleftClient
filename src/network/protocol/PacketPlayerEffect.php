<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\network\protocol\types\Effect;
use pocketmine\utils\BinaryStream;

class PacketPlayerEffect extends MineleftPacket {

	const MODE_ADD = 0;
	const MODE_MODIFY = 1;
	const MODE_REMOVE = 2;

	public int $profileRuntimeId;

	public Effect $effect;

	public int $amplifier;

	public int $mode;

	public function getProtocolId(): int {
		return ProtocolIds::PLAYER_EFFECT;
	}

	public function encode(BinaryStream $out): void {
		$out->putInt($this->profileRuntimeId);
		$out->putInt($this->effect->value);
		$out->putInt($this->amplifier);
		$out->putInt($this->mode);
	}

	public function decode(BinaryStream $in): void {
		$this->profileRuntimeId = $in->getInt();
		$this->effect = Effect::from($in->getInt());
		$this->amplifier = $in->getInt();
		$this->mode = $in->getInt();
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
