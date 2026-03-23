<?php

/**
 * Unit tests for DeepLinkRegistrationListener.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Listener
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\Listener;

use OCA\LarpingApp\Listener\DeepLinkRegistrationListener;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DeepLinkRegistrationListener.
 */
class DeepLinkRegistrationListenerTest extends TestCase
{

    private DeepLinkRegistrationListener $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listener = new DeepLinkRegistrationListener();
    }

    public function testHandleIgnoresEventsWithoutRegisterDeepLink(): void
    {
        $event = $this->createMock(Event::class);

        // Should not throw — just returns early.
        $this->listener->handle($event);

        self::assertTrue(true); // No exception means success.
    }

    public function testHandleRegistersAllObjectTypes(): void
    {
        $registeredLinks = [];

        $event = new class($registeredLinks) extends Event {

            private array $links;

            public function __construct(private array &$storage)
            {
                $this->links = &$storage;
            }

            public function registerDeepLink(string $appId, string $schemaSlug, string $urlTemplate): void
            {
                $this->links[] = [
                    'appId' => $appId,
                    'slug' => $schemaSlug,
                    'url' => $urlTemplate,
                ];
            }
        };

        $this->listener->handle($event);

        self::assertCount(8, $registeredLinks);

        $slugs = array_column($registeredLinks, 'slug');
        self::assertContains('character', $slugs);
        self::assertContains('player', $slugs);
        self::assertContains('ability', $slugs);
        self::assertContains('skill', $slugs);
        self::assertContains('item', $slugs);
        self::assertContains('condition', $slugs);
        self::assertContains('effect', $slugs);
        self::assertContains('event', $slugs);
    }

    public function testHandleUsesCorrectAppId(): void
    {
        $registeredLinks = [];

        $event = new class($registeredLinks) extends Event {

            public function __construct(private array &$storage)
            {
            }

            public function registerDeepLink(string $appId, string $schemaSlug, string $urlTemplate): void
            {
                $this->storage[] = ['appId' => $appId, 'slug' => $schemaSlug, 'url' => $urlTemplate];
            }
        };

        $this->listener->handle($event);

        foreach ($registeredLinks as $link) {
            self::assertSame('larpingapp', $link['appId']);
        }
    }

    public function testHandleUsesCorrectUrlPatterns(): void
    {
        $registeredLinks = [];

        $event = new class($registeredLinks) extends Event {

            public function __construct(private array &$storage)
            {
            }

            public function registerDeepLink(string $appId, string $schemaSlug, string $urlTemplate): void
            {
                $this->storage[$schemaSlug] = $urlTemplate;
            }
        };

        $this->listener->handle($event);

        self::assertSame('/apps/larpingapp/#/characters/{uuid}', $registeredLinks['character']);
        self::assertSame('/apps/larpingapp/#/players/{uuid}', $registeredLinks['player']);
        self::assertSame('/apps/larpingapp/#/abilities/{uuid}', $registeredLinks['ability']);
        self::assertSame('/apps/larpingapp/#/skills/{uuid}', $registeredLinks['skill']);
        self::assertSame('/apps/larpingapp/#/items/{uuid}', $registeredLinks['item']);
        self::assertSame('/apps/larpingapp/#/conditions/{uuid}', $registeredLinks['condition']);
        self::assertSame('/apps/larpingapp/#/effects/{uuid}', $registeredLinks['effect']);
        self::assertSame('/apps/larpingapp/#/events/{uuid}', $registeredLinks['event']);
    }
}
