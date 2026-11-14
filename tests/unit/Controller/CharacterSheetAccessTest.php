<?php

/**
 * The character sheet access matrix: admin, game master, owner.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @category  Test
 * @package   OCA\Larpinq\Tests\Unit\Controller
 * @author    Conduction B.V. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/player-character-sheet-access/specs/pdf-export/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\CharactersController;
use OCA\Larpinq\Service\DocuDeskPdfRenderer;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\SkillRequirementService;
use OCA\Larpinq\Support\AppIdentity;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Tests who may download a character sheet and what an owner's render contains.
 *
 * @covers \OCA\Larpinq\Controller\CharactersController
 * @covers \OCA\Larpinq\Controller\CallerAccess
 */
class CharacterSheetAccessTest extends TestCase {

	private const TEMPLATE = '00000000-0000-4000-8000-00000000000a';

	private const CHARACTER_ID = '11111111-2222-4333-8444-555555555555';

	/** @var array<string,mixed>|null The character the fetch answers, or null for a missing one. */
	private ?array $character = [
		'id' => self::CHARACTER_ID,
		'name' => 'Sir Lancelot',
		'ownerUid' => 'alice',
		'slNotesPrivate' => 'Secretly the traitor',
		'slNotesPublic' => 'Knight of the round table',
	];

	/** @var list<array> The contexts handed to the renderer. */
	private array $rendered = [];

	/**
	 * Download the sheet as a caller.
	 *
	 * @param string $uid   The caller.
	 * @param bool   $admin Whether the caller is a Nextcloud admin.
	 * @param bool   $gm    Whether the caller is in the game-master group.
	 *
	 * @return DataDownloadResponse|JSONResponse
	 */
	private function downloadAs(string $uid, bool $admin = false, bool $gm = false): DataDownloadResponse|JSONResponse {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $who): bool => $who === $uid && $admin);
		$groups->method('isInGroup')->willReturnCallback(
			static fn (string $who, string $group): bool => $who === $uid && $gm && $group === AppIdentity::GM_GROUP
		);

		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObject')->willReturnCallback(function (): array {
			if ($this->character === null) {
				throw new DoesNotExistException(msg: 'missing');
			}

			return $this->character;
		});

		$renderer = $this->createMock(originalClassName: DocuDeskPdfRenderer::class);
		$renderer->method('normaliseTemplateId')->willReturnArgument(0);
		$renderer->method('isDocuDeskAvailable')->willReturn(true);
		$renderer->method('getTemplate')->willReturn(['id' => self::TEMPLATE, 'name' => 'Sheet']);
		$renderer->method('render')->willReturnCallback(function (array $templateData, array $context): string {
			$this->rendered[] = $context;

			return '%PDF-1.7 sheet';
		});

		$controller = new CharactersController(
			appName: 'larpinq',
			request: $this->createMock(originalClassName: IRequest::class),
			objectFetcher: $fetcher,
			pdfRenderer: $renderer,
			callerAccess: new CallerAccess(userSession: $session, groupManager: $groups),
			requirementService: $this->createMock(originalClassName: SkillRequirementService::class),
		);

		return $controller->downloadPdf(id: self::CHARACTER_ID, template: self::TEMPLATE);
	}//end downloadAs()

	/**
	 * The owner gets the PDF, and the render context has no GM-private notes.
	 *
	 * @return void
	 */
	public function testTheOwnerDownloadsTheirSheetWithoutTheGmPrivateNotes(): void {
		$result = $this->downloadAs(uid: 'alice');

		self::assertInstanceOf(expected: DataDownloadResponse::class, actual: $result);
		self::assertSame(expected: '%PDF-1.7 sheet', actual: $result->render());
		self::assertCount(expectedCount: 1, haystack: $this->rendered);
		self::assertArrayNotHasKey(key: 'slNotesPrivate', array: $this->rendered[0]['character']);
		self::assertSame(expected: 'Knight of the round table', actual: $this->rendered[0]['character']['slNotesPublic']);
		self::assertSame(expected: 'Sir Lancelot', actual: $this->rendered[0]['character']['name']);
	}

	/**
	 * A player who does not own the character gets 403.
	 *
	 * @return void
	 */
	public function testAnotherPlayerIsDeniedForAnExistingCharacter(): void {
		$result = $this->downloadAs(uid: 'bob');

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: 403, actual: $result->getStatus());
		self::assertSame(expected: ['error' => 'Access denied'], actual: $result->getData());
		self::assertSame(expected: [], actual: $this->rendered);
	}

	/**
	 * A non-owner cannot tell an existing character from a missing one.
	 *
	 * @return void
	 */
	public function testAnotherPlayerGetsTheSameAnswerForAMissingCharacter(): void {
		$existing = $this->downloadAs(uid: 'bob');
		$this->character = null;
		$missing = $this->downloadAs(uid: 'bob');

		self::assertSame(expected: [$existing->getStatus(), $existing->getData()], actual: [$missing->getStatus(), $missing->getData()]);
	}

	/**
	 * A game master who is not an admin gets the sheet with the GM-private notes.
	 *
	 * @return void
	 */
	public function testAGameMasterGetsTheSheetWithTheGmPrivateNotes(): void {
		$result = $this->downloadAs(uid: 'carol', gm: true);

		self::assertInstanceOf(expected: DataDownloadResponse::class, actual: $result);
		self::assertSame(expected: 'Secretly the traitor', actual: $this->rendered[0]['character']['slNotesPrivate']);
	}

	/**
	 * An admin gets the full sheet, as before.
	 *
	 * @return void
	 */
	public function testAnAdminGetsTheFullSheetAsBefore(): void {
		$result = $this->downloadAs(uid: 'root', admin: true);

		self::assertInstanceOf(expected: DataDownloadResponse::class, actual: $result);
		self::assertSame(expected: $this->character, actual: $this->rendered[0]['character']);
	}

	/**
	 * A game master still gets 404 for a missing character.
	 *
	 * @return void
	 */
	public function testAGameMasterStillGetsNotFoundForAMissingCharacter(): void {
		$this->character = null;

		$result = $this->downloadAs(uid: 'carol', gm: true);

		self::assertSame(expected: 404, actual: $result->getStatus());
	}

	/**
	 * An empty or absent owner matches no caller.
	 *
	 * @return void
	 */
	public function testACharacterWithoutAnOwnerGrantsNoPlayerAccess(): void {
		foreach ([['ownerUid' => ''], [], ['ownerUid' => null]] as $owner) {
			$this->character = array_merge(['id' => self::CHARACTER_ID, 'name' => 'Nobody\'s'], $owner);

			self::assertSame(expected: 403, actual: $this->downloadAs(uid: '')->getStatus(), message: json_encode(value: $owner));
			self::assertSame(expected: 403, actual: $this->downloadAs(uid: 'bob')->getStatus(), message: json_encode(value: $owner));
		}

		self::assertSame(expected: [], actual: $this->rendered);
	}

	/**
	 * The route is open to non-admins, so the guard in the body decides.
	 *
	 * @return void
	 */
	public function testTheRouteIsOpenToNonAdminsSoTheBodyGuardDecides(): void {
		$method = new \ReflectionMethod(objectOrMethod: CharactersController::class, method: 'downloadPdf');
		$attributes = $method->getAttributes(name: NoAdminRequired::class);

		self::assertCount(expectedCount: 1, haystack: $attributes);
	}
}
