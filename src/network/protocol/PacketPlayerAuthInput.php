<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\network\protocol\types\InputData;
use Lyrica0954\Mineleft\utils\BinaryUtils;
use pocketmine\utils\BinaryStream;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class PacketPlayerAuthInput extends MineleftPacket {

	public UuidInterface $playerUuid;

	public InputData $inputData;

	public function getProtocolId(): int {
		return ProtocolIds::PLAYER_AUTH_INPUT;
	}

	public function encode(BinaryStream $out): void {
		BinaryUtils::putString($out, $this->playerUuid->toString());

		$this->inputData->write($out);
	}

	public function decode(BinaryStream $in): void {
		$this->playerUuid = Uuid::fromString(BinaryUtils::getString($in));

		$inputData = new InputData();
		$inputData->read($in);
		
		$this->inputData = $inputData;
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
