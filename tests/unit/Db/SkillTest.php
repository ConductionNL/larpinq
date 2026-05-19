<?php
declare(strict_types=1);
namespace OCA\LarpingApp\Tests\Unit\Db;

use OCA\LarpingApp\Db\Skill;
use PHPUnit\Framework\TestCase;

class SkillTest extends TestCase
{
    public function testJsonSerializeIncludesRequiredFields(): void
    {
        $skill = new Skill();
        $skill->setName('Healing');
        $skill->setDescription('Restore HP');

        $json = $skill->jsonSerialize();

        self::assertArrayHasKey('id', $json);
        self::assertSame('Healing', $json['name']);
    }

    public function testInternalEntityLacksEffectsField(): void
    {
        $skill = new Skill();
        $fields = $skill->getJsonFields();

        self::assertNotContains('effects', $fields);
        self::assertNotContains('requiredSkills', $fields);
    }
}
