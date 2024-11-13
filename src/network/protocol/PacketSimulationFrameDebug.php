<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\network\protocol\handler\IMineleftPacketHandler;
use Lyrica0954\Mineleft\utils\BinaryUtils;
use pocketmine\math\Vector3;
use pocketmine\utils\BinaryStream;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class PacketSimulationFrameDebug extends MineleftPacket {

	public UuidInterface $playerUuid;

	public int $frame;

	public Vector3 $position;

	public Vector3 $delta;

	public Vector3 $clientPosition;

	public Vector3 $clientDelta;

	public function getProtocolId(): int {
		return ProtocolIds::SIMULATION_FRAME_DEBUG;
	}

	public function encode(BinaryStream $out): void {
		BinaryUtils::putString($out, $this->playerUuid->toString());
		$out->putInt($this->frame);
		BinaryUtils::putVec3f($out, $this->position);
		BinaryUtils::putVec3f($out, $this->delta);
		BinaryUtils::putVec3f($out, $this->clientPosition);
		BinaryUtils::putVec3f($out, $this->clientDelta);
	}

	public function decode(BinaryStream $in): void {
		$this->playerUuid = Uuid::fromString(BinaryUtils::getString($in));
		$this->frame = $in->getInt();
		$this->position = BinaryUtils::getVec3f($in);
		$this->delta = BinaryUtils::getVec3f($in);
		$this->clientPosition = BinaryUtils::getVec3f($in);
		$this->clientDelta = BinaryUtils::getVec3f($in);
	}

	public function bounds(): PacketBounds {
		return PacketBounds::CLIENT;
	}

	public function callHandler(IMineleftPacketHandler $packetHandler): void {
		$packetHandler->handleSimulationFrameDebug($this);
	}
}
