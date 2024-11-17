<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\player;

use Closure;
use pocketmine\utils\ObjectSet;
use RuntimeException;

class BlockSyncPromise {

	const PHASE_NONE = 0;
	const PHASE_ACK = 1;
	const PHASE_AUTH = 2;

	private int $synchronizationPhase;

	private int $previous;

	private int $target;

	private ObjectSet $then;

	private bool $synced;

	public function __construct(
		int $previous,
		int $sync
	) {
		$this->synchronizationPhase = self::PHASE_NONE;
		$this->previous = $previous;
		$this->target = $sync;
		$this->then = new ObjectSet();
		$this->synced = false;
	}

	public function getSynchronizationPhase(): int {
		return $this->synchronizationPhase;
	}

	public function nextSynchronizationPhase(int $synchronizationPhase): void {
		if ($this->synchronizationPhase + 1 !== $synchronizationPhase) {
			throw new RuntimeException("New synchronization phase must be next of the current phase");
		}

		$this->synchronizationPhase = $synchronizationPhase;

		if ($synchronizationPhase === self::PHASE_ACK) { // need to wait auth?
			$this->onSync();
		}
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

	public function getPrevious(): int {
		return $this->previous;
	}

	public function getTarget(): int {
		return $this->target;
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
