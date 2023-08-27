<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\Packet;
use ReflectionClass;

abstract class MineleftPacket implements Packet {

	public function getName(): string {
		return (new ReflectionClass($this))->getShortName();
	}

	abstract public function getProtocolId(): int;
}
