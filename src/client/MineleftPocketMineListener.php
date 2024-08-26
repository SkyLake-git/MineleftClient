<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client;

use Closure;
use Lyrica0954\Mineleft\client\processor\PlayerAttributeProcessor;
use Lyrica0954\Mineleft\client\processor\PlayerEffectProcessor;
use Lyrica0954\Mineleft\client\processor\PlayerFlagsProcessor;
use Lyrica0954\Mineleft\client\processor\PlayerMotionProcessor;
use Lyrica0954\Mineleft\client\processor\PlayerPositionProcessor;
use Lyrica0954\Mineleft\client\task\ChunkSerializingTask;
use Lyrica0954\Mineleft\Main;
use Lyrica0954\Mineleft\network\protocol\PacketLevelChunk;
use Lyrica0954\Mineleft\network\protocol\PacketPlayerLogin;
use Lyrica0954\Mineleft\network\protocol\types\ChunkSendingMethod;
use Lyrica0954\Mineleft\network\protocol\types\PlayerInfo;
use Lyrica0954\Mineleft\player\PlayerSession;
use Lyrica0954\Mineleft\player\PlayerSessionManager;
use Lyrica0954\Mineleft\rak\MineleftRakLibInterface;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MobEffectPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\PlayerMovementSettings;
use pocketmine\network\mcpe\protocol\types\PlayerMovementType;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\player\Player;
use pocketmine\world\World;
use T;

class MineleftPocketMineListener implements Listener {

	/**
	 * @var array<int, Player>
	 */
	private array $playerByActorIdMap;

	/**
	 * @var array<string, int>
	 */
	private array $beforeTickIdsMap;

	public function __construct(
		protected MineleftClient $client
	) {
		$this->playerByActorIdMap = [];
		$this->beforeTickIdsMap = [];
	}

	public function onChunkLoad(ChunkLoadEvent $event): void {
		if ($this->client->getChunkSendingMethod() === ChunkSendingMethod::REALTIME) {
			print_r("sending chunk\n");
			$task = new ChunkSerializingTask($event->getChunk(), function(string $payload) use ($event): void {
				$packet = new PacketLevelChunk();
				$packet->x = $event->getChunkX();
				$packet->z = $event->getChunkZ();
				$packet->extraPayload = $payload;
				$packet->worldName = $event->getWorld()->getFolderName();

				$this->client->getSession()->sendPacket($packet);
			});

			$this->client->getPMMPServer()->getAsyncPool()->submitTask($task);
		}
	}

	public function onWorldLoad(WorldLoadEvent $event): void {

	}

	public function processWorld(World $world): void {

	}

	public function onPlayerJoin(PlayerJoinEvent $event): void {
		$player = $event->getPlayer();

		$packet = new PacketPlayerLogin();
		$packet->worldName = $player->getWorld()->getFolderName();
		$packet->playerInfo = new PlayerInfo($player->getName(), $player->getUniqueId());
		$packet->position = $player->getPosition()->asVector3();

		$this->client->getSession()->sendPacket($packet);

		$this->playerByActorIdMap[$player->getId()] = $player;
	}

	public function onPlayerQuit(PlayerQuitEvent $event): void {
		unset($this->playerByActorIdMap[$event->getPlayer()->getId()]);
	}

