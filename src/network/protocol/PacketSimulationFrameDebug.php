<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\network\protocol\handler\IMineleftPacketHandler;
use Lyrica0954\Mineleft\utils\CodecHelper;
use pocketmine\math\Vector3;
use pocketmine\utils\BinaryStream;

class PacketSimulationFrameDebug extends MineleftPacket {

	public int $profileRuntimeId;

	public int $frame;

	public Vector3 $position;

	public Vector3 $delta;

	public Vector3 $clientPosition;

	public Vector3 $clientDelta;

	public function getProtocolId(): int {
		return ProtocolIds::SIMULATION_FRAME_DEBUG;
	}

	public function encode(BinaryStream $out): void {
		$out->putInt($this->profileRuntimeId);
		$out->putInt($this->frame);
		CodecHelper::putVec3f($out, $this->position);
		CodecHelper::putVec3f($out, $this->delta);
		CodecHelper::putVec3f($out, $this->clientPosition);
		CodecHelper::putVec3f($out, $this->clientDelta);
	}

	public function decode(BinaryStream $in): void {
		// todo: improve uuid encoding
		$this->profileRuntimeId = $in->getInt();
		$this->frame = $in->getInt();
		$this->position = CodecHelper::getVec3f($in);
		$this->delta = CodecHelper::getVec3f($in);
		$this->clientPosition = CodecHelper::getVec3f($in);
		$this->clientDelta = CodecHelper::getVec3f($in);
	}

	public function bounds(): PacketBounds {
		return PacketBounds::CLIENT;
	}

	public function callHandler(IMineleftPacketHandler $packetHandler): void {
		$packetHandler->handleSimulationFrameDebug($this);
	}
}
