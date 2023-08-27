<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network;

use Logger;
use Lyrica0954\Mineleft\network\protocol\MineleftPacketPool;

class MineleftSessionBootstrap {

	public static function start(string $address, int $port, Logger $logger): MineleftSession {
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		$packetPool = new MineleftPacketPool();

		socket_connect($socket, $address, $port);

		// 8MB send & recv buffer
		socket_set_nonblock($socket);
		socket_setopt($socket, SOL_SOCKET, SO_RCVBUF, 1024 ** 8);
		socket_setopt($socket, SOL_SOCKET, SO_SNDBUF, 1024 ** 8);

		return new MineleftSession($socket, $packetPool, $logger);
	}

}
