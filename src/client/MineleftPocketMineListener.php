<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client;

use Closure;
use Lyrica0954\Mineleft\client\processor\PlayerAttributeProcessor;
use Lyrica0954\Mineleft\client\processor\PlayerFlagsProcessor;
use Lyrica0954\Mineleft\client\processor\PlayerMotionProcessor;
use Lyrica0954\Mineleft\client\task\ChunkSerializingTask;
use Lyrica0954\Mineleft\Main;
use Lyrica0954\Mineleft\network\protocol\PacketLevelChunk;
use Lyrica0954\Mineleft\network\protocol\PacketPlayerAuthInput;
use Lyrica0954\Mineleft\network\protocol\PacketPlayerLogin;
use Lyrica0954\Mineleft\network\protocol\types\ChunkSendingMethod;
use Lyrica0954\Mineleft\network\protocol\types\InputData;
use Lyrica0954\Mineleft\network\protocol\types\InputFlags;
use Lyrica0954\Mineleft\network\protocol\types\PlayerInfo;
use Lyrica0954\Mineleft\utils\WorldUtils;
use pocketmine\block\Air;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\network\mcpe\protocol\types\entity\Attribute;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\world\World;
use WeakMap;

class MineleftPocketMineListener implements Listener {
	protected WeakMap $lastSprintInput;

	protected WeakMap $lastSneakInput;

	protected WeakMap $lastPosition;

	/**
	 * @var WeakMap<NetworkSession, int>
	 */
	private WeakMap $firstAuthInputTick;

	public function __construct(
		protected MineleftClient $client
	) {
		$this->lastSprintInput = new WeakMap();
		$this->lastSneakInput = new WeakMap();
		$this->lastPosition = new WeakMap();
		$this->firstAuthInputTick = new WeakMap();
	}

