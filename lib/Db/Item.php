<?php

/**
 * Item entity for LarpingApp
 *
 * @category  Entity
 * @package   OCA\LarpingApp\Db
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link      https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * Entity representing an Item in the LARP game.
 *
 * @method string getName()
 * @method void setName(string $name)
 * @method string getDescription()
 * @method void setDescription(string $description)
 */
class Item extends Entity implements JsonSerializable
{

    /**
     * The name of the item.
     *
     * @var string
     */
    protected $name;

    /**
     * The description of the item.
     *
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
    }//end __construct()

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
            'description',
        ];
    }//end getJsonFields()

    /**
     * Hydrate the entity from an array of data
     *
     * @param array<string,mixed> $data The data to hydrate from.
     *
     * @return void
     */
    public function hydrate(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }//end hydrate()

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
    }//end jsonSerialize()
}//end class
