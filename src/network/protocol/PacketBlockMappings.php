<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\mc\Block;
use Lyrica0954\Mineleft\net\PacketBounds;
use pocketmine\utils\BinaryStream;

class PacketBlockMappings extends MineleftPacket {

	/**
	 * @var array<int, Block>
	 */
	public array $mappings;

	public function getProtocolId(): int {
		return ProtocolIds::BLOCK_MAPPINGS;
	}

	public function encode(BinaryStream $out): void {
		$out->putInt(count($this->mappings));

		foreach ($this->mappings as $block) {
			$block->write($out);
		}
	}

	public function decode(BinaryStream $in): void {
		$count = $in->getInt();
		$this->mappings = [];
		for ($i = 0; $i < $count; $i++) {
			$block = new Block(0, "", []);
			$block->read($in);

			$this->mappings[$block->getNetworkId()] = $block;
		}
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
