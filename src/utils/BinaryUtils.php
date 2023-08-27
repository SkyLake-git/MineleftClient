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

	public static function putVec3d(BinaryStream $stream, Vector3 $v): void {
		$stream->putDouble($v->x);
		$stream->putDouble($v->y);
		$stream->putDouble($v->z);
	}

	public static function getVec3d(BinaryStream $stream): Vector3 {
		return new Vector3(
			$stream->getDouble(),
			$stream->getDouble(),
			$stream->getDouble()
		);
	}

	public static function getString(BinaryStream $stream): string {
		return $stream->get($stream->getInt());
	}

}
