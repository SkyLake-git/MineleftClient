<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\utils\BinaryUtils;
use pocketmine\math\Vector3;
use pocketmine\utils\BinaryStream;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class PacketSetPlayerMotion extends MineleftPacket {

	public UuidInterface $playerUuid;

	public Vector3 $motion;

	public function getProtocolId(): int {
		return ProtocolIds::SET_PLAYER_MOTION;
	}

	public function encode(BinaryStream $out): void {
		BinaryUtils::putString($out, $this->playerUuid->toString());
		BinaryUtils::putVec3d($out, $this->motion);
	}

	public function decode(BinaryStream $in): void {
		$this->playerUuid = Uuid::fromString(BinaryUtils::getString($in));
		$this->motion = BinaryUtils::getVec3d($in);
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
