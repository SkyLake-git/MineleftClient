<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\player\debug;

class MineleftDebugOption {

	public bool $simulation;

	public function __construct(bool $simulation) {
		$this->simulation = $simulation;
	}

}
