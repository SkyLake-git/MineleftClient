<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\utils\BinaryUtils;
use pocketmine\utils\BinaryStream;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class PacketSetPlayerFlags extends MineleftPacket {

	public UuidInterface $playerUuid;

	public int $flags;

	public function getProtocolId(): int {
		return ProtocolIds::SET_PLAYER_FLAGS;
	}

	public function encode(BinaryStream $out): void {
		BinaryUtils::putString($out, $this->playerUuid->toString());
		$out->putLong($this->flags);
	}

	public function decode(BinaryStream $in): void {
		$this->playerUuid = Uuid::fromString(BinaryUtils::getString($in));
		$this->flags = $in->getLong();
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
