<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\utils;

use pocketmine\math\Vector3;
use pocketmine\utils\BinaryStream;

class BinaryUtils {

	public static function putString(BinaryStream $stream, string $v): void {
		$stream->putInt(strlen($v));
		$stream->put($v);
	}

	public static function putVec3f(BinaryStream $stream, Vector3 $v): void {
		$stream->putFloat($v->x);
		$stream->putFloat($v->y);
		$stream->putFloat($v->z);
	}

	public static function getVec3f(BinaryStream $stream): Vector3 {
		return new Vector3(
			$stream->getFloat(),
			$stream->getFloat(),
			$stream->getFloat()
		);
	}

	public static function getString(BinaryStream $stream): string {
		return $stream->get($stream->getInt());
	}

}
