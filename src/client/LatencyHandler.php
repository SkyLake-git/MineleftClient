<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client;

use Closure;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;

class LatencyHandler {

	private static int $timestamp = 0;

	/**
	 * @var array<int, array{0: NetworkSession, 1: (Closure(NetworkSession, NetworkStackLatencyPacket): void)}>
	 */
	private array $pending;

	public function __construct() {
		$this->pending = [];
	}

	public function onDataPacketReceive(DataPacketReceiveEvent $event): void {
		$packet = $event->getPacket();
		$session = $event->getOrigin();

		if ($packet instanceof NetworkStackLatencyPacket) {
			$pendingData = $this->pending[$packet->timestamp] ?? null;

			if ($pendingData === null) {
				return;
			}

			/**
			 * @var array{0: NetworkSession, 1: (Closure(NetworkSession, NetworkStackLatencyPacket): void)} $pendingData
			 */

			[$targetSession, $onResponse] = $pendingData;

			if ($targetSession !== $session) {
				return;
			}

			($onResponse)($session, $packet);

			unset($this->pending[$packet->timestamp]);
		}
	}

	public function request(NetworkSession $session, Closure $onResponse, bool $immediate = false): void {
		$timestamp = self::nextTimestamp();
		$this->pending[$this->getExpectResponseTimestamp($timestamp)] = [$session, $onResponse];

		$packet = NetworkStackLatencyPacket::request($timestamp);

		$session->sendDataPacket($packet, $immediate);
	}

	public static function nextTimestamp(): int {
		return self::$timestamp -= 1;
	}

	private function getExpectResponseTimestamp(int $requestTimestamp): int {
		return $requestTimestamp * (10 ** 6);
	}

}
