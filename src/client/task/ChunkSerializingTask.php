<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client\task;

use Closure;
use Lyrica0954\Mineleft\network\protocol\types\MineleftChunkSerializer;
use pocketmine\scheduler\AsyncTask;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;

class ChunkSerializingTask extends AsyncTask {

	protected string $internalSerializedChunk;

	public function __construct(Chunk $chunk, Closure $callback) {
		$this->internalSerializedChunk = FastChunkSerializer::serializeTerrain($chunk);
		$this->storeLocal("callback", $callback);
	}

	public function onRun(): void {
		$chunk = FastChunkSerializer::deserializeTerrain($this->internalSerializedChunk);

		$this->setResult(MineleftChunkSerializer::serialize($chunk));
	}

	public function onCompletion(): void {
		($this->fetchLocal("callback"))($this->getResult());
	}
}
