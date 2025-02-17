<?php

namespace OCA\LarpingApp\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class Condition extends Entity implements JsonSerializable
{
    protected ?string $name = null;
    protected ?string $description = null;
    protected ?string $effect = null;
    protected ?array $effects = [];
    protected ?bool $unique = false;

    public function __construct()
    {
        $this->addType('name', 'string');
        $this->addType('description', 'string');
        $this->addType('effect', 'string');
        $this->addType('effects', 'array');
        $this->addType('unique', 'boolean');
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
            'unique' => $this->unique,
        ];
    }
}