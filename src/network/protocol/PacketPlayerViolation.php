<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\network\protocol\handler\IMineleftPacketHandler;
use Lyrica0954\Mineleft\network\protocol\types\ViolationLevel;
use Lyrica0954\Mineleft\utils\CodecHelper;
use pocketmine\utils\BinaryStream;
use RuntimeException;

class PacketPlayerViolation extends MineleftPacket {

	public int $profileRuntimeId;

	public string $message;

	public ViolationLevel $level;

	public function getProtocolId(): int {
		return ProtocolIds::PLAYER_VIOLATION;
	}

	public function encode(BinaryStream $out): void {
		$out->putInt($this->profileRuntimeId);
		CodecHelper::putString($out, $this->message);
		$out->putInt($this->level->value);
	}

	public function decode(BinaryStream $in): void {
		$this->profileRuntimeId = $in->getInt();
		$this->message = CodecHelper::getString($in);
		$this->level = ViolationLevel::tryFrom($in->getInt()) ?? throw new RuntimeException("Invalid violation level");
	}

	public function bounds(): PacketBounds {
		return PacketBounds::CLIENT;
	}

	public function callHandler(IMineleftPacketHandler $packetHandler): void {
		$packetHandler->handlePlayerViolation($this);
	}
}
