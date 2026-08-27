<?php

/**
 * Unit tests for DocuDeskPdfRenderer.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use Exception;
use OCA\Larpinq\Service\DocuDeskPdfRenderer;
use OCP\App\IAppManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for the shared DocuDesk PDF rendering helper.
 *
 * This helper is the single rendering path for both the character-sheet export
 * and the event run-sheet export, so a regression here breaks two user-facing
 * downloads at once.
 */
class DocuDeskPdfRendererTest extends TestCase {

	private IAppManager&MockObject $appManager;
	private ContainerInterface&MockObject $container;
	private LoggerInterface&MockObject $logger;
	private DocuDeskPdfRenderer $renderer;

	protected function setUp(): void {
		parent::setUp();

		$this->appManager = $this->createMock(IAppManager::class);
		$this->container = $this->createMock(ContainerInterface::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->renderer = new DocuDeskPdfRenderer(
			$this->appManager,
			$this->container,
			$this->logger,
		);
	}

	public function testIsDocuDeskAvailableReflectsAppManager(): void {
		$this->appManager->expects(self::once())
			->method('isEnabledForUser')
			->with('docudesk')
			->willReturn(true);

		self::assertTrue($this->renderer->isDocuDeskAvailable());
	}

	public function testIsDocuDeskAvailableFalseWhenAppDisabled(): void {
		$this->appManager->method('isEnabledForUser')->willReturn(false);

		self::assertFalse($this->renderer->isDocuDeskAvailable());
	}

	/**
	 * @return array<string,array{0:string,1:string|null}>
	 */
	public static function templateIdProvider(): array {
		return [
			'lower-case uuid passes through' => ['3f2504e0-4f89-11d3-9a0c-0305e82c3301', '3f2504e0-4f89-11d3-9a0c-0305e82c3301'],
			'upper-case uuid is lower-cased' => ['3F2504E0-4F89-11D3-9A0C-0305E82C3301', '3f2504e0-4f89-11d3-9a0c-0305e82c3301'],
			'path traversal rejected' => ['../../etc/passwd', null],
			'empty rejected' => ['', null],
			'not a uuid rejected' => ['tpl-1', null],
			'uuid with suffix rejected' => ['3f2504e0-4f89-11d3-9a0c-0305e82c3301/../x', null],
			'non-hex characters rejected' => ['zzzzzzzz-4f89-11d3-9a0c-0305e82c3301', null],
		];
	}

	/**
	 * @dataProvider templateIdProvider
	 */
	public function testNormaliseTemplateId(string $input, ?string $expected): void {
		self::assertSame($expected, $this->renderer->normaliseTemplateId($input));
	}

	public function testGetTemplateReturnsTemplateData(): void {
		$templateData = ['content' => '<p>hi</p>', 'format' => 'A4', 'orientation' => 'P'];

		$templateService = $this->getMockBuilder(\stdClass::class)
			->addMethods(['getTemplate'])
			->getMock();
		$templateService->expects(self::once())
			->method('getTemplate')
			->with('3f2504e0-4f89-11d3-9a0c-0305e82c3301')
			->willReturn($templateData);

		$this->container->method('get')->willReturn($templateService);

		self::assertSame($templateData, $this->renderer->getTemplate('3f2504e0-4f89-11d3-9a0c-0305e82c3301'));
	}

	public function testGetTemplateReturnsNullWhenLookupThrows(): void {
		$templateService = $this->getMockBuilder(\stdClass::class)
			->addMethods(['getTemplate'])
			->getMock();
		$templateService->method('getTemplate')
			->willThrowException(new Exception('Template not found'));

		$this->container->method('get')->willReturn($templateService);

		self::assertNull($this->renderer->getTemplate('3f2504e0-4f89-11d3-9a0c-0305e82c3301'));
	}

	public function testGetTemplateReturnsNullWhenDocuDeskServiceIsAbsent(): void {
		$this->container->method('get')
			->willThrowException(new class('missing') extends Exception implements \Psr\Container\NotFoundExceptionInterface {
			});

		self::assertNull($this->renderer->getTemplate('3f2504e0-4f89-11d3-9a0c-0305e82c3301'));
	}

	public function testRenderPassesContentContextAndOptionsThrough(): void {
		$templateData = [
			'content' => '<h1>{{ character.name }}</h1>',
			'format' => 'A3',
			'orientation' => 'L',
		];
		$context = ['character' => ['name' => 'Sir Lancelot']];

		$pdfService = $this->getMockBuilder(\stdClass::class)
			->addMethods(['renderPdf'])
			->getMock();
		$pdfService->expects(self::once())
			->method('renderPdf')
			->with(
				'<h1>{{ character.name }}</h1>',
				$context,
				['format' => 'A3', 'orientation' => 'L'],
			)
			->willReturn('%PDF-1.4 bytes');

		$this->container->method('get')->willReturn($pdfService);

		self::assertSame('%PDF-1.4 bytes', $this->renderer->render($templateData, $context));
	}

	/**
	 * A template that omits format/orientation must still render, on the A4/portrait
	 * defaults — the shape DocuDesk templates most commonly ship with.
	 */
	public function testRenderAppliesA4PortraitDefaults(): void {
		$pdfService = $this->getMockBuilder(\stdClass::class)
			->addMethods(['renderPdf'])
			->getMock();
		$pdfService->expects(self::once())
			->method('renderPdf')
			->with('', [], ['format' => 'A4', 'orientation' => 'P'])
			->willReturn('%PDF-1.4');

		$this->container->method('get')->willReturn($pdfService);

		self::assertSame('%PDF-1.4', $this->renderer->render([], []));
	}

	public function testRenderReturnsNullAndLogsWhenRenderThrows(): void {
		$pdfService = $this->getMockBuilder(\stdClass::class)
			->addMethods(['renderPdf'])
			->getMock();
		$pdfService->method('renderPdf')
			->willThrowException(new Exception('Render failed: /internal/path/file.php line 42'));

		$this->container->method('get')->willReturn($pdfService);

		// The full exception is logged server-side so operators can diagnose it,
		// while the caller only learns that rendering failed.
		$this->logger->expects(self::once())
			->method('error')
			->with('PDF generation failed', self::anything());

		self::assertNull($this->renderer->render(['content' => ''], []));
	}
}
