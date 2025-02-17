<?php

namespace OCA\LarpingApp\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class Skill extends Entity implements JsonSerializable
{
    protected ?string $name = null;
    protected ?string $description = null;
    protected ?string $effect = null;
    protected ?array $effects = null;
    protected ?array $requiredSkills = [];
    protected ?array $requiredStats = [];
    protected ?array $requiredConditions = [];
    protected ?array $requiredEffects = [];
    protected ?int $requiredScore = null;

    public function __construct()
    {
        $this->addType('name', 'string');
        $this->addType('description', 'string');
        $this->addType('effect', 'string');
        $this->addType('effects', 'json');
        $this->addType('requiredSkills', 'json');
        $this->addType('requiredStats', 'json');
        $this->addType('requiredConditions', 'json');
        $this->addType('requiredEffects', 'json');
        $this->addType('requiredScore', 'integer');
    }

    public function getJsonFields(): array
    {
        return array_keys(
            array_filter(
                $this->getFieldTypes(), function ($field) {
                    return $field === 'json';
                }
            )
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
                //                ("Error writing $key");
            }
        }

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
        'id' => $this->id,
        'name' => $this->name,
        'description' => $this->description,
        'effect' => $this->effect,
        'effects' => $this->effects,
        'requiredSkills' => $this->requiredSkills,
        'requiredStats' => $this->requiredStats,
        'requiredConditions' => $this->requiredConditions,
        'requiredEffects' => $this->requiredEffects,
        'requiredScore' => $this->requiredScore,
        ];
    }
}