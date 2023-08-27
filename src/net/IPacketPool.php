<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\net;

interface IPacketPool {

	public function get(int $id): ?Packet;
}
