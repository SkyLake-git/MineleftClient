<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol\types;

class BlockPosition {

	public int $x;

	public int $y;

	public int $z;

	public function __construct(int $x, int $y, int $z) {
		$this->x = $x;
		$this->y = $y;
		$this->z = $z;
	}
}
