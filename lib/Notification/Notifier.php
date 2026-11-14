<?php

/**
 * Larpinq Notifier
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Notification
 * @package  OCA\Larpinq\Notification
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-participants-who-were-there-are-invited-once-req-eps-002
 */

declare(strict_types=1);

namespace OCA\Larpinq\Notification;

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Service\FeedbackDelivery;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/**
 * Renders larpinq's notifications: the post-event feedback invitation and reminder.
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-participants-who-were-there-are-invited-once-req-eps-002
 */
class Notifier implements INotifier {

	/**
	 * Constructor.
	 *
	 * @param IFactory      $l10nFactory Translations in the reader's language.
	 * @param IURLGenerator $urls        Builds the icon URL.
	 */
	public function __construct(
		private readonly IFactory $l10nFactory,
		private readonly IURLGenerator $urls,
	) {
	}//end __construct()

	/**
	 * The notifier id.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-participants-who-were-there-are-invited-once-req-eps-002
	 */
	public function getID(): string {
		return Application::APP_ID;
	}//end getID()

	/**
	 * The notifier name.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-participants-who-were-there-are-invited-once-req-eps-002
	 */
	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('Larpinq');
	}//end getName()

	/**
	 * Render a feedback invitation or reminder.
	 *
	 * @param INotification $notification The notification.
	 * @param string        $languageCode The reader's language.
	 *
	 * @return INotification The rendered notification.
	 *
	 * @throws UnknownNotificationException When it is not one of larpinq's.
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-participants-who-were-there-are-invited-once-req-eps-002
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		$subject = $notification->getSubject();
		if ($notification->getApp() !== Application::APP_ID
			|| in_array($subject, [FeedbackDelivery::SUBJECT_INVITATION, FeedbackDelivery::SUBJECT_REMINDER], true) === false
		) {
			throw new UnknownNotificationException();
		}

		$l      = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$params = $notification->getSubjectParameters();
		$event  = (string)($params['event'] ?? '');
		$text   = $l->t('Tell us about %s', [$event]);
		if ($subject === FeedbackDelivery::SUBJECT_REMINDER) {
			$text = $l->t('Reminder: tell us about %s', [$event]);
		}

		$notification->setParsedSubject($text)
			->setIcon($this->urls->getAbsoluteURL($this->urls->imagePath(Application::APP_ID, 'app-dark.svg')));
		$link = (string)($params['link'] ?? '');
		if ($link !== '') {
			$notification->setLink($link);
		}

		return $notification;
	}//end prepare()

}//end class
