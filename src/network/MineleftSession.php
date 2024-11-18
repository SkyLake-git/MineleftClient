<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network;

use Exception;
use Logger;
use Lyrica0954\Mineleft\net\IPacketPool;
use Lyrica0954\Mineleft\net\SocketWrapper;
use Lyrica0954\Mineleft\network\protocol\handler\IMineleftPacketHandler;
use Lyrica0954\Mineleft\network\protocol\handler\NormalMineleftPacketHandler;
use Lyrica0954\Mineleft\network\protocol\MineleftPacket;
use pocketmine\utils\BinaryStream;
use RuntimeException;
use Socket;
use SplQueue;

class MineleftSession {

	protected Socket $socket;

	protected SocketWrapper $wrapper;

	/**
	 * @var SplQueue<int, string>
	 */
	protected SplQueue $queue;

	protected Logger $logger;

	protected IMineleftPacketHandler $packetHandler;

	public function __construct(Socket $socket, IPacketPool $packetPool, Logger $logger) {
		$this->socket = $socket;
		$this->queue = new SplQueue();
		$this->logger = $logger;
		$this->wrapper = new SocketWrapper($this->socket, $packetPool);
		$this->packetHandler = new NormalMineleftPacketHandler($this);
	}

	/**
	 * @return Logger
	 */
	public function getLogger(): Logger {
		return $this->logger;
	}

	/**
	 * @return IMineleftPacketHandler
	 */
	public function getPacketHandler(): IMineleftPacketHandler {
		return $this->packetHandler;
	}

	public function socketTick(): void {
		// todo: thread
		while (($batch = $this->wrapper->readPacket()) !== null) {
			foreach ($batch as $packet) {
				if (!$packet instanceof MineleftPacket) {
					$this->logger->warning("Received un-Mineleft packet. ignoring");
					continue;
				}

				$this->handlePacket($packet);
			}
		}

		$this->flushSendQueue();
	}

	public function handlePacket(MineleftPacket $packet): void {
		if (!$packet->bounds()->client()) {
			$this->logger->warning("Invalid bounding packet received. Ignoring");

			return;
		}

		//$this->logger->info("Received packet {$packet->getName()}");

		$packet->callHandler($this->packetHandler);
	}

	public function flushSendQueue(): void {
		for ($i = 0; $i < $this->queue->count(); $i++) {
			$buf = $this->queue->dequeue();
			$this->wrapper->writePacket($buf);
		}
	}

	public function sendPacket(MineleftPacket $packet): void {
		$buffer = new BinaryStream();

		$buffer->putShort($packet->getProtocolId());

		try {
			$packet->encode($buffer);
		} catch (Exception $e) {
			throw new RuntimeException("Mineleft packet encoding failed");
		}

		$this->queue->enqueue($buffer->getBuffer());
	}

}
