<?php
declare(strict_types=1);
namespace OCA\LarpingApp\Tests\Unit\Db;

use OCA\LarpingApp\Db\Event;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    public function testEventUsesTitleNotName(): void
    {
        $e = new Event();
        $e->setTitle('Summer LARP');
        $j = $e->jsonSerialize();
        self::assertArrayHasKey('title', $j);
        self::assertArrayNotHasKey('name', $j);
        self::assertSame('Summer LARP', $j['title']);
    }

    public function testJsonSerializeFields(): void
    {
        $j = (new Event())->jsonSerialize();
        foreach (['id', 'title', 'description', 'startDate', 'endDate', 'userId'] as $f) {
            self::assertArrayHasKey($f, $j);
        }
        self::assertCount(6, $j);
    }

    public function testGetJsonFields(): void
    {
        self::assertSame(['id', 'title', 'description', 'startDate', 'endDate', 'userId'], (new Event())->getJsonFields());
    }

    public function testHydrate(): void
    {
        $e = new Event();
        $e->hydrate(['title' => 'Winter', 'description' => 'Cold', 'userId' => 'admin']);
        $j = $e->jsonSerialize();
        self::assertSame('Winter', $j['title']);
        self::assertSame('Cold', $j['description']);
        self::assertSame('admin', $j['userId']);
    }

    public function testUserIdProperty(): void
    {
        $e = new Event();
        $e->setUserId('p1');
        self::assertSame('p1', $e->jsonSerialize()['userId']);
    }

    public function testToArrayMatchesJsonSerialize(): void
    {
        $e = new Event();
        $e->setTitle('Test');
        self::assertSame($e->jsonSerialize(), $e->toArray());
    }

    public function testDefaultsAreNull(): void
    {
        $j = (new Event())->jsonSerialize();
        self::assertNull($j['title']);
        self::assertNull($j['description']);
        self::assertNull($j['startDate']);
        self::assertNull($j['endDate']);
        self::assertNull($j['userId']);
    }
}
