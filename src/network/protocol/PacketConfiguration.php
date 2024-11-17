<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\network\protocol\types\ChunkSendingMethod;
use Lyrica0954\Mineleft\utils\CodecHelper;
use pocketmine\utils\BinaryStream;
use RuntimeException;

class PacketConfiguration extends MineleftPacket {

	public string $defaultWorldName;

	public ChunkSendingMethod $chunkSendingMethod;

	public function getProtocolId(): int {
		return ProtocolIds::CONFIGURATION;
	}

	public function encode(BinaryStream $out): void {
		CodecHelper::putString($out, $this->defaultWorldName);
		$out->putInt($this->chunkSendingMethod->value);
	}

	public function decode(BinaryStream $in): void {
		$this->defaultWorldName = CodecHelper::getString($in);
		$this->chunkSendingMethod = ChunkSendingMethod::tryFrom($in->getInt()) ?? throw new RuntimeException("Invalid chunk sending method");
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
