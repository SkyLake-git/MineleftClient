<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\network\protocol\handler\IMineleftPacketHandler;
use Lyrica0954\Mineleft\utils\CodecHelper;
use pocketmine\math\Vector3;
use pocketmine\utils\BinaryStream;

class PacketCorrectMovement extends MineleftPacket {

	public int $profileRuntimeId;

	public Vector3 $position;

	public Vector3 $delta;

	public bool $onGround;

	public int $frame;

	public function getProtocolId(): int {
		return ProtocolIds::CORRECT_MOVEMENT;
	}

	public function encode(BinaryStream $out): void {
		$out->putInt($this->profileRuntimeId);
		CodecHelper::putVec3f($out, $this->position);
		CodecHelper::putVec3f($out, $this->delta);
		$out->putBool($this->onGround);
		$out->putInt($this->frame);
	}

	public function decode(BinaryStream $in): void {
		$this->profileRuntimeId = $in->getInt();
		$this->position = CodecHelper::getVec3f($in);
		$this->delta = CodecHelper::getVec3f($in);
		$this->onGround = $in->getBool();
		$this->frame = $in->getInt();
	}

	public function bounds(): PacketBounds {
		return PacketBounds::CLIENT;
	}

	public function callHandler(IMineleftPacketHandler $packetHandler): void {
		$packetHandler->handleCorrectMovement($this);
	}
}
