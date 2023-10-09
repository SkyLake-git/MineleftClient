<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol\handler;

use Lyrica0954\Mineleft\network\protocol\PacketPlayerViolation;

interface IMineleftPacketHandler {

	public function handlePlayerViolation(PacketPlayerViolation $packet): void;
}
