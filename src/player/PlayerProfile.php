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
use Lyrica0954\Mineleft\player\debug\PlayerDebuggingProfile;
use Lyrica0954\Mineleft\utils\WorldUtils;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\CorrectPlayerMovePredictionPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\entity\Attribute;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\network\mcpe\protocol\types\PlayerBlockActionWithBlockInfo;
use pocketmine\player\Player;
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

	private ReflectionProperty $playerAuthInputPacketItemInteractionDataProperty;

	private float $lastBlockPlaceTime;

	private ?UseItemTransactionData $lastBlockPlaceData;

	private Logger $logger;

	private PlayerDebuggingProfile $debuggingProfile;

	/**
	 * @var array<int, true>
	 */
	private array $clientSideUpdatedBlocks;

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
		$this->playerAuthInputPacketItemInteractionDataProperty = (new ReflectionClass(PlayerAuthInputPacket::class))->getProperty("itemInteractionData");
		$this->lastBlockPlaceTime = 0;
		$this->lastBlockPlaceData = null;
		$this->debuggingProfile = new PlayerDebuggingProfile($this->mineleftClient, $this->player);
		$this->clientSideUpdatedBlocks = [];
	}

	public function getLogger(): Logger {
		return $this->logger;
	}

	public function getDebuggingProfile(): PlayerDebuggingProfile {
		return $this->debuggingProfile;
	}

	/**
	 * @return int
	 */
	public function getRuntimeId(): int {
		return $this->runtimeId;
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

		$this->processServerAuthoritativeBlockBreaking($packet);
		$interactionData = $packet->getItemInteractionData();
		if ($interactionData !== null) {
			$this->processBlockPlacing($interactionData->getTransactionData());
			if ($interactionData->getTransactionData()->getActionType() === UseItemTransactionData::ACTION_CLICK_BLOCK) {
				$this->playerAuthInputPacketItemInteractionDataProperty->setValue($packet);
			}
		}

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

	public function processServerAuthoritativeBlockBreaking(PlayerAuthInputPacket $packet): void {
		$actions = [];
		foreach ($packet->getBlockActions() ?? [] as $index => $blockAction) {
			if ($blockAction instanceof PlayerBlockActionWithBlockInfo) {
				// hiding these action because pmmp dropping packet for unknown action
				if ($blockAction->getActionType() === PlayerAction::PREDICT_DESTROY_BLOCK) {
					// worst server-authoritative-block-breaking implementation
					$blockPos = $blockAction->getBlockPosition();
					$position = new Vector3($blockPos->getX(), $blockPos->getY(), $blockPos->getZ());
					if ($this->player->breakBlock($position)) {
						$this->clientSideUpdatedBlocks[World::blockHash($blockPos->getX(), $blockPos->getY(), $blockPos->getZ())] = true;
						WorldUtils::broadcastUpdateBlockImmediately($this->player->getWorld(), $position);
					}
					continue;
				} elseif ($blockAction->getActionType() === PlayerAction::CONTINUE_DESTROY_BLOCK) {
					continue;
				}
			}
			$actions[$index] = $blockAction;
		}

		$this->playerAuthInputPacketBlockActionsProperty->setValue($packet, $actions);
	}

	public function processBlockPlacing(UseItemTransactionData $useItem): void {
		if ($useItem->getActionType() === UseItemTransactionData::ACTION_CLICK_BLOCK) {
			$handItem = $useItem->getItemInHand();

			// pmmp
			$spamBug = ($this->lastBlockPlaceData !== null &&
				microtime(true) - $this->lastBlockPlaceTime < 0.1 &&
				$this->lastBlockPlaceData->getPlayerPosition()->distanceSquared($useItem->getPlayerPosition()) < 0.00001 &&
				$this->lastBlockPlaceData->getBlockPosition()->equals($useItem->getBlockPosition()) &&
				$this->lastBlockPlaceData->getClickPosition()->distanceSquared($useItem->getClickPosition()) < 0.00001
			);
			// ---

			$this->lastBlockPlaceData = $useItem;
			$this->lastBlockPlaceTime = microtime(true);
			if ($spamBug) {
				return;
			}

			if (!in_array($useItem->getFace(), Facing::ALL, true)) {
				return;
			}

			if (!$handItem->getItemStack()->isNull()) {
				$blockPos = $useItem->getBlockPosition();
				$vector = new Vector3(
					$blockPos->getX(),
					$blockPos->getY(),
					$blockPos->getZ()
				);

				if (!$this->player->interactBlock($vector, $useItem->getFace(), $useItem->getClickPosition())) {
					// ---- pmmp
					if ($vector->distanceSquared($this->player->getLocation()) < 10000) {
						$blocks = $vector->sidesArray();
						if ($useItem->getFace() !== null) {
							$sidePos = $vector->getSide($useItem->getFace());

							array_push($blocks, ...$sidePos->sidesArray()); //getAllSides() on each of these will include $blockPos and $sidePos because they are next to each other
						} else {
							$blocks[] = $blockPos;
						}
						foreach ($this->player->getWorld()->createBlockUpdatePackets($blocks) as $packet) {
							$this->player->getNetworkSession()->sendDataPacket($packet);
						}
					}
					// ----
				} else {
					$this->clientSideUpdatedBlocks[World::blockHash($blockPos->getX(), $blockPos->getY(), $blockPos->getZ())] = true;
					WorldUtils::broadcastUpdateBlockImmediately($this->player->getWorld(), new Vector3($blockPos->getX(), $blockPos->getY(), $blockPos->getZ()));
				}
				// fixme: hack
			}
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
			return $blockSync->getPrevious();
		} else {
			return TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($this->player->getWorld()->getBlock($position)->getStateId());
		}
	}

	public function getBlockSync(Vector3 $position): ?BlockSyncPromise {
		return $this->blockSyncPromises[World::blockHash($position->getFloorX(), $position->getFloorY(), $position->getFloorZ())] ?? null;
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

	public function startBlockSync(Vector3 $position, int $target): ?BlockSyncPromise {
		$hash = World::blockHash($position->getFloorX(), $position->getFloorY(), $position->getFloorZ());
		$previousBlock = $this->mineleftClient->getBlockChangeManager()->fetchBlockChange($position->getFloorX(), $position->getFloorY(), $position->getFloorZ(), $this->getPlayer()->getWorld());

		if ($previousBlock === null) {
			// maybe plugin
			$this->getLogger()->debug("Failed to start block synchronization (Previous block was null)");

			return null;
		}

		$previous = TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($previousBlock->getStateId());
		$this->getLogger()->debug("Syncing block at $position->x, $position->y, $position->z ($previous -> $target)");


		$blockSync = $this->registerBlockSync(
			$position,
			$previous,
			$target
		);

		if (isset($this->clientSideUpdatedBlocks[$hash])) {
			// if the block update was client-sided, mark as synchronized
			$blockSync->onSync();
			unset($this->clientSideUpdatedBlocks[$hash]);
		}

		return $blockSync;
	}

	/**
	 * @return Player
	 */
	public function getPlayer(): Player {
		return $this->player;
	}

	public function registerBlockSync(Vector3 $position, int $previous, int $target): BlockSyncPromise {
		return ($this->blockSyncPromises[World::blockHash($position->getFloorX(), $position->getFloorY(), $position->getFloorZ())] = new BlockSyncPromise($previous, $target))->then(fn() => $this->onCompleteBlockSync($position));
	}

	protected function onCompleteBlockSync(Vector3 $position): void {
		$promise = $this->getBlockSync($position);

		if ($promise === null) {
			return;
		}

		$this->logger->debug("Synced block $position");

		unset($this->blockSyncPromises[World::blockHash($position->getFloorX(), $position->getFloorY(), $position->getFloorZ())]);

		if ($this->mineleftClient->getChunkSendingMethod() !== ChunkSendingMethod::ALTERNATE) {
			$packet = new PacketUpdateBlock();
			$packet->blockPosition = new BlockPosition($position->getFloorX(), $position->getFloorY(), $position->getFloorZ());
			$packet->block = $promise->getTarget();
			$packet->viewerProfileRuntimeIds = [$this->runtimeId]; // todo:

			$this->mineleftClient->getSession()->sendPacket($packet);
		}
	}
}
