<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client;

use Closure;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\promise\PromiseResolver;
use RuntimeException;

class LatencyHandler {

	private static int $timestamp = 0;

	public function __construct() {
	}

	public function request(NetworkSession $session, Closure $onACK, bool $immediate = false): void {
		// This should be a class member...
		$cls = Closure::bind(function(): ?PromiseResolver {
			if (empty($this->sendBuffer)) {
				return null;
			}

			$this->sendBufferAckPromises[] = $resolver = new PromiseResolver();

			return $resolver;
		}, $session, NetworkSession::class);


		if ($cls === false) {
			throw new RuntimeException("Closure::bind() failed");
		}

		if (($resolver = ($cls)()) !== null) {
			$resolver->getPromise()->onCompletion($onACK, fn() => null);
		} else {
			$timestamp = self::nextTimestamp();
			$packet = NetworkStackLatencyPacket::create($timestamp, false);

			$session->sendDataPacketWithReceipt($packet, $immediate)->onCompletion($onACK, fn() => null);
		}
	}

	public static function nextTimestamp(): int {
		return self::$timestamp -= 1;
	}
}
