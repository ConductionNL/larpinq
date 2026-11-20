<?php

/**
 * Contract of DocuDeskPdfRenderer
 *
 * The public surface of DocuDeskPdfRenderer that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category  Kernel
 * @package   OCA\Larpinq\Kernel\Contracts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Kernel\Contracts;

/**
 * Contract of DocuDeskPdfRenderer.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface DocuDeskPdfRendererInterface {

	/**
	 * Fetch a template definition from DocuDesk's TemplateService.
	 *
	 * @param string $templateId The UUID-validated template id.
	 *
	 * @return array<string,mixed>|null The template data, or null when not found.
	 *
	 * @psalm-suppress MixedMethodCall    DocuDesk is an optional cross-app dependency.
	 * @psalm-suppress MixedReturnStatement DocuDesk is an optional cross-app dependency.
	 * @psalm-suppress MixedInferredReturnType DocuDesk is an optional cross-app dependency.
	 *
	 * @spec openspec/specs/pdf-export/spec.md
	 */
	public function getTemplate(string $templateId): ?array;

	/**
	 * Whether the DocuDesk app is installed and enabled for the current user.
	 *
	 * @return bool True when DocuDesk is available.
	 *
	 * @spec openspec/specs/pdf-export/spec.md
	 */
	public function isDocuDeskAvailable(): bool;

	/**
	 * The character-sheet templates, as id and name, for the download dialog.
	 *
	 * Asked server-side so the frontend never names the document app's id,
	 * which moved from docudesk to filinq. Templates live under the frozen
	 * namespace `larpingapp` (pdf-export spec).
	 *
	 * @return array<int,array{id:string,name:string}> The templates; [] when none can be read.
	 *
	 * @psalm-suppress MixedMethodCall DocuDesk is an optional cross-app dependency.
	 * @psalm-suppress MixedAssignment DocuDesk is an optional cross-app dependency.
	 *
	 * @spec openspec/specs/pdf-export/spec.md
	 */
	public function listTemplates(): array;

	/**
	 * Validate that a template id is a UUID (prevents path traversal/injection).
	 *
	 * @param string $template The candidate template id.
	 *
	 * @return string|null The lower-cased UUID, or null when not a UUID.
	 *
	 * @spec openspec/specs/pdf-export/spec.md
	 */
	public function normaliseTemplateId(string $template): ?string;

	/**
	 * Render a PDF for a template and data context via DocuDesk's PdfService.
	 *
	 * Returns the PDF bytes, or null on a render error (logged server-side; the
	 * caller maps null to a generic 500 to avoid leaking DocuDesk internals).
	 *
	 * @param array<string,mixed> $templateData The template definition.
	 * @param array<string,mixed> $context The render data context.
	 *
	 * @return string|null The rendered PDF bytes, or null on failure.
	 *
	 * @psalm-suppress MixedMethodCall DocuDesk is an optional cross-app dependency.
	 *
	 * @spec openspec/specs/pdf-export/spec.md
	 */
	public function render(array $templateData, array $context): ?string;
}//end interface
