<?php

/**
 * Larpinq Unknown Feedback Answers
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-those-who-have-not-answered-are-reminded-once-req-eps-003
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * The count-only fallback of design D3: who answered is not known per user,
 * so the reminder goes to every invited participant. Replaced by a reader of
 * Forms submissions when that lands (task 2.2).
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-those-who-have-not-answered-are-reminded-once-req-eps-003
 */
class UnknownFeedbackAnswers implements FeedbackAnswers {

	/**
	 * Nobody can be told apart.
	 *
	 * @param int $formId The form id.
	 *
	 * @return null
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-those-who-have-not-answered-are-reminded-once-req-eps-003
	 */
	public function answeredBy(int $formId): ?array {
		unset($formId);
		return null;
	}//end answeredBy()

}//end class
