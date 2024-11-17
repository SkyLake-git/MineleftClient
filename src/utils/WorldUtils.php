<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\utils;

use pocketmine\block\Block;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionProperty;

class WorldUtils {

	private static ?ReflectionProperty $worldChangedBlocksProperty;

	public static function broadcastUpdateBlockImmediately(World $world, Vector3 $position): void {
		$changedBlocks = self::getWorldChangedBlocksProperty()->getValue($world);
		$chunkX = $position->getFloorX() >> Chunk::COORD_BIT_SIZE;
		$chunkZ = $position->getFloorZ() >> Chunk::COORD_BIT_SIZE;
		unset($changedBlocks[World::chunkHash($chunkX, $chunkZ)][World::chunkBlockHash($position->getFloorX(), $position->getFloorY(), $position->getFloorZ())]);
		self::getWorldChangedBlocksProperty()->setValue($world, $changedBlocks);

		$packets = $world->createBlockUpdatePackets([$position]);
		NetworkBroadcastUtils::broadcastPackets($world->getChunkPlayers($chunkX, $chunkZ), $packets);
	}

	public static function getWorldChangedBlocksProperty(): ReflectionProperty {
		return self::$worldChangedBlocksProperty ??= (new ReflectionClass(World::class))->getProperty("changedBlocks");
	}

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
