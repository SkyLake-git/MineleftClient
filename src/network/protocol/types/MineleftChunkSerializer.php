<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol\types;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\utils\BinaryStream;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\SubChunk;

class MineleftChunkSerializer {

	public static function serialize(Chunk $chunk): string {
		$out = new BinaryStream();
		$out->putInt(Chunk::MAX_SUBCHUNKS);

		foreach ($chunk->getSubChunks() as $y => $subChunk) {
			$out->putInt($y);

			$data = self::serializeSubChunk($subChunk);
			$out->put($data);
		}

		return $out->getBuffer();
	}

	public static function serializeSubChunk(SubChunk $subChunk): string {
		$out = new BinaryStream();

		// todo: use LevelChunkPacket to save serializing cost
		$out->putInt(count($subChunk->getBlockLayers()));
		foreach ($subChunk->getBlockLayers() as $layer) {
			$out->putInt(SubChunk::EDGE_LENGTH);
			for ($cx = 0; $cx < SubChunk::EDGE_LENGTH; ++$cx) {
				for ($cy = 0; $cy < SubChunk::EDGE_LENGTH; ++$cy) {
					for ($cz = 0; $cz < SubChunk::EDGE_LENGTH; ++$cz) {
						$block = $layer->get($cx, $cy, $cz);
						$networkId = TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($block);
						$out->putInt($networkId);
					}
				}
			}
		}

		return $out->getBuffer();
	}

	/**
	 * @param array{
	 *     0: Vector3,
	 *     1: int
	 * } $blocks
	 * @return string
	 */
	public static function convertBlocks(array $blocks): string {
		$out = new BinaryStream();

		$out->putInt(count($blocks));

		foreach ($blocks as $data) {
			[$pos, $stateId] = $data;

			$out->putLong(morton3d_encode($pos->x, $pos->y, $pos->z));
			$networkId = TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($stateId);
			$out->putInt($networkId);
		}

		return $out->getBuffer();
	}

}
