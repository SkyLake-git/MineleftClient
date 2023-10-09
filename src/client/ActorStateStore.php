<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\client;

use pocketmine\network\mcpe\protocol\types\entity\Attribute as NetworkAttribute;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;

class ActorStateStore {

	/**
	 * @var array<int, EntityMetadataCollection>
	 */
	protected array $metadata;

	/**
	 * @var array<int, array<int, NetworkAttribute>>
	 */
	protected array $attribute;

	public function __construct() {
		$this->metadata = [];
	}

	public function storeMetadata(int $actorId, EntityMetadataCollection $metadata): void {
		$this->metadata[$actorId] = $metadata;
	}

	public function updateAttribute(int $actorId, array $attribute): void {
		$old = $this->attribute[$actorId] ?? [];
		$this->attribute[$actorId] = array_merge($old, $attribute);
	}

	public function getAttribute(int $actorId): ?array {
		return $this->attribute[$actorId] ?? null;
	}

	public function updateMetadata(int $actorId, array $metadata): void {
		$this->metadata[$actorId] ??= new EntityMetadataCollection();
		$this->metadata[$actorId]->setAtomicBatch($metadata);
	}

	public function getMetadata(int $actorId): ?EntityMetadataCollection {
		return $this->metadata[$actorId] ?? null;
	}

}
