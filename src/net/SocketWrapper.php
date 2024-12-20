<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\net;

use Exception;
use pocketmine\utils\BinaryStream;
use RuntimeException;
use Socket;

class SocketWrapper {

	protected Socket $socket;

	protected IPacketPool $packetPool;

	public function __construct(Socket $socket, IPacketPool $packetPool) {
		$this->socket = $socket;
		$this->packetPool = $packetPool;
	}

	public function readPacket(): ?array {
		$buffer = @socket_read($this->socket, 65535);

		if ($buffer === false) {
			$errno = socket_last_error($this->socket);
			if ($errno === SOCKET_EWOULDBLOCK) {
				return null;
			}
			throw new RuntimeException("Reading data from socket failed: " . trim(socket_strerror($errno)));
		}

		$stream = new BinaryStream($buffer);

		$batch = [];

		while (!$stream->feof()) {
			$packetId = $stream->getShort();

			$packet = $this->packetPool->get($packetId);

			if ($packet === null) {
				break;
			}

			try {
				$packet->decode($stream);
			} catch (Exception $e) {
				throw PacketProcessingException::wrap($e, "Decoding failed");
			}

			$batch[] = $packet;
		}

		if (!$stream->feof()) {
			throw new PacketProcessingException("Buffer remaining: " . base64_encode($stream->getRemaining()));
		}

		return $batch;
	}

	public function writePacket(string $packet): void {
		$result = @socket_send($this->socket, $packet, strlen($packet), 0);

		if ($result === false) {
			$errno = socket_last_error($this->socket);
			if ($errno === SOCKET_EWOULDBLOCK) {
				return;
			}
			throw new RuntimeException("Writing data to socket failed: " . trim(socket_strerror($errno)));
		}
	}

}
