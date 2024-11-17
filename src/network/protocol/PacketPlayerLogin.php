<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\network\protocol\types\PlayerInfo;
use Lyrica0954\Mineleft\utils\CodecHelper;
use pocketmine\math\Vector3;
use pocketmine\utils\BinaryStream;

class PacketPlayerLogin extends MineleftPacket {

	public PlayerInfo $playerInfo;

	public int $profileRuntimeId;

	public string $worldName;

	public Vector3 $position;

	public function getProtocolId(): int {
		return ProtocolIds::PLAYER_LOGIN;
	}

	public function encode(BinaryStream $out): void {
		CodecHelper::putString($out, $this->playerInfo->getName());
		CodecHelper::putUUID($out, $this->playerInfo->getUuid());
		$out->putInt($this->profileRuntimeId);
		CodecHelper::putString($out, $this->worldName);
		CodecHelper::putVec3f($out, $this->position);
	}

	public function decode(BinaryStream $in): void {
		$name = CodecHelper::getString($in);
		$uuid = CodecHelper::getUUID($in);

		$this->playerInfo = new PlayerInfo($name, $uuid);
		$this->profileRuntimeId = $in->getInt();
		$this->worldName = CodecHelper::getString($in);
		$this->position = CodecHelper::getVec3f($in);
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
