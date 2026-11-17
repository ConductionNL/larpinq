<?php

/**
 * Contract of EventFeedbackService
 *
 * The public surface of EventFeedbackService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category  Kernel
 * @package   OCA\Larpinq\Kernel\Contracts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Kernel\Contracts;

use DateTimeImmutable;

/**
 * Contract of EventFeedbackService.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface EventFeedbackServiceInterface {

	/**
	 * Invite and remind for every event whose feedback is open at $now.
	 *
	 * @param DateTimeImmutable $now The moment of the run.
	 *
	 * @return array{events: int, invited: int, reminded: int}
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-participants-who-were-there-are-invited-once-req-eps-002
	 */
	public function daily(DateTimeImmutable $now): array;

	/**
	 * An event by id, read with the app's authority, or null when it does not exist.
	 *
	 * @param string $id The event id.
	 *
	 * @return array|null The event.
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-game-masters-see-who-answered-req-eps-004
	 */
	public function event(string $id): ?array;

	/**
	 * Who was invited to give feedback on an event and who answered, for game masters (REQ-EPS-004).
	 *
	 * `answered` and `notAnswered` are null when Forms cannot tell who
	 * submitted: the page then shows the invitations only.
	 *
	 * @param array $event The event.
	 *
	 * @return array<string, mixed> formId, opensAt, closesAt, invited, answered (int|null) and notAnswered (list of uid and character, or null).
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-game-masters-see-who-answered-req-eps-004
	 */
	public function summary(array $event): array;
}//end interface
