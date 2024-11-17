<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client\pmmp;

use Closure;
use pocketmine\timings\TimingsHandler;

class MineleftCallbackTimingsHandler extends TimingsHandler {

	public function __construct(string $name, ?TimingsHandler $parent, string $group, private Closure $callbackStart, private Closure $callbackStop) {
		parent::__construct($name, $parent, $group);
	}

	public function startTiming(): void {
		parent::startTiming();
		// index 1 is TimingsHandler::startTiming()
		$setBlockFunctionArgs = debug_backtrace(limit: 2)[1]["args"];
		if (count($setBlockFunctionArgs) >= 4) {
			($this->callbackStart)(...$setBlockFunctionArgs);
		}
	}

	public function stopTiming(): void {
		parent::stopTiming();
		($this->callbackStop)();
	}
}
