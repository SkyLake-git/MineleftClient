<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol\types;

use pocketmine\utils\BinaryStream;

class ProfileDebugOptions {

	public bool $simulation;

	public function __construct(bool $simulation = false) {
		$this->simulation = $simulation;
	}

	public function write(BinaryStream $stream): void {
		$stream->putBool($this->simulation);
	}

	public function read(BinaryStream $stream): void {
		$this->simulation = $stream->getBool();
	}
}
