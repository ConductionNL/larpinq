<?php

/**
 * Golden test for the character sheet download of CharactersController.
 *
 * Each case calls downloadPdf() with scripted fetcher, renderer, session and
 * group answers, and records the response (status, body or download) and
 * every fetcher and renderer call. Compared with
 * fixtures/characters-pdf.golden.json. Set RECORD_GOLDEN=1 to rewrite it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/pdf-export/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\CharactersController;
use OCA\Larpinq\Service\DocuDeskPdfRenderer;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\SkillRequirementService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * The character sheet download, case by case.
 *
 * @covers \OCA\Larpinq\Controller\CharactersController
 * @uses   \OCA\Larpinq\Controller\CallerAccess
 */
class CharactersPdfGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/characters-pdf.golden.json';

	private const TEMPLATE = '0b6f1c7e-1d2a-4c3b-9e8f-112233445566';

	/**
	 * The cases.
	 *
	 * @return array<string,array<string,mixed>> Case name to case.
	 */
	private static function cases(): array {
		$cases = [
			'signed out' => ['uid' => null],
			'player' => ['uid' => 'player1'],
			'game master who is not an admin' => ['uid' => 'gm1'],
			'admin' => [],
			'invalid template' => ['template' => '../../etc/passwd'],
			'invalid template, player' => ['template' => 'x', 'uid' => 'player1'],
			'invalid template, no DocuDesk' => ['template' => 'x', 'docudesk' => false],
			'no DocuDesk' => ['docudesk' => false],
			'character does not exist' => ['fetch' => 'does-not-exist'],
			'character read fails' => ['fetch' => 'exception'],
			'no template' => ['templateData' => null],
			'render failed' => ['pdf' => null],
			'unnamed character' => ['character' => ['id' => 'c1']],
			'empty name' => ['character' => ['id' => 'c1', 'name' => '']],
			'numeric name' => ['character' => ['id' => 'c1', 'name' => 42]],
		];

		foreach ($cases as $name => $case) {
			$cases[$name] = $case + [
				'uid' => 'admin1',
				'template' => self::TEMPLATE,
				'docudesk' => true,
				'fetch' => 'ok',
				'character' => ['id' => 'c1', 'name' => 'Mirela'],
				'templateData' => ['id' => self::TEMPLATE, 'name' => 'Sheet'],
				'pdf' => '%PDF-1.7 sheet',
			];
		}

		return $cases;
	}//end cases()

	/**
	 * Every case matches the recorded outcome.
	 *
	 * @return void
	 */
	public function testMatchesTheRecordedOutcomes(): void {
		$actual = [];
		foreach (self::cases() as $name => $case) {
			$actual[$name] = json_encode(value: $this->outcome(case: $case), flags: JSON_PRETTY_PRINT);
		}

		if (getenv('RECORD_GOLDEN') === '1') {
			file_put_contents(filename: self::FIXTURE, data: json_encode(value: $actual, flags: JSON_PRETTY_PRINT)."\n");
		}

		$expected = json_decode(json: (string)file_get_contents(filename: self::FIXTURE), associative: true);
		foreach ($expected as $name => $outcome) {
			self::assertSame(expected: $outcome, actual: $actual[$name] ?? null, message: $name);
		}

		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
	}//end testMatchesTheRecordedOutcomes()

	/**
	 * Run one case through the controller.
	 *
	 * @param array<string,mixed> $case The case.
	 *
	 * @return array<string,mixed> The response and the calls.
	 */
	private function outcome(array $case): array {
		$calls = [];
		$controller = new CharactersController(
			appName: 'larpinq',
			request: $this->createMock(originalClassName: IRequest::class),
			objectFetcher: $this->fetcher(case: $case, calls: $calls),
			pdfRenderer: $this->renderer(case: $case, calls: $calls),
			callerAccess: $this->callerAccess(uid: $case['uid']),
			requirementService: $this->createMock(originalClassName: SkillRequirementService::class)
		);

		$response = $controller->downloadPdf(id: 'c1', template: $case['template']);
		$outcome = ['status' => $response->getStatus(), 'calls' => $calls];
		if ($response instanceof DataDownloadResponse) {
			$headers = new \ReflectionProperty(Response::class, 'headers');
			$outcome['download'] = [$response->render(), $headers->getValue($response)['Content-Disposition'] ?? null];
		}

		if ($response instanceof JSONResponse) {
			$outcome['data'] = $response->getData();
		}

		return $outcome;
	}//end outcome()

	/**
	 * The object fetcher, recording each call.
	 *
	 * @param array<string,mixed> $case  The case.
	 * @param array<int,mixed>    $calls The call log.
	 *
	 * @return RegisterObjectFetcher The double.
	 */
	private function fetcher(array $case, array &$calls): RegisterObjectFetcher {
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObject')->willReturnCallback(
			function (string $objectType, string $id) use ($case, &$calls): array {
				$calls[] = ['getObject', $objectType, $id];
				if ($case['fetch'] === 'does-not-exist') {
					throw new DoesNotExistException('gone');
				}

				if ($case['fetch'] === 'exception') {
					throw new \RuntimeException('unreadable');
				}

				return $case['character'];
			}
		);

		return $fetcher;
	}//end fetcher()

	/**
	 * The PDF renderer, recording each call.
	 *
	 * @param array<string,mixed> $case  The case.
	 * @param array<int,mixed>    $calls The call log.
	 *
	 * @return DocuDeskPdfRenderer The double.
	 */
	private function renderer(array $case, array &$calls): DocuDeskPdfRenderer {
		$renderer = $this->createMock(originalClassName: DocuDeskPdfRenderer::class);
		$renderer->method('normaliseTemplateId')->willReturnCallback(
			function (string $template) use (&$calls): ?string {
				$calls[] = ['normaliseTemplateId', $template];
				if (preg_match('/^[0-9a-f-]{36}$/', $template) !== 1) {
					return null;
				}

				return $template;
			}
		);
		$renderer->method('isDocuDeskAvailable')->willReturnCallback(
			function () use ($case, &$calls): bool {
				$calls[] = ['isDocuDeskAvailable'];
				return $case['docudesk'];
			}
		);
		$renderer->method('getTemplate')->willReturnCallback(
			function (string $templateId) use ($case, &$calls): ?array {
				$calls[] = ['getTemplate', $templateId];
				return $case['templateData'];
			}
		);
		$renderer->method('render')->willReturnCallback(
			function (array $templateData, array $context) use ($case, &$calls): ?string {
				$calls[] = ['render', $templateData, $context];
				return $case['pdf'];
			}
		);

		return $renderer;
	}//end renderer()

	/**
	 * The real caller access over a session and a group manager that know two users.
	 *
	 * @param string|null $uid The signed-in user, or null.
	 *
	 * @return CallerAccess The caller access.
	 */
	private function callerAccess(?string $uid): CallerAccess {
		$session = $this->createMock(originalClassName: IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(fn (string $who): bool => $who === 'admin1');
		$groups->method('isInGroup')->willReturnCallback(
			fn (string $who, string $group): bool => $who === 'gm1' && $group === 'gamemasters'
		);

		return new CallerAccess(userSession: $session, groupManager: $groups);
	}//end callerAccess()
}//end class
