<?php

/**
 * Larpinq Event Capacity Lock
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
 * @spec openspec/changes/larpinq-manifest-tier-4/tasks.md#phase-4--verification
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

/**
 * One exclusive lock per event, held by this request until released.
 *
 * A decision that counts an event's free places is taken under it, so two
 * sign-ups for the last place cannot both see it free. Kept out of
 * RegistrationService so that service stays under the phpmd class
 * complexity limit.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class EventCapacityLock {

	/**
	 * Lock key prefix, one lock per event.
	 *
	 * @var string
	 */
	public const PREFIX = 'larpinq/event-capacity/';

	/**
	 * Events whose lock this request holds.
	 *
	 * @var array<string, true>
	 */
	private array $held = [];

	/**
	 * Constructor.
	 *
	 * @param ILockingProvider                       $locking The lock provider.
	 * @param array{attempts?: int, waitMicros?: int} $retry   How often a busy lock is tried (default 30)
	 *                                                        and the pause between two tries in microseconds (default 100000).
	 *
	 * @spec openspec/changes/larpinq-manifest-tier-4/tasks.md#phase-4--verification
	 */
	public function __construct(
		private readonly ILockingProvider $locking,
		private readonly array $retry = [],
	) {
	}//end __construct()

	/**
	 * Take the event's lock, trying a few times while another request holds it.
	 *
	 * @param string $eventId The event.
	 *
	 * @return bool Whether this request holds the lock.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 * @spec openspec/changes/larpinq-manifest-tier-4/tasks.md#phase-4--verification
	 */
	public function acquire(string $eventId): bool {
		if (isset($this->held[$eventId]) === true) {
			return true;
		}

		for ($attempt = 1; $attempt <= max(1, (int)($this->retry['attempts'] ?? 30)); $attempt++) {
			try {
				$this->locking->acquireLock(self::PREFIX . $eventId, ILockingProvider::LOCK_EXCLUSIVE);
				$this->held[$eventId] = true;
				return true;
			} catch (LockedException $e) {
				usleep((int)($this->retry['waitMicros'] ?? 100000));
			}
		}

		return false;
	}//end acquire()

	/**
	 * Release the event's lock when this request holds it.
	 *
	 * @param string $eventId The event.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 * @spec openspec/changes/larpinq-manifest-tier-4/tasks.md#phase-4--verification
	 */
	public function release(string $eventId): void {
		if (isset($this->held[$eventId]) === false) {
			return;
		}

		unset($this->held[$eventId]);
		$this->locking->releaseLock(self::PREFIX . $eventId, ILockingProvider::LOCK_EXCLUSIVE);
	}//end release()
}//end class