	public function onChunkLoad(ChunkLoadEvent $event): void {
		if ($this->client->getChunkSendingMethod() === ChunkSendingMethod::REALTIME) {
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
	}

	public function onDataPacketSend(DataPacketSendEvent $event): void {
		$packets = $event->getPackets();

		foreach ($packets as $packet) {
			$doActorFilteredPairing = function(DataPacket $packet, Closure $onResponse, int $actorId) use ($event): void {
				foreach ($event->getTargets() as $target) {
					if ($target->getPlayer()?->getId() !== $actorId) {
						continue;
					}
					Main::getLatencyHandler()->request($target, $onResponse);
				}
			};

			if ($packet instanceof SetActorDataPacket) {
				$doActorFilteredPairing(
					$packet,
					function(NetworkSession $session, NetworkStackLatencyPacket $pk) use ($packet): void {
						$this->client->getActorStateStore($session)->updateMetadata($packet->actorRuntimeId, $packet->metadata);
						PlayerFlagsProcessor::process($this->client, $session);
					},
					$packet->actorRuntimeId
				);
			} elseif ($packet instanceof UpdateAttributesPacket) {
				$doActorFilteredPairing(
					$packet,
					fn(NetworkSession $session, NetworkStackLatencyPacket $pk) => PlayerAttributeProcessor::process($this->client, $session, $packet->entries),
					$packet->actorRuntimeId
				);
			} elseif ($packet instanceof SetActorMotionPacket) {
				$doActorFilteredPairing(
					$packet,
					fn(NetworkSession $session, NetworkStackLatencyPacket $pk) => PlayerMotionProcessor::process($this->client, $session, $packet->motion),
					$packet->actorRuntimeId
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

		if (is_null($player)) {
			return;
		}

		if (!$player->isOnline()) {
			return;
		}

		if (!$player->spawned) {
			return;
		}

		if ($packet instanceof PlayerAuthInputPacket) {
			if (!isset($this->firstAuthInputTick[$player->getNetworkSession()])) {
				$this->firstAuthInputTick[$player->getNetworkSession()] = $packet->getTick();
				PlayerAttributeProcessor::process($this->client, $player->getNetworkSession(), [new Attribute(\pocketmine\entity\Attribute::MOVEMENT_SPEED, 0, 100, 0.1, 0.1, [])]);
			}

			$pk = new PacketPlayerAuthInput();
			$pk->playerUuid = $player->getUniqueId();
			$pk->inputData = new InputData();
			$pk->requestedPosition = $packet->getPosition();

			$position = $packet->getPosition();
			$diff = $packet->getPosition()->subtractVector($this->lastPosition[$player] ?? $position);
			$delta = $packet->getDelta();
			$nearbyBlocks = WorldUtils::getNearbyBlocks($player->getWorld(), $player->getBoundingBox()->expandedCopy(0.75, 1.5, 0.75)->offset($diff->x, $diff->y, $diff->z)->addCoord($delta->x, $delta->y, $delta->z));

			$this->lastPosition[$player] = $packet->getPosition();
			$networkNearbyBlocks = [];
			foreach ($nearbyBlocks as $block) {
				if ($block instanceof Air) {
					continue;
				}
				$pos = $block->getPosition();
				$networkNearbyBlocks[morton3d_encode($pos->x, $pos->y, $pos->z)] = TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($block->getStateId());
			}

			$pk->nearbyBlocks = $networkNearbyBlocks;

			$resolveOnOffInputFlags = function(int $inputFlags, int $startFlag, int $stopFlag): ?bool {
				$enabled = ($inputFlags & (1 << $startFlag)) !== 0;
				$disabled = ($inputFlags & (1 << $stopFlag)) !== 0;
				if ($enabled !== $disabled) {
					return $enabled;
				}

				return null;
			};

			$toggleSprint = $resolveOnOffInputFlags($packet->getInputFlags(), PlayerAuthInputFlags::START_SPRINTING, PlayerAuthInputFlags::STOP_SPRINTING);
			$toggleSneaking = $resolveOnOffInputFlags($packet->getInputFlags(), PlayerAuthInputFlags::START_SNEAKING, PlayerAuthInputFlags::STOP_SNEAKING);

			$sneaking = $this->lastSneakInput[$player] ?? null;
			$sprinting = $this->lastSprintInput[$player] ?? null;

			// ON -> -1 tick
			// OFF -> 0 tick

			if ($toggleSneaking !== null) {
				$sneaking = $this->lastSneakInput[$player] = $toggleSneaking;
			}

			if ($toggleSprint !== null) {
				$sprinting = $this->lastSprintInput[$player] = $toggleSprint;
			}

			if ($packet->hasFlag(PlayerAuthInputFlags::UP))
				$pk->inputData->appendFlag(InputFlags::UP);
			if ($packet->hasFlag(PlayerAuthInputFlags::DOWN))
				$pk->inputData->appendFlag(InputFlags::DOWN);
			if ($packet->hasFlag(PlayerAuthInputFlags::LEFT))
				$pk->inputData->appendFlag(InputFlags::LEFT);
			if ($packet->hasFlag(PlayerAuthInputFlags::RIGHT))
				$pk->inputData->appendFlag(InputFlags::RIGHT);
			if ($packet->hasFlag(PlayerAuthInputFlags::JUMPING))
				$pk->inputData->appendFlag(InputFlags::JUMP);
			if ($sneaking)
				$pk->inputData->appendFlag(InputFlags::SNEAK);
			if ($sprinting)
				$pk->inputData->appendFlag(InputFlags::SPRINT);
			if ($packet->hasFlag(PlayerAuthInputFlags::UP_LEFT))
				$pk->inputData->appendFlag(InputFlags::UP_LEFT);
			if ($packet->hasFlag(PlayerAuthInputFlags::UP_RIGHT))
				$pk->inputData->appendFlag(InputFlags::UP_RIGHT);
			if ($packet->hasFlag(PlayerAuthInputFlags::MISSED_SWING))
				$pk->inputData->appendFlag(InputFlags::MISSED_SWING);

			$pk->inputData->setDelta($packet->getDelta());
			$pk->inputData->setMoveVecX($packet->getMoveVecX());
			$pk->inputData->setMoveVecZ($packet->getMoveVecZ());
			$pk->inputData->setYaw($packet->getYaw());
			$pk->inputData->setPitch($packet->getPitch());

			$this->client->getSession()->sendPacket($pk);
		}
	}
}