	/**
	 * @param DataPacketSendEvent $event
	 * @return void
	 * @priority LOWEST
	 */
	public function onDataPacketSend(DataPacketSendEvent $event): void {
		$packets = $event->getPackets();

		foreach ($packets as $packet) {
			if ($packet instanceof NetworkStackLatencyPacket) {
				continue;
			}
			if ($packet instanceof StartGamePacket) {
				$packet->playerMovementSettings = new PlayerMovementSettings(
					PlayerMovementType::SERVER_AUTHORITATIVE_V2_REWIND,
					40,
					false
				);
			}

			/**
			 * @template T of ClientboundPacket&DataPacket
			 * @param T $packet
			 * @param Closure(T, PlayerSession): void $applier
			 * @param Closure(): void $onACK
			 * @return void
			 */
			$applyWithReceipt = function(mixed &$packet, Closure $applier, Closure $onACK) use ($event): void {
				foreach ($event->getTargets() as $session) {
					if ($session->getPlayer() === null) {
						return;
					}

					$playerSession = PlayerSessionManager::getSession($session->getPlayer());

					$applier($packet, $playerSession);

					Main::getLatencyHandler()->request($session, fn() => $onACK($playerSession));
				}
			};

			if ($packet instanceof SetActorDataPacket) {
				$applyWithReceipt(
					$packet,
					function(mixed $packet, PlayerSession $session): void {
						/**
						 * @var SetActorDataPacket $packet
						 */

						$packet->tick = $session->getTickId();
					},
					function(PlayerSession $session) use ($packet): void {
						$session->getActorStateStore()->updateMetadata($packet->actorRuntimeId, $packet->metadata);

						if ($packet->actorRuntimeId === $session->getPlayer()->getId()) {
							PlayerFlagsProcessor::process($this->client, $session);
						}
					},
				);

			} elseif ($packet instanceof UpdateAttributesPacket) {
				$applyWithReceipt(
					$packet,
					function(mixed $packet, PlayerSession $session): void {
						/**
						 * @var UpdateAttributesPacket $packet
						 */

						$packet->tick = $session->getTickId();
					},
					function(PlayerSession $session) use ($packet): void {
						if ($packet->actorRuntimeId === $session->getPlayer()->getId()) {
							PlayerAttributeProcessor::process($this->client, $session, $packet->entries);
						}
					}

				);
			} elseif ($packet instanceof SetActorMotionPacket) {
				$applyWithReceipt(
					$packet,
					function(mixed $packet, PlayerSession $session): void {
						/**
						 * @var SetActorMotionPacket $packet
						 */

						$packet->tick = $session->getTickId();
					},
					function(PlayerSession $session) use ($packet): void {
						if ($packet->actorRuntimeId === $session->getPlayer()->getId()) {
							PlayerMotionProcessor::process($this->client, $session, $packet->motion);
						}
					}
				);
			} elseif ($packet instanceof MobEffectPacket) {
				$applyWithReceipt(
					$packet,
					function(mixed $packet, PlayerSession $session): void {
						/**
						 * @var MobEffectPacket $packet
						 */

						$packet->tick = $session->getTickId();
					},
					function(PlayerSession $session) use ($packet): void {
						if ($packet->actorRuntimeId === $session->getPlayer()->getId()) {
							PlayerEffectProcessor::process($this->client, $session, $packet->effectId, $packet->amplifier, $packet->eventId);
						}
					}
				);
			} elseif ($packet instanceof MovePlayerPacket) {
				$applyWithReceipt(
					$packet,
					function(mixed $packet, PlayerSession $session): void {
						/**
						 * @var MovePlayerPacket $packet
						 */

						$packet->tick = $session->getTickId();
					},
					function(PlayerSession $session) use ($packet): void {
						if ($packet->actorRuntimeId === $session->getPlayer()->getId()) {
							PlayerPositionProcessor::process($this->client, $session, $packet->position);
						}
					}
				);
			}
		}
	}

	/**
	 * @param DataPacketReceiveEvent $event
	 * @return void
	 * @priority MONITOR
	 */
	public function onDataPacketReceive(DataPacketReceiveEvent $event): void {
		$packet = $event->getPacket();
		$player = $event->getOrigin()->getPlayer();

		if ($packet instanceof PlayerAuthInputPacket) {
			$uuid = $event->getOrigin()->getPlayerInfo()->getUuid()->toString();
			if (is_null($player) || !$player->isOnline() || !$player->spawned) {
				$this->beforeTickIdsMap[$uuid] ??= 0;
				$this->beforeTickIdsMap[$uuid]++;

				return;
			}

			if (isset($this->beforeTickIdsMap[$uuid])) {
				//PlayerSessionManager::getSession($player)->setTickId($this->beforeTickIdsMap[$uuid]);
				unset($this->beforeTickIdsMap[$uuid]);
			}

			PlayerSessionManager::getSession($player)->handleAuthInput($packet);
		}

	}
}
