<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network;

use ErrorException;
use Logger;
use Lyrica0954\Mineleft\network\protocol\MineleftPacketPool;
use RuntimeException;

class MineleftSessionBootstrap {

	public static function start(string $address, int $port, Logger $logger): MineleftSession {
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		if ($socket === false) {
			$logger->error("socket_create() returned false");
			throw new RuntimeException(socket_strerror(socket_last_error()));
		}

		$packetPool = new MineleftPacketPool();

		try {
			$success = socket_connect($socket, $address, $port);

			if (!$success) {
				$logger->error("socket_connect() returned false");
				throw new RuntimeException(socket_strerror(socket_last_error($socket)));
			}
		} /** @noinspection PhpRedundantCatchClauseInspection */ catch (ErrorException $e) { // known error
			throw new RuntimeException("Failed to connect mineleft server", previous: $e);
		}

		// 8MB send & recv buffer
		socket_set_nonblock($socket);
		socket_setopt($socket, SOL_SOCKET, SO_RCVBUF, 1024 ** 8);
		socket_setopt($socket, SOL_SOCKET, SO_SNDBUF, 1024 ** 8);

		return new MineleftSession($socket, $packetPool, $logger);
	}

}
