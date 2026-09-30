<?php

/**
 * Portaliq PortalAccountClaimRequestedEvent
 *
 * The typed request (ADR-041) an app dispatches to link one of its own
 * records to a portal account. The claim is written server-side under the
 * dispatching app's own id, so an app can never write a claim under another
 * app's name and client input never reaches `claims`.
 *
 * @category Event
 * @package  OCA\Portaliq\Event
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/portal-identity-space/specs/portal-identity-space/spec.md
 *
 * TEST-ONLY COPY of portaliq/lib/Event/PortalAccountClaimRequestedEvent.php (development
 * 49d6be88), the real class verbatim, loaded only by tests/bootstrap.php when portaliq is
 * not on the path, so larpinq's claim listener test dispatches the REAL event class.
 */

declare(strict_types=1);

namespace OCA\Portaliq\Event;

use OCP\EventDispatcher\Event;

/**
 * An app asks portaliq to write its claim on a portal account.
 *
 * @spec openspec/changes/portal-identity-space/specs/portal-identity-space/spec.md
 */
class PortalAccountClaimRequestedEvent extends Event {
	/**
	 * The result slot: 'ok' once the claim landed, 'refused' otherwise.
	 *
	 * @var string
	 */
	private string $result = '';

	/**
	 * Constructor.
	 *
	 * @param string $appId The dispatching app, taken from its own context.
	 * @param string $subjectRef The account to write on.
	 * @param string $claimName The claim to set, e.g. `linkedRequesterId`.
	 * @param string $value The claim value.
	 */
	public function __construct(
		private readonly string $appId,
		private readonly string $subjectRef,
		private readonly string $claimName,
		private readonly string $value,
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * The dispatching app.
	 *
	 * @return string
	 */
	public function getAppId(): string {
		return $this->appId;
	}//end getAppId()

	/**
	 * The account to write on.
	 *
	 * @return string
	 */
	public function getSubjectRef(): string {
		return $this->subjectRef;
	}//end getSubjectRef()

	/**
	 * The claim to set.
	 *
	 * @return string
	 */
	public function getClaimName(): string {
		return $this->claimName;
	}//end getClaimName()

	/**
	 * The claim value.
	 *
	 * @return string
	 */
	public function getValue(): string {
		return $this->value;
	}//end getValue()

	/**
	 * Portaliq's answer.
	 *
	 * @param string $result 'ok' when the claim landed, 'refused' otherwise.
	 *
	 * @return void
	 */
	public function answer(string $result): void {
		$this->result = $result;
	}//end answer()

	/**
	 * The result slot, '' while nothing has answered.
	 *
	 * @return string
	 */
	public function getResult(): string {
		return $this->result;
	}//end getResult()
}//end class
