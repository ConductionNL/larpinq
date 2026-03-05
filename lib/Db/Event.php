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
 */
class Event extends Entity implements JsonSerializable
{

    /**
     * The title of the event.
     *
     * @var string
     */
    protected $title;

    /**
     * The description of the event.
     *
     * @var string
     */
    protected $description;

    /**
     * The start date of the event.
     *
     * @var DateTime
     */
    protected $startDate;

    /**
     * The end date of the event.
     *
     * @var DateTime
     */
    protected $endDate;

    /**
     * The user ID who owns the event.
     *
     * @var string
     */
    protected $userId;

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

    /**
     * Convert to array
     *
     * @param array $params Optional parameters to include
     *
     * @return array The event data as array
     */
    public function toArray(array $params=[]): array
    {
        // ... existing code ...
    }//end toArray()
}//end class
