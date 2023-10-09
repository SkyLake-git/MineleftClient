<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client;

use Logger;
use Lyrica0954\Mineleft\client\processor\PlayerAttributeProcessor;
use Lyrica0954\Mineleft\client\processor\PlayerFlagsProcessor;
use Lyrica0954\Mineleft\Main;
use Lyrica0954\Mineleft\mc\Block;
use Lyrica0954\Mineleft\mc\BlockAttributeFlags;
use Lyrica0954\Mineleft\network\MineleftSession;
use Lyrica0954\Mineleft\network\MineleftSessionBootstrap;
use Lyrica0954\Mineleft\network\protocol\MineleftPacket;
use Lyrica0954\Mineleft\network\protocol\PacketBlockMappings;
use Lyrica0954\Mineleft\network\protocol\PacketConfiguration;
use Lyrica0954\Mineleft\network\protocol\types\ChunkSendingMethod;
use pocketmine\block\Liquid;
use pocketmine\item\StringToItemParser;
use pocketmine\math\AxisAlignedBB;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\ObjectSet;
use ReflectionClass;
use WeakMap;

class MineleftClient {

	protected MineleftSession $session;

	protected MineleftPocketMineListener $listener;

	protected Server $pocketmine;

	/**
	 * @var WeakMap<NetworkSession, PacketPairing>
	 */
	protected WeakMap $packetPairings;

	/**
	 * @var WeakMap<NetworkSession, ActorStateStore>
	 */
	protected WeakMap $actorStateStore;

	protected ?SleeperHandlerEntry $sleeperHandlerEntry;

	protected ChunkSendingMethod $chunkSendingMethod;

	protected ObjectSet $tickHooks;

	/**
	 * @var array<int, MineleftPacket[]>
	 */
	protected array $packetFutures;

	public function __construct(
		Server $server,
		string $address,
		int    $port,
		Logger $logger
	) {
		$this->pocketmine = $server;
		$this->tickHooks = new ObjectSet();
		$this->packetPairings = new WeakMap();
		$this->actorStateStore = new WeakMap();
		$this->sleeperHandlerEntry = null;
		$this->chunkSendingMethod = ChunkSendingMethod::ALTERNATE;
		$this->session = MineleftSessionBootstrap::start($address, $port, $logger);
		$this->listener = new MineleftPocketMineListener($this);
	}

	public function start(): void {
		$conf = new PacketConfiguration();
		$conf->defaultWorldName = $this->pocketmine->getWorldManager()->getDefaultWorld()->getFolderName();
		$conf->chunkSendingMethod = $this->chunkSendingMethod;

		$this->session->sendPacket($conf);

		$this->sendBlockMappings();


		$this->sleeperHandlerEntry = $this->pocketmine->getTickSleeper()->addNotifier(function(): void {
			foreach ($this->packetPairings as $pairing) {
				$pairing->requestPairing();
				$pairing->incrementTick();
			}
		});


		$notifier = $this->sleeperHandlerEntry->createNotifier();
		$notifier->wakeupSleeper();

		Main::getInstance()->getScheduler()->scheduleRepeatingTask(
			new ClosureTask(function() use ($notifier): void {
				$notifier->wakeupSleeper();
			}), 1
		);
	}

