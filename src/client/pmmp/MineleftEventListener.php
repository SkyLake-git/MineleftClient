<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client\pmmp;

use Closure;
use Lyrica0954\Mineleft\client\MineleftClient;
use Lyrica0954\Mineleft\client\processor\PlayerAttributeProcessor;
use Lyrica0954\Mineleft\client\processor\PlayerEffectProcessor;
use Lyrica0954\Mineleft\client\processor\PlayerFlagsProcessor;
use Lyrica0954\Mineleft\client\processor\PlayerMotionProcessor;
use Lyrica0954\Mineleft\client\processor\PlayerPositionProcessor;
use Lyrica0954\Mineleft\client\task\ChunkSerializingTask;
use Lyrica0954\Mineleft\Main;
use Lyrica0954\Mineleft\network\protocol\PacketDestroyChunk;
use Lyrica0954\Mineleft\network\protocol\PacketLevelChunk;
use Lyrica0954\Mineleft\network\protocol\PacketPlayerLogin;
use Lyrica0954\Mineleft\network\protocol\types\ChunkSendingMethod;
use Lyrica0954\Mineleft\network\protocol\types\PlayerInfo;
use Lyrica0954\Mineleft\player\BlockSyncPromise;
use Lyrica0954\Mineleft\player\PlayerProfile;
use Lyrica0954\Mineleft\player\PlayerProfileManager;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\event\world\ChunkUnloadEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
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
use pocketmine\network\mcpe\protocol\types\ServerAuthMovementMode;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\player\Player;

class MineleftEventListener implements Listener {

	/**
	 * @var array<int, Player>
	 */
	private array $playerByActorIdMap;

	/**
	 * @var array<string, int>
	 */
	private array $beforeTickIdsMap;

	private array $blockChangeAdapters;

	public function __construct(
		protected MineleftClient $client
	) {
		$this->playerByActorIdMap = [];
		$this->beforeTickIdsMap = [];
		$this->blockChangeAdapters = [];
	}

	public function onChunkUnload(ChunkUnloadEvent $event): void {
		if ($this->client->getChunkSendingMethod() === ChunkSendingMethod::REALTIME) {
			$this->client->getSession()->getLogger()->info("Destroying chunk (x: {$event->getChunkX()}, z: {$event->getChunkZ()})");
			$packet = new PacketDestroyChunk();
			$packet->x = $event->getChunkX();
			$packet->z = $event->getChunkZ();
			$packet->worldName = $event->getWorld()->getFolderName();

			$this->client->getSession()->sendPacket($packet);
		}
	}

