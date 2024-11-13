<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol\handler;

use Lyrica0954\Mineleft\network\protocol\PacketCorrectMovement;
use Lyrica0954\Mineleft\network\protocol\PacketPlayerViolation;
use Lyrica0954\Mineleft\network\protocol\PacketSimulationFrameDebug;

interface IMineleftPacketHandler {

	public function handlePlayerViolation(PacketPlayerViolation $packet): void;

	public function handleCorrectMovement(PacketCorrectMovement $packet): void;

	public function handleSimulationFrameDebug(PacketSimulationFrameDebug $packet): void;
}
