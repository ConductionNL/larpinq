<?php
declare(strict_types=1);
namespace OCA\LarpingApp\Tests\Unit\Db;

use OCA\LarpingApp\Db\Effect;
use PHPUnit\Framework\TestCase;

class EffectTest extends TestCase
{
    public function testJsonSerializeIncludesRequiredFields(): void
    {
        $effect = new Effect();
        $effect->setName('Fireball');
        $effect->setDescription('Deals fire damage');

        $json = $effect->jsonSerialize();

        self::assertArrayHasKey('id', $json);
        self::assertArrayHasKey('name', $json);
        self::assertArrayHasKey('description', $json);
        self::assertSame('Fireball', $json['name']);
    }

    public function testInternalEntityLacksModifierField(): void
    {
        $effect = new Effect();
        $fields = $effect->getJsonFields();

        // Documented: internal entity only has id, name, description.
        self::assertNotContains('modifier', $fields);
        self::assertNotContains('modification', $fields);
        self::assertNotContains('abilities', $fields);
    }

    public function testHydratePopulatesFields(): void
    {
        $effect = new Effect();
        $effect->hydrate(['name' => 'Heal', 'description' => 'Restore HP']);

        self::assertSame('Heal', $effect->getName());
    }
}
