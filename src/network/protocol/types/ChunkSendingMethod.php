<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol\types;

enum ChunkSendingMethod: int {

	case PRE = 0;
	case REALTIME = 1;
	case ALTERNATE = 2;

}
