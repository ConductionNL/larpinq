<?php

/**
 * Setting entity for LarpingApp
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
 * Entity representing a Setting in the LARP game.
 *
 * @method string getName()
 * @method void setName(string $name)
 * @method string getValue()
 * @method void setValue(string $value)
 *
 * @psalm-suppress PropertyNotSetInConstructor $id is set by the parent Entity class.
 */
class Setting extends Entity implements JsonSerializable
{

    /**
     * The name of the setting.
     *
     * @var string|null
     *
     * @psalm-suppress PossiblyUnusedProperty Accessed via Entity __call magic (getName/setName).
     */
    protected $name = null;

    /**
     * The value of the setting.
     *
     * @var string|null
     *
     * @psalm-suppress PossiblyUnusedProperty Accessed via Entity __call magic (getValue/setValue).
     */
    protected $value = null;

    /**
     * Constructor to set the defaults
     */
    public function __construct()
    {
        $this->addType(fieldName: 'name', type: 'string');
        $this->addType(fieldName: 'value', type: 'string');
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
            'value',
        ];
    }//end getJsonFields()

    /**
     * Hydrate the entity from an array of data
     *
     * @param array<string,mixed> $data The data to hydrate from.
     *
     * @return void
     *
     * @psalm-suppress PossiblyUnusedMethod Called from SettingMapper::createFromArray and updateFromArray.
     */
    public function hydrate(array $data): void
    {
        /** @psalm-suppress MixedAssignment Dynamic entity property */
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
            /** @psalm-suppress MixedAssignment Dynamic entity property */
            $data[$field] = $this->$field;
        }

        return $data;
    }//end jsonSerialize()
}//end class
