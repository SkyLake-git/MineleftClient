<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\network\protocol\types\ProfileDebugOptions;
use pocketmine\utils\BinaryStream;

/**
 * preferences?
 */
class PacketUpdateProfileSettings extends MineleftPacket {

	public int $profileRuntimeId;

	public ProfileDebugOptions $debugOptions;

	public function getProtocolId(): int {
		return ProtocolIds::UPDATE_PROFILE_SETTINGS;
	}

	public function encode(BinaryStream $out): void {
		$out->putInt($this->profileRuntimeId);
		$this->debugOptions->write($out);
	}

	public function decode(BinaryStream $in): void {
		$this->profileRuntimeId = $in->getInt();
		$this->debugOptions = new ProfileDebugOptions();
		$this->debugOptions->read($in);
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}