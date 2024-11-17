<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\player\debug;

use pocketmine\entity\Entity;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\PlayerSkinPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\types\AbilitiesData;
use pocketmine\network\mcpe\protocol\types\command\CommandPermissions;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\network\mcpe\protocol\types\GameMode;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\PlayerPermissions;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\UpdateAbilitiesPacket;
use pocketmine\player\Player;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class MineleftDebugVirtualEntity {

	private UuidInterface $uuid;

	private array $viewers;

	private Vector3 $position;

	private float $yaw;

	private float $pitch;

	private ItemStackWrapper $handItem;

	private int $entityRuntimeId;

	public function __construct(
		private string   $name,
		private SkinData $skinData,
		Vector3          $position
	) {
		$this->viewers = [];
		$this->position = $position;
		$this->yaw = 0;
		$this->pitch = 0;
		$this->handItem = new ItemStackWrapper(0, TypeConverter::getInstance()->coreItemStackToNet(VanillaItems::AIR()));
		$this->entityRuntimeId = Entity::nextRuntimeId();
		$this->uuid = Uuid::uuid4();
	}

	public function broadcastMovement(Vector3 $position, float $yaw, float $pitch): void {
		$packets = [
			MoveActorAbsolutePacket::create(
				$this->entityRuntimeId,
				$position->add(0, 1.62, 0),
				$pitch,
				$yaw,
				$yaw,
				0
			)
		];
		NetworkBroadcastUtils::broadcastPackets($this->viewers, $packets);
		$this->position = clone $position;
		$this->yaw = $yaw;
		$this->pitch = $pitch;
	}

	public function broadcastHandItem(ItemStackWrapper $item): void {
		$packets = [
			MobEquipmentPacket::create(
				$this->entityRuntimeId,
				$item,
				0,
				0,
				ContainerIds::INVENTORY
			)
		];
		NetworkBroadcastUtils::broadcastPackets($this->viewers, $packets);
		$this->handItem = $item;
	}

	public function getPosition(): Vector3 {
		return $this->position;
	}

	public function getYaw(): float {
		return $this->yaw;
	}

	public function getPitch(): float {
		return $this->pitch;
	}

	public function spawnTo(Player $player): void {
		if (!$player->isOnline()) {
			return;
		}
		$this->viewers[spl_object_hash($player)] = $player;

		$packets = [];
		$packets[] = AddPlayerPacket::create(
			$this->uuid,
			$this->name,
			$this->entityRuntimeId,
			"",
			$this->position,
			null,
			$this->yaw,
			$this->pitch,
			$this->yaw,
			$this->handItem,
			GameMode::ADVENTURE,
			[],
			new PropertySyncData([], []),
			UpdateAbilitiesPacket::create(new AbilitiesData(CommandPermissions::INTERNAL, PlayerPermissions::VISITOR, $this->entityRuntimeId, [])),
			[],
			"",
			0
		);


		$packets[] = PlayerSkinPacket::create(
			$this->uuid,
			"Mineleft",
			"Mineleft",
			$this->skinData
		);


		foreach ($packets as $pk) {
			$player->getNetworkSession()->sendDataPacket($pk);
		}
	}

	public function despawnFromAll(): void {
		foreach ($this->viewers as $player) {
			$this->despawnFrom($player);
		}
	}

	public function despawnFrom(Player $player): void {
		if (!$player->isOnline()) {
			return;
		}
		if (isset($this->viewers[spl_object_hash($player)])) {
			$player->getNetworkSession()->sendDataPacket(RemoveActorPacket::create($this->entityRuntimeId));
			unset($this->viewers[spl_object_hash($player)]);
		}
	}
}
