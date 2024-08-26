<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\network\protocol\types\PlayerInfo;
use Lyrica0954\Mineleft\utils\BinaryUtils;
use pocketmine\math\Vector3;
use pocketmine\utils\BinaryStream;
use Ramsey\Uuid\Uuid;

class PacketPlayerLogin extends MineleftPacket {

	public PlayerInfo $playerInfo;

	public string $worldName;

	public Vector3 $position;

	public function getProtocolId(): int {
		return ProtocolIds::PLAYER_LOGIN;
	}

	public function encode(BinaryStream $out): void {
		BinaryUtils::putString($out, $this->playerInfo->getName());
		BinaryUtils::putString($out, $this->playerInfo->getUuid()->toString());
		BinaryUtils::putString($out, $this->worldName);
		BinaryUtils::putVec3f($out, $this->position);
	}

	public function decode(BinaryStream $in): void {
		$name = BinaryUtils::getString($in);
		$uuid = Uuid::fromString(BinaryUtils::getString($in));

		$this->playerInfo = new PlayerInfo($name, $uuid);

		$this->worldName = BinaryUtils::getString($in);
		$this->position = BinaryUtils::getVec3f($in);
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
