<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\utils\BinaryUtils;
use pocketmine\math\Vector3;
use pocketmine\utils\BinaryStream;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class PacketPlayerTeleport extends MineleftPacket {

	public UuidInterface $playerUuid;

	public string $worldName;

	public Vector3 $position;

	public function getProtocolId(): int {
		return ProtocolIds::PLAYER_TELEPORT;
	}

	public function encode(BinaryStream $out): void {
		BinaryUtils::putString($out, $this->playerUuid->toString());
		BinaryUtils::putString($out, $this->worldName);
		BinaryUtils::putVec3d($out, $this->position);
	}

	public function decode(BinaryStream $in): void {
		$this->playerUuid = Uuid::fromString(BinaryUtils::getString($in));
		$this->worldName = BinaryUtils::getString($in);
		$this->position = BinaryUtils::getVec3d($in);
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
