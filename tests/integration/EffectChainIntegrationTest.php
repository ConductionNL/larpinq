<?php

/**
 * Effect-chain integration test for the LarpingApp game-mechanics engine.
 *
 * Satisfies TEST-031 of the game-mechanics spec ("effect chain integrity must
 * be verified end-to-end"). Where {@see \OCA\LarpingApp\Tests\Unit\Service\GameMechanicsTest}
 * exercises one mechanic at a time, this test drives the WHOLE chain in a
 * single derivation pass: a roster of characters, each carrying skills, items,
 * conditions and events, whose linked effects fan out across several abilities
 * with mixed positive/negative modifications and mixed cumulative semantics.
 *
 * It asserts the integrity invariants that the chain must preserve:
 *   - effects compose additively across every entity type;
 *   - non-cumulative effects de-duplicate even when reached via two entities;
 *   - cumulative effects stack on every encounter;
 *   - the audit trail length matches the number of applications;
 *   - characters are computed independently (no cross-character bleed).
 *
 * LarpingApp has no local entity classes — every entity is an OpenRegister
 * object (associative array) — so the chain is fed through a mocked
 * RegisterObjectFetcher exactly as production resolves it from OpenRegister.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Integration
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Integration;

use OCA\LarpingApp\Service\CharacterService;
use OCA\LarpingApp\Service\EffectApplier;
use OCA\LarpingApp\Service\RegisterObjectFetcher;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * End-to-end effect-chain resolution across all mechanics.
 *
 * @spec openspec/changes/game-mechanics/tasks.md
 */
class EffectChainIntegrationTest extends TestCase
{

    private CharacterService $service;

    /**
     * Wire a complete world of abilities, effects and effect-bearing entities,
     * then expose it through a CharacterService backed by a mock fetcher.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $abilities = [
            ['id' => 'str', 'name' => 'Strength', 'base' => 10],
            ['id' => 'hp',  'name' => 'Health',   'base' => 20],
            ['id' => 'mag', 'name' => 'Magic',    'base' => 0],
        ];

        $effects = [
            // Cumulative buff to Strength — stacks on every encounter.
            [
                'id'           => 'eff-might',
                'name'         => 'Might',
                'modifier'     => 2,
                'modification' => 'positive',
                'cumulative'   => 'cumulative',
                'abilities'    => ['str'],
            ],
            // Non-cumulative buff to Health — applies once even via two entities.
            [
                'id'           => 'eff-vigour',
                'name'         => 'Vigour',
                'modifier'     => 5,
                'modification' => 'positive',
                'cumulative'   => 'non-cumulative',
                'abilities'    => ['hp'],
            ],
            // Negative condition on Health — the debuff path.
            [
                'id'           => 'eff-poison',
                'name'         => 'Poison',
                'modifier'     => 3,
                'modification' => 'negative',
                'abilities'    => ['hp'],
            ],
            // Event grants raw Magic via the stat_id fallback path.
            [
                'id'           => 'eff-attune',
                'name'         => 'Attunement',
                'modifier'     => 4,
                'modification' => 'positive',
                'stat_id'      => 'mag',
            ],
        ];

        // Skill and item BOTH carry the cumulative Might effect (should stack)
        // AND the non-cumulative Vigour effect (should apply once).
        $skills = [
            ['id' => 'skill-warrior', 'name' => 'Warrior', 'effects' => ['eff-might', 'eff-vigour']],
        ];
        $items = [
            ['id' => 'item-belt', 'name' => 'Belt of Might', 'effects' => ['eff-might', 'eff-vigour']],
        ];
        $conditions = [
            ['id' => 'cond-poisoned', 'name' => 'Poisoned', 'effects' => ['eff-poison']],
        ];
        $events = [
            ['id' => 'evt-ritual', 'name' => 'Attunement Ritual', 'effects' => ['eff-attune']],
        ];

        $characters = [
            // Loaded character: every entity type contributes.
            [
                'id'         => 'char-hero',
                'name'       => 'Hero',
                'skills'     => ['skill-warrior'],
                'items'      => ['item-belt'],
                'conditions' => ['cond-poisoned'],
                'events'     => ['evt-ritual'],
            ],
            // Bare character: no linked entities, pure base scores.
            [
                'id'   => 'char-rookie',
                'name' => 'Rookie',
            ],
        ];

        $fetcher = $this->createMock(RegisterObjectFetcher::class);
        $fetcher->method('getObjects')
            ->willReturnCallback(
                static function (string $type) use ($abilities, $effects, $skills, $items, $conditions, $events, $characters): array {
                    return match ($type) {
                        'ability'   => $abilities,
                        'effect'    => $effects,
                        'skill'     => $skills,
                        'item'      => $items,
                        'condition' => $conditions,
                        'event'     => $events,
                        'character' => $characters,
                        default     => [],
                    };
                }
            );

        $this->service = new CharacterService($fetcher, $this->createMock(LoggerInterface::class), new EffectApplier());
    }//end setUp()

    /**
     * The full chain resolves the loaded hero correctly across every ability.
     *
     * Strength: base 10 + Might(2) via skill + Might(2) via item = 14 (cumulative stacks).
     * Health:   base 20 + Vigour(5) once (non-cumulative, reached via skill AND item) - Poison(3) = 22.
     * Magic:    base 0  + Attunement(4) via event stat_id = 4.
     *
     * @return void
     */
    public function testFullChainDerivesLoadedCharacter(): void
    {
        $hero = $this->service->calculateCharacter(
            [
                'id'         => 'char-hero',
                'skills'     => ['skill-warrior'],
                'items'      => ['item-belt'],
                'conditions' => ['cond-poisoned'],
                'events'     => ['evt-ritual'],
            ]
        );

        self::assertSame(14, $hero['stats']['str']['value'], 'Cumulative Might must stack across skill + item');
        self::assertSame(22, $hero['stats']['hp']['value'], 'Vigour applies once; Poison subtracts');
        self::assertSame(4, $hero['stats']['mag']['value'], 'Event stat_id fallback grants Magic');
    }//end testFullChainDerivesLoadedCharacter()

