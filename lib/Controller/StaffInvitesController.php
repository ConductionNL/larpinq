<?php

/**
 * StaffInvitesController for Larpinq
 *
 * Staff invites and the Staff page: a game master creates and revokes invite
 * links and trims the team; a signed-in user opens a link and joins its role.
 *
 * @category  Controller
 * @package   OCA\Larpinq\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use DomainException;
use InvalidArgumentException;
use OCA\Larpinq\Service\StaffInviteService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OutOfBoundsException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The staff invite and Staff page endpoints.
 *
 * Creating, revoking, listing and removing are game master only (an admin
 * counts); opening and redeeming a link needs only a signed-in user and is
 * rate limited per user, so a token cannot be guessed by trying.
 *
 * @category Controller
 * @package  OCA\Larpinq\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md
 */
class StaffInvitesController extends Controller {

	/**
	 * What the landing page and the redeem answer say for each refused state.
	 *
	 * @var array<string, string>
	 */
	private const REFUSALS = [
		StaffInviteService::STATE_EXPIRED => 'This invite has expired',
		StaffInviteService::STATE_USED_UP => 'This invite is used up',
		StaffInviteService::STATE_REVOKED => 'This invite was revoked',
	];

	/**
	 * Constructor.
	 *
	 * @param IRequest $request The request.
	 * @param StaffInviteService $invites The staff invites.
	 * @param CallerAccess $callerAccess The caller checks.
	 * @param IURLGenerator $urlGenerator Builds the invite link.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		IRequest $request,
		private readonly StaffInviteService $invites,
		private readonly CallerAccess $callerAccess,
		private readonly IURLGenerator $urlGenerator,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(appName: 'larpinq', request: $request);
	}//end __construct()

	/**
	 * Create an invite for `role`, valid `days` days (default 7) for `maxUses` people (default 1).
	 *
	 * The plain token and the link are in this answer only.
	 *
	 * @return JSONResponse 201 with the invite, token and link; 400, 401 or 403.
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-a-game-master-invites-with-a-link-req-asr-004
	 */
	#[NoAdminRequired]
	public function create(): JSONResponse {
		[$actingUid, $denied] = $this->callerAccess->resolveGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		try {
			$created = $this->invites->create(
				role: (string)$this->request->getParam('role', ''),
				days: (int)$this->request->getParam('days', StaffInviteService::DEFAULT_DAYS),
				maxUses: (int)$this->request->getParam('maxUses', 1),
				actingUid: $actingUid
			);
		} catch (InvalidArgumentException $exception) {
			return new JSONResponse(data: ['error' => $exception->getMessage()], statusCode: Http::STATUS_BAD_REQUEST);
		} catch (Throwable $exception) {
			$this->logger->error('[StaffInvitesController] creating an invite failed: ' . $exception->getMessage());
			return new JSONResponse(data: ['error' => 'The invite could not be created'], statusCode: Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$created['link'] = $this->urlGenerator->linkToRouteAbsolute('larpinq.dashboard.page') . 'staff-invite/' . $created['token'];
		return new JSONResponse(data: $created, statusCode: Http::STATUS_CREATED);
	}//end create()

	/**
	 * The role and state of the invite `token` opens, for the landing page.
	 *
	 * @return JSONResponse 200 with role and state; 401, or 404 for an unknown token.
	 *
	 * @no-admin-idor-exempt The token is the capability (REQ-ASR-004): it is
	 * 128 random bits, stored only as a hash, and an invite is found by that
	 * hash alone, never by a caller-chosen id. Any signed-in user holding the
	 * link may see its role and state; guessing is rate limited per user.
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-a-game-master-invites-with-a-link-req-asr-004
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 300)]
	public function inspect(): JSONResponse {
		if ($this->callerAccess->user() === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			return new JSONResponse(data: $this->invites->inspect(token: (string)$this->request->getParam('token', '')));
		} catch (OutOfBoundsException) {
			return new JSONResponse(data: ['error' => 'No such invite'], statusCode: Http::STATUS_NOT_FOUND);
		} catch (Throwable $exception) {
			$this->logger->error('[StaffInvitesController] reading an invite failed: ' . $exception->getMessage());
			return new JSONResponse(data: ['error' => 'The invite could not be read'], statusCode: Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}//end inspect()

	/**
	 * Join the role of the invite `token` opens.
	 *
	 * @return JSONResponse 200 with role and joined; 401, 404 for an unknown token, 410 for an expired, used-up or revoked invite.
	 *
	 * @no-admin-idor-exempt The token is the capability (REQ-ASR-004): an
	 * invite is found by the hash of its 128-bit token alone, the service
	 * refuses a revoked, expired or used-up one, and redeeming only ever adds
	 * the signed-in caller to the invite's staff role. Rate limited per user.
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-a-game-master-invites-with-a-link-req-asr-004
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 10, period: 300)]
	public function redeem(): JSONResponse {
		$user = $this->callerAccess->user();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			return new JSONResponse(data: $this->invites->redeem(token: (string)$this->request->getParam('token', ''), user: $user));
		} catch (OutOfBoundsException) {
			return new JSONResponse(data: ['error' => 'No such invite'], statusCode: Http::STATUS_NOT_FOUND);
		} catch (DomainException $exception) {
			$state = $exception->getMessage();
			return new JSONResponse(data: ['error' => self::REFUSALS[$state] ?? 'This invite adds nobody', 'state' => $state], statusCode: Http::STATUS_GONE);
		} catch (Throwable $exception) {
			$this->logger->error('[StaffInvitesController] redeeming an invite failed: ' . $exception->getMessage());
			return new JSONResponse(data: ['error' => 'The invite could not be redeemed'], statusCode: Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}//end redeem()

	/**
	 * Revoke an invite.
	 *
	 * @param string $id The invite id.
	 *
	 * @return JSONResponse 200 with the revoked invite; 401, 403 or 404.
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-game-masters-see-and-manage-the-team-req-asr-005
	 */
	#[NoAdminRequired]
	public function revoke(string $id): JSONResponse {
		[$actingUid, $denied] = $this->callerAccess->resolveGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		try {
			return new JSONResponse(data: $this->invites->revoke(id: $id, actingUid: $actingUid));
		} catch (Throwable $exception) {
			return new JSONResponse(data: ['error' => 'Invite not found'], statusCode: Http::STATUS_NOT_FOUND);
		}
	}//end revoke()

	/**
	 * The members of each role and every invite with its state.
	 *
	 * @return JSONResponse 200 with roles and invites; 401 or 403.
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-game-masters-see-and-manage-the-team-req-asr-005
	 */
	#[NoAdminRequired]
	public function team(): JSONResponse {
		[, $denied] = $this->callerAccess->resolveGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		try {
			return new JSONResponse(data: $this->invites->team());
		} catch (Throwable $exception) {
			$this->logger->error('[StaffInvitesController] reading the team failed: ' . $exception->getMessage());
			return new JSONResponse(data: ['error' => 'The team could not be read'], statusCode: Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}//end team()

	/**
	 * Remove a member from a role.
	 *
	 * @param string $role The role group.
	 * @param string $uid The member.
	 *
	 * @return JSONResponse 200 with removed; 400, 401, 403, or 404 when the user is not in the role.
	 *
	 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-game-masters-see-and-manage-the-team-req-asr-005
	 */
	#[NoAdminRequired]
	public function removeMember(string $role, string $uid): JSONResponse {
		[$actingUid, $denied] = $this->callerAccess->resolveGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		try {
			$removed = $this->invites->removeMember(role: $role, uid: $uid, actingUid: $actingUid);
		} catch (InvalidArgumentException | DomainException $exception) {
			return new JSONResponse(data: ['error' => $exception->getMessage()], statusCode: Http::STATUS_BAD_REQUEST);
		}

		if ($removed === false) {
			return new JSONResponse(data: ['error' => 'Not a member of this role'], statusCode: Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse(data: ['removed' => true, 'role' => $role, 'uid' => $uid]);
	}//end removeMember()
}//end class
