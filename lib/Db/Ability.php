<?php

namespace OCA\LarpingApp\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class Ability extends Entity implements JsonSerializable
{

    protected ?string $name         = null;
    protected ?string $description  = null;
    protected ?int $base = 0;
    protected ?bool $allowedNegative = false;
    public function __construct()
    {
        $this->addType(fieldName: 'name', type: 'string');
        $this->addType(fieldName: 'description', type: 'string');
        $this->addType(fieldName: 'base', type: 'string');
        $this->addType(fieldName: 'allowedNegative', type: 'boolean');

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
        $array = [
        'id' => $this->id,
        'name' => $this->name,
        'description' => $this->description,
        'base' => $this->base,
        'allowedNegative' => $this->allowedNegative,
        ];

        $jsonFields = $this->getJsonFields();

        foreach ($array as $key => $value) {
            if (in_array($key, $jsonFields) === true && $value === null) {
                $array[$key] = [];
            }
        }

        return $array;
    }
}
