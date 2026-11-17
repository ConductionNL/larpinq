<?php

/**
 * FormsFeedbackAnswers for Larpinq
 *
 * Who answered a feedback form, read from Nextcloud Forms' own submissions.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-game-masters-see-who-answered-req-eps-004
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The user ids that submitted a form, from Forms' SubmissionMapper (design D3).
 *
 * The answers stay in Forms; only who submitted is read. When Forms is not
 * installed, or its submissions cannot be read, the answer is null: the
 * count-only fallback, under which every invitee is reminded.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-game-masters-see-who-answered-req-eps-004
 */
class FormsFeedbackAnswers implements FeedbackAnswers {

	/**
	 * Forms' submission mapper.
	 *
	 * @var string
	 */
	public const SUBMISSION_MAPPER = 'OCA\Forms\Db\SubmissionMapper';

	/**
	 * The user id prefix Forms gives an anonymous submission.
	 *
	 * @var string
	 */
	private const ANONYMOUS = 'anon-user-';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container The server container, holding Forms' mapper when Forms is installed.
	 * @param LoggerInterface    $logger    The logger.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The user ids that submitted the form, each once; null when Forms cannot tell.
	 *
	 * @param int $formId The form id.
	 *
	 * @return list<string>|null
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-those-who-have-not-answered-are-reminded-once-req-eps-003
	 */
	public function answeredBy(int $formId): ?array {
		if ($formId <= 0 || $this->container->has(self::SUBMISSION_MAPPER) === false) {
			return null;
		}

		try {
			$submissions = $this->container->get(self::SUBMISSION_MAPPER)->findByForm($formId);
		} catch (Throwable $e) {
			$this->logger->warning('Larpinq: the submissions of form {form} could not be read.', ['form' => $formId, 'exception' => $e]);
			return null;
		}

		if (is_iterable($submissions) === false) {
			return null;
		}

		return self::submitters(submissions: $submissions);
	}//end answeredBy()

	/**
	 * The named users among submissions, each once.
	 *
	 * @param iterable<mixed> $submissions Forms' submissions.
	 *
	 * @return list<string>
	 */
	private static function submitters(iterable $submissions): array {
		$uids = [];
		foreach ($submissions as $submission) {
			if (is_object($submission) === false || method_exists($submission, 'getUserId') === false) {
				continue;
			}

			$uid = (string)$submission->getUserId();
			if ($uid !== '' && str_starts_with($uid, self::ANONYMOUS) === false) {
				$uids[$uid] = true;
			}
		}

		return array_keys($uids);
	}//end submitters()
}//end class
