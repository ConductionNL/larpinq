<?php

/**
 * OpenRegister NotAuthorizedException
 *
 * This file contains the exception class for authorization errors.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category Exception
 * @package  OCA\OpenRegister\Exception
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://OpenRegister.app
 *
 * TEST-ONLY COPY of openregister/lib/Exception/NotAuthorizedException.php (openregister main c3d2067b),
 * the real class verbatim, loaded only by tests/bootstrap.php when OpenRegister
 * itself is not on the path. OpenRegisterStubDriftTest compares it with the
 * real file when the openregister source sits beside this app.
 */

namespace OCA\OpenRegister\Exception;

use Exception;

/**
 * Exception thrown when a user is not authorized to perform an action
 *
 * Thrown when a user attempts to perform an operation they don't have
 * permission for. Used for access control and authorization checks.
 * Typically results in HTTP 403 Forbidden response.
 *
 * @category Exception
 * @package  OCA\OpenRegister\Exception
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://OpenRegister.app
 */
class NotAuthorizedException extends Exception {
	/**
	 * The message when the thrower names none.
	 *
	 * @var string
	 */
	protected $message = 'You are not authorized to perform this action';

	/**
	 * HTTP 403 Forbidden unless the thrower names another code.
	 *
	 * @var integer
	 */
	protected $code = 403;
}//end class
