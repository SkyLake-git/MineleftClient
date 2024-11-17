<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\player;

use Closure;
use pocketmine\utils\ObjectSet;
use RuntimeException;

class BlockSyncPromise {

	private int $previous;

	private int $target;

	private ObjectSet $then;

	private bool $synced;

	public function __construct(
		int $previous,
		int $sync
	) {
		$this->previous = $previous;
		$this->target = $sync;
		$this->then = new ObjectSet();
		$this->synced = false;
	}

	public function getPrevious(): int {
		return $this->previous;
	}

	public function getTarget(): int {
		return $this->target;
	}

	public function onSync(): void {
		if ($this->synced) {
			throw new RuntimeException("Already synced");
		}
		foreach ($this->then as $callback) {
			($callback)($this);
		}
		$this->synced = true;
	}

	public function then(Closure $callback): self {
		if ($this->synced) {
			($callback)($this);

			return $this;
		}

		$this->then->add($callback);

		return $this;
	}
}
