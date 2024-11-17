<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client;

use Logger;
use Lyrica0954\Mineleft\client\pmmp\MineleftEventListener;
use Lyrica0954\Mineleft\mc\Block;
use Lyrica0954\Mineleft\mc\BlockAttributeFlags;
use Lyrica0954\Mineleft\network\MineleftSession;
use Lyrica0954\Mineleft\network\MineleftSessionBootstrap;
use Lyrica0954\Mineleft\network\protocol\PacketBlockMappings;
use Lyrica0954\Mineleft\network\protocol\PacketConfiguration;
use Lyrica0954\Mineleft\network\protocol\types\ChunkSendingMethod;
use pocketmine\block\Liquid;
use pocketmine\data\bedrock\block\BlockStateDeserializeException;
use pocketmine\data\bedrock\block\convert\BlockStateToObjectDeserializer;
use pocketmine\data\bedrock\block\convert\UnsupportedBlockStateException;
use pocketmine\math\AxisAlignedBB;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\Server;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\ObjectSet;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use ReflectionClass;

class MineleftClient {

	protected int $nullBlockNetworkId;

	protected MineleftSession $session;

	protected MineleftEventListener $listener;

	protected Server $pocketmine;

	protected ?SleeperHandlerEntry $sleeperHandlerEntry;

	protected ChunkSendingMethod $chunkSendingMethod;

	protected ObjectSet $tickHooks;

	protected MineleftBlockChangeManager $blockChangeManager;

	public function __construct(
		Server $server,
		string $address,
		int    $port,
		Logger $logger
	) {
		$this->pocketmine = $server;
		$this->tickHooks = new ObjectSet();
		$this->sleeperHandlerEntry = null;
		$this->chunkSendingMethod = ChunkSendingMethod::REALTIME;
		$this->session = MineleftSessionBootstrap::start($address, $port, $logger);
		$this->listener = new MineleftEventListener($this);
		$this->blockChangeManager = new MineleftBlockChangeManager();

		foreach ($this->pocketmine->getWorldManager()->getWorlds() as $world) {
			$this->blockChangeManager->createAdapter($world);
		}
	}

	public function start(): void {
		$conf = new PacketConfiguration();
		$conf->defaultWorldName = $this->pocketmine->getWorldManager()->getDefaultWorld()->getFolderName();
		$conf->chunkSendingMethod = $this->chunkSendingMethod;

		$this->session->sendPacket($conf);

		$this->sendBlockMappings();
	}

	protected function sendBlockMappings(): void {
		$packet = new PacketBlockMappings();
		$packet->mappings = [];

		$lookupTable = (new ReflectionClass(BlockStateDictionary::class))->getProperty("stateDataToStateIdLookup")->getValue(TypeConverter::getInstance()->getBlockTranslator()->getBlockStateDictionary());

		$deserializeFuncs = (new ReflectionClass(BlockStateToObjectDeserializer::class))->getProperty("deserializeFuncs")->getValue(GlobalBlockStateHandlers::getDeserializer());

		foreach ($lookupTable as $name => $data) {
			$list = [];
			if (is_int($data)) {
				$list[] = $data;
			} else {
				$list = $data;
			}

			foreach ($list as $id) {
				$stateData = TypeConverter::getInstance()->getBlockTranslator()->getBlockStateDictionary()->generateDataFromStateId($id);
				if (!isset($deserializeFuncs[$stateData->getName()])) {
					// uhm, sorry!
					continue;
				}
				try {
					$block = GlobalBlockStateHandlers::getDeserializer()->deserializeBlock($stateData);
				} catch (UnsupportedBlockStateException) {
					// uhm......................
					continue;
				} catch (BlockStateDeserializeException) {
					$this->getSession()->getLogger()->debug("Failed to deserialize {$stateData->getName()}");
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

				foreach ($stateData->getStates() as $key => $tag) {
					if ($tag instanceof IntTag) {
						$netBlock->getStateData()->integerMap[$key] = $tag->getValue();
					}
				}

				if (str_starts_with($stateData->getName(), "minecraft:water")) {
				}

				if (str_starts_with($stateData->getName(), "minecraft:air")) {
					$packet->nullBlockNetworkId = $id;
					$this->nullBlockNetworkId = $id;
				}

				$packet->mappings[$id] = $netBlock;
			}
		}

		$this->session->sendPacket($packet);
	}

	/**
	 * @return MineleftSession
	 */
	public function getSession(): MineleftSession {
		return $this->session;
	}

	public function getBlockChangeManager(): MineleftBlockChangeManager {
		return $this->blockChangeManager;
	}

	/**
	 * @return int
	 */
	public function getNullBlockNetworkId(): int {
		return $this->nullBlockNetworkId;
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
	 * @return MineleftEventListener
	 */
	public function getListener(): MineleftEventListener {
		return $this->listener;
	}

	public function tick(): void {
		foreach ($this->tickHooks as $hook) {
			($hook)();
		}

		$this->session->socketTick();
	}

	public function close(): void {
		$this->pocketmine->getTickSleeper()->removeNotifier($this->sleeperHandlerEntry->getNotifierId());
	}
}
