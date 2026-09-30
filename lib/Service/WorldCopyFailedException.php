<?php

/**
 * A world copy that failed halfway.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/setting-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use RuntimeException;
use Throwable;

/**
 * Raised when a world copy fails after it started writing. The copy has been
 * rolled back as far as possible; the leftovers are the ids it could not
 * remove.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/setting-management/spec.md
 */
class WorldCopyFailedException extends RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param string $message What failed.
	 * @param list<string> $leftovers The ids the rollback could not remove.
	 * @param Throwable|null $previous The cause.
	 *
	 * @spec openspec/specs/setting-management/spec.md
	 */
	public function __construct(
		string $message,
		private readonly array $leftovers = [],
		?Throwable $previous = null,
	) {
		parent::__construct(message: $message, code: 0, previous: $previous);
	}//end __construct()

	/**
	 * The ids the rollback could not remove.
	 *
	 * @return list<string> The ids.
	 *
	 * @spec openspec/specs/setting-management/spec.md
	 */
	public function getLeftovers(): array {
		return $this->leftovers;
	}//end getLeftovers()
}//end class
