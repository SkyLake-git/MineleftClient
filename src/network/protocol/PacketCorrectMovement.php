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

class PacketCorrectMovement extends MineleftPacket {

	public UuidInterface $playerUuid;

	public Vector3 $position;

	public Vector3 $delta;

	public bool $onGround;

	public int $frame;

	public function getProtocolId(): int {
		return ProtocolIds::CORRECT_MOVEMENT;
	}

	public function encode(BinaryStream $out): void {
		BinaryUtils::putString($out, $this->playerUuid->toString());
		BinaryUtils::putVec3f($out, $this->position);
		BinaryUtils::putVec3f($out, $this->delta);
		$out->putBool($this->onGround);
		$out->putInt($this->frame);
	}

	public function decode(BinaryStream $in): void {
		$this->playerUuid = Uuid::fromString(BinaryUtils::getString($in));
		$this->position = BinaryUtils::getVec3f($in);
		$this->delta = BinaryUtils::getVec3f($in);
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
