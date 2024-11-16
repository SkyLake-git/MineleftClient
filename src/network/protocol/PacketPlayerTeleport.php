<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\utils\BinaryUtils;
use pocketmine\math\Vector3;
use pocketmine\utils\BinaryStream;
use Ramsey\Uuid\UuidInterface;

class PacketPlayerTeleport extends MineleftPacket {

	public UuidInterface $playerUuid;

	public string $worldName;

	public Vector3 $position;

	public function getProtocolId(): int {
		return ProtocolIds::PLAYER_TELEPORT;
	}

	public function encode(BinaryStream $out): void {
		BinaryUtils::putUUID($out, $this->playerUuid);
		BinaryUtils::putString($out, $this->worldName);
		BinaryUtils::putVec3f($out, $this->position);
	}

	public function decode(BinaryStream $in): void {
		$this->playerUuid = BinaryUtils::getUUID($in);
		$this->worldName = BinaryUtils::getString($in);
		$this->position = BinaryUtils::getVec3f($in);
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
