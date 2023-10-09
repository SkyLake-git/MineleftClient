<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client;

use Closure;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\utils\ObjectSet;

class PacketPairing {

	/**
	 * @var array<int, (ClientboundPacket&DataPacket)[]>
	 */
	protected array $unconfirmedPackets;

	/**
	 * @var array<int, (ClientboundPacket&DataPacket)[]>
	 */
	protected array $confirmedPackets;

	protected int $lastConfirmedTick;

	/**
	 * @var ObjectSet<Closure(int, ClientboundPacket[]): void>
	 */
	protected ObjectSet $confirmListeners;

	protected int $tick;

	public function __construct(protected NetworkSession $session, protected LatencyHandler $latencyHandler, int $tick) {
		$this->unconfirmedPackets = [];
		$this->confirmedPackets = [];
		$this->confirmListeners = new ObjectSet();
		$this->lastConfirmedTick = 0;
		$this->tick = $tick;
	}

	/**
	 * @return int
	 */
	public function getLastConfirmedTick(): int {
		return $this->lastConfirmedTick;
	}

	/**
	 * @return ObjectSet<Closure(int, ClientboundPacket[]): void>
	 */
	public function getConfirmListeners(): ObjectSet {
		return $this->confirmListeners;
	}

	public function requestPairing(): void {
		$tick = $this->getTick();
		$this->latencyHandler->request($this->session, function(NetworkSession $session, NetworkStackLatencyPacket $packet) use ($tick): void {
			$this->confirm($tick);

			$this->updateTick();
		}, true);
	}

	public function getTick(): int {
		return $this->tick;
	}

	public function confirm(int $tick): void {
		$list = $this->unconfirmedPackets[$tick] ?? [];
		$this->confirmedPackets[$tick] = $list;
		unset($this->unconfirmedPackets[$tick]);

		$this->lastConfirmedTick = $tick;

		foreach ($this->confirmListeners as $listener) {
			($listener)($tick, $list);
		}
	}

	public function updateTick(): void {
		$tick = $this->getTick();
		$outdatedTick = $tick - 40;
		if (isset($this->unconfirmedPackets[$outdatedTick])) {
			unset($this->unconfirmedPackets[$outdatedTick]);
		}

		if (isset($this->confirmedPackets[$outdatedTick])) {
			unset($this->confirmedPackets[$outdatedTick]);
		}
	}

	public function incrementTick(): void {
		$this->tick++;
	}

	public function addUnconfirm(ClientboundPacket $packet): void {
		$this->markUnconfirmed($packet);
	}

	protected function markUnconfirmed(ClientboundPacket $packet): void {
		$tick = $this->getTick();
		$this->unconfirmedPackets[$tick][spl_object_hash($packet)] = $packet;
	}

	protected function markConfirmed(ClientboundPacket $packet): void {
		$tick = $this->getTick();
		$this->confirmedPackets[$tick][spl_object_hash($packet)] = $packet;
	}

}
