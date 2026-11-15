<?php

/**
 * Unit tests for CharacterStatsPresenter.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\CharacterStatsPresenter;
use PHPUnit\Framework\TestCase;

/**
 * The stat sheet's abilities with their modifiers, and the experience line.
 *
 * @covers \OCA\Larpinq\Service\CharacterStatsPresenter
 */
class CharacterStatsPresenterTest extends TestCase {

	/**
	 * The presenter.
	 *
	 * @var CharacterStatsPresenter
	 */
	private CharacterStatsPresenter $presenter;

	/**
	 * Set up the subject and its collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->presenter = new CharacterStatsPresenter();
	}

	/**
	 * No stats give no abilities and no experience line.
	 *
	 * @return void
	 */
	public function testNoStatsGiveNoAbilitiesAndNoExperienceLine(): void {
		$this->assertSame(expected: ['abilities' => [], 'xp' => null], actual: $this->presenter->present(stats: [], xpAbilityId: 'xp'));
	}

	/**
	 * An ability is cast and defaulted.
	 *
	 * @return void
	 */
	public function testAnAbilityIsCastAndDefaulted(): void {
		$result = $this->presenter->present(stats: [42 => ['name' => 'Strength', 'base' => '3', 'value' => '5'], 'agi' => []], xpAbilityId: null);

		$this->assertSame(
			expected: [
				['id' => '42', 'name' => 'Strength', 'base' => 3, 'final' => 5, 'modifiers' => []],
				['id' => 'agi', 'name' => '', 'base' => 0, 'final' => 0, 'modifiers' => []],
			],
			actual: $result['abilities']
		);
		$this->assertNull(actual: $result['xp']);
	}

	/**
	 * An effect modifier names its source and change.
	 *
	 * @return void
	 */
	public function testAnEffectModifierNamesItsSourceAndChange(): void {
		$stats = [
			'str' => [
				'name' => 'Strength',
				'base' => 2,
				'value' => 4,
				'audit' => [
					['source' => 'item', 'sourceId' => 'i1', 'sourceName' => 'Belt', 'effectName' => 'Mighty', 'old' => 2, 'new' => 5],
					['source' => 'condition', 'sourceId' => 'c1', 'sourceName' => 'Tired', 'effectName' => 'Weak', 'old' => '5', 'new' => '4'],
				],
			],
		];

		$this->assertSame(
			expected: [
				['source' => 'item', 'sourceId' => 'i1', 'sourceName' => 'Belt', 'effectName' => 'Mighty', 'change' => 3, 'old' => 2, 'new' => 5],
				['source' => 'condition', 'sourceId' => 'c1', 'sourceName' => 'Tired', 'effectName' => 'Weak', 'change' => -1, 'old' => 5, 'new' => 4],
			],
			actual: $this->presenter->present(stats: $stats, xpAbilityId: null)['abilities'][0]['modifiers']
		);
	}

	/**
	 * An experience award names the award and its reason.
	 *
	 * @return void
	 */
	public function testAnExperienceAwardNamesTheAwardAndItsReason(): void {
		$stats = ['xp' => ['audit' => [['type' => 'xpAward', 'award' => ['id' => 'a1', 'reason' => 'Event 3'], 'old' => 0, 'new' => 10]]]];

		$this->assertSame(
			expected: [['source' => 'xpAward', 'sourceId' => 'a1', 'sourceName' => 'Event 3', 'effectName' => '', 'change' => 10, 'old' => 0, 'new' => 10]],
			actual: $this->presenter->present(stats: $stats, xpAbilityId: null)['abilities'][0]['modifiers']
		);
	}

	/**
	 * An award without details and an entry without fields are empty strings.
	 *
	 * @return void
	 */
	public function testAnAwardWithoutDetailsAndAnEntryWithoutFieldsAreEmptyStrings(): void {
		$stats = ['xp' => ['audit' => [['type' => 'xpAward'], []]]];

		$this->assertSame(
			expected: [
				['source' => 'xpAward', 'sourceId' => '', 'sourceName' => '', 'effectName' => '', 'change' => 0, 'old' => 0, 'new' => 0],
				['source' => '', 'sourceId' => '', 'sourceName' => '', 'effectName' => '', 'change' => 0, 'old' => 0, 'new' => 0],
			],
			actual: $this->presenter->present(stats: $stats, xpAbilityId: null)['abilities'][0]['modifiers']
		);
	}

	/**
	 * Audit entries that are no arrays are skipped.
	 *
	 * @return void
	 */
	public function testAuditEntriesThatAreNoArraysAreSkipped(): void {
		$stats = ['str' => ['audit' => ['junk', 7, null, ['source' => 'item', 'old' => 1, 'new' => 2]]]];

		$modifiers = $this->presenter->present(stats: $stats, xpAbilityId: null)['abilities'][0]['modifiers'];
		$this->assertCount(expectedCount: 1, haystack: $modifiers);
		$this->assertSame(expected: 'item', actual: $modifiers[0]['source']);
	}

	/**
	 * The experience line adds gains to the base and counts losses as spent.
	 *
	 * @return void
	 */
	public function testTheExperienceLineAddsGainsToTheBaseAndCountsLossesAsSpent(): void {
		$stats = [
			'str' => ['base' => 1, 'value' => 1],
			'xp' => [
				'name' => 'Experience',
				'base' => 5,
				'value' => 12,
				'audit' => [
					['type' => 'xpAward', 'award' => ['id' => 'a1'], 'old' => 5, 'new' => 15],
					['source' => 'skill', 'old' => 15, 'new' => 12],
					['source' => 'skill', 'old' => 12, 'new' => 12],
					['type' => 'xpAward', 'award' => ['id' => 'a2'], 'old' => 12, 'new' => 14],
					['source' => 'skill', 'old' => 14, 'new' => 12],
				],
			],
		];

		$this->assertSame(
			expected: ['ability' => 'xp', 'earned' => 17, 'spent' => 5, 'left' => 12],
			actual: $this->presenter->present(stats: $stats, xpAbilityId: 'xp')['xp']
		);
	}

	/**
	 * There is no experience line for an ability the sheet lacks.
	 *
	 * @return void
	 */
	public function testThereIsNoExperienceLineForAnAbilityTheSheetLacks(): void {
		$this->assertNull(actual: $this->presenter->present(stats: ['str' => ['base' => 1]], xpAbilityId: 'xp')['xp']);
	}

	/**
	 * A numeric experience ability id matches its string form.
	 *
	 * @return void
	 */
	public function testANumericExperienceAbilityIdMatchesItsStringForm(): void {
		$line = $this->presenter->present(stats: [7 => ['base' => 2, 'value' => 2]], xpAbilityId: '7')['xp'];

		$this->assertSame(expected: ['ability' => '7', 'earned' => 2, 'spent' => 0, 'left' => 2], actual: $line);
	}
}
