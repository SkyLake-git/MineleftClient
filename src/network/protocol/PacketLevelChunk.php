<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\utils\CodecHelper;
use pocketmine\utils\BinaryStream;

class PacketLevelChunk extends MineleftPacket {

	public int $x;

	public int $z;

	public string $extraPayload;

	public string $worldName;

	public function getProtocolId(): int {
		return ProtocolIds::LEVEL_CHUNK;
	}

	public function encode(BinaryStream $out): void {
		$out->putInt($this->x);
		$out->putInt($this->z);

		CodecHelper::putString($out, $this->extraPayload);
		CodecHelper::putString($out, $this->worldName);
	}

	public function decode(BinaryStream $in): void {
		$this->x = $in->getInt();
		$this->z = $in->getInt();
		$this->extraPayload = CodecHelper::getString($in);

		$this->worldName = CodecHelper::getString($in);
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
