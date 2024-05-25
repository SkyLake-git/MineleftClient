<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\player;

use Lyrica0954\Mineleft\client\MineleftClient;
use pocketmine\player\Player;
use RuntimeException;
use WeakMap;

class PlayerSessionManager {

	/**
	 * @var WeakMap<Player, PlayerSession>|null
	 */
	private static ?WeakMap $map = null;

	private static ?MineleftClient $client = null;

	public static function initClient(MineleftClient $client): void {
		self::$client ??= $client;
	}

	public static function getSession(Player $player): PlayerSession {
		return self::getMap()[$player] ??= new PlayerSession(self::getClient(), $player);
	}

	private static function getMap(): WeakMap {
		return self::$map ??= new WeakMap();
	}

	/**
	 * @return MineleftClient
	 */
	public static function getClient(): MineleftClient {
		return self::$client ?? throw new RuntimeException("Not initialized");
	}
}
