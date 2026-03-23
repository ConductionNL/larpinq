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
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link      https://larpingapp.com
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
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 *
 * @template-implements IEventListener<Event>
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
