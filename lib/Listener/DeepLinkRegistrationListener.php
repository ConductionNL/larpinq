<?php

/**
 * Deep link registration listener for Larpinq.
 *
 * Registers URL patterns with OpenRegister's unified search provider
 * so that Larpinq objects link directly to Larpinq detail views.
 *
 * @category  Listener
 * @package   OCA\Larpinq\Listener
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-2
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-6
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-7
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-8
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-9
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-10
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-11
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-12
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-13
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-14
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-15
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Registers deep link URL patterns for all Larpinq object types.
 *
 * Listens for OpenRegister's DeepLinkRegistrationEvent and registers
 * URL templates so that unified search results link to Larpinq views.
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-2
 */
class DeepLinkRegistrationListener implements IEventListener {

	/**
	 * Register slug Larpinq's schemas live in.
	 *
	 * `DeepLinkRegistrationEvent::register()` takes the register slug as its
	 * second argument; a deep link registered without it cannot be resolved.
	 *
	 * ⚠️ FROZEN ON THE OLD APP ID, DELIBERATELY. This is the OpenRegister
	 * REGISTER SLUG, not the app id. OpenRegister matches an existing register
	 * by slug, so renaming it to `larpinq` would not rename the register — it
	 * would fail to find the one holding every existing character, player,
	 * item and event, and the importer would create a fresh EMPTY register
	 * beside it. Nothing errors; the data simply stops being visible.
	 * The slug is internal and no user ever sees it. Do not "finish the job".
	 *
	 * @var string
	 */
	private const REGISTER_SLUG = 'larpinq';

	/**
	 * URL templates for each object type.
	 *
	 * Maps schema slugs to their Larpinq frontend routes.
	 *
	 * ⚠️ The keys MUST be the real, INSTANCE-GLOBAL schema slugs, not the bare
	 * type names. `item` and `event` collide across the fleet, so Larpinq's
	 * own schemas are namespaced `larping_item` / `larping_event` (see
	 * `lib/Settings/larpinq_register.json` and the `manifest-namespaced-
	 * schema-slugs` change). The bare spellings previously used here matched no
	 * schema on any instance.
	 *
	 * @var array<string, string>
	 */
	private const DEEP_LINK_MAP = [
		'character' => '/apps/larpinq/#/characters/{uuid}',
		'player' => '/apps/larpinq/#/players/{uuid}',
		'ability' => '/apps/larpinq/#/abilities/{uuid}',
		'skill' => '/apps/larpinq/#/skills/{uuid}',
		'larping_item' => '/apps/larpinq/#/items/{uuid}',
		'condition' => '/apps/larpinq/#/conditions/{uuid}',
		'effect' => '/apps/larpinq/#/effects/{uuid}',
		'larping_event' => '/apps/larpinq/#/events/{uuid}',
	];

	/**
	 * Handle the deep link registration event.
	 *
	 * @param Event $event The event to handle.
	 *
	 * @return void
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister event is an optional dependency.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-2
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-6
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-7
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-8
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-9
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-10
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-11
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-12
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-13
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-14
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-15
	 */
	public function handle(Event $event): void {
		// Only handle DeepLinkRegistrationEvent from OpenRegister.
		//
		// ⚠️ THE GUARD USED TO NAME A METHOD THAT DOES NOT EXIST.
		// It probed `registerDeepLink` — `DeepLinkRegistrationEvent` has never
		// declared such a method; the API is `register()`. So the guard was
		// false on every dispatch, this listener returned immediately, and
		// Larpinq registered ZERO deep links while its registration looked
		// healthy. Had the guard been dropped without renaming the call, the
		// body would have fatalled with "Call to undefined method" instead —
		// the guard was the only thing hiding a broken call. Same family as the
		// `method_exists()` probe in CharacterRequirementListener (#308): a
		// defensive guard that silently disables the feature it guards.
		//
		// `register()` is a declared method, so `method_exists()` is a correct
		// probe HERE (unlike a magic Entity getter).
		if (method_exists($event, 'register') === false) {
			return;
		}

		foreach (self::DEEP_LINK_MAP as $schemaSlug => $urlTemplate) {
			$event->register(
				// First argument is the APP ID (it moves); the second is the
				// register slug (frozen above). They are different identifiers
				// that happened to share a spelling before the rename.
				'larpinq',
				self::REGISTER_SLUG,
				$schemaSlug,
				$urlTemplate
			);
		}
	}//end handle()
}//end class
