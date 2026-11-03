<?php

/**
 * Unit tests for DeepLinkRegistrationListener.
 *
 * ⚠️ WHY THIS FILE WAS REWRITTEN
 * Every fake below used to declare
 * `registerDeepLink(string $appId, string $schemaSlug, string $urlTemplate)`.
 * `OCA\OpenRegister\Event\DeepLinkRegistrationEvent` has never had such a
 * method — its API is
 * `register(string $appId, string $registerSlug, string $schemaSlug, string $urlTemplate, string $icon = '', ?string $displayName = null)`.
 * The listener's own guard probed `method_exists($event, 'registerDeepLink')`,
 * which is false against the real class, so in production the listener returned
 * immediately and Larpinq registered ZERO deep links — while this suite went
 * green against an API that existed nowhere but in this file.
 *
 * The fakes below therefore mirror the REAL signature exactly. If OpenRegister
 * changes it, these tests must fail; that is the whole point of them.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\DeepLinkRegistrationListener;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\TestCase;

/**
 * Recording stand-in for OpenRegister's DeepLinkRegistrationEvent.
 *
 * Declares `register()` with the real class's exact signature (including the
 * `$registerSlug` argument the old fakes omitted) and records every call.
 */
class RecordingDeepLinkEvent extends Event {

	/**
	 * Every registration this event received, in order.
	 *
	 * @var array<int, array<string, string|null>>
	 */
	public array $links = [];

	/**
	 * Record a deep-link registration.
	 *
	 * @param string $appId The consuming app id.
	 * @param string $registerSlug The register slug.
	 * @param string $schemaSlug The schema slug.
	 * @param string $urlTemplate The URL template.
	 * @param string $icon Optional icon identifier.
	 * @param string|null $displayName Optional display name.
	 *
	 * @return void
	 */
	public function register(
		string $appId,
		string $registerSlug,
		string $schemaSlug,
		string $urlTemplate,
		string $icon = '',
		?string $displayName = null,
	): void {
		$this->links[] = [
			'appId' => $appId,
			'registerSlug' => $registerSlug,
			'schemaSlug' => $schemaSlug,
			'urlTemplate' => $urlTemplate,
			'icon' => $icon,
			'displayName' => $displayName,
		];
	}//end register()
}//end class

/**
 * Tests for DeepLinkRegistrationListener.
 */
class DeepLinkRegistrationListenerTest extends TestCase {

	/** @var DeepLinkRegistrationListener The listener. */
	private DeepLinkRegistrationListener $listener;

	/**
	 * Set up the listener under test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->listener = new DeepLinkRegistrationListener();
	}//end setUp()

	/**
	 * An event that does not expose `register()` is ignored, not fatal.
	 *
	 * This is the graceful-degradation contract for an OpenRegister release
	 * that predates the deep-link registry.
	 *
	 * @return void
	 */
	public function testHandleIgnoresAnEventWithoutRegister(): void {
		$event = new class extends Event {
		};

		self::assertFalse(
			condition: method_exists($event, 'register'),
			message: 'control: the bare Event must not expose register()'
		);

		$this->listener->handle($event);

		self::assertTrue(condition: true, message: 'returning early must not throw');
	}//end testHandleIgnoresAnEventWithoutRegister()

	/**
	 * All eight object types are registered, under their REAL schema slugs.
	 *
	 * @return void
	 */
	public function testHandleRegistersAllObjectTypes(): void {
		$event = new RecordingDeepLinkEvent();
		$this->listener->handle($event);

		self::assertCount(expectedCount: 8, haystack: $event->links);

		$slugs = array_column($event->links, 'schemaSlug');
		self::assertContains(needle: 'character', haystack: $slugs);
		self::assertContains(needle: 'player', haystack: $slugs);
		self::assertContains(needle: 'ability', haystack: $slugs);
		self::assertContains(needle: 'skill', haystack: $slugs);
		self::assertContains(needle: 'condition', haystack: $slugs);
		self::assertContains(needle: 'effect', haystack: $slugs);

		// The namespaced slugs — `item` and `event` collide instance-globally,
		// so Larpinq's own schemas are `larping_item` / `larping_event`.
		// Registering the bare spelling resolves to no schema at all.
		self::assertContains(needle: 'larping_item', haystack: $slugs);
		self::assertContains(needle: 'larping_event', haystack: $slugs);
		self::assertNotContains(needle: 'item', haystack: $slugs);
		self::assertNotContains(needle: 'event', haystack: $slugs);
	}//end testHandleRegistersAllObjectTypes()

	/**
	 * Every registration carries the app id AND the register slug.
	 *
	 * The register slug is the argument the previous (non-existent) three-arg
	 * `registerDeepLink()` call had no room for.
	 *
	 * @return void
	 */
	public function testHandleUsesCorrectAppIdAndRegisterSlug(): void {
		$event = new RecordingDeepLinkEvent();
		$this->listener->handle($event);

		self::assertNotEmpty(actual: $event->links, message: 'control: something must have been registered');
		foreach ($event->links as $link) {
			self::assertSame(expected: 'larpinq', actual: $link['appId']);
			self::assertSame(expected: 'larpinq', actual: $link['registerSlug']);
		}
	}//end testHandleUsesCorrectAppIdAndRegisterSlug()

	/**
	 * Each schema slug maps to its Larpinq frontend route.
	 *
	 * @return void
	 */
	public function testHandleUsesCorrectUrlPatterns(): void {
		$event = new RecordingDeepLinkEvent();
		$this->listener->handle($event);

		$bySlug = array_column($event->links, 'urlTemplate', 'schemaSlug');

		self::assertSame(expected: '/apps/larpinq/characters/{uuid}', actual: $bySlug['character']);
		self::assertSame(expected: '/apps/larpinq/players/{uuid}', actual: $bySlug['player']);
		self::assertSame(expected: '/apps/larpinq/abilities/{uuid}', actual: $bySlug['ability']);
		self::assertSame(expected: '/apps/larpinq/skills/{uuid}', actual: $bySlug['skill']);
		self::assertSame(expected: '/apps/larpinq/items/{uuid}', actual: $bySlug['larping_item']);
		self::assertSame(expected: '/apps/larpinq/conditions/{uuid}', actual: $bySlug['condition']);
		self::assertSame(expected: '/apps/larpinq/effects/{uuid}', actual: $bySlug['effect']);
		self::assertSame(expected: '/apps/larpinq/events/{uuid}', actual: $bySlug['larping_event']);
	}//end testHandleUsesCorrectUrlPatterns()
}//end class
