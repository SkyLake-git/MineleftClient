<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\network\protocol\types\BlockPosition;
use Lyrica0954\Mineleft\utils\CodecHelper;
use pocketmine\utils\BinaryStream;

class PacketUpdateBlock extends MineleftPacket {

	/**
	 * todo: use
	 */
	public array $viewerProfileRuntimeIds;

	public BlockPosition $blockPosition;

	public int $block;

	public function getProtocolId(): int {
		return ProtocolIds::UPDATE_BLOCK;
	}

	public function encode(BinaryStream $out): void {
		$out->putInt(count($this->viewerProfileRuntimeIds));
		foreach ($this->viewerProfileRuntimeIds as $id) {
			$out->putInt($id);
		}
		CodecHelper::putBlockPosition($out, $this->blockPosition);
		$out->putInt($this->block);
	}

	public function decode(BinaryStream $in): void {
		$this->viewerProfileRuntimeIds = [];
		$count = $in->getInt();
		for ($i = 0; $i < $count; $i++) {
			$this->viewerProfileRuntimeIds[] = $in->getInt();
		}
		$this->blockPosition = CodecHelper::getBlockPosition($in);
		$this->block = $in->getInt();
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
