<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client;

use Lyrica0954\Mineleft\network\protocol\PacketLevelChunk;
use Lyrica0954\Mineleft\network\protocol\PacketPlayerAuthInput;
use Lyrica0954\Mineleft\network\protocol\PacketPlayerLogin;
use Lyrica0954\Mineleft\network\protocol\PacketPlayerTeleport;
use Lyrica0954\Mineleft\network\protocol\types\InputData;
use Lyrica0954\Mineleft\network\protocol\types\InputFlags;
use Lyrica0954\Mineleft\network\protocol\types\MineleftChunkSerializer;
use Lyrica0954\Mineleft\network\protocol\types\PlayerInfo;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;

class MineleftPocketMineListener implements Listener {

	public function __construct(
		protected MineleftClient $client
	) {
	}

	public function onChunkLoad(ChunkLoadEvent $event): void {
		$payload = MineleftChunkSerializer::serialize($event->getChunk(), $event->getChunkX(), $event->getChunkZ());

		$packet = new PacketLevelChunk();
		$packet->x = $event->getChunkX();
		$packet->z = $event->getChunkZ();
		$packet->extraPayload = $payload;
		$packet->worldName = $event->getWorld()->getFolderName();

		$this->client->getSession()->sendPacket($packet);
	}

	public function onPlayerJoin(PlayerJoinEvent $event): void {
		$player = $event->getPlayer();

		$packet = new PacketPlayerLogin();
		$packet->worldName = $player->getWorld()->getFolderName();
		$packet->playerInfo = new PlayerInfo($player->getName(), $player->getUniqueId());
		$packet->position = $player->getPosition()->asVector3();

		$this->client->getSession()->sendPacket($packet);
	}

	public function onDataPacketReceive(DataPacketReceiveEvent $event): void {
		$packet = $event->getPacket();
		$player = $event->getOrigin()->getPlayer();

		if (is_null($player)) {
			return;
		}

		if ($packet instanceof PlayerAuthInputPacket) {
			$pk = new PacketPlayerAuthInput();
			$pk->playerUuid = $player->getUniqueId();
			$pk->inputData = new InputData();

			if ($packet->hasFlag(PlayerAuthInputFlags::UP))
				$pk->inputData->appendFlag(InputFlags::UP);
			if ($packet->hasFlag(PlayerAuthInputFlags::DOWN))
				$pk->inputData->appendFlag(InputFlags::DOWN);
			if ($packet->hasFlag(PlayerAuthInputFlags::LEFT))
				$pk->inputData->appendFlag(InputFlags::LEFT);
			if ($packet->hasFlag(PlayerAuthInputFlags::RIGHT))
				$pk->inputData->appendFlag(InputFlags::RIGHT);
			if ($packet->hasFlag(InputFlags::JUMP))
				$pk->inputData->appendFlag(InputFlags::JUMP);
			if ($packet->hasFlag(PlayerAuthInputFlags::SNEAKING))
				$pk->inputData->appendFlag(InputFlags::SNEAK);
			if ($packet->hasFlag(PlayerAuthInputFlags::UP_LEFT))
				$pk->inputData->appendFlag(InputFlags::UP_LEFT);
			if ($packet->hasFlag(PlayerAuthInputFlags::UP_RIGHT))
				$pk->inputData->appendFlag(InputFlags::UP_RIGHT);
			if ($packet->hasFlag(PlayerAuthInputFlags::MISSED_SWING))
				$pk->inputData->appendFlag(InputFlags::MISSED_SWING);

			$pk->inputData->setDelta($packet->getDelta());
			$pk->inputData->setMoveVecX($packet->getMoveVecX());
			$pk->inputData->setMoveVecZ($packet->getMoveVecZ());

			$this->client->getSession()->sendPacket($pk);

			$pkMove = new PacketPlayerTeleport();
			$pkMove->playerUuid = $player->getUniqueId();
			$pkMove->worldName = $player->getWorld()->getFolderName();
			$pkMove->position = $player->getPosition()->asVector3();

			$this->client->getSession()->sendPacket($pkMove);
		}
	}
}
