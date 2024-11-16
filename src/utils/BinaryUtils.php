<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\utils;

use GMP;
use pocketmine\math\Vector3;
use pocketmine\utils\BinaryStream;
use Ramsey\Uuid\Rfc4122\FieldsInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

class BinaryUtils {

	public static function putString(BinaryStream $stream, string $v): void {
		$stream->putInt(strlen($v));
		$stream->put($v);
	}

	public static function putUUID(BinaryStream $stream, UuidInterface $uuid): void {
		$fields = $uuid->getFields();
		if (!$fields instanceof FieldsInterface) {
			throw new RuntimeException("Requires RFC4122 uuid fields");
		}
		$hex = bin2hex($fields->getBytes());
		$a = substr($hex, 0, 16);
		$b = substr($hex, 16, 32);
		$stream->put(self::putGMPLong($a));
		$stream->put(self::putGMPLong($b));
	}

	public static function putGMPLong(string $hex): string {
		$gmp = gmp_init($hex, 16);
		$r = fn($gmp, $n) => gmp_div_q($gmp, gmp_pow(2, $n));
		// bit shift
		$part = fn($n) => chr(gmp_intval(gmp_and(0xff, $r($gmp, $n))));

		$buffer = "";
		for ($i = 7; $i >= 0; $i--) {
			$buffer .= $part($i * 8);
		}

		// big-endian

		return $buffer;
	}

	public static function getUUID(BinaryStream $stream): UuidInterface {
		$r = fn() => gmp_strval(self::getGMPLong($stream->get(8)), 16);
		$bytes = hex2bin($r() . $r());

		return Uuid::fromBytes($bytes);
	}

	public static function getGMPLong(string $bin): GMP {
		return gmp_import($bin, flags: GMP_MSW_FIRST | GMP_BIG_ENDIAN);

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
