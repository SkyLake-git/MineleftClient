<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\mc;

use Lyrica0954\Mineleft\utils\CodecHelper;
use pocketmine\math\AxisAlignedBB;
use pocketmine\utils\BinaryStream;

class Block {

	protected int $networkId;

	protected string $identifier;

	/**
	 * @var AxisAlignedBB[]
	 */
	protected array $collisionBoxes;

	protected float $friction;

	protected int $flags;

	protected BlockStateData $stateData;

	/**
	 * @param int $networkId
	 * @param string $identifier
	 * @param AxisAlignedBB[] $collisionBoxes
	 * @param float $friction
	 */
	public function __construct(int $networkId, string $identifier, array $collisionBoxes, float $friction = 0.6) {
		$this->networkId = $networkId;
		$this->identifier = $identifier;
		$this->collisionBoxes = $collisionBoxes;
		$this->friction = $friction;
		$this->flags = 0;
		$this->stateData = new BlockStateData();
	}

	/**
	 * @return int
	 */
	public function getFlags(): int {
		return $this->flags;
	}

	public function appendAttributeFlag(int $flag): void {
		$this->flags |= (1 << $flag);
	}

	public function hasAttributeFlag(int $flag): bool {
		return ($this->flags & (1 << $flag)) != 0;
	}

	/**
	 * @return float
	 */
	public function getFriction(): float {
		return $this->friction;
	}

	/**
	 * @return BlockStateData
	 */
	public function getStateData(): BlockStateData {
		return $this->stateData;
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
		$this->identifier = CodecHelper::getString($stream);
		$this->collisionBoxes = [];
		$count = $stream->getInt();
		for ($i = 0; $i < $count; $i++) {
			$this->collisionBoxes[] = new AxisAlignedBB(
				$stream->getFloat(),
				$stream->getFloat(),
				$stream->getFloat(),
				$stream->getFloat(),
				$stream->getFloat(),
				$stream->getFloat()
			);
		}
		$this->friction = $stream->getFloat();
		$this->flags = $stream->getInt();
		$this->stateData = new BlockStateData();
		$this->stateData->read($stream);
	}

	public function write(BinaryStream $stream): void {
		$stream->putInt($this->networkId);
		CodecHelper::putString($stream, $this->identifier);
		$stream->putInt(count($this->collisionBoxes));

		foreach ($this->collisionBoxes as $box) {
			$stream->putFloat($box->minX);
			$stream->putFloat($box->minY);
			$stream->putFloat($box->minZ);
			$stream->putFloat($box->maxX);
			$stream->putFloat($box->maxY);
			$stream->putFloat($box->maxZ);
		}

		$stream->putFloat($this->friction);
		$stream->putInt($this->flags);
		$this->stateData->write($stream);
	}
}
