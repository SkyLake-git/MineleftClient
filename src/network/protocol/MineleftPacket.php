<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\Packet;
use Lyrica0954\Mineleft\network\protocol\handler\IMineleftPacketHandler;
use ReflectionClass;
use RuntimeException;

abstract class MineleftPacket implements Packet {

	public function getName(): string {
		return (new ReflectionClass($this))->getShortName();
	}

	abstract public function getProtocolId(): int;

	public function callHandler(IMineleftPacketHandler $packetHandler): void {
		throw new RuntimeException("callHandler not implemented");
	}
}
