<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol\types;

use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\utils\BinaryStream;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\SubChunk;

class MineleftChunkSerializer {

	public static function serialize(Chunk $chunk, int $chunkX, int $chunkY): string {
		$out = new BinaryStream();
		$out->putInt(Chunk::MAX_SUBCHUNKS);

		foreach ($chunk->getSubChunks() as $y => $subChunk) {
			$out->putInt($y);

			$data = self::serializeSubChunk($subChunk, $chunkX, $chunkY);
			$out->put($data);
		}

		return $out->getBuffer();
	}

	public static function serializeSubChunk(SubChunk $subChunk, int $chunkX, int $chunkY): string {
		$out = new BinaryStream();

		$out->putInt(count($subChunk->getBlockLayers()));
		foreach ($subChunk->getBlockLayers() as $layer) {
			$out->putInt(SubChunk::EDGE_LENGTH ** 3);
			for ($cx = 0; $cx < SubChunk::EDGE_LENGTH; $cx++) {
				for ($cy = 0; $cy < SubChunk::EDGE_LENGTH; $cy++) {
					for ($cz = 0; $cz < SubChunk::EDGE_LENGTH; $cz++) {
						$block = $layer->get($cx, $cy, $cz);
						$out->putLong(morton3d_encode($cx, $cy, $cz));
						$networkId = TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($block);
						$out->putInt($networkId);
					}
				}
			}
		}

		return $out->getBuffer();
	}

	public static function serializeBlock(): string {

	}

}
