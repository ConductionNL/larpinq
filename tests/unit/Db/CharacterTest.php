<?php
declare(strict_types=1);
namespace OCA\LarpingApp\Tests\Unit\Db;

use OCA\LarpingApp\Db\Character;
use PHPUnit\Framework\TestCase;

class CharacterTest extends TestCase
{
    public function testJsonSerializeIncludesRequiredFields(): void
    {
        $character = new Character();
        $character->setName('Sir Lancelot');
        $character->setDescription('A noble knight');

        $json = $character->jsonSerialize();

        self::assertArrayHasKey('id', $json);
        self::assertSame('Sir Lancelot', $json['name']);
    }

    public function testInternalEntityIsSkeletal(): void
    {
        $character = new Character();
        $fields = $character->getJsonFields();

        self::assertCount(3, $fields);
        self::assertContains('id', $fields);
        self::assertContains('name', $fields);
        self::assertContains('description', $fields);
    }

    public function testInternalEntityLacksFullCharacterFields(): void
    {
        $character = new Character();
        $fields = $character->getJsonFields();

        // Full character fields only exist in OpenRegister mode.
        self::assertNotContains('skills', $fields);
        self::assertNotContains('items', $fields);
        self::assertNotContains('conditions', $fields);
        self::assertNotContains('stats', $fields);
        self::assertNotContains('gold', $fields);
    }
}
