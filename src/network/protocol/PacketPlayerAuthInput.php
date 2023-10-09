<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\PacketBounds;
use Lyrica0954\Mineleft\network\protocol\types\InputData;
use Lyrica0954\Mineleft\utils\BinaryUtils;
use pocketmine\math\Vector3;
use pocketmine\utils\BinaryStream;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class PacketPlayerAuthInput extends MineleftPacket {

	public UuidInterface $playerUuid;

	public InputData $inputData;

	public Vector3 $requestedPosition;

	/**
	 * @var array<int, int>
	 */
	public array $nearbyBlocks;

	public function getProtocolId(): int {
		return ProtocolIds::PLAYER_AUTH_INPUT;
	}

	public function encode(BinaryStream $out): void {
		BinaryUtils::putString($out, $this->playerUuid->toString());

		$this->inputData->write($out);
		BinaryUtils::putVec3d($out, $this->requestedPosition);

		$withNearbyBlocks = count($this->nearbyBlocks) > 0;

		$out->putBool($withNearbyBlocks);
		if ($withNearbyBlocks) {
			$out->putInt(count($this->nearbyBlocks));
			foreach ($this->nearbyBlocks as $code => $block) {
				$out->putLong($code);
				$out->putInt($block);
			}
		}
	}

	public function decode(BinaryStream $in): void {
		$this->playerUuid = Uuid::fromString(BinaryUtils::getString($in));

		$inputData = new InputData();
		$inputData->read($in);

		$this->inputData = $inputData;
		$this->requestedPosition = BinaryUtils::getVec3d($in);

		$this->nearbyBlocks = [];
		$withNearbyBlocks = $in->getBool();
		if ($withNearbyBlocks) {
			$count = $in->getInt();
			for ($i = 0; $i < $count; $i++) {
				$this->nearbyBlocks[$in->getLong()] = $in->getInt();
			}
		}
	}

	public function bounds(): PacketBounds {
		return PacketBounds::SERVER;
	}
}
