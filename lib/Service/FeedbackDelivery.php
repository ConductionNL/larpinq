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
use OCP\IL10N;
use OCP\IUser;
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
	 * @param string                                          $uid    The participant's user id.
	 * @param array{event: array, formId: int, reminder: bool} $invite The event (id, name), the feedback form and whether this is the reminder.
	 *
	 * @return bool False when the account does not exist, so nothing was sent.
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-participants-who-were-there-are-invited-once-req-eps-002
	 */
	public function send(string $uid, array $invite): bool {
		$user = $this->users->get($uid);
		if ($user === null) {
			return false;
		}

		$event    = $invite['event'];
		$reminder = $invite['reminder'];
		$name     = (string)($event['name'] ?? '');
		$link     = $this->formLink(formId: $invite['formId']);
		$subject  = self::SUBJECT_INVITATION;
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

		$this->mail(user: $user, text: ['event' => $name, 'link' => $link, 'reminder' => $reminder]);

		return true;
	}//end send()

	/**
	 * Mail the participant when the account has an address. A failing mail
	 * is logged; the notification already went out.
	 *
	 * @param IUser                                              $user The participant: address, name and language.
	 * @param array{event: string, link: string, reminder: bool} $text The event name, the form link and whether this is the reminder.
	 *
	 * @return void
	 */
	private function mail(IUser $user, array $text): void {
		$address = (string)$user->getEMailAddress();
		if ($address === '') {
			return;
		}

		[$event, $link] = [$text['event'], $text['link']];
		$l    = $this->l10nFactory->get(Application::APP_ID, $this->l10nFactory->getUserLanguage($user));
		$body = $l->t(
			'Thank you for playing at %1$s. The story team would like to hear what your character did and what you plan next. Fill in the form here: %2$s',
			[$event, $link]
		);
		if ($text['reminder'] === true) {
			$body = $l->t('The feedback form for %1$s closes soon. If you have not filled it in yet, you can do so here: %2$s', [$event, $link]);
		}

		$this->deliver(user: $user, mail: ['subject' => self::subjectText(l: $l, event: $event, reminder: $text['reminder']), 'body' => $body]);
	}//end mail()

	/**
	 * The title of the invitation or the reminder, in the reader's language.
	 *
	 * @param IL10N  $l        The reader's translations.
	 * @param string $event    The event name.
	 * @param bool   $reminder Whether this is the reminder.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-participants-who-were-there-are-invited-once-req-eps-002
	 */
	public static function subjectText(IL10N $l, string $event, bool $reminder): string {
		if ($reminder === true) {
			return $l->t('Reminder: tell us about %s', [$event]);
		}

		return $l->t('Tell us about %s', [$event]);
	}//end subjectText()

	/**
	 * Send the mail; a failure is logged, never thrown.
	 *
	 * @param IUser                                $user The participant.
	 * @param array{subject: string, body: string} $mail The subject and the plain body.
	 *
	 * @return void
	 */
	private function deliver(IUser $user, array $mail): void {
		try {
			$message = $this->mailer->createMessage();
			$message->setTo([(string)$user->getEMailAddress() => $user->getDisplayName()]);
			$message->setSubject($mail['subject']);
			$message->setPlainBody($mail['body']);
			$this->mailer->send($message);
		} catch (Throwable $e) {
			$this->logger->warning('Larpinq: the feedback mail to {uid} was not sent.', ['uid' => $user->getUID(), 'exception' => $e]);
		}
	}//end deliver()

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

	/**
	 * The link to the form's results in Nextcloud Forms, or to Forms itself
	 * when the form cannot be looked up.
	 *
	 * @param int $formId The form id.
	 *
	 * @return string The absolute URL.
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-game-masters-see-who-answered-req-eps-004
	 */
	public function resultsLink(int $formId): string {
		$link = $this->formLink(formId: $formId);
		if (str_ends_with($link, '/apps/forms/') === true) {
			return $link;
		}

		return $link . '/results';
	}//end resultsLink()

}//end class
