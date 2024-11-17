<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\utils\CodecHelper;
use pocketmine\math\Vector3;
use pocketmine\utils\BinaryStream;

class PacketPlayerTeleport extends MineleftPacket {

	public int $profileRuntimeId;

	public string $worldName;

	public Vector3 $position;

	public function getProtocolId(): int {
		return ProtocolIds::PLAYER_TELEPORT;
	}

	public function encode(BinaryStream $out): void {
		$out->putInt($this->profileRuntimeId);
		CodecHelper::putString($out, $this->worldName);
		CodecHelper::putVec3f($out, $this->position);
	}

	public function decode(BinaryStream $in): void {
		$this->profileRuntimeId = $in->getInt();
		$this->worldName = CodecHelper::getString($in);
		$this->position = CodecHelper::getVec3f($in);
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
