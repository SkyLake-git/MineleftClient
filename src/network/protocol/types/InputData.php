<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol\types;

use Lyrica0954\Mineleft\utils\BinaryUtils;
use pocketmine\math\Vector3;
use pocketmine\utils\BinaryStream;

class InputData {

	protected int $flags;

	protected Vector3 $delta;

	protected float $moveVecX;

	protected float $moveVecZ;

	protected float $yaw;

	protected float $pitch;

	public function __construct() {
		$this->flags = 0;
		$this->delta = Vector3::zero();
		$this->moveVecX = 0;
		$this->moveVecZ = 0;
		$this->yaw = 0;
		$this->pitch = 0;
	}

	public function read(BinaryStream $stream): void {
		$this->flags = $stream->getInt();
		$this->delta = BinaryUtils::getVec3d($stream);
		$this->moveVecX = $stream->getFloat();
		$this->moveVecZ = $stream->getFloat();
		$this->yaw = $stream->getFloat();
		$this->pitch = $stream->getFloat();
	}

	public function write(BinaryStream $stream): void {
		$stream->putInt($this->flags);
		BinaryUtils::putVec3d($stream, $this->delta);
		$stream->putFloat($this->moveVecX);
		$stream->putFloat($this->moveVecZ);
		$stream->putFloat($this->yaw);
		$stream->putFloat($this->pitch);
	}

	public function appendFlag(int $flag): void {
		$this->flags |= (1 << $flag);
	}

	/**
	 * @return Vector3
	 */
	public function getDelta(): Vector3 {
		return $this->delta;
	}

	/**
	 * @param Vector3 $delta
	 */
	public function setDelta(Vector3 $delta): void {
		$this->delta = $delta;
	}

	/**
	 * @return int
	 */
	public function getFlags(): int {
		return $this->flags;
	}

	/**
	 * @param int $flags
	 */
	public function setFlags(int $flags): void {
		$this->flags = $flags;
	}

	/**
	 * @return float
	 */
	public function getMoveVecX(): float {
		return $this->moveVecX;
	}

	/**
	 * @param float $moveVecX
	 */
	public function setMoveVecX(float $moveVecX): void {
		$this->moveVecX = $moveVecX;
	}

	/**
	 * @return float
	 */
	public function getMoveVecZ(): float {
		return $this->moveVecZ;
	}

	/**
	 * @param float $moveVecZ
	 */
	public function setMoveVecZ(float $moveVecZ): void {
		$this->moveVecZ = $moveVecZ;
	}

	/**
	 * @return float
	 */
	public function getPitch(): float {
		return $this->pitch;
	}

	/**
	 * @param float $pitch
	 */
	public function setPitch(float $pitch): void {
		$this->pitch = $pitch;
	}

	/**
	 * @return float
	 */
	public function getYaw(): float {
		return $this->yaw;
	}

	/**
	 * @param float $yaw
	 */
	public function setYaw(float $yaw): void {
		$this->yaw = $yaw;
	}

}
