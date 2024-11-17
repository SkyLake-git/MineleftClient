<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client;

use Closure;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;

class LatencyHandler {

	private static int $timestamp = 0;

	public function __construct() {
	}

	public function request(NetworkSession $session, Closure $onACK, bool $immediate = false): void {
		$timestamp = self::nextTimestamp();

		// todo: use internal
		$packet = NetworkStackLatencyPacket::create($timestamp, false);

		$session->sendDataPacketWithReceipt($packet, $immediate)->onCompletion($onACK, function(): void {
			// todo:
		});
	}

	public static function nextTimestamp(): int {
		return self::$timestamp -= 1;
	}
}