    /**
     * The audit trail length reflects the actual number of applications: Might
     * applied twice (cumulative) and Vigour once (non-cumulative dedup) on the
     * two abilities they target.
     *
     * @return void
     */
    public function testChainAuditTrailReflectsApplications(): void
    {
        $hero = $this->service->calculateCharacter(
            [
                'id'         => 'char-hero',
                'skills'     => ['skill-warrior'],
                'items'      => ['item-belt'],
                'conditions' => ['cond-poisoned'],
                'events'     => ['evt-ritual'],
            ]
        );

        // Strength touched twice (cumulative Might via skill then item).
        self::assertCount(2, $hero['stats']['str']['audit']);
        // Health touched twice: Vigour once (deduped) + Poison once.
        self::assertCount(2, $hero['stats']['hp']['audit']);
        // Magic touched once (event).
        self::assertCount(1, $hero['stats']['mag']['audit']);
    }//end testChainAuditTrailReflectsApplications()

    /**
     * calculateAllCharacters resolves the whole roster and keeps characters
     * independent — the bare rookie must retain pure base scores while the
     * hero is fully derived in the same pass.
     *
     * @return void
     */
    public function testChainComputesRosterIndependently(): void
    {
        $roster = $this->service->calculateAllCharacters();

        self::assertCount(2, $roster);

        $byId = [];
        foreach ($roster as $character) {
            $byId[$character['id']] = $character;
        }

        // Hero fully derived.
        self::assertSame(14, $byId['char-hero']['stats']['str']['value']);
        self::assertSame(22, $byId['char-hero']['stats']['hp']['value']);

        // Rookie untouched — pure base scores, empty audits (no cross-bleed).
        self::assertSame(10, $byId['char-rookie']['stats']['str']['value']);
        self::assertSame(20, $byId['char-rookie']['stats']['hp']['value']);
        self::assertSame(0, $byId['char-rookie']['stats']['mag']['value']);
        self::assertSame([], $byId['char-rookie']['stats']['str']['audit']);
    }//end testChainComputesRosterIndependently()
}//end class
