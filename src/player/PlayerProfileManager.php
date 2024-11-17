<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\player;

use Lyrica0954\Mineleft\client\MineleftClient;
use pocketmine\player\Player;
use RuntimeException;

class PlayerProfileManager {

	private static int $nextProfileId = 0;

	private static array $map = [];

	private static array $uuidToProfileIdMap = [];

	private static ?MineleftClient $client = null;

	public static function initClient(MineleftClient $client): void {
		self::$client ??= $client;
	}

	public static function getSessionById(int $profileRuntimeId): ?PlayerProfile {
		return self::$map[$profileRuntimeId] ?? null;
	}

	public static function getSession(Player $player): PlayerProfile {
		$id = self::$uuidToProfileIdMap[$player->getUniqueId()->toString()] ?? null;
		if ($id === null) {
			return self::createSession($player);
		}

		$profile = self::$map[$id] ?? null;

		if ($profile === null) {
			return self::createSession($player);
		}

		return $profile;
	}

	/**
	 * @param Player $player
	 * @return PlayerProfile
	 * @internal
	 */
	public static function createSession(Player $player): PlayerProfile {
		self::$map[$id = self::$nextProfileId++] = $profile = new PlayerProfile(self::getClient(), $player, $id);
		self::$uuidToProfileIdMap[$player->getUniqueId()->toString()] = $id;

		return $profile;
	}

	/**
	 * @return MineleftClient
	 */
	public static function getClient(): MineleftClient {
		return self::$client ?? throw new RuntimeException("Not initialized");
	}

	public static function removeSession(Player $player): void {
		$id = self::$uuidToProfileIdMap[$player->getUniqueId()->toString()] ?? null;
		if ($id === null) {
			return;
		}

		unset(self::$uuidToProfileIdMap[$player->getUniqueId()->toString()]);
		unset(self::$map[$id]);
	}
}
