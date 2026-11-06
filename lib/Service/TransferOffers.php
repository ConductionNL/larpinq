<?php

/**
 * Larpinq Transfer Offers
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

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Handing a registration to another player (registration-cancel-transfer-refund
 * REQ-RCT-004). The participant offers it to another player's account; it
 * becomes that player's only when they accept. The place, ticket, price lines
 * and payment stay with the registration; the character is cleared so the new
 * player picks their own. An offer lapses after 7 days or at the cancel-by
 * date, whichever comes first: on accept, and in the daily payments job.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class TransferOffers {

	public const OFFERED = 'offered';

	/**
	 * How long an offer stands.
	 *
	 * @var string
	 */
	private const STANDS_FOR = 'P7D';

	/**
	 * Open offers one daily run reads (hydra ADR-058, bounded).
	 *
	 * @var integer
	 */
	private const PAGE = 500;

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads and writes the register.
	 * @param CancellationPolicy $policy Who may hand over, and until when.
	 * @param ITimeFactory $time The clock.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly CancellationPolicy $policy,
		private readonly ITimeFactory $time,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Offer a registration to another player's account.
	 *
	 * @param string $registrationId The registration.
	 * @param string $actingUid The participant.
	 * @param string $recipientUid The account of the player it goes to.
	 *
	 * @return array<string, mixed> The stored registration.
	 *
	 * @throws RegistrationChangeRefusedException When it cannot be offered.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function offer(string $registrationId, string $actingUid, string $recipientUid): array {
		$registration = $this->registration(registrationId: $registrationId);
		if ($this->policy->isParticipant(registration: $registration, uid: $actingUid) === false) {
			throw new RegistrationChangeRefusedException('Only the player can offer this registration to someone else.', 403);
		}

		$open = $this->policy->isOpen(event: $this->event(registration: $registration), now: $this->time->now());
		if ((string)($registration['status'] ?? '') !== 'accepted' || $open === false) {
			throw new RegistrationChangeRefusedException('Only an accepted registration can be handed over, before the cancel-by date.', 409);
		}

		$recipientUid = trim($recipientUid);
		$players = [];
		if ($recipientUid !== '' && $recipientUid !== $actingUid) {
			$players = $this->fetcher->getObjectsWithAppAuthority(objectType: 'player', filters: ['userUid' => $recipientUid], limit: 1);
		}

		if ($players === []) {
			throw new RegistrationChangeRefusedException('There is no other player with that account.', 404);
		}

		return $this->save(
			registrationId: $registrationId,
			fields: [
				'transferTo' => (string)($players[0]['id'] ?? ''),
				'transferToUid' => $recipientUid,
				'transferStatus' => self::OFFERED,
				'transferOfferedAt' => $this->time->now()->format(DateTimeInterface::ATOM),
			]
		);
	}//end offer()

	/**
	 * Accept an offered registration: it becomes the caller's.
	 *
	 * @param string $registrationId The registration.
	 * @param string $actingUid The account it was offered to.
	 *
	 * @return array<string, mixed> The stored registration.
	 *
	 * @throws RegistrationChangeRefusedException When there is no open offer for the caller.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function accept(string $registrationId, string $actingUid): array {
		$registration = $this->registration(registrationId: $registrationId);
		if ((string)($registration['transferStatus'] ?? '') !== self::OFFERED) {
			throw new RegistrationChangeRefusedException('There is no open offer on this registration.', 409);
		}

		if ($actingUid === '' || (string)($registration['transferToUid'] ?? '') !== $actingUid) {
			throw new RegistrationChangeRefusedException('This registration is offered to someone else.', 403);
		}

		$now = $this->time->now();
		if ($this->hasLapsed(registration: $registration, now: $now) === true) {
			throw new RegistrationChangeRefusedException('This offer has lapsed.', 409);
		}

		return $this->save(
			registrationId: $registrationId,
			fields: [
				'player' => (string)($registration['transferTo'] ?? ''),
				'playerUid' => $actingUid,
				'character' => '',
				'bookedByUid' => '',
				'transferredFrom' => (string)($registration['player'] ?? ''),
				'transferredAt' => $now->format(DateTimeInterface::ATOM),
				'transferStatus' => 'accepted',
				'transferToUid' => '',
			]
		);
	}//end accept()

	/**
	 * Withdraw an open offer: the participant or a game master.
	 *
	 * @param string $registrationId The registration.
	 * @param string $actingUid Who withdraws.
	 *
	 * @return array<string, mixed> The stored registration.
	 *
	 * @throws RegistrationChangeRefusedException When there is no open offer, or not theirs.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function withdraw(string $registrationId, string $actingUid): array {
		$registration = $this->registration(registrationId: $registrationId);
		$participant = $this->policy->isParticipant(registration: $registration, uid: $actingUid);
		if ($participant === false && $this->policy->isGameMaster(uid: $actingUid) === false) {
			throw new RegistrationChangeRefusedException('Only the player or a game master can withdraw this offer.', 403);
		}

		if ((string)($registration['transferStatus'] ?? '') !== self::OFFERED) {
			throw new RegistrationChangeRefusedException('There is no open offer on this registration.', 409);
		}

		return $this->save(registrationId: $registrationId, fields: ['transferStatus' => 'withdrawn', 'transferToUid' => '']);
	}//end withdraw()

	/**
	 * The daily pass: lapse every offer that stood 7 days or reached the cancel-by date.
	 *
	 * @param DateTimeImmutable $now The moment of the run.
	 *
	 * @return int How many offers lapsed.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function lapse(DateTimeImmutable $now): int {
		$lapsed = 0;
		$open = $this->fetcher->getObjectsWithAppAuthority(objectType: 'registration', filters: ['transferStatus' => self::OFFERED], limit: self::PAGE);
		foreach ($open as $registration) {
			if ($this->hasLapsed(registration: $registration, now: $now) === false) {
				continue;
			}

			try {
				$this->save(registrationId: (string)($registration['id'] ?? ''), fields: ['transferStatus' => 'lapsed', 'transferToUid' => '']);
				$lapsed++;
			} catch (Throwable $e) {
				$this->logger->error(
					'Larpinq: the transfer offer on registration {id} did not lapse.',
					['id' => (string)($registration['id'] ?? ''), 'exception' => $e]
				);
			}
		}

		return $lapsed;
	}//end lapse()

	/**
	 * Whether an offer stood 7 days, or the cancel-by date came.
	 *
	 * @param array<string, mixed> $registration The registration.
	 * @param DateTimeImmutable $now The moment.
	 *
	 * @return bool True when it lapsed.
	 */
	private function hasLapsed(array $registration, DateTimeImmutable $now): bool {
		if ($this->policy->isOpen(event: $this->event(registration: $registration), now: $now) === false) {
			return true;
		}

		try {
			$offeredAt = new DateTimeImmutable((string)($registration['transferOfferedAt'] ?? ''));
		} catch (Throwable $e) {
			return true;
		}

		return $now > $offeredAt->add(new DateInterval(self::STANDS_FOR));
	}//end hasLapsed()

	/**
	 * Write fields onto a registration with the app's authority, through the listeners.
	 *
	 * @param string $registrationId The registration.
	 * @param array<string, mixed> $fields The fields.
	 *
	 * @return array<string, mixed> The stored registration.
	 */
	private function save(string $registrationId, array $fields): array {
		return $this->fetcher->saveObjectWithAppAuthority(objectType: 'registration', data: $fields, uuid: $registrationId);
	}//end save()

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
}//end class
