<?php

declare(strict_types=1);

/**
 * Event entity for LarpingApp
 *
 * @category  Apps
 * @package   LarpingApp
 * @author    Ruben Linde <ruben@nextcloud.com>
 * @copyright 2024 Ruben Linde
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 *
 * @php-version 8.2
 */

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
     * Event properties
     */
    protected $title;
    protected $description;
    protected $startDate;
    protected $endDate;
    protected $userId;

    /**
     * Constructor to set the defaults
     */
    public function __construct()
    {
        $this->addType('title', 'string');
        $this->addType('description', 'string');
        $this->addType('startDate', 'datetime');
        $this->addType('endDate', 'datetime');
        $this->addType('userId', 'string');
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
            'title',
            'description',
            'startDate',
            'endDate',
            'userId'
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

    /**
     * Convert to array
     *
     * @param array $params Optional parameters to include
     * 
     * @return array The event data as array
     */
    public function toArray(array $params = []): array
    {
        // ... existing code ...
    }
}