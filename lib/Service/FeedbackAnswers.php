<?php

/**
 * Larpinq Feedback Answers
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
 * Who answered a feedback form. The answers live in Nextcloud Forms; this
 * only reads which accounts submitted.
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-those-who-have-not-answered-are-reminded-once-req-eps-003
 */
interface FeedbackAnswers {

	/**
	 * The user ids that submitted the form, or null when that cannot be told
	 * per user (then every invited participant is reminded, design D3).
	 *
	 * @param int $formId The Nextcloud Forms form id.
	 *
	 * @return list<string>|null
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-those-who-have-not-answered-are-reminded-once-req-eps-003
	 */
	public function answeredBy(int $formId): ?array;

}//end interface
