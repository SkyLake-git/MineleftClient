<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\player;

use Logger;
use Lyrica0954\Mineleft\client\ActorStateStore;
use Lyrica0954\Mineleft\client\MineleftClient;
use Lyrica0954\Mineleft\client\processor\PlayerAttributeProcessor;
use Lyrica0954\Mineleft\network\protocol\PacketCorrectMovement;
use Lyrica0954\Mineleft\network\protocol\PacketPlayerAuthInput;
use Lyrica0954\Mineleft\network\protocol\PacketUpdateBlock;
use Lyrica0954\Mineleft\network\protocol\types\BlockPosition;
use Lyrica0954\Mineleft\network\protocol\types\ChunkSendingMethod;
use Lyrica0954\Mineleft\network\protocol\types\InputData;
use Lyrica0954\Mineleft\network\protocol\types\InputFlags;
use Lyrica0954\Mineleft\utils\WorldUtils;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\CorrectPlayerMovePredictionPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\entity\Attribute;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\network\mcpe\protocol\types\PlayerBlockActionWithBlockInfo;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\World;
use PrefixedLogger;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use ReflectionProperty;


class PlayerProfile {

	private int $runtimeId;

	private bool $firstAuthInputFlag;

	private ActorStateStore $actorStateStore;

	private int $tickId;

	private ?Vector3 $lastPosition;

	private ?bool $lastSneakInput;

	private ?bool $lastSprintInput;

	private int $lastMovementCorrection;

	private ?PacketCorrectMovement $queuedCorrectMovement;

	/**
	 * @var BlockSyncPromise[]
	 */
	private array $blockSyncPromises;

	private ReflectionProperty $playerAuthInputPacketBlockActionsProperty;

	private Logger $logger;

	public function __construct(
		private readonly MineleftClient $mineleftClient,
		private readonly Player         $player,
		int                             $runtimeId
	) {
		$this->logger = new PrefixedLogger($this->mineleftClient->getSession()->getLogger(), "Profile: {$this->player->getName()}");
		$this->actorStateStore = new ActorStateStore();
		$this->firstAuthInputFlag = false;
		$this->lastPosition = null;
		$this->lastSprintInput = null;
		$this->lastSneakInput = null;
		$this->tickId = 0;
		$this->lastMovementCorrection = 0;
		$this->queuedCorrectMovement = null;
		$this->blockSyncPromises = [];
		$this->runtimeId = $runtimeId;
		$this->playerAuthInputPacketBlockActionsProperty = (new ReflectionClass(PlayerAuthInputPacket::class))->getProperty("blockActions");
	}

	public function getLogger(): Logger {
		return $this->logger;
	}

	/**
	 * @return int
	 */
	public function getRuntimeId(): int {
		return $this->runtimeId;
	}

	public function startBlockSync(Vector3 $position, int $previous, int $target): BlockSyncPromise {
		$this->mineleftClient->getSession()->getLogger()->info("Starting block sync at $position tick: " . Server::getInstance()->getTick());

		return ($this->blockSyncPromises[World::blockHash($position->getFloorX(), $position->getFloorY(), $position->getFloorZ())] = new BlockSyncPromise($previous, $target))->then(fn() => $this->onCompleteBlockSync($position));

	}

	protected function onCompleteBlockSync(Vector3 $position): void {
		$promise = $this->getBlockSync($position);

		if ($promise === null) {
			return;
		}

		$this->mineleftClient->getSession()->getLogger()->info("Synced at $position tick: " . Server::getInstance()->getTick());

		unset($this->blockSyncPromises[World::blockHash($position->getFloorX(), $position->getFloorY(), $position->getFloorZ())]);

		if ($this->mineleftClient->getChunkSendingMethod() !== ChunkSendingMethod::ALTERNATE) {
			$packet = new PacketUpdateBlock();
			$packet->blockPosition = new BlockPosition($position->getFloorX(), $position->getFloorY(), $position->getFloorZ());
			$packet->block = $promise->getTarget();
			$packet->viewerProfileRuntimeIds = [$this->runtimeId]; // todo:

			$this->mineleftClient->getSession()->sendPacket($packet);
		}
	}

	public function getBlockSync(Vector3 $position): ?BlockSyncPromise {
		return $this->blockSyncPromises[World::blockHash($position->getFloorX(), $position->getFloorY(), $position->getFloorZ())] ?? null;
	}

	/**
	 * @return int
	 *
	 * Returns client simulation tick id
	 */
	public function getTickId(): int {
		return $this->tickId;
	}

	/**
	 * @param int $tickId
	 */
	public function setTickId(int $tickId): void {
		$this->tickId = $tickId;
	}

	/**
	 * @return ActorStateStore
	 */
	public function getActorStateStore(): ActorStateStore {
		return $this->actorStateStore;
	}

	/**
	 * @return Player
	 */
	public function getPlayer(): Player {
		return $this->player;
	}

