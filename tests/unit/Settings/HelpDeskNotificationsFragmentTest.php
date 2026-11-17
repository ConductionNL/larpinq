<?php

/**
 * The help desk's declared notifications match how the question's status moves.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-game-masters-answer-from-one-list-req-chd-002
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Listener\HelpMessageListener;
use PHPUnit\Framework\TestCase;

/**
 * HelpMessageListener moves a question to answered with a plain save, not
 * through the lifecycle's `answer` transition, so OpenRegister dispatches an
 * update and never a transition event for it. The asker's notification has to
 * fire on that update.
 */
class HelpDeskNotificationsFragmentTest extends TestCase {

	/**
	 * The help question schema as the fragment declares it.
	 *
	 * @return array<string, mixed>
	 */
	private function question(): array {
		$fragment = json_decode((string)file_get_contents(dirname(__DIR__, 3) . '/lib/Settings/register.d/communication-help-desk.json'), true);

		return $fragment['components']['schemas']['helpQuestion'];
	}//end question()

	/**
	 * The asker is notified when an update moves the question from open to answered.
	 *
	 * @return void
	 */
	public function testTheAnswerNotificationFiresOnTheStatusUpdate(): void {
		$rule = $this->question()['x-openregister-notifications']['question-answered'];

		$this->assertSame(expected: 'updated', actual: $rule['trigger']['type']);
		$this->assertSame(
			expected: ['field' => 'status', 'operator' => 'equals', 'from' => 'open', 'value' => 'answered'],
			actual: $rule['trigger']['condition']
		);
		$this->assertSame(expected: [['kind' => 'field', 'field' => 'askerUid']], actual: $rule['recipients']);
	}//end testTheAnswerNotificationFiresOnTheStatusUpdate()

	/**
	 * The condition names the move the listener makes when a game master writes.
	 *
	 * @return void
	 */
	public function testTheConditionMatchesTheListenersMove(): void {
		$condition = $this->question()['x-openregister-notifications']['question-answered']['trigger']['condition'];

		$this->assertSame(expected: $condition['value'], actual: HelpMessageListener::statusAfter(status: $condition['from'], fromOrganiser: true));
	}//end testTheConditionMatchesTheListenersMove()
}//end class
