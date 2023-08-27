<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\net;

use Exception;
use RuntimeException;

class PacketProcessingException extends RuntimeException {

	public static function wrap(Exception $exception, string $prefix = ""): self {
		return new self(($prefix !== "" ? $prefix . ": " : "") . $exception->getMessage(), $exception->getCode(), $exception);
	}
}
