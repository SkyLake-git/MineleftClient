<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\net;

enum PacketBounds {

	case SERVER;
	case CLIENT;
	case BOTH;

	public function client(): bool {
		return $this === self::CLIENT || $this === self::BOTH;
	}

	public function server(): bool {
		return $this === self::SERVER || $this === self::BOTH;
	}
}
