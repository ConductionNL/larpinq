<?php

namespace OCA\LarpingApp\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class Character extends Entity implements JsonSerializable
{
	protected ?string $name = null;
	protected ?string $ocName = null;
	protected ?string $description = null;
	protected ?string $background = null;
	protected ?string $itemsAndMoney = null;
	protected ?string $notice = null;
	protected ?string $faith = null;
	protected ?string $slNotesPublic = null;
	protected ?string $slNotesPrivate = null;
	protected ?string $card = null;
	protected ?array $stats = [];
	protected ?int $gold = null;
	protected ?int $silver = null;
	protected ?int $copper = null;
	protected ?array $events = [];
	protected ?array $skills = [];
	protected ?array $items = [];
	protected ?array $conditions = null;
	protected ?string $type = 'player';
	protected ?string $approved = 'no';

	public function __construct() {
		$this->addType('name', 'string');
		$this->addType('ocName', 'string');
		$this->addType('description', 'string');
		$this->addType('background', 'string');
		$this->addType('itemsAndMoney', 'string');
		$this->addType('notice', 'string');
		$this->addType('faith', 'string');
		$this->addType('slNotesPublic', 'string');
		$this->addType('slNotesPrivate', 'string');
		$this->addType('card', 'string');
		$this->addType('stats', 'json');
		$this->addType('gold', 'integer');
		$this->addType('silver', 'integer');
		$this->addType('copper', 'integer');
		$this->addType('events', 'json');
		$this->addType('skills', 'json');
		$this->addType('items', 'json');
		$this->addType('conditions', 'json');
		$this->addType('type', 'string');
		$this->addType('approved', 'string');
	}

	public function getJsonFields(): array
	{
		return array_keys(
			array_filter($this->getFieldTypes(), function ($field) {
				return $field === 'json';
			})
		);
	}

	public function hydrate(array $object): self
	{
		$jsonFields = $this->getJsonFields();

		foreach($object as $key => $value) {
			if (in_array($key, $jsonFields) === true && $value === []) {
				$value = [];
			}

			$method = 'set'.ucfirst($key);

			try {
				$this->$method($value);
			} catch (\Exception $exception) {
//				("Error writing $key");
			}
		}

		return $this;
	}

	public function jsonSerialize(): array
	{
		return [
			'id' => $this->id,
			'name' => $this->name,
			'ocName' => $this->ocName,
			'description' => $this->description,
			'background' => $this->background,
			'itemsAndMoney' => $this->itemsAndMoney,
			'notice' => $this->notice,
			'faith' => $this->faith,
			'slNotesPublic' => $this->slNotesPublic,
			'slNotesPrivate' => $this->slNotesPrivate,
			'card' => $this->card,
			'stats' => $this->stats,
			'gold' => $this->gold,
			'silver' => $this->silver,
			'copper' => $this->copper,
			'events' => $this->events,
			'skills' => $this->skills,
			'conditions' => $this->conditions,
			'type' => $this->type,
			'approved' => $this->approved,
		];
	}
}
