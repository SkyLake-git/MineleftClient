<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol;

use Lyrica0954\Mineleft\net\IPacketPool;
use Lyrica0954\Mineleft\net\Packet;

class MineleftPacketPool implements IPacketPool {

	/**
	 * @var array<int, class-string<Packet>>
	 */
	protected array $pool;

	public function __construct() {
		$this->pool = [];

		$this->register(ProtocolIds::PLAYER_LOGIN, new PacketPlayerLogin());
		$this->register(ProtocolIds::LEVEL_CHUNK, new PacketLevelChunk());
		$this->register(ProtocolIds::PLAYER_TELEPORT, new PacketPlayerTeleport());
		$this->register(ProtocolIds::CONFIGURATION, new PacketConfiguration());
		$this->register(ProtocolIds::BLOCK_MAPPINGS, new PacketBlockMappings());
	}

	public function register(int $id, Packet $packet): void {
		$this->pool[$id] = $packet::class;
	}

	public function get(int $id): ?Packet {
		$class = $this->pool[$id] ?? null;

		if ($class === null) {
			return null;
		}

		return new $class();
	}
}