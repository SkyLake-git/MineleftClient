<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\utils\BinaryUtils;
use pocketmine\utils\BinaryStream;

class PacketConfiguration extends MineleftPacket {

	public string $defaultWorldName;

	public function getProtocolId(): int {
		return ProtocolIds::CONFIGURATION;
	}

	public function encode(BinaryStream $out): void {
		BinaryUtils::putString($out, $this->defaultWorldName);
	}

	public function decode(BinaryStream $in): void {
		$this->defaultWorldName = BinaryUtils::getString($in);
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
