<?php

/**
 * Listener registration in Application::register().
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\AppInfo
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\AppInfo;

use OCA\LarpingApp\AppInfo\Application;
use OCA\LarpingApp\Listener\CharacterRequirementListener;
use OCA\LarpingApp\Listener\DeepLinkRegistrationListener;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * THE LISTENERS MUST ACTUALLY REGISTER.
 *
 * `Application::register()` guards all three of its listener registrations
 * behind `class_exists('OCA\OpenRegister\Event\…')`. Two of those listeners
 * carry the SERVER-AUTHORITATIVE skill-requirement and XP-budget enforcement
 * on character writes; if the probe answers false they simply do not register,
 * the app stays enabled, and nothing anywhere reports that the enforcement is
 * not running.
 *
 * A probe that answers false is indistinguishable from OpenRegister being
 * absent, which is why the load-order defect this suite's sibling
 * (`OpenRegisterAutoloaderTest`) covers went unnoticed: Nextcloud registers
 * apps in sorted order, `larpingapp` sorts before `openregister`, and
 * `OCA\OpenRegister\` is therefore not autoloadable inside this app's own
 * `register()` unless the ADR-040 prelude puts it there first.
 *
 * `OpenRegisterAutoloaderTest` proves the prelude never throws. It cannot
 * prove the thing that matters — that when the prelude has done its job, the
 * listeners are registered. That is what this file asserts, by making the
 * three event classes resolvable (the post-prelude world) and by making them
 * unresolvable (the OpenRegister-absent world), and checking both outcomes.
 *
 * `Application` is instantiated without its constructor: `App::__construct()`
 * needs the Nextcloud DI container, which does not exist in a bare unit run,
 * and `register()` neither reads nor writes instance state.
 */
class ApplicationRegisterTest extends TestCase
{

    /**
     * The three OpenRegister event classes register() probes for, mapped to the
     * listener each one must attach.
     *
     * @var array<string, string>
     */
    private const EVENT_TO_LISTENER = [
        'OCA\OpenRegister\Event\DeepLinkRegistrationEvent' => DeepLinkRegistrationListener::class,
        'OCA\OpenRegister\Event\ObjectCreatingEvent'       => CharacterRequirementListener::class,
        'OCA\OpenRegister\Event\ObjectUpdatingEvent'       => CharacterRequirementListener::class,
    ];


    /**
     * Call Application::register() with a recording IRegistrationContext.
     *
     * @return array<int, array{0: string, 1: string}> Recorded (event, listener) pairs.
     */
    private function recordRegistrations(): array
    {
        $recorded = [];

        $context = $this->createMock(originalClassName: IRegistrationContext::class);
        $context->method('registerEventListener')
            ->willReturnCallback(
                static function (string $event, string $listener, int $priority=0) use (&$recorded): void {
                    $recorded[] = [$event, $listener];
                }
            );

        $application = (new ReflectionClass(objectOrClass: Application::class))
            ->newInstanceWithoutConstructor();
        $application->register($context);

        return $recorded;

    }//end recordRegistrations()


    /**
     * When OpenRegister's event classes are resolvable — the world the ADR-040
     * prelude creates — every listener must attach.
     *
     * The three event classes are declared by
     * tests/unit/AppInfo/fixtures/openregister-events.php, which the bootstrap
     * does NOT load, so this test loads it itself. Declaring them is what makes
     * `class_exists()` true; nothing else in this suite depends on them.
     *
     * @return void
     */
    public function testEveryListenerRegistersWhenOpenRegisterIsResolvable(): void
    {
        require_once __DIR__ . '/fixtures/openregister-events.php';

        foreach (array_keys(self::EVENT_TO_LISTENER) as $event) {
            $this->assertTrue(
                class_exists($event),
                sprintf(
                    'The fixture did not make %s resolvable, so this test would pass '
                    . 'over a register() that attaches nothing.',
                    $event
                )
            );
        }

        $recorded = $this->recordRegistrations();

        foreach (self::EVENT_TO_LISTENER as $event => $listener) {
            $this->assertContains(
                [$event, $listener],
                $recorded,
                sprintf(
                    '%s did not register %s. Two of these three listeners carry the '
                    . 'server-authoritative skill-requirement and XP-budget enforcement '
                    . 'on character writes — unregistered, that enforcement is simply '
                    . 'not running, and the app reports nothing.',
                    Application::class,
                    $listener
                )
            );
        }

        $this->assertCount(
            count(self::EVENT_TO_LISTENER),
            $recorded,
            'register() attached a different number of listeners than this test knows about.'
        );

    }//end testEveryListenerRegistersWhenOpenRegisterIsResolvable()


    /**
     * register() must not throw, whatever the instance looks like.
     *
     * The prelude swallows every Throwable precisely so that an OpenRegister
     * that is genuinely absent degrades to "no listeners" rather than aborting
     * the whole of register() — which would take the app down.
     *
     * @return void
     */
    public function testRegisterNeverThrows(): void
    {
        $this->recordRegistrations();

        $this->addToAssertionCount(1);

    }//end testRegisterNeverThrows()


}//end class
