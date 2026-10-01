<?php

/**
 * Larpinq Registration Change Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use DateTimeInterface;
use OCP\AppFramework\Utility\ITimeFactory;
use Throwable;

/**
 * Cancelling one registration and adding participants to a booking
 * (registration-cancel-transfer-refund REQ-RCT-001, REQ-RCT-002), and what the
 * signed-in user may change on a registration.
 *
 * The status and the booking fields are written by larpinq and game masters
 * only, so the checks live here, behind larpinq's endpoints, and the writes go
 * with the app's authority through the registration listeners: the freed place
 * goes to the waiting list there, and a paid cancellation is settled there.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class RegistrationChangeService {

	private const CANCELLABLE = ['pending', 'accepted', 'waitlisted'];

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads and writes the register.
	 * @param CancellationPolicy $policy Who may change what, and until when.
	 * @param ITimeFactory $time The clock.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly CancellationPolicy $policy,
		private readonly ITimeFactory $time,
	) {
	}//end __construct()

	/**
	 * Cancel one registration. Its place goes to the waiting list, and a paid
	 * one is refunded or credited.
	 *
	 * @param string $registrationId The registration.
	 * @param string $actingUid Who cancels.
	 * @param string $choice `refund` or `credit` when the policy lets the player choose, else ''.
	 *
	 * @return array<string, mixed> The stored registration.
	 *
	 * @throws RegistrationChangeRefusedException When the caller may not cancel it now.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function cancel(string $registrationId, string $actingUid, string $choice = ''): array {
		$registration = $this->registration(registrationId: $registrationId);
		$event = $this->event(registration: $registration);
		$gameMaster = $this->policy->isGameMaster(uid: $actingUid);
		if ($gameMaster === false && $this->isOwn(registration: $registration, uid: $actingUid) === false) {
			throw new RegistrationChangeRefusedException('Only the player, the person who booked or a game master can cancel this registration.', 403);
		}

		if (in_array((string)($registration['status'] ?? ''), self::CANCELLABLE, true) === false) {
			throw new RegistrationChangeRefusedException('This registration cannot be cancelled any more.', 409);
		}

		if ($gameMaster === false && $this->policy->isOpen(event: $event, now: $this->time->now()) === false) {
			throw new RegistrationChangeRefusedException('The cancel-by date has passed. Please contact the organisers to cancel.', 409);
		}

		$fields = ['status' => 'cancelled', 'cancelReason' => 'organiser', 'cancelledBy' => $actingUid];
		if ($gameMaster === false) {
			$fields['cancelReason'] = 'player';
		}

		if ($this->policy->choosesMoneyBack(event: $event) === true && in_array($choice, [CancellationPolicy::REFUND, CancellationPolicy::CREDIT], true) === true) {
			$fields['settlementChoice'] = $choice;
		}

		$this->fetcher->saveObjectWithAppAuthority(objectType: 'registration', data: $fields, uuid: $registrationId);
		return $this->registration(registrationId: $registrationId);
	}//end cancel()

	/**
	 * Add a participant to the booking of the caller's own registration: a new
	 * player with a name, or a player the caller booked before. The participant
	 * gets a registration of their own, decided like any other sign-up.
	 *
	 * @param string $registrationId The caller's own registration.
	 * @param string $actingUid Who books.
	 * @param string $name The new participant's name, or ''.
	 * @param string $playerId A player the caller booked before, or ''.
	 *
	 * @return array<string, mixed> The participant's registration.
	 *
	 * @throws RegistrationChangeRefusedException When the caller may not add to this booking.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function addParticipant(string $registrationId, string $actingUid, string $name, string $playerId = ''): array {
		$registration = $this->registration(registrationId: $registrationId);
		if ($this->policy->isParticipant(registration: $registration, uid: $actingUid) === false || (string)($registration['status'] ?? '') === 'cancelled') {
			throw new RegistrationChangeRefusedException('Only the person who signed up can add participants to this booking.', 403);
		}

		$player = $this->participant(actingUid: $actingUid, name: trim($name), playerId: $playerId);
		$group = (string)($registration['bookingGroup'] ?? '');
		if ($group === '') {
			$group = $this->uuid();
			$this->fetcher->saveObjectWithAppAuthority(objectType: 'registration', data: ['bookingGroup' => $group, 'bookedByUid' => $actingUid], uuid: $registrationId);
		}

		return $this->fetcher->saveObjectWithAppAuthority(
			objectType: 'registration',
			data: [
				'event' => (string)($registration['event'] ?? ''),
				'player' => $player,
				'submitterUid' => $actingUid,
				'bookedByUid' => $actingUid,
				'bookingGroup' => $group,
				'submittedAt' => $this->time->now()->format(DateTimeInterface::ATOM),
			]
		);
	}//end addParticipant()

	/**
	 * What the caller may change on a registration now, for the page.
	 *
	 * @param string $registrationId The registration.
	 * @param string $actingUid The caller.
	 *
	 * @return array<string, mixed> The answers.
	 *
	 * @throws RegistrationChangeRefusedException When the registration does not exist.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function whatMayChange(string $registrationId, string $actingUid): array {
		$registration = $this->registration(registrationId: $registrationId);
		$event = $this->event(registration: $registration);
		$gameMaster = $this->policy->isGameMaster(uid: $actingUid);
		$open = $this->policy->isOpen(event: $event, now: $this->time->now());
		$live = in_array((string)($registration['status'] ?? ''), self::CANCELLABLE, true);
		$participant = $this->policy->isParticipant(registration: $registration, uid: $actingUid);
		$offered = (string)($registration['transferStatus'] ?? '') === TransferOffers::OFFERED;
		$cancelBy = $this->policy->cancelBy(event: $event);

		return [
			'canCancel' => $live === true && ($gameMaster === true || ($open === true && $this->isOwn(registration: $registration, uid: $actingUid) === true)),
			'cancelBy' => $cancelBy?->format(DateTimeInterface::ATOM),
			'paid' => (string)($registration['paymentState'] ?? '') === RegistrationPaymentService::PAID,
			'choosesMoneyBack' => $this->policy->choosesMoneyBack(event: $event),
			'canOfferTransfer' => $live === true && $open === true && $participant === true && $offered === false,
			'canWithdrawTransfer' => $offered === true && ($participant === true || $gameMaster === true),
			'canAcceptTransfer' => $offered === true && (string)($registration['transferToUid'] ?? '') === $actingUid,
			'canAddParticipant' => $live === true && $participant === true,
			'transferStatus' => (string)($registration['transferStatus'] ?? ''),
			'settlement' => (string)($registration['settlement'] ?? ''),
		];
	}//end whatMayChange()

	/**
	 * The participant's player: a player the caller booked before, or a new one.
	 *
	 * @param string $actingUid Who books.
	 * @param string $name The new participant's name, or ''.
	 * @param string $playerId A player the caller booked before, or ''.
	 *
	 * @return string The player id.
	 *
	 * @throws RegistrationChangeRefusedException When neither is usable.
	 */
	private function participant(string $actingUid, string $name, string $playerId): string {
		if ($playerId !== '') {
			$booked = $this->fetcher->getObjectsWithAppAuthority(objectType: 'registration', filters: ['bookedByUid' => $actingUid, 'player' => $playerId], limit: 1);
			if ($booked === []) {
				throw new RegistrationChangeRefusedException('You can only add players you booked before.', 403);
			}

			return $playerId;
		}

		if ($name === '') {
			throw new RegistrationChangeRefusedException('Give the participant a name.', 422);
		}

		$player = $this->fetcher->saveObjectWithAppAuthority(objectType: 'player', data: ['name' => mb_substr($name, 0, 255)]);
		return (string)($player['id'] ?? '');
	}//end participant()

	/**
	 * Whether the caller is the participant or the booker.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param string $uid The caller.
	 *
	 * @return bool True for their own registration.
	 */
	private function isOwn(array $registration, string $uid): bool {
		return $this->policy->isParticipant(registration: $registration, uid: $uid) === true
			|| $this->policy->isBooker(registration: $registration, uid: $uid) === true;
	}//end isOwn()

	/**
	 * A registration, read by larpinq (the caller's rights are checked here).
	 *
	 * @param string $registrationId The registration.
	 *
	 * @return array<string, mixed> The registration.
	 *
	 * @throws RegistrationChangeRefusedException When it does not exist.
	 */
	private function registration(string $registrationId): array {
		try {
			return $this->fetcher->getObject(objectType: 'registration', id: $registrationId);
		} catch (Throwable $e) {
			throw new RegistrationChangeRefusedException('Registration not found.', 404);
		}
	}//end registration()

	/**
	 * The registration's event, or an empty event when it cannot be read.
	 *
	 * @param array<string, mixed> $registration The registration.
	 *
	 * @return array<string, mixed> The event.
	 */
	private function event(array $registration): array {
		try {
			return $this->fetcher->getObject(objectType: 'event', id: (string)($registration['event'] ?? ''));
		} catch (Throwable $e) {
			return [];
		}
	}//end event()

	/**
	 * A new booking group id.
	 *
	 * @return string A version 4 uuid.
	 */
	private function uuid(): string {
		$bytes = random_bytes(16);
		$bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
		$bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
	}//end uuid()
}//end class
