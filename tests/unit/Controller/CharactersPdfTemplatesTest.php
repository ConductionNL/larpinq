<?php

/**
 * CharactersPdfTemplatesTest.
 *
 * The Download as PDF header action reads GET /api/pdf/templates as its
 * visibility predicate, and the dialog lists what it returns. So the answer
 * must say `available: false` without the document app, and reduce the
 * document app's templates to id and name, asked under the larpingapp
 * namespace.
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

use OCA\Larpinq\Controller\CharactersController;
use OCA\Larpinq\Service\DocuDeskPdfRenderer;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\SkillRequirementService;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * GET /api/pdf/templates.
 */
class CharactersPdfTemplatesTest extends TestCase {

	/**
	 * A controller over a document app that is (or is not) installed.
	 *
	 * @param bool $installed Whether the document app answers.
	 * @param object|null $templateService The document app's TemplateService.
	 *
	 * @return CharactersController The controller.
	 */
	private function controller(bool $installed, ?object $templateService = null): CharactersController {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isInstalled')->willReturn($installed);
		$appManager->method('isEnabledForUser')->willReturn($installed);
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $class): ?object => str_ends_with($class, '\\Service\\TemplateService') ? $templateService : null
		);

		return new CharactersController(
			'larpinq',
			$this->createMock(IRequest::class),
			$this->createMock(RegisterObjectFetcher::class),
			new DocuDeskPdfRenderer($appManager, $container, $this->createMock(LoggerInterface::class)),
			$this->createMock(IUserSession::class),
			$this->createMock(IGroupManager::class),
			$this->createMock(SkillRequirementService::class),
		);
	}//end controller()

	/**
	 * Without the document app the answer is unavailable, so the action hides.
	 *
	 * @return void
	 */
	public function testWithoutTheDocumentAppTheActionIsUnavailable(): void {
		$data = $this->controller(installed: false)->pdfTemplates()->getData();

		$this->assertSame(['available' => false, 'templates' => []], $data);
	}//end testWithoutTheDocumentAppTheActionIsUnavailable()

	/**
	 * Templates are asked under larpingapp and reduced to id and name.
	 *
	 * @return void
	 */
	public function testTemplatesAreListedByIdAndName(): void {
		$service = new class {
			/** @var array<int,string> */
			public array $asked = [];

			/**
			 * Same signature as the document app's TemplateService.
			 *
			 * @param string $namespace The app namespace.
			 *
			 * @return array<int,array<string,mixed>> The templates.
			 */
			public function getTemplatesByNamespace(string $namespace): array {
				$this->asked[] = $namespace;
				return [
					['id' => 'tpl-1', 'name' => 'Character sheet', 'content' => '<h1/>'],
					['@self' => ['id' => 'tpl-2'], 'title' => 'Card'],
					['name' => 'No id, dropped'],
				];
			}
		};

		$data = $this->controller(installed: true, templateService: $service)->pdfTemplates()->getData();

		$this->assertSame(['larpingapp'], $service->asked);
		$this->assertSame(
			['available' => true, 'templates' => [['id' => 'tpl-1', 'name' => 'Character sheet'], ['id' => 'tpl-2', 'name' => 'Card']]],
			$data
		);
	}//end testTemplatesAreListedByIdAndName()

	/**
	 * A document app that throws still answers available with no templates.
	 *
	 * @return void
	 */
	public function testAFailingDocumentAppListsNothing(): void {
		$service = new class {
			/**
			 * Throws like a broken TemplateService.
			 *
			 * @param string $namespace The app namespace.
			 *
			 * @return array<int,array<string,mixed>> Never returns.
			 */
			public function getTemplatesByNamespace(string $namespace): array {
				throw new \Exception('register missing: ' . $namespace);
			}
		};

		$data = $this->controller(installed: true, templateService: $service)->pdfTemplates()->getData();

		$this->assertSame(['available' => true, 'templates' => []], $data);
	}//end testAFailingDocumentAppListsNothing()
}//end class
