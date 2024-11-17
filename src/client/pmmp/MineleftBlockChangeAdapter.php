<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client\pmmp;

use pocketmine\block\Block;
use pocketmine\world\World;
use ReflectionClass;

class MineleftBlockChangeAdapter {

	private array $blockChanges;

	public function __construct(private World $world) {
		$original = $this->world->timings->setBlock;
		$override = new MineleftCallbackTimingsHandler(
			$original->getName(),
			(new ReflectionClass($original))->getProperty("parent")->getValue($original),
			$original->getGroup(),
			function(int $x, int $y, int $z, Block $block): void {
				$this->blockChanges[World::blockHash($x, $y, $z)] = $this->world->getBlockAt($x, $y, $z);
				// todo:
			},
			function(): void {

			}
		);

		$this->world->timings->setBlock = $override;
	}

	public function fetchBlockChange(int $x, int $y, int $z): ?Block {
		$block = $this->blockChanges[$hash = World::blockHash($x, $y, $z)] ?? null;

		if ($block === null) {
			return null;
		}

		unset($this->blockChanges[$hash]);

		return $block;
	}
}
