<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client;

use Lyrica0954\Mineleft\client\pmmp\MineleftBlockChangeAdapter;
use pocketmine\block\Block;
use pocketmine\world\World;

class MineleftBlockChangeManager {

	/**
	 * @var MineleftBlockChangeAdapter[]
	 */
	private array $adapters;

	public function __construct() {
		$this->adapters = [];
	}

	public function fetchBlockChange(int $x, int $y, int $z, World $world): ?Block {
		return ($this->adapters[$world->getFolderName()] ?? null)?->fetchBlockChange($x, $y, $z);
	}

	public function createAdapter(World $world): void {
		$this->adapters[$world->getFolderName()] = new MineleftBlockChangeAdapter($world);
	}

	public function deleteAdapter(World $world): void {
		unset($this->adapters[$world->getFolderName()]);
	}

}
