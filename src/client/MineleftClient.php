<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client;

use Logger;
use Lyrica0954\Mineleft\mc\Block;
use Lyrica0954\Mineleft\mc\BlockAttributeFlags;
use Lyrica0954\Mineleft\network\MineleftSession;
use Lyrica0954\Mineleft\network\MineleftSessionBootstrap;
use Lyrica0954\Mineleft\network\protocol\PacketBlockMappings;
use Lyrica0954\Mineleft\network\protocol\PacketConfiguration;
use pocketmine\block\Liquid;
use pocketmine\item\StringToItemParser;
use pocketmine\math\AxisAlignedBB;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use ReflectionClass;

class MineleftClient {

	protected MineleftSession $session;

	protected MineleftPocketMineListener $listener;

	protected Server $pocketmine;

	public function __construct(
		Server $server,
		string $address,
		int    $port,
		Logger $logger
	) {
		$this->pocketmine = $server;
		$this->session = MineleftSessionBootstrap::start($address, $port, $logger);
		$this->listener = new MineleftPocketMineListener($this);
	}

	public function start(): void {
		$conf = new PacketConfiguration();
		$conf->defaultWorldName = $this->pocketmine->getWorldManager()->getDefaultWorld()->getFolderName();

		$this->session->sendPacket($conf);

		$this->sendBlockMappings();
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

				$packet->mappings[$id] = $netBlock;
			}
		}

		$this->session->sendPacket($packet);
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
		$this->session->socketTick();
	}
}
