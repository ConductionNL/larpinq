<?php

/**
 * Larpinq Help Desk Caller
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-the-asker-can-reply-until-the-question-is-closed-req-chd-003
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use OCP\IGroupManager;
use OCP\IUserSession;

/**
 * Who writes on the help desk: the signed-in account, and whether it is a
 * game master (a member of the organisers' group).
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-the-asker-can-reply-until-the-question-is-closed-req-chd-003
 */
final class HelpDeskCaller {

	/**
	 * Constructor.
	 *
	 * @param IUserSession  $userSession  The session of the writing account.
	 * @param IGroupManager $groupManager Group membership, for the game-master check.
	 */
	public function __construct(
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
	) {
	}//end __construct()

	/**
	 * The signed-in account's uid, or '' for a write without one (an import,
	 * a repair step or a background job).
	 *
	 * @return string The uid, or ''.
	 *
	 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-the-asker-can-reply-until-the-question-is-closed-req-chd-003
	 */
	public function uid(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return '';
		}

		return $user->getUID();
	}//end uid()

	/**
	 * Whether an account is a game master. No account is not one.
	 *
	 * @param string $uid The account's uid, or ''.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-game-masters-answer-from-one-list-req-chd-002
	 */
	public function isOrganiser(string $uid): bool {
		return $uid !== '' && $this->groupManager->isInGroup($uid, HelpMessageListener::ORGANISERS) === true;
	}//end isOrganiser()
}//end class
