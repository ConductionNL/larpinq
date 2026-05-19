<?php
declare(strict_types=1);
namespace OCA\LarpingApp\Tests\Unit\Db;

use OCA\LarpingApp\Db\Condition;
use PHPUnit\Framework\TestCase;

class ConditionTest extends TestCase
{
    public function testJsonSerializeIncludesRequiredFields(): void
    {
        $condition = new Condition();
        $condition->setName('Poisoned');
        $condition->setDescription('Losing HP');

        $json = $condition->jsonSerialize();

        self::assertArrayHasKey('id', $json);
        self::assertSame('Poisoned', $json['name']);
    }

    public function testInternalEntityLacksEffectsAndUniqueFields(): void
    {
        $condition = new Condition();
        $fields = $condition->getJsonFields();

        self::assertNotContains('effects', $fields);
        self::assertNotContains('unique', $fields);
    }
}
