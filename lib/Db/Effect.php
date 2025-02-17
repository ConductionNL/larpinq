<?php

namespace OCA\LarpingApp\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class Effect extends Entity implements JsonSerializable
{
    protected ?string $name = null;
    protected ?string $description = null;
    protected ?string $statId = null;
    protected ?int $modifier = null;
    protected ?string $modification = 'positive';
    protected ?string $cumulative = 'non-cumulative';

    public function __construct()
    {
        $this->addType('name', 'string');
        $this->addType('description', 'string');
        $this->addType('statId', 'string');
        $this->addType('modifier', 'integer');
        $this->addType('modification', 'string');
        $this->addType('cumulative', 'string');
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
        'statId' => $this->statId,
        'modifier' => $this->modifier,
        'modification' => $this->modification,
        'cumulative' => $this->cumulative,
        ];
    }
}