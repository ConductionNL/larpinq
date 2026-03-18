<?php

/**
 * Event entity for LarpingApp
 *
 * @category  Entity
 * @package   OCA\LarpingApp\Db
 * @author    Ruben Linde <ruben@nextcloud.com>
 * @copyright 2024 Ruben Linde
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link      https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * Event entity class
 *
 * @category  Apps
 * @package   LarpingApp
 * @author    Ruben Linde <ruben@nextcloud.com>
 * @copyright 2024 Ruben Linde
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link      https://larpingapp.com
 *
 * @psalm-suppress PropertyNotSetInConstructor $id is set by the parent Entity class.
 */
class Event extends Entity implements JsonSerializable
{

    /**
     * The title of the event.
     *
     * @var string
     *
     * @psalm-suppress PossiblyUnusedProperty Accessed via Entity __call magic (getTitle/setTitle).
     */
    protected $title = null;

    /**
     * The description of the event.
     *
     * @var string
     *
     * @psalm-suppress PossiblyUnusedProperty Accessed via Entity __call magic (getDescription/setDescription).
     */
    protected $description = null;

    /**
     * The start date of the event.
     *
     * @var DateTime
     *
     * @psalm-suppress PossiblyUnusedProperty Accessed via Entity __call magic (getStartDate/setStartDate).
     */
    protected $startDate = null;

    /**
     * The end date of the event.
     *
     * @var DateTime
     *
     * @psalm-suppress PossiblyUnusedProperty Accessed via Entity __call magic (getEndDate/setEndDate).
     */
    protected $endDate = null;

    /**
     * The user ID who owns the event.
     *
     * @var string
     *
     * @psalm-suppress PossiblyUnusedProperty Accessed via Entity __call magic (getUserId/setUserId).
     */
    protected $userId = null;

    /**
     * Constructor to set the defaults
     */
    public function __construct()
    {
        $this->addType(fieldName: 'title', type: 'string');
        $this->addType(fieldName: 'description', type: 'string');
        $this->addType(fieldName: 'startDate', type: 'datetime');
        $this->addType(fieldName: 'endDate', type: 'datetime');
        $this->addType(fieldName: 'userId', type: 'string');
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
            'title',
            'description',
            'startDate',
            'endDate',
            'userId',
        ];
    }//end getJsonFields()

    /**
     * Hydrate the entity from an array of data
     *
     * @param array<string,mixed> $data The data to hydrate from.
     *
     * @return void
     *
     * @psalm-suppress PossiblyUnusedMethod Called from EventMapper::createFromArray and updateFromArray.
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

    /**
     * Convert to array
     *
     * @param array $params Optional parameters to include
     *
     * @return array The event data as array
     *
     * @psalm-suppress PossiblyUnusedMethod Part of the entity API for array conversion.
     * @psalm-suppress PossiblyUnusedParam Reserved for future use to filter output fields.
     */
    public function toArray(array $params=[]): array
    {
        return $this->jsonSerialize();
    }//end toArray()
}//end class