	public function getUuid(): UuidInterface {
		return $this->player->getUniqueId();
	}

	public function queueCorrectMovement(PacketCorrectMovement $packet): void {
		if ($this->queuedCorrectMovement !== null) {
			return;
		}
		$this->queuedCorrectMovement = $packet;
	}

	public function handleAuthInput(PlayerAuthInputPacket $packet): void {
		if (!$this->firstAuthInputFlag) {
			$this->firstAuthInputFlag = true;
			PlayerAttributeProcessor::process($this->mineleftClient, $this, [new Attribute(\pocketmine\entity\Attribute::MOVEMENT_SPEED, 0, 100, 0.1, 0.1, [])]);
		} else {
			$this->tickId++;
		}

		$actions = [];
		foreach ($packet->getBlockActions() ?? [] as $index => $blockAction) {
			if ($blockAction instanceof PlayerBlockActionWithBlockInfo) {
				// hiding these action because pmmp dropping packet for unknown action
				if ($blockAction->getActionType() === PlayerAction::PREDICT_DESTROY_BLOCK) {
					// worst server-authoritative-block-breaking implementation
					$blockPos = $blockAction->getBlockPosition();
					$position = new Vector3($blockPos->getX(), $blockPos->getY(), $blockPos->getZ());
					$this->player->breakBlock($position);
					continue;
				} elseif ($blockAction->getActionType() === PlayerAction::CONTINUE_DESTROY_BLOCK) {
					continue;
				}
			}
			$actions[$index] = $blockAction;
		}

		$this->playerAuthInputPacketBlockActionsProperty->setValue($packet, $actions);

		$this->sendAuthInputPacket($packet);

		foreach ($this->blockSyncPromises as $blockSync) {
			if ($blockSync->getSynchronizationPhase() === BlockSyncPromise::PHASE_ACK) {
				$blockSync->nextSynchronizationPhase(BlockSyncPromise::PHASE_AUTH);
			}
		}

		if ($this->shouldCorrectMovement($packet->getTick())) {
			$this->sendCorrectMovement();
		}
	}

	protected function sendAuthInputPacket(PlayerAuthInputPacket $packet): void {
		$player = $this->player;
		$pk = new PacketPlayerAuthInput();
		$pk->profileRuntimeId = $this->runtimeId;
		$pk->frame = $this->tickId;
		$pk->inputData = new InputData();
		$pk->requestedPosition = $packet->getPosition();

		$position = $packet->getPosition();
		$diff = $packet->getPosition()->subtractVector($this->lastPosition ?? $position);
		$delta = $packet->getDelta();
		$nearbyBlocks = WorldUtils::getNearbyBlocks($player->getWorld(), $player->getBoundingBox()->expandedCopy(1.75, 2.25, 1.75)->offset($diff->x, $diff->y, $diff->z)->addCoord($delta->x, $delta->y, $delta->z));

		$this->lastPosition = $packet->getPosition();
		$networkNearbyBlocks = [];
		foreach ($nearbyBlocks as $block) {
			$seeingBlock = $this->getSeeingBlockAt($pos = $block->getPosition());
			if ($seeingBlock === $this->mineleftClient->getNullBlockNetworkId()) {
				continue; // air
			}
			$networkNearbyBlocks[morton3d_encode($pos->x, $pos->y, $pos->z)] = $seeingBlock;
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

		$sneaking = $this->lastSneakInput ?? null;
		$sprinting = $this->lastSprintInput ?? null;

		// ON -> -1 tick
		// OFF -> 0 tick

		if ($toggleSneaking !== null) {
			$sneaking = $this->lastSneakInput = $toggleSneaking;
		}

		if ($toggleSprint !== null) {
			$sprinting = $this->lastSprintInput = $toggleSprint;
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

		$this->mineleftClient->getSession()->sendPacket($pk);
	}

	public function getSeeingBlockAt(Vector3 $position): int {
		$blockSync = $this->getBlockSync($position);

		if ($blockSync !== null) {
			$this->mineleftClient->getSession()->getLogger()->info("Using block sync cache $position");

			return $blockSync->getPrevious();
		} else {
			return TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($this->player->getWorld()->getBlock($position)->getStateId());
		}
	}

	public function shouldCorrectMovement(int $frame): bool {
		return $frame - $this->lastMovementCorrection > 10;
	}

	public function sendCorrectMovement(): void {
		if ($this->queuedCorrectMovement === null) {
			return;
		}

		$packet = $this->queuedCorrectMovement;

		$correction = CorrectPlayerMovePredictionPacket::create(
			$packet->position->add(0, 1.621, 0),
			$packet->delta,
			$packet->onGround,
			$packet->frame,
			CorrectPlayerMovePredictionPacket::PREDICTION_TYPE_PLAYER,
			null,
			null
		);

		$this->player->getNetworkSession()->sendDataPacket($correction);

		$this->lastMovementCorrection = $packet->frame;
		$this->queuedCorrectMovement = null;
	}
}
