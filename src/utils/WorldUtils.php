<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\utils;

use pocketmine\block\Block;
use pocketmine\math\AxisAlignedBB;
use pocketmine\world\World;

class WorldUtils {

	/**
	 * @return Block[]
	 */
	public static function getNearbyBlocks(World $world, AxisAlignedBB $bb): array {
		$minX = (int) floor($bb->minX - 1);
		$minY = (int) floor($bb->minY - 1);
		$minZ = (int) floor($bb->minZ - 1);
		$maxX = (int) floor($bb->maxX + 1);
		$maxY = (int) floor($bb->maxY + 1);
		$maxZ = (int) floor($bb->maxZ + 1);
		$blocks = [];
		for ($z = $minZ; $z <= $maxZ; ++$z) {
			for ($x = $minX; $x <= $maxX; ++$x) {
				for ($y = $minY; $y <= $maxY; ++$y) {
					$block = $world->getBlockAt($x, $y, $z);
					$blocks[] = $block;
				}
			}
		}

		return $blocks;
	}
}
