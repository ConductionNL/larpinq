<?php

/**
 * Tests for FeedbackDelivery: one notification and one mail with the form link.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
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

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\FeedbackDelivery;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Anna gets a notification and an email with the form link.
 *
 * @covers \OCA\Larpinq\Service\FeedbackDelivery
 */
class FeedbackDeliveryTest extends TestCase {

	/** @var array<string, mixed> What the notification was given. */
	private array $notification = [];

	/** @var array<string, mixed> What the mail was given. */
	private array $mail = [];

	/**
	 * A delivery whose only account is Anna, with the given address.
	 *
	 * @param string $address  Anna's address.
	 * @param bool   $hasForms Whether Forms finds the form.
	 * @param bool   $mailFails Whether sending the mail throws.
	 * @param LoggerInterface|null $logger The logger.
	 *
	 * @return FeedbackDelivery
	 */
	private function delivery(string $address = 'anna@example.org', bool $hasForms = true, bool $mailFails = false, ?LoggerInterface $logger = null): FeedbackDelivery {
		$this->notification = [];
		$this->mail = [];
		$note = $this->createMock(INotification::class);
		foreach (['setApp', 'setUser', 'setDateTime', 'setObject', 'setSubject'] as $setter) {
			$note->method($setter)->willReturnCallback(function (...$args) use ($setter, $note): INotification {
				$this->notification[$setter] = $args;

				return $note;
			});
		}
		$notifications = $this->createMock(INotificationManager::class);
		$notifications->method('createNotification')->willReturn($note);
		$notifications->method('notify')->willReturnCallback(function (INotification $sent) use ($note): void {
			self::assertSame($note, $sent);
			$this->notification['notified'] = true;
		});

		$message = $this->createMock(IMessage::class);
		foreach (['setTo', 'setSubject', 'setPlainBody'] as $setter) {
			$message->method($setter)->willReturnCallback(function (...$args) use ($setter, $message): IMessage {
				$this->mail[$setter] = $args[0];

				return $message;
			});
		}
		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createMessage')->willReturn($message);
		$send = $mailer->method('send');
		if ($mailFails === true) {
			$send->willThrowException(new RuntimeException('smtp down'));
		}

		$anna = $this->createMock(IUser::class);
		$anna->method('getEMailAddress')->willReturn($address);
		$anna->method('getDisplayName')->willReturn('Anna');
		$users = $this->createMock(IUserManager::class);
		$users->method('get')->willReturnCallback(static fn (string $uid): ?IUser => $uid === 'anna' ? $anna : null);

		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('getAbsoluteURL')->willReturnCallback(static fn (string $path): string => 'https://cloud.example.org' . $path);
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($text, $params));
		$l10n = $this->createMock(IFactory::class);
		$l10n->method('get')->willReturn($l);

		$form = new class {
			/** @return string */
			public function getHash(): string {
				return 'aBc123';
			}
		};
		$formMapper = new class($form, $hasForms) {
			/**
			 * @param object $form     The form.
			 * @param bool   $hasForms Whether it is found.
			 */
			public function __construct(private object $form, private bool $hasForms) {
			}

			/**
			 * @param int $id The id.
			 *
			 * @return object
			 */
			public function findById(int $id): object {
				if ($this->hasForms === false || $id !== 42) {
					throw new RuntimeException('no form');
				}

				return $this->form;
			}
		};
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->with('OCA\Forms\Db\FormMapper')->willReturn($formMapper);

		return new FeedbackDelivery($notifications, $mailer, $users, $urls, $l10n, $container, ($logger ?? $this->createMock(LoggerInterface::class)));
	}//end delivery()

	/**
	 * Scenario "Anna gets her invitation": a notification and an email, both with the form link.
	 *
	 * @return void
	 */
	public function testAnInvitationIsANotificationAndAMailWithTheFormLink(): void {
		$sent = $this->delivery()->send(uid: 'anna', event: ['id' => 'e-1', 'name' => 'Summer Siege 2025'], formId: 42, reminder: false);

		self::assertTrue($sent);
		self::assertTrue($this->notification['notified']);
		self::assertSame(['larpinq'], $this->notification['setApp']);
		self::assertSame(['anna'], $this->notification['setUser']);
		self::assertSame(['event', 'e-1'], $this->notification['setObject']);
		self::assertSame([FeedbackDelivery::SUBJECT_INVITATION, ['event' => 'Summer Siege 2025', 'link' => 'https://cloud.example.org/apps/forms/aBc123']], $this->notification['setSubject']);
		self::assertSame(['anna@example.org' => 'Anna'], $this->mail['setTo']);
		self::assertSame('Tell us about Summer Siege 2025', $this->mail['setSubject']);
		self::assertStringContainsString('https://cloud.example.org/apps/forms/aBc123', $this->mail['setPlainBody']);
	}//end testAnInvitationIsANotificationAndAMailWithTheFormLink()

	/**
	 * A reminder has its own subject; without an address only the notification goes out.
	 *
	 * @return void
	 */
	public function testAReminderAndAnAccountWithoutAnAddress(): void {
		$this->delivery()->send(uid: 'anna', event: ['id' => 'e-1', 'name' => 'Summer Siege 2025'], formId: 42, reminder: true);
		self::assertSame(FeedbackDelivery::SUBJECT_REMINDER, $this->notification['setSubject'][0]);
		self::assertSame('Reminder: tell us about Summer Siege 2025', $this->mail['setSubject']);

		$this->delivery(address: '')->send(uid: 'anna', event: ['id' => 'e-1', 'name' => 'Summer Siege 2025'], formId: 42, reminder: false);
		self::assertSame([], $this->mail);
	}//end testAReminderAndAnAccountWithoutAnAddress()

	/**
	 * Without Forms the link opens Forms itself; a failing mail is logged and the send still counts.
	 *
	 * @return void
	 */
	public function testFallbacks(): void {
		self::assertSame('https://cloud.example.org/apps/forms/', $this->delivery(hasForms: false)->formLink(formId: 42));

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('warning');
		self::assertTrue($this->delivery(mailFails: true, logger: $logger)->send(uid: 'anna', event: ['id' => 'e-1', 'name' => 'X'], formId: 42, reminder: false));
	}//end testFallbacks()

	/**
	 * An account that does not exist gets nothing.
	 *
	 * @return void
	 */
	public function testAnUnknownAccountGetsNothing(): void {
		$notifications = $this->createMock(INotificationManager::class);
		$notifications->expects($this->never())->method('notify');
		$users = $this->createMock(IUserManager::class);
		$delivery = new FeedbackDelivery($notifications, $this->createMock(IMailer::class), $users, $this->createMock(IURLGenerator::class), $this->createMock(IFactory::class), $this->createMock(ContainerInterface::class), $this->createMock(LoggerInterface::class));

		self::assertFalse($delivery->send(uid: 'ghost', event: ['id' => 'e-1'], formId: 42, reminder: false));
	}//end testAnUnknownAccountGetsNothing()

}//end class
