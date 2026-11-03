<?php

/**
 * Unit tests for CharactersController.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use Exception;
use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\CharactersController;
use OCA\Larpinq\Service\DocuDeskPdfRenderer;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for CharactersController.
 */
class CharactersControllerTest extends TestCase {

	/** @var CharactersController The controller. */
	private CharactersController $controller;
	/** @var RegisterObjectFetcher&MockObject The object fetcher. */
	private RegisterObjectFetcher&MockObject $objectFetcher;
	/** @var IAppManager&MockObject The app manager. */
	private IAppManager&MockObject $appManager;
	/** @var ContainerInterface&MockObject The container. */
	private ContainerInterface&MockObject $container;
	/** @var IUserSession&MockObject The user session. */
	private IUserSession&MockObject $userSession;
	/** @var IGroupManager&MockObject The group manager. */
	private IGroupManager&MockObject $groupManager;
	/** @var LoggerInterface&MockObject The logger. */
	private LoggerInterface&MockObject $logger;
	/** @var \OCA\Larpinq\Service\SkillRequirementService&MockObject The requirement service. */
	private \OCA\Larpinq\Service\SkillRequirementService&MockObject $requirementService;

	/**
	 * A REAL renderer wired to the same IAppManager / ContainerInterface / Logger
	 * mocks the controller used to hold directly. Keeping it real (rather than a
	 * mock of the renderer) means every downloadPdf test below still drives the
	 * identical end-to-end pipeline — appManager -> container -> TemplateService
	 * -> PdfService — so these tests remain a behaviour-preservation check across
	 * the consolidation, not an assertion about the new internal shape.
	 */
	private DocuDeskPdfRenderer $pdfRenderer;

