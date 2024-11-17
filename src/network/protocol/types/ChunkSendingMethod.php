<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol\types;

enum ChunkSendingMethod: int {

	/**
	 * Not implemented
	 *
	 * Send chunk before server started
	 * <p>
	 * Server: High memory usage
	 * <p>
	 * Client: Very low cpu usage (High before server startup)
	 * <p>
	 * Recommended for servers that use fewer chunks, such as PvP servers
	 */
	case PRE = 0;

	/**
	 * Send chunk when chunk loaded
	 * <p>
	 * Server: Medium memory usage
	 * <p>
	 * Client: Medium cpu usage
	 */
	case REALTIME = 1;

	/**
	 * Send nearby blocks in PacketPlayerAuthInput
	 * <p>
	 * Server: Very low memory usage
	 * <p>
	 * Client: High cpu usage
	 */
	case ALTERNATE = 2;

}
