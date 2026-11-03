<?php

/**
 * TicketChoice for Larpinq
 *
 * One ticket and option choice under check: the registration as it will be,
 * the registration as stored, and the code it matched.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * The registration being priced, its stored version and its matched code.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */
final class TicketChoice {

	/**
	 * The matched code, or null when none was typed.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $codeRow = null;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $registration The registration as it will be.
	 * @param array<string, mixed> $stored The registration as stored, empty on a create.
	 */
	public function __construct(
		public readonly array $registration,
		private readonly array $stored,
	) {
	}//end __construct()

	/**
	 * The same choice with its matched code.
	 *
	 * @param array<string, mixed>|null $codeRow The matched code.
	 *
	 * @return self The choice.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function withCode(?array $codeRow): self {
		$copy = clone $this;
		$copy->codeRow = $codeRow;

		return $copy;
	}//end withCode()

	/**
	 * The matched code, or null when none was typed.
	 *
	 * @return array<string, mixed>|null The code.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function codeRow(): ?array {
		return $this->codeRow;
	}//end codeRow()

	/**
	 * The event of the registration.
	 *
	 * @return string The event id, or '' when it has none.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function eventId(): string {
		return (string)($this->registration['event'] ?? '');
	}//end eventId()

	/**
	 * The stored line of an unchanged choice.
	 *
	 * @param string $kind ticket or option.
	 * @param string $ref The chosen object.
	 *
	 * @return array<string, mixed>|null The line, or null when the choice is new.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function keptLine(string $kind, string $ref): ?array {
		foreach ((array)($this->stored['lines'] ?? []) as $line) {
			if (is_array($line) === true && ($line['kind'] ?? '') === $kind && ($line['ref'] ?? '') === $ref) {
				return $line;
			}
		}

		return null;
	}//end keptLine()
}//end class
