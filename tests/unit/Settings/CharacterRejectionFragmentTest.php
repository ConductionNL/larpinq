<?php

/**
 * Unit tests for the character rejection in the merged register (REQ-CTA-001).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/character-management/spec.md#requirement-a-game-master-rejects-a-submitted-character-with-a-reason-req-cta-001
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The rejected state, its reason, its transitions and the notifications, as OpenRegister imports them.
 */
class CharacterRejectionFragmentTest extends TestCase {

	/**
	 * The merged character schema: monolith plus every fragment.
	 *
	 * @return array<string, mixed> The schema.
	 */
	private function character(): array {
		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		self::assertIsArray(actual: $monolith);

		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);
		$merged = $merge->invoke($reflection->newInstanceWithoutConstructor(), $monolith, $appPath);

		return $merged['components']['schemas']['character'];
	}//end character()

	/**
	 * Approval reads not yet approved, approved or rejected, and starts as not yet approved.
	 *
	 * @return void
	 */
	public function testApprovalHasARejectedState(): void {
		$approved = $this->character()['properties']['approved'];

		self::assertSame(expected: ['no', 'approved', 'rejected'], actual: $approved['enum']);
		self::assertSame(expected: 'no', actual: $approved['default']);
	}//end testApprovalHasARejectedState()

	/**
	 * The reason is text that only game masters write, read by them and the owner only.
	 *
	 * @return void
	 */
	public function testTheReasonIsWrittenByGameMastersOnly(): void {
		$reason = $this->character()['properties']['rejectionReason'];

		self::assertSame(expected: 'string', actual: $reason['type']);
		self::assertSame(expected: ['gamemasters'], actual: $reason['authorization']['update']);
		self::assertSame(
			expected: ['gamemasters', ['group' => 'larpers', 'match' => ['ownerUid' => '$userId']]],
			actual: $reason['authorization']['read']
		);
	}//end testTheReasonIsWrittenByGameMastersOnly()

	/**
	 * Game masters reject a waiting character with a required reason; a rejected one goes back to waiting.
	 *
	 * @return void
	 */
	public function testTheRejectAndResubmitTransitions(): void {
		$lifecycle = $this->character()['configuration']['x-openregister-lifecycle'];
		$transitions = $lifecycle['transitions'];

		self::assertSame(expected: 'approved', actual: $lifecycle['field']);
		self::assertSame(expected: ['no'], actual: $transitions['reject']['from']);
		self::assertSame(expected: 'rejected', actual: $transitions['reject']['to']);
		self::assertSame(expected: ['gamemasters'], actual: $transitions['reject']['authorization']);
		self::assertSame(expected: [['field' => 'rejectionReason', 'required' => true]], actual: $transitions['reject']['inputs']);
		self::assertSame(expected: ['rejected'], actual: $transitions['resubmit']['from']);
		self::assertSame(expected: 'no', actual: $transitions['resubmit']['to']);
		// Approval itself is unchanged: only a waiting character is approved.
		self::assertSame(expected: ['no'], actual: $transitions['approved']['from']);
	}//end testTheRejectAndResubmitTransitions()

	/**
	 * The owner hears of the rejection and its reason; the game masters hear of the resubmission.
	 *
	 * @return void
	 */
	public function testTheNotifications(): void {
		$notifications = $this->character()['x-openregister-notifications'];

		$rejected = $notifications['character-rejected'];
		self::assertSame(expected: ['type' => 'transition', 'action' => 'reject'], actual: $rejected['trigger']);
		self::assertSame(expected: [['kind' => 'field', 'field' => 'ownerUid']], actual: $rejected['recipients']);
		self::assertStringContainsString(needle: '{{rejectionReason}}', haystack: $rejected['subject']['en']);
		self::assertStringContainsString(needle: '{{rejectionReason}}', haystack: $rejected['subject']['nl']);

		$resubmitted = $notifications['character-resubmitted'];
		self::assertSame(expected: ['type' => 'transition', 'action' => 'resubmit'], actual: $resubmitted['trigger']);
		self::assertSame(expected: [['kind' => 'groups', 'groups' => ['gamemasters']]], actual: $resubmitted['recipients']);
		// The notifications that were there stay.
		self::assertArrayHasKey(key: 'character-approved', array: $notifications);
		self::assertArrayHasKey(key: 'character-submitted', array: $notifications);
	}//end testTheNotifications()
}//end class
