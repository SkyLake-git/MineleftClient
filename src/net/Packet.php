<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\net;

use Exception;
use pocketmine\utils\BinaryStream;

interface Packet {

	/**
	 * @param BinaryStream $out
	 * @return void
	 *
	 * @throws Exception
	 */
	public function encode(BinaryStream $out): void;

	/**
	 * @param BinaryStream $in
	 * @return void
	 *
	 * @throws Exception
	 */
	public function decode(BinaryStream $in): void;

	public function bounds(): PacketBounds;
}
