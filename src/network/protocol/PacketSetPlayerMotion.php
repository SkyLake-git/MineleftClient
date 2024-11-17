<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\utils\CodecHelper;
use pocketmine\math\Vector3;
use pocketmine\utils\BinaryStream;

class PacketSetPlayerMotion extends MineleftPacket {

	public int $profileRuntimeId;

	public Vector3 $motion;

	public function getProtocolId(): int {
		return ProtocolIds::SET_PLAYER_MOTION;
	}

	public function encode(BinaryStream $out): void {
		$out->putInt($this->profileRuntimeId);
		CodecHelper::putVec3f($out, $this->motion);
	}

	public function decode(BinaryStream $in): void {
		$this->profileRuntimeId = $in->getInt();
		$this->motion = CodecHelper::getVec3f($in);
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