	public function onChunkLoad(ChunkLoadEvent $event): void {
		if ($this->client->getChunkSendingMethod() === ChunkSendingMethod::REALTIME) {
			$this->client->getSession()->getLogger()->info("Serializing chunk (x: {$event->getChunkX()}, z: {$event->getChunkZ()})");
			$task = new ChunkSerializingTask($event->getChunk(), function(string $payload) use ($event): void {
				$this->client->getSession()->getLogger()->info("Chunk payload sent (x: {$event->getChunkX()}, z: {$event->getChunkZ()})");
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
		var_dump("created adapter on {$event->getWorld()->getFolderName()}");
		$this->client->getBlockChangeManager()->createAdapter($event->getWorld());
	}

	public function onWorldUnload(WorldUnloadEvent $event): void {
		$this->client->getBlockChangeManager()->deleteAdapter($event->getWorld());
	}

	public function onPlayerJoin(PlayerJoinEvent $event): void {
		$player = $event->getPlayer();

		$profile = PlayerProfileManager::createSession($player);
		$packet = new PacketPlayerLogin();
		$packet->worldName = $player->getWorld()->getFolderName();
		$packet->playerInfo = new PlayerInfo($player->getName(), $player->getUniqueId());
		$packet->profileRuntimeId = $profile->getRuntimeId();
		$packet->position = $player->getPosition()->asVector3();

		$this->client->getSession()->sendPacket($packet);

		$this->playerByActorIdMap[$player->getId()] = $player;

	}

	public function onPlayerQuit(PlayerQuitEvent $event): void {
		unset($this->playerByActorIdMap[$event->getPlayer()->getId()]);
		PlayerProfileManager::removeSession($event->getPlayer());
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
					ServerAuthMovementMode::SERVER_AUTHORITATIVE_V3,
					40,
					true
				);
			}

			/**
			 * @template T of ClientboundPacket&DataPacket
			 * @param ClientboundPacket $packet
			 * @param Closure(T, PlayerProfile): void $applier
			 * @param Closure(): void $onACK
			 * @return void
			 */
			$applyWithReceipt = function(mixed &$packet, Closure $applier, Closure $onACK) use ($event): void {
				foreach ($event->getTargets() as $session) {
					if ($session->getPlayer() === null) {
						return;
					}

					$playerSession = PlayerProfileManager::getSession($session->getPlayer());

					$applier($packet, $playerSession);

					Main::getLatencyHandler()->request($session, fn() => $onACK($playerSession));
				}
			};

			if ($packet instanceof SetActorDataPacket) {
				$applyWithReceipt(
					$packet,
					function(mixed $packet, PlayerProfile $session): void {
						/**
						 * @var SetActorDataPacket $packet
						 */

						$packet->tick = $session->getTickId();
					},
					function(PlayerProfile $session) use ($packet): void {
						$session->getActorStateStore()->updateMetadata($packet->actorRuntimeId, $packet->metadata);

						if ($packet->actorRuntimeId === $session->getPlayer()->getId()) {
							PlayerFlagsProcessor::process($this->client, $session);
						}
					},
				);

			} elseif ($packet instanceof UpdateAttributesPacket) {
				$applyWithReceipt(
					$packet,
					function(mixed $packet, PlayerProfile $session): void {
						/**
						 * @var UpdateAttributesPacket $packet
						 */

						$packet->tick = $session->getTickId();
					},
					function(PlayerProfile $session) use ($packet): void {
						if ($packet->actorRuntimeId === $session->getPlayer()->getId()) {
							PlayerAttributeProcessor::process($this->client, $session, $packet->entries);
						}
					}

				);
			} elseif ($packet instanceof SetActorMotionPacket) {
				$applyWithReceipt(
					$packet,
					function(mixed $packet, PlayerProfile $session): void {
						/**
						 * @var SetActorMotionPacket $packet
						 */

						$packet->tick = $session->getTickId();
					},
					function(PlayerProfile $session) use ($packet): void {
						if ($packet->actorRuntimeId === $session->getPlayer()->getId()) {
							PlayerMotionProcessor::process($this->client, $session, $packet->motion);
						}
					}
				);
			} elseif ($packet instanceof MobEffectPacket) {
				$applyWithReceipt(
					$packet,
					function(mixed $packet, PlayerProfile $session): void {
						/**
						 * @var MobEffectPacket $packet
						 */

						$packet->tick = $session->getTickId();
					},
					function(PlayerProfile $session) use ($packet): void {
						if ($packet->actorRuntimeId === $session->getPlayer()->getId()) {
							PlayerEffectProcessor::process($this->client, $session, $packet->effectId, $packet->amplifier, $packet->eventId);
						}
					}
				);
			} elseif ($packet instanceof MovePlayerPacket) {
				$applyWithReceipt(
					$packet,
					function(mixed $packet, PlayerProfile $session): void {
						/**
						 * @var MovePlayerPacket $packet
						 */

						$packet->tick = $session->getTickId();
					},
					function(PlayerProfile $session) use ($packet): void {
						if ($packet->actorRuntimeId === $session->getPlayer()->getId()) {
							PlayerPositionProcessor::process($this->client, $session, $packet->position);
						}
					}
				);
			} elseif ($packet instanceof UpdateBlockPacket) {
				$applyWithReceipt(
					$packet,
					function(mixed $packet, PlayerProfile $session): void {
						/**
						 * @var UpdateBlockPacket $packet
						 */

						$position = new Vector3($packet->blockPosition->getX(), $packet->blockPosition->getY(), $packet->blockPosition->getZ());
						$previousBlock = $this->client->getBlockChangeManager()->fetchBlockChange($position->getFloorX(), $position->getFloorY(), $position->getFloorZ(), $session->getPlayer()->getWorld());

						if ($previousBlock === null) {
							// maybe plugin
							$session->getLogger()->debug("Failed to start block synchronization (Previous block was null)");

							return;
						}

						$previous = TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($previousBlock->getStateId());
						$session->getLogger()->debug("Syncing block at $position->x, $position->y, $position->z ($previous -> $packet->blockRuntimeId)");
						$session->startBlockSync(
							$position,
							$previous,
							$packet->blockRuntimeId
						);
					},
					function(PlayerProfile $session) use ($packet): void {
						$position = new Vector3($packet->blockPosition->getX(), $packet->blockPosition->getY(), $packet->blockPosition->getZ());
						$blockSync = $session->getBlockSync($position);

						if ($blockSync === null) {
							return;
						}

						if ($blockSync->getSynchronizationPhase() !== BlockSyncPromise::PHASE_NONE) {
							return;
						}

						$session->getBlockSync($position)?->nextSynchronizationPhase(BlockSyncPromise::PHASE_ACK);
					}
				);
			}
		}
	}

	/**
	 * @param DataPacketReceiveEvent $event
	 * @return void
	 * @priority HIGHEST
	 * @handleCancelled
	 */
	public function onDataPacketReceive(DataPacketReceiveEvent $event): void {
		$packet = $event->getPacket();
		$player = $event->getOrigin()->getPlayer();

		if ($packet instanceof PlayerAuthInputPacket) {
			$uuid = $event->getOrigin()->getPlayerInfo()->getUuid()->toString();
			if (is_null($player) || !$player->isOnline() || !$player->spawned) {
				$this->beforeTickIdsMap[$uuid] ??= $packet->getTick();
				$this->beforeTickIdsMap[$uuid]++;

				return;
			}

			if (isset($this->beforeTickIdsMap[$uuid])) {
				PlayerProfileManager::getSession($player)->setTickId($this->beforeTickIdsMap[$uuid]);
				unset($this->beforeTickIdsMap[$uuid]);
			}

			PlayerProfileManager::getSession($player)->handleAuthInput($packet);
		}

	}
}
