<?php

/**
 * Tests for the larpinq Notifier.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Notification
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

namespace OCA\Larpinq\Tests\Unit\Notification;

use OCA\Larpinq\Notification\Notifier;
use OCA\Larpinq\Service\FeedbackDelivery;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\UnknownNotificationException;
use PHPUnit\Framework\TestCase;

/**
 * The invitation and the reminder render with the event name and the form link.
 *
 * @covers \OCA\Larpinq\Notification\Notifier
 * @uses   \OCA\Larpinq\Service\FeedbackDelivery
 */
class NotifierTest extends TestCase {

	/**
	 * The notifier with a pass-through translation.
	 *
	 * @return Notifier
	 */
	private function notifier(): Notifier {
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($text, $params));
		$l10n = $this->createMock(IFactory::class);
		$l10n->method('get')->willReturn($l);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('imagePath')->willReturn('/apps/larpinq/img/app-dark.svg');
		$urls->method('getAbsoluteURL')->willReturnCallback(static fn (string $path): string => 'https://cloud.example.org' . $path);

		return new Notifier($l10n, $urls);
	}//end notifier()

	/**
	 * A notification double that records what it is given.
	 *
	 * @param string $app     The app.
	 * @param string $subject The subject.
	 * @param array  $parsed  Collects parsed subject and link.
	 *
	 * @return INotification
	 */
	private function notification(string $app, string $subject, array &$parsed): INotification {
		$note = $this->createMock(INotification::class);
		$note->method('getApp')->willReturn($app);
		$note->method('getSubject')->willReturn($subject);
		$note->method('getSubjectParameters')->willReturn(['event' => 'Summer Siege 2025', 'link' => 'https://cloud.example.org/apps/forms/aBc123']);
		foreach (['setParsedSubject', 'setLink', 'setIcon'] as $setter) {
			$note->method($setter)->willReturnCallback(function (string $value) use ($setter, $note, &$parsed): INotification {
				$parsed[$setter] = $value;

				return $note;
			});
		}

		return $note;
	}//end notification()

	public function testAnInvitationAndAReminderRender(): void {
		$parsed = [];
		$this->notifier()->prepare($this->notification('larpinq', FeedbackDelivery::SUBJECT_INVITATION, $parsed), 'nl');
		self::assertSame('Tell us about Summer Siege 2025', $parsed['setParsedSubject']);
		self::assertSame('https://cloud.example.org/apps/forms/aBc123', $parsed['setLink']);
		self::assertSame('https://cloud.example.org/apps/larpinq/img/app-dark.svg', $parsed['setIcon']);

		$parsed = [];
		$this->notifier()->prepare($this->notification('larpinq', FeedbackDelivery::SUBJECT_REMINDER, $parsed), 'en');
		self::assertSame('Reminder: tell us about Summer Siege 2025', $parsed['setParsedSubject']);
	}

	public function testAnotherAppsOrAnUnknownSubjectIsNotOurs(): void {
		$parsed = [];
		foreach ([['files', FeedbackDelivery::SUBJECT_INVITATION], ['larpinq', 'something_else']] as [$app, $subject]) {
			try {
				$this->notifier()->prepare($this->notification($app, $subject, $parsed), 'en');
				self::fail($app . '/' . $subject . ' was rendered');
			} catch (UnknownNotificationException) {
				self::assertSame([], $parsed);
			}
		}

		self::assertSame('larpinq', $this->notifier()->getID());
	}
}
