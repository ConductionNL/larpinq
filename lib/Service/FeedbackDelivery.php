<?php

/**
 * Larpinq Feedback Delivery
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
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-participants-who-were-there-are-invited-once-req-eps-002
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use DateTime;
use OCA\Larpinq\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Notification\IManager as INotificationManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Sends one participant a feedback invitation or reminder: a Nextcloud
 * notification and, when the account has an address, an email, both with
 * the link to the form.
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-participants-who-were-there-are-invited-once-req-eps-002
 */
class FeedbackDelivery {

	/**
	 * The notification subject of an invitation.
	 *
	 * @var string
	 */
	public const SUBJECT_INVITATION = 'feedback_invitation';

	/**
	 * The notification subject of a reminder.
	 *
	 * @var string
	 */
	public const SUBJECT_REMINDER = 'feedback_reminder';

	/**
	 * Constructor.
	 *
	 * @param INotificationManager $notifications Nextcloud notifications.
	 * @param IMailer              $mailer        Nextcloud mail.
	 * @param IUserManager         $users         Resolves the participant's account.
	 * @param IURLGenerator        $urls          Builds the form link.
	 * @param IFactory             $l10nFactory   Translations in the participant's language.
	 * @param ContainerInterface   $container     Resolves Nextcloud Forms when it is installed.
	 * @param LoggerInterface      $logger        The logger.
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-participants-who-were-there-are-invited-once-req-eps-002
	 */
	public function __construct(
		private readonly INotificationManager $notifications,
		private readonly IMailer $mailer,
		private readonly IUserManager $users,
		private readonly IURLGenerator $urls,
		private readonly IFactory $l10nFactory,
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Ask one participant for feedback, or remind them.
	 *
	 * @param string $uid      The participant's user id.
	 * @param array  $event    The event (id, name).
	 * @param int    $formId   The feedback form.
	 * @param bool   $reminder Whether this is the reminder.
	 *
	 * @return bool False when the account does not exist, so nothing was sent.
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-participants-who-were-there-are-invited-once-req-eps-002
	 */
	public function send(string $uid, array $event, int $formId, bool $reminder): bool {
		$user = $this->users->get($uid);
		if ($user === null) {
			return false;
		}

		$name    = (string)($event['name'] ?? '');
		$link    = $this->formLink(formId: $formId);
		$subject = self::SUBJECT_INVITATION;
		if ($reminder === true) {
			$subject = self::SUBJECT_REMINDER;
		}

		$notification = $this->notifications->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($uid)
			->setDateTime(new DateTime())
			->setObject('event', (string)($event['id'] ?? ''))
			->setSubject($subject, ['event' => $name, 'link' => $link]);
		$this->notifications->notify($notification);

		$this->mail(
			address: (string)$user->getEMailAddress(),
			displayName: $user->getDisplayName(),
			uid: $uid,
			event: $name,
			link: $link,
			reminder: $reminder
		);

		return true;
	}//end send()

	/**
	 * Mail the participant when the account has an address. A failing mail
	 * is logged; the notification already went out.
	 *
	 * @param string $address     The address, or ''.
	 * @param string $displayName The participant's name.
	 * @param string $uid         The user id, for the language.
	 * @param string $event       The event name.
	 * @param string $link        The form link.
	 * @param bool   $reminder    Whether this is the reminder.
	 *
	 * @return void
	 */
	private function mail(string $address, string $displayName, string $uid, string $event, string $link, bool $reminder): void {
		if ($address === '') {
			return;
		}

		$l = $this->l10nFactory->get(Application::APP_ID, $this->l10nFactory->getUserLanguage($this->users->get($uid)));
		$subject = $l->t('Tell us about %s', [$event]);
		$body    = $l->t(
			'Thank you for playing at %1$s. The story team would like to hear what your character did and what you plan next. Fill in the form here: %2$s',
			[$event, $link]
		);
		if ($reminder === true) {
			$subject = $l->t('Reminder: tell us about %s', [$event]);
			$body    = $l->t('The feedback form for %1$s closes soon. If you have not filled it in yet, you can do so here: %2$s', [$event, $link]);
		}

		try {
			$message = $this->mailer->createMessage();
			$message->setTo([$address => $displayName]);
			$message->setSubject($subject);
			$message->setPlainBody($body);
			$this->mailer->send($message);
		} catch (Throwable $e) {
			$this->logger->warning('Larpinq: the feedback mail to {uid} was not sent.', ['uid' => $uid, 'exception' => $e]);
		}
	}//end mail()

	/**
	 * The link to the form in Nextcloud Forms, or to Forms itself when the
	 * form cannot be looked up.
	 *
	 * @param int $formId The form id.
	 *
	 * @return string The absolute URL.
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-participants-who-were-there-are-invited-once-req-eps-002
	 */
	public function formLink(int $formId): string {
		try {
			$hash = (string)$this->container->get('OCA\Forms\Db\FormMapper')->findById($formId)->getHash();
		} catch (Throwable $e) {
			$hash = '';
		}

		return $this->urls->getAbsoluteURL('/apps/forms/' . $hash);
	}//end formLink()

}//end class