	protected function sendBlockMappings(): void {
		$packet = new PacketBlockMappings();
		$packet->mappings = [];

		$lookupTable = (new ReflectionClass(BlockStateDictionary::class))->getProperty("stateDataToStateIdLookup")->getValue(TypeConverter::getInstance()->getBlockTranslator()->getBlockStateDictionary());

		foreach ($lookupTable as $name => $data) {
			$list = [];
			if (is_int($data)) {
				$list[] = $data;
			} else {
				$list = $data;
			}

			foreach ($list as $id) {
				$stateData = TypeConverter::getInstance()->getBlockTranslator()->getBlockStateDictionary()->generateDataFromStateId($id);
				$block = StringToItemParser::getInstance()->parse(explode(":", $stateData->getName())[1])?->getBlock();

				if (is_null($block)) {
					continue;
				}

				try {
					$block->getPosition()->x = 0;
					$block->getPosition()->y = 0;
					$block->getPosition()->z = 0;
					$boxes = $block->getCollisionBoxes();
				} catch (AssumptionFailedError) {
					$boxes = [AxisAlignedBB::one()]; // patch for relative-bounding box
				}

				$netBlock = new Block($id, $stateData->getName(), $boxes, $block->getFrictionFactor());

				if ($block instanceof Liquid) {
					$netBlock->appendAttributeFlag(BlockAttributeFlags::LIQUID);
				}

				if ($block->canClimb()) {
					$netBlock->appendAttributeFlag(BlockAttributeFlags::CLIMBABLE);
				}

				if (str_starts_with($stateData->getName(), "minecraft:water")) {
				}

				if (str_starts_with($stateData->getName(), "minecraft:air")) {
					$packet->nullBlockNetworkId = $id;
				}

				$packet->mappings[$id] = $netBlock;
			}
		}

		$this->session->sendPacket($packet);
	}

	/**
	 * @return array
	 */
	public function getPacketFutures(): array {
		return $this->packetFutures;
	}

	public function postFuturePacket(int $tick, MineleftPacket $packet): void {
		$this->packetFutures[$tick] ??= [];
		$this->packetFutures[$tick][] = $packet;
	}

	/**
	 * @return ObjectSet
	 */
	public function getTickHooks(): ObjectSet {
		return $this->tickHooks;
	}

	/**
	 * @return ChunkSendingMethod
	 */
	public function getChunkSendingMethod(): ChunkSendingMethod {
		return $this->chunkSendingMethod;
	}

	/**
	 * @return Server
	 */
	public function getPMMPServer(): Server {
		return $this->pocketmine;
	}

	/**
	 * @return MineleftPocketMineListener
	 */
	public function getListener(): MineleftPocketMineListener {
		return $this->listener;
	}

	/**
	 * @return MineleftSession
	 */
	public function getSession(): MineleftSession {
		return $this->session;
	}

	public function tick(): void {
		foreach ($this->tickHooks as $hook) {
			($hook)();
		}

		foreach ($this->packetFutures[Server::getInstance()->getTick()] ?? [] as $packet) {
			$this->session->sendPacket($packet);
		}

		$this->session->socketTick();
	}

	public function close(): void {
		$this->pocketmine->getTickSleeper()->removeNotifier($this->sleeperHandlerEntry->getNotifierId());
	}

	public function getPacketPairing(NetworkSession $session): ?PacketPairing {
		return $this->packetPairings[$session] ?? null;
	}

	public function loadPacketPairing(NetworkSession $session, int $tick): PacketPairing {
		$packetPairing = new PacketPairing($session, Main::getLatencyHandler(), $tick);
		$this->packetPairings[$session] = $packetPairing;
		$packetPairing->getConfirmListeners()->add(function(int $tick, array $packets) use ($session, $packetPairing): void {
			foreach ($packets as $pk) {
				$this->handlePacketConfirm($session, $pk);
			}
		});

		return $packetPairing;
	}

	private function handlePacketConfirm(NetworkSession $session, ClientboundPacket $packet): void {
		if ($packet instanceof SetActorDataPacket) {
			$this->getActorStateStore($session)->updateMetadata($packet->actorRuntimeId, $packet->metadata);

			if ($packet->actorRuntimeId === $session->getPlayer()?->getId()) {
				PlayerFlagsProcessor::process($this, $session);
			}
		} elseif ($packet instanceof UpdateAttributesPacket) {
			$this->getActorStateStore($session)->updateAttribute($packet->actorRuntimeId, $packet->entries);
			if ($packet->actorRuntimeId === $session->getPlayer()?->getId()) {
				PlayerAttributeProcessor::process($this, $session, $packet->entries);
			}
		}
	}

	public function getActorStateStore(NetworkSession $session): ActorStateStore {
		return $this->actorStateStore[$session] ??= new ActorStateStore();
	}
}
