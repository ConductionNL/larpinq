<?php
declare(strict_types=1);
namespace OCA\LarpingApp\Tests\Unit\Db;

use OCA\LarpingApp\Db\Ability;
use PHPUnit\Framework\TestCase;

class AbilityTest extends TestCase
{
    public function testJsonSerializeIncludesRequiredFields(): void
    {
        $ability = new Ability();
        $ability->setName('Strength');
        $ability->setDescription('Physical power');

        $json = $ability->jsonSerialize();

        self::assertArrayHasKey('id', $json);
        self::assertArrayHasKey('name', $json);
        self::assertArrayHasKey('description', $json);
        self::assertSame('Strength', $json['name']);
        self::assertSame('Physical power', $json['description']);
    }

    public function testGetJsonFieldsReturnsExpectedFields(): void
    {
        $ability = new Ability();
        $fields = $ability->getJsonFields();

        self::assertContains('id', $fields);
        self::assertContains('name', $fields);
        self::assertContains('description', $fields);
    }

    public function testHydratePopulatesFields(): void
    {
        $ability = new Ability();
        $ability->hydrate(['name' => 'Mana', 'description' => 'Magic power']);

        self::assertSame('Mana', $ability->getName());
        self::assertSame('Magic power', $ability->getDescription());
    }

    public function testInternalEntityLacksBaseField(): void
    {
        $ability = new Ability();
        $fields = $ability->getJsonFields();

        // Documented bug MECH-010: base is NOT in getJsonFields() for internal entity.
        self::assertNotContains('base', $fields);
    }
}
