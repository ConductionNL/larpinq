<?php
declare(strict_types=1);
namespace OCA\LarpingApp\Tests\Unit\Db;

use OCA\LarpingApp\Db\Player;
use PHPUnit\Framework\TestCase;

class PlayerTest extends TestCase
{
    public function testJsonSerializeFields(): void
    {
        $j = (new Player())->jsonSerialize();
        foreach (['id', 'name', 'description'] as $f) {
            self::assertArrayHasKey($f, $j);
        }
        self::assertCount(3, $j);
    }

    public function testGetJsonFields(): void
    {
        self::assertSame(['id', 'name', 'description'], (new Player())->getJsonFields());
    }

    public function testNameSetAndGet(): void
    {
        $p = new Player();
        $p->setName('John Doe');
        self::assertSame('John Doe', $p->getName());
        self::assertSame('John Doe', $p->jsonSerialize()['name']);
    }

    public function testDescriptionSetAndGet(): void
    {
        $p = new Player();
        $p->setDescription('Experienced');
        self::assertSame('Experienced', $p->getDescription());
    }

    public function testHydrate(): void
    {
        $p = new Player();
        $p->hydrate(['name' => 'Alice', 'description' => 'New']);
        $j = $p->jsonSerialize();
        self::assertSame('Alice', $j['name']);
        self::assertSame('New', $j['description']);
    }

    public function testDefaultsAreNull(): void
    {
        $j = (new Player())->jsonSerialize();
        self::assertNull($j['name']);
        self::assertNull($j['description']);
    }
}
