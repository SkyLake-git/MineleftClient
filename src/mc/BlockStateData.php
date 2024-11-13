<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\mc;

use Lyrica0954\Mineleft\utils\BinaryUtils;
use pocketmine\utils\BinaryStream;

class BlockStateData {

	/**
	 * @var array<string, int>
	 */
	public array $integerMap;

	public function __construct() {
		$this->integerMap = [];
	}

	public function read(BinaryStream $stream): void {
		$count = $stream->getInt();

		$this->integerMap = [];
		for ($i = 0; $i < $count; $i++) {
			$this->integerMap[BinaryUtils::getString($stream)] = $stream->getInt();
		}
	}

	public function write(BinaryStream $stream): void {
		$stream->putInt(count($this->integerMap));

		foreach ($this->integerMap as $k => $v) {
			BinaryUtils::putString($stream, $k);
			$stream->putInt($v);
		}
	}
}
