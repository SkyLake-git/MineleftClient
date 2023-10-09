<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol\types;

enum ViolationLevel: int {

	case VERBOSE = 0;
	case POSSIBLY = 1;
	case MAYBE = 2;
	case PROBABLY = 3;

	public function displayName(): string {
		return match ($this) {
			self::VERBOSE => "verbose",
			self::POSSIBLY => "possibly",
			self::MAYBE => "maybe",
			self::PROBABLY => "probably"
		};
	}
}
