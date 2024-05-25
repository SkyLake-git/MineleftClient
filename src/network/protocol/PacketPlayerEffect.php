<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\network\protocol\types\Effect;
use Lyrica0954\Mineleft\utils\BinaryUtils;
use pocketmine\utils\BinaryStream;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class PacketPlayerEffect extends MineleftPacket {

	const MODE_ADD = 0;
	const MODE_MODIFY = 1;
	const MODE_REMOVE = 2;

	public Effect $effect;

	public int $amplifier;

	public UuidInterface $playerUuid;

	public int $mode;

	public function getProtocolId(): int {
		return ProtocolIds::PLAYER_EFFECT;
	}

	public function encode(BinaryStream $out): void {
		BinaryUtils::putString($out, $this->playerUuid->toString());
		$out->putInt($this->effect->value);
		$out->putInt($this->amplifier);
		$out->putInt($this->mode);
	}

	public function decode(BinaryStream $in): void {
		$this->playerUuid = Uuid::fromString(BinaryUtils::getString($in));
		$this->effect = Effect::from($in->getInt());
		$this->amplifier = $in->getInt();
		$this->mode = $in->getInt();
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
