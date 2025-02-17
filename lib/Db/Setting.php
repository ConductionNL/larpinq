<?php

namespace OCA\LarpingApp\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class Setting extends Entity implements JsonSerializable
{

    protected ?string $title         = null;
    protected ?string $summary      = null;
    protected ?string $description  = null;
    protected ?string $image        = null;
    protected ?string $search        = null;

    protected bool    $listed       = false;
    protected ?string $organisation = null;
    protected ?array   $metadata    = null;

    public function __construct()
    {
        $this->addType(fieldName: 'title', type: 'string');
        $this->addType(fieldName: 'summary', type: 'string');
        $this->addType(fieldName: 'description', type: 'string');
        $this->addType(fieldName: 'image', type: 'string');
        $this->addType(fieldName: 'search', type: 'string');
        $this->addType(fieldName: 'listed', type: 'boolean');
        $this->addType(fieldName: 'organisation', type: 'string');
        $this->addType(fieldName: 'metadata', type: 'json');

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
        'title' => $this->title,
        'summary' => $this->summary,
        'description' => $this->description,
        'image' => $this->image,
        'search' => $this->search,
        'listed' => $this->listed,
        'metadata' => $this->metadata,
        'organisation'=> $this->organisation,

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
