<?php

/**
 * Deep link registration listener for LarpingApp.
 *
 * Registers URL patterns with OpenRegister's unified search provider
 * so that LarpingApp objects link directly to LarpingApp detail views.
 *
 * @category  Listener
 * @package   OCA\LarpingApp\Listener
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

namespace OCA\LarpingApp\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Registers deep link URL patterns for all LarpingApp object types.
 *
 * Listens for OpenRegister's DeepLinkRegistrationEvent and registers
 * URL templates so that unified search results link to LarpingApp views.
 *
 * @category Listener
 * @package  OCA\LarpingApp\Listener
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-2
 */
class DeepLinkRegistrationListener implements IEventListener
{

    /**
     * URL templates for each object type.
     *
     * Maps schema slugs to their LarpingApp frontend routes.
     *
     * @var array<string, string>
     */
    private const DEEP_LINK_MAP = [
        'character' => '/apps/larpingapp/#/characters/{uuid}',
        'player'    => '/apps/larpingapp/#/players/{uuid}',
        'ability'   => '/apps/larpingapp/#/abilities/{uuid}',
        'skill'     => '/apps/larpingapp/#/skills/{uuid}',
        'item'      => '/apps/larpingapp/#/items/{uuid}',
        'condition' => '/apps/larpingapp/#/conditions/{uuid}',
        'effect'    => '/apps/larpingapp/#/effects/{uuid}',
        'event'     => '/apps/larpingapp/#/events/{uuid}',
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
    public function handle(Event $event): void
    {
        // Only handle DeepLinkRegistrationEvent from OpenRegister.
        if (method_exists($event, 'registerDeepLink') === false) {
            return;
        }

        foreach (self::DEEP_LINK_MAP as $schemaSlug => $urlTemplate) {
            $event->registerDeepLink('larpingapp', $schemaSlug, $urlTemplate);
        }
    }//end handle()
}//end class
