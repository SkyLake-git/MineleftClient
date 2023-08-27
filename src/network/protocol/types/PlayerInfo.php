<?php

declare(strict_types=1);

namespace Lyrica0954\Mineleft\network\protocol\types;

use Ramsey\Uuid\UuidInterface;

class PlayerInfo {

	protected string $name;

	protected UuidInterface $uuid;

	/**
	 * @param string $name
	 * @param UuidInterface $uuid
	 */
	public function __construct(string $name, UuidInterface $uuid) {
		$this->name = $name;
		$this->uuid = $uuid;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}


	/**
	 * @return UuidInterface
	 */
	public function getUuid(): UuidInterface {
		return $this->uuid;
	}


}
