<?php
declare(strict_types=1);
namespace OCA\LarpingApp\Tests\Unit\Db;

use OCA\LarpingApp\Db\Item;
use PHPUnit\Framework\TestCase;

class ItemTest extends TestCase
{
    public function testJsonSerializeIncludesRequiredFields(): void
    {
        $item = new Item();
        $item->setName('Sword');
        $item->setDescription('A sharp blade');

        $json = $item->jsonSerialize();

        self::assertArrayHasKey('id', $json);
        self::assertSame('Sword', $json['name']);
    }

    public function testInternalEntityLacksEffectsAndUniqueFields(): void
    {
        $item = new Item();
        $fields = $item->getJsonFields();

        self::assertNotContains('effects', $fields);
        self::assertNotContains('unique', $fields);
        self::assertNotContains('characters', $fields);
    }
}