	/**
	 * Build the doubles and the subject every test starts from.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->objectFetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$this->appManager = $this->createMock(originalClassName: IAppManager::class);
		$this->container = $this->createMock(originalClassName: ContainerInterface::class);
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
		$this->groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
		$this->requirementService = $this->createMock(originalClassName: \OCA\Larpinq\Service\SkillRequirementService::class);

		$this->pdfRenderer = new DocuDeskPdfRenderer(
			appManager: $this->appManager,
			container: $this->container,
			logger: $this->logger,
		);

		// Default: authenticated admin user.
		$mockUser = $this->createMock(originalClassName: IUser::class);
		$mockUser->method('getUID')->willReturn('admin');
		$this->userSession->method('getUser')->willReturn($mockUser);
		$this->groupManager->method('isAdmin')->with('admin')->willReturn(true);

		$this->controller = new CharactersController(
			appName: 'larpinq',
			request: $this->createMock(originalClassName: IRequest::class),
			objectFetcher: $this->objectFetcher,
			pdfRenderer: $this->pdfRenderer,
			callerAccess: new CallerAccess(userSession: $this->userSession, groupManager: $this->groupManager),
			requirementService: $this->requirementService,
		);
	}

	/**
	 * Download PDF returns401 when not authenticated.
	 *
	 * @return void
	 */
	public function testDownloadPdfReturns401WhenNotAuthenticated(): void {
		$unauthSession = $this->createMock(originalClassName: IUserSession::class);
		$unauthSession->method('getUser')->willReturn(null);

		$controller = new CharactersController(
			appName: 'larpinq',
			request: $this->createMock(originalClassName: IRequest::class),
			objectFetcher: $this->objectFetcher,
			pdfRenderer: $this->pdfRenderer,
			callerAccess: new CallerAccess(userSession: $unauthSession, groupManager: $this->groupManager),
			requirementService: $this->requirementService,
		);

		$result = $controller->downloadPdf('char-1', 'tpl-1');

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: 401, actual: $result->getStatus());
		self::assertArrayHasKey(key: 'error', array: $result->getData());
	}

	/**
	 * Download PDF returns403 for non admin user.
	 *
	 * @return void
	 */
	public function testDownloadPdfReturns403ForNonAdminUser(): void {
		$nonAdminUser = $this->createMock(originalClassName: IUser::class);
		$nonAdminUser->method('getUID')->willReturn('player1');

		$nonAdminSession = $this->createMock(originalClassName: IUserSession::class);
		$nonAdminSession->method('getUser')->willReturn($nonAdminUser);

		$nonAdminGroupManager = $this->createMock(originalClassName: IGroupManager::class);
		$nonAdminGroupManager->method('isAdmin')->with('player1')->willReturn(false);

		$controller = new CharactersController(
			appName: 'larpinq',
			request: $this->createMock(originalClassName: IRequest::class),
			objectFetcher: $this->objectFetcher,
			pdfRenderer: $this->pdfRenderer,
			callerAccess: new CallerAccess(userSession: $nonAdminSession, groupManager: $nonAdminGroupManager),
			requirementService: $this->requirementService,
		);

		$result = $controller->downloadPdf('char-1', 'tpl-1');

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: 403, actual: $result->getStatus());
		self::assertArrayHasKey(key: 'error', array: $result->getData());
	}

	/**
	 * Download PDF returns424 when docu desk not installed.
	 *
	 * @return void
	 */
	public function testDownloadPdfReturns424WhenDocuDeskNotInstalled(): void {
		$this->appManager->method('isInstalled')->willReturnCallback(
			// An instance running EITHER name must resolve. Pinning 'docudesk'
			// here is what let the production regression through unnoticed.
			static fn (string $id): bool => in_array($id, ['filinq', 'docudesk'], true)
		);
		$this->appManager->method('isEnabledForUser')
			->with(self::logicalOr('filinq', 'docudesk'))
			->willReturn(false);

		// The template MUST be a well-formed UUID here. downloadPdf() validates
		// the template id BEFORE probing for DocuDesk, so that a malformed id is
		// a 400 regardless of which optional apps are installed. Passing a
		// non-UUID (this test used to pass 'tpl-1') would short-circuit at the
		// 400 and this assertion would be green without the 424 branch ever
		// being reached — two different failure modes conflated into one.
		$result = $this->controller->downloadPdf('char-1', '00000000-0000-4000-8000-000000000010');

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: 424, actual: $result->getStatus());
		self::assertStringContainsString(needle: 'DocuDesk', haystack: $result->getData()['error']);
	}

	/**
	 * A malformed template id is a 400 even when DocuDesk is ABSENT.
	 *
	 * This is the combination no test covered, and the gap is exactly why the
	 * bug survived: every existing case fixed one guard while leaving the other
	 * in its passing state, so either guard order satisfied all of them. On CI
	 * — where DocuDesk is not installed alongside Larpinq — the availability
	 * probe ran first and answered 424 to every request, making the documented
	 * 400 contract unreachable and hiding the fact that a crafted template
	 * value was never rejected on its own merits. Reverting the guard order in
	 * CharactersController::downloadPdf() turns this test red (424 != 400).
	 */
	public function testDownloadPdfReturns400ForNonUuidTemplateEvenWhenDocuDeskAbsent(): void {
		$this->appManager->method('isInstalled')->willReturnCallback(
			// An instance running EITHER name must resolve. Pinning 'docudesk'
			// here is what let the production regression through unnoticed.
			static fn (string $id): bool => in_array($id, ['filinq', 'docudesk'], true)
		);
		$this->appManager->method('isEnabledForUser')
			->with(self::logicalOr('filinq', 'docudesk'))
			->willReturn(false);

		$result = $this->controller->downloadPdf('char-1', 'not-a-uuid');

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: 400, actual: $result->getStatus());
		self::assertStringContainsString(needle: 'UUID', haystack: $result->getData()['error']);
	}

	/**
	 * Download PDF returns404 when character not found.
	 *
	 * @return void
	 */
	public function testDownloadPdfReturns404WhenCharacterNotFound(): void {
		// FleetAppId resolves the installed id BEFORE asking whether it is
		// enabled, so both probes are stubbed. Stubbing only the second one
		// leaves the resolver with no id and every PDF route answers 424.
		$this->appManager->method('isInstalled')->willReturn(true);
		$this->appManager->method('isEnabledForUser')->willReturn(true);
		$this->objectFetcher->method('getObject')
			->willThrowException(new Exception('Not found'));

		$result = $this->controller->downloadPdf('nonexistent', '00000000-0000-0000-0000-000000000001');

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: 404, actual: $result->getStatus());
		self::assertSame(expected: 'Character not found', actual: $result->getData()['error']);
	}

	/**
	 * Download PDF returns404 when template not found.
	 *
	 * @return void
	 */
	public function testDownloadPdfReturns404WhenTemplateNotFound(): void {
		// FleetAppId resolves the installed id BEFORE asking whether it is
		// enabled, so both probes are stubbed. Stubbing only the second one
		// leaves the resolver with no id and every PDF route answers 424.
		$this->appManager->method('isInstalled')->willReturn(true);
		$this->appManager->method('isEnabledForUser')->willReturn(true);
		$this->objectFetcher->method('getObject')
			->willReturn(['id' => 'char-1', 'name' => 'Fighter']);

		$mockTemplateService = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['getTemplate'])
			->getMock();
		$mockTemplateService->method('getTemplate')
			->willThrowException(new Exception('Template not found'));

		$this->container->method('get')
			->willReturnCallback(function (string $class) use ($mockTemplateService) {
				if ($class === 'OCA\DocuDesk\Service\TemplateService') {
					return $mockTemplateService;
				}
				return null;
			});

		$result = $this->controller->downloadPdf('char-1', '00000000-0000-0000-0000-000000000002');

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: 404, actual: $result->getStatus());
	}

	/**
	 * Download PDF returns data download response on success.
	 *
	 * @return void
	 */
	public function testDownloadPdfReturnsDataDownloadResponseOnSuccess(): void {
		// FleetAppId resolves the installed id BEFORE asking whether it is
		// enabled, so both probes are stubbed. Stubbing only the second one
		// leaves the resolver with no id and every PDF route answers 424.
		$this->appManager->method('isInstalled')->willReturn(true);
		$this->appManager->method('isEnabledForUser')->willReturn(true);
		$this->objectFetcher->method('getObject')
			->willReturn(['id' => 'char-1', 'name' => 'Sir Lancelot']);

		$mockTemplateService = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['getTemplate'])
			->getMock();
		$mockTemplateService->method('getTemplate')
			->willReturn([
				'content' => '<h1>{{ character.name }}</h1>',
				'format' => 'A4',
				'orientation' => 'P',
			]);

		$mockPdfService = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['renderPdf'])
			->getMock();
		$mockPdfService->method('renderPdf')
			->willReturn('%PDF-1.4 mock content');

		$this->container->method('get')
			->willReturnCallback(function (string $class) use ($mockTemplateService, $mockPdfService) {
				if ($class === 'OCA\DocuDesk\Service\TemplateService') {
					return $mockTemplateService;
				}
				if ($class === 'OCA\DocuDesk\Service\PdfService') {
					return $mockPdfService;
				}
				return null;
			});

		$result = $this->controller->downloadPdf('char-1', '00000000-0000-0000-0000-000000000003');

		self::assertInstanceOf(expected: DataDownloadResponse::class, actual: $result);
	}

	/**
	 * Download PDF returns500 on render failure with generic message.
	 *
	 * @return void
	 */
	public function testDownloadPdfReturns500OnRenderFailureWithGenericMessage(): void {
		// FleetAppId resolves the installed id BEFORE asking whether it is
		// enabled, so both probes are stubbed. Stubbing only the second one
		// leaves the resolver with no id and every PDF route answers 424.
		$this->appManager->method('isInstalled')->willReturn(true);
		$this->appManager->method('isEnabledForUser')->willReturn(true);
		$this->objectFetcher->method('getObject')
			->willReturn(['id' => 'char-1', 'name' => 'Fighter']);

		$mockTemplateService = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['getTemplate'])
			->getMock();
		$mockTemplateService->method('getTemplate')
			->willReturn(['content' => '', 'format' => 'A4', 'orientation' => 'P']);

		$mockPdfService = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['renderPdf'])
			->getMock();
		$mockPdfService->method('renderPdf')
			->willThrowException(new Exception('Render failed: /internal/path/file.php line 42'));

		$this->container->method('get')
			->willReturnCallback(function (string $class) use ($mockTemplateService, $mockPdfService) {
				if ($class === 'OCA\DocuDesk\Service\TemplateService') {
					return $mockTemplateService;
				}
				if ($class === 'OCA\DocuDesk\Service\PdfService') {
					return $mockPdfService;
				}
				return null;
			});

		$result = $this->controller->downloadPdf('char-1', '00000000-0000-0000-0000-000000000004');

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: 500, actual: $result->getStatus());
		self::assertStringContainsString(needle: 'PDF generation failed', haystack: $result->getData()['error']);
		// The raw exception message (including internal paths) must NOT be leaked.
		self::assertStringNotContainsString(needle: '/internal/path/file.php', haystack: $result->getData()['error']);
	}

	/**
	 * Download PDF returns400 for non UUID template.
	 *
	 * @return void
	 */
	public function testDownloadPdfReturns400ForNonUuidTemplate(): void {
		// FleetAppId resolves the installed id BEFORE asking whether it is
		// enabled, so both probes are stubbed. Stubbing only the second one
		// leaves the resolver with no id and every PDF route answers 424.
		$this->appManager->method('isInstalled')->willReturn(true);
		$this->appManager->method('isEnabledForUser')->willReturn(true);

		$result = $this->controller->downloadPdf('char-1', '../../etc/passwd');

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: 400, actual: $result->getStatus());
		self::assertStringContainsString(needle: 'UUID', haystack: $result->getData()['error']);
	}

	/**
	 * Download PDF translates does not exist exception to404.
	 *
	 * @return void
	 */
	public function testDownloadPdfTranslatesDoesNotExistExceptionTo404(): void {
		// FleetAppId resolves the installed id BEFORE asking whether it is
		// enabled, so both probes are stubbed. Stubbing only the second one
		// leaves the resolver with no id and every PDF route answers 424.
		$this->appManager->method('isInstalled')->willReturn(true);
		$this->appManager->method('isEnabledForUser')->willReturn(true);
		$this->objectFetcher->method('getObject')
			->willThrowException(new DoesNotExistException('no such character'));

		$result = $this->controller->downloadPdf('char-1', '00000000-0000-0000-0000-00000000000a');

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: 404, actual: $result->getStatus());
		self::assertSame(expected: 'Character not found', actual: $result->getData()['error']);
		// The OR exception message must not leak to the client.
		self::assertStringNotContainsString(needle: 'no such character', haystack: $result->getData()['error']);
	}

	/**
	 * The consolidation must not alter what DocuDesk actually receives. Pins the
	 * template body, the render context and the page options passed to renderPdf,
	 * plus the derived download filename — the details a refactor could silently
	 * change while every status-code assertion stayed green.
	 */
	public function testDownloadPdfPassesUnchangedContentContextAndOptionsToDocuDesk(): void {
		// FleetAppId resolves the installed id BEFORE asking whether it is
		// enabled, so both probes are stubbed. Stubbing only the second one
		// leaves the resolver with no id and every PDF route answers 424.
		$this->appManager->method('isInstalled')->willReturn(true);
		$this->appManager->method('isEnabledForUser')->willReturn(true);

		$character = ['id' => 'char-1', 'name' => 'Sir Lancelot'];
		$this->objectFetcher->method('getObject')->willReturn($character);

		$templateData = [
			'content' => '<h1>{{ character.name }}</h1>',
			'format' => 'A3',
			'orientation' => 'L',
		];

		$mockTemplateService = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['getTemplate'])
			->getMock();
		$mockTemplateService->method('getTemplate')->willReturn($templateData);

		$mockPdfService = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['renderPdf'])
			->getMock();
		$mockPdfService->expects(self::once())
			->method('renderPdf')
			->with(
				'<h1>{{ character.name }}</h1>',
				['character' => $character, 'template' => $templateData],
				['format' => 'A3', 'orientation' => 'L'],
			)
			->willReturn('%PDF-1.4 mock content');

		$this->container->method('get')
			->willReturnCallback(function (string $class) use ($mockTemplateService, $mockPdfService) {
				if ($class === 'OCA\DocuDesk\Service\TemplateService') {
					return $mockTemplateService;
				}
				if ($class === 'OCA\DocuDesk\Service\PdfService') {
					return $mockPdfService;
				}
				return null;
			});

		$result = $this->controller->downloadPdf('char-1', '00000000-0000-0000-0000-00000000000b');

		self::assertInstanceOf(expected: DataDownloadResponse::class, actual: $result);
		self::assertSame(expected: '%PDF-1.4 mock content', actual: $result->render());
		self::assertStringContainsString(
			needle: 'Sir Lancelot_character_sheet.pdf',
			haystack: $this->filenameFromResponse(response: $result),
		);
	}

	/**
	 * The template id is lower-cased before it reaches DocuDesk, so an
	 * upper-case UUID resolves the same template as its lower-case form.
	 */
	public function testDownloadPdfLowerCasesTemplateIdBeforeLookup(): void {
		// FleetAppId resolves the installed id BEFORE asking whether it is
		// enabled, so both probes are stubbed. Stubbing only the second one
		// leaves the resolver with no id and every PDF route answers 424.
		$this->appManager->method('isInstalled')->willReturn(true);
		$this->appManager->method('isEnabledForUser')->willReturn(true);
		$this->objectFetcher->method('getObject')->willReturn(['id' => 'char-1', 'name' => 'Fighter']);

		$mockTemplateService = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['getTemplate'])
			->getMock();
		$mockTemplateService->expects(self::once())
			->method('getTemplate')
			->with('0000000a-0000-0000-0000-00000000000c')
			->willReturn(['content' => '', 'format' => 'A4', 'orientation' => 'P']);

		$mockPdfService = $this->getMockBuilder(className: \stdClass::class)
			->addMethods(['renderPdf'])
			->getMock();
		$mockPdfService->method('renderPdf')->willReturn('%PDF-1.4');

		$this->container->method('get')
			->willReturnCallback(function (string $class) use ($mockTemplateService, $mockPdfService) {
				if ($class === 'OCA\DocuDesk\Service\TemplateService') {
					return $mockTemplateService;
				}
				if ($class === 'OCA\DocuDesk\Service\PdfService') {
					return $mockPdfService;
				}
				return null;
			});

		$result = $this->controller->downloadPdf('char-1', '0000000A-0000-0000-0000-00000000000C');

		self::assertInstanceOf(expected: DataDownloadResponse::class, actual: $result);
	}

	/**
	 * Read the Content-Disposition off a response without calling getHeaders(),
	 * which reaches into the \OC runtime container and is unavailable in a pure
	 * unit test. The protected `headers` array is populated by addHeader() in the
	 * DownloadResponse constructor, so reflection sees the real value.
	 */
	private function filenameFromResponse(DataDownloadResponse $response): string {
		$property = new \ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers');
		$property->setAccessible(true);
		/* @var array<string,string> $headers */
		$headers = $property->getValue($response);

		foreach ($headers as $name => $value) {
			if (strtolower((string)$name) === 'content-disposition') {
				return (string)$value;
			}
		}
		return '';
	}

	/**
	 * Requirement report translates does not exist exception to404.
	 *
	 * @return void
	 */
	public function testRequirementReportTranslatesDoesNotExistExceptionTo404(): void {
		$this->objectFetcher->method('getObject')
			->willThrowException(new DoesNotExistException('no such character'));

		$result = $this->controller->requirementReport('00000000-0000-0000-0000-00000000000d');

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: 404, actual: $result->getStatus());
		self::assertSame(expected: 'Character not found', actual: $result->getData()['error']);
	}

	/**
	 * Requirement report returns401 when not authenticated.
	 *
	 * @return void
	 */
	public function testRequirementReportReturns401WhenNotAuthenticated(): void {
		$unauthSession = $this->createMock(originalClassName: IUserSession::class);
		$unauthSession->method('getUser')->willReturn(null);

		$controller = new CharactersController(
			appName: 'larpinq',
			request: $this->createMock(originalClassName: IRequest::class),
			objectFetcher: $this->objectFetcher,
			pdfRenderer: $this->pdfRenderer,
			callerAccess: new CallerAccess(userSession: $unauthSession, groupManager: $this->groupManager),
			requirementService: $this->requirementService,
		);

		$result = $controller->requirementReport('00000000-0000-0000-0000-000000000005');

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: 401, actual: $result->getStatus());
	}

	/**
	 * Requirement report returns404 when character not found.
	 *
	 * @return void
	 */
	public function testRequirementReportReturns404WhenCharacterNotFound(): void {
		$this->objectFetcher->method('getObject')
			->willThrowException(new Exception('Not found'));

		$result = $this->controller->requirementReport('00000000-0000-0000-0000-000000000006');

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: 404, actual: $result->getStatus());
	}

	/**
	 * Requirement report returns report.
	 *
	 * @return void
	 */
	public function testRequirementReportReturnsReport(): void {
		$this->objectFetcher->method('getObject')
			->willReturn(['id' => 'char-1', 'name' => 'Fighter', 'skills' => []]);

		$report = ['valid' => true, 'requirements' => [], 'budget' => ['ok' => true], 'dependents' => []];
		$this->requirementService->method('validate')->willReturn($report);

		$result = $this->controller->requirementReport('00000000-0000-0000-0000-000000000007');

		self::assertInstanceOf(expected: JSONResponse::class, actual: $result);
		self::assertSame(expected: 200, actual: $result->getStatus());
		self::assertSame(expected: $report, actual: $result->getData());
	}
}
