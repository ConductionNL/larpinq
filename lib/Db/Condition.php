<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Ruben Linde <ruben@larpingapp.com>
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @license   AGPL-3.0-or-later
 */

namespace OCA\LarpingApp\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getName()
 * @method void setName(string $name)
 * @method string getDescription()
 * @method void setDescription(string $description)
 */
class Condition extends Entity implements JsonSerializable
{
    /**
     * @var string 
     */
    protected $name;
    
    /**
     * @var string 
     */
    protected $description;

    /**
     * Constructor to set the defaults
     */
    public function __construct()
    {
        $this->addType('name', 'string');
        $this->addType('description', 'string');
    }

    /**
     * Get the fields that should be serialized to JSON
     *
     * @return array<string>
     */
    public function getJsonFields(): array
    {
        return [
            'id',
            'name',
            'description'
        ];
    }

    /**
     * Hydrate the entity from an array of data
     *
     * @param  array<string,mixed> $data
     * @return void
     */
    public function hydrate(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Serialize the entity to JSON
     *
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [];
        foreach ($this->getJsonFields() as $field) {
            $data[$field] = $this->$field;
        }
        return $data;
    }
}