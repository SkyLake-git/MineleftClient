<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\mc;

use Lyrica0954\Mineleft\utils\BinaryUtils;
use pocketmine\math\AxisAlignedBB;
use pocketmine\utils\BinaryStream;

class Block {

	protected int $networkId;

	protected string $identifier;

	/**
	 * @var AxisAlignedBB[]
	 */
	protected array $collisionBoxes;

	/**
	 * @param int $networkId
	 * @param string $identifier
	 * @param AxisAlignedBB[] $collisionBoxes
	 */
	public function __construct(int $networkId, string $identifier, array $collisionBoxes) {
		$this->networkId = $networkId;
		$this->identifier = $identifier;
		$this->collisionBoxes = $collisionBoxes;
	}

	/**
	 * @return string
	 */
	public function getIdentifier(): string {
		return $this->identifier;
	}

	/**
	 * @return array
	 */
	public function getCollisionBoxes(): array {
		return $this->collisionBoxes;
	}

	/**
	 * @return int
	 */
	public function getNetworkId(): int {
		return $this->networkId;
	}

	public function read(BinaryStream $stream): void {
		$this->networkId = $stream->getInt();
		$this->identifier = BinaryUtils::getString($stream);
		$this->collisionBoxes = [];
		$count = $stream->getInt();
		for ($i = 0; $i < $count; $i++) {
			$this->collisionBoxes[] = new AxisAlignedBB(
				$stream->getDouble(),
				$stream->getDouble(),
				$stream->getDouble(),
				$stream->getDouble(),
				$stream->getDouble(),
				$stream->getDouble()
			);
		}
	}

	public function write(BinaryStream $stream): void {
		$stream->putInt($this->networkId);
		BinaryUtils::putString($stream, $this->identifier);
		$stream->putInt(count($this->collisionBoxes));

		foreach ($this->collisionBoxes as $box) {
			$stream->putDouble($box->minX);
			$stream->putDouble($box->minY);
			$stream->putDouble($box->minZ);
			$stream->putDouble($box->maxX);
			$stream->putDouble($box->maxY);
			$stream->putDouble($box->maxZ);
		}
	}
}
