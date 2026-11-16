<?php

/**
 * Bindings of the Kernel contracts to their Service implementations
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 *
 * @spec exclude Structural contract wiring, no functional requirement.
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use OCA\Larpinq\Kernel\Contracts\CharacterConnectionGuardInterface;
use OCA\Larpinq\Kernel\Contracts\CharacterStatSheetInterface;
use OCA\Larpinq\Kernel\Contracts\CheckinByCodeInterface;
use OCA\Larpinq\Kernel\Contracts\CustomFieldGuardInterface;
use OCA\Larpinq\Kernel\Contracts\DemoDataServiceInterface;
use OCA\Larpinq\Kernel\Contracts\DocuDeskPdfRendererInterface;
use OCA\Larpinq\Kernel\Contracts\EventCareRecordsInterface;
use OCA\Larpinq\Kernel\Contracts\EventFeedbackServiceInterface;
use OCA\Larpinq\Kernel\Contracts\IdListNormaliserInterface;
use OCA\Larpinq\Kernel\Contracts\PaymentFollowUpInterface;
use OCA\Larpinq\Kernel\Contracts\PaymentLeafInterface;
use OCA\Larpinq\Kernel\Contracts\PlayerAttendanceServiceInterface;
use OCA\Larpinq\Kernel\Contracts\PurchaseServiceInterface;
use OCA\Larpinq\Kernel\Contracts\RegisterObjectFetcherInterface;
use OCA\Larpinq\Kernel\Contracts\RegistrationChangeServiceInterface;
use OCA\Larpinq\Kernel\Contracts\RegistrationPaymentServiceInterface;
use OCA\Larpinq\Kernel\Contracts\RegistrationServiceInterface;
use OCA\Larpinq\Kernel\Contracts\RegistrationSettlementInterface;
use OCA\Larpinq\Kernel\Contracts\RegistrationWriteCheckInterface;
use OCA\Larpinq\Kernel\Contracts\SettingsServiceInterface;
use OCA\Larpinq\Kernel\Contracts\SignupFormBindingInterface;
use OCA\Larpinq\Kernel\Contracts\SkillRequirementServiceInterface;
use OCA\Larpinq\Kernel\Contracts\TicketChoiceServiceInterface;
use OCA\Larpinq\Kernel\Contracts\TransferOffersInterface;
use OCA\Larpinq\Kernel\Contracts\UniqueHolderConflictFinderInterface;
use OCA\Larpinq\Kernel\Contracts\WorldCopyServiceInterface;
use OCA\Larpinq\Kernel\Contracts\XpAwardBatchServiceInterface;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

/**
 * Nextcloud autowires concrete classes but not interfaces, so every contract
 * in OCA\Larpinq\Kernel\Contracts that a caller type-hints needs an alias to
 * the class that implements it. An alias resolves to the same shared instance
 * as the implementation itself.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *
 * @spec exclude Structural contract wiring, no functional requirement.
 */
final class ServiceContractRegistrar {

	/**
	 * Alias every contract to its implementation.
	 *
	 * @param IRegistrationContext $context Registration context
	 *
	 * @return void
	 *
	 * @spec exclude Structural contract wiring, no functional requirement.
	 */
	public function register(IRegistrationContext $context): void {
		$context->registerServiceAlias(CharacterConnectionGuardInterface::class, CharacterConnectionGuard::class);
		$context->registerServiceAlias(CharacterStatSheetInterface::class, CharacterStatSheet::class);
		$context->registerServiceAlias(CheckinByCodeInterface::class, CheckinByCode::class);
		$context->registerServiceAlias(CustomFieldGuardInterface::class, CustomFieldGuard::class);
		$context->registerServiceAlias(DemoDataServiceInterface::class, DemoDataService::class);
		$context->registerServiceAlias(DocuDeskPdfRendererInterface::class, DocuDeskPdfRenderer::class);
		$context->registerServiceAlias(EventCareRecordsInterface::class, EventCareRecords::class);
		$context->registerServiceAlias(EventFeedbackServiceInterface::class, EventFeedbackService::class);
		$context->registerServiceAlias(IdListNormaliserInterface::class, IdListNormaliser::class);
		$context->registerServiceAlias(PaymentFollowUpInterface::class, PaymentFollowUp::class);
		$context->registerServiceAlias(PaymentLeafInterface::class, PaymentLeaf::class);
		$context->registerServiceAlias(PlayerAttendanceServiceInterface::class, PlayerAttendanceService::class);
		$context->registerServiceAlias(PurchaseServiceInterface::class, PurchaseService::class);
		$context->registerServiceAlias(RegisterObjectFetcherInterface::class, RegisterObjectFetcher::class);
		$context->registerServiceAlias(RegistrationChangeServiceInterface::class, RegistrationChangeService::class);
		$context->registerServiceAlias(RegistrationPaymentServiceInterface::class, RegistrationPaymentService::class);
		$context->registerServiceAlias(RegistrationServiceInterface::class, RegistrationService::class);
		$context->registerServiceAlias(RegistrationSettlementInterface::class, RegistrationSettlement::class);
		$context->registerServiceAlias(RegistrationWriteCheckInterface::class, RegistrationWriteCheck::class);
		$context->registerServiceAlias(SettingsServiceInterface::class, SettingsService::class);
		$context->registerServiceAlias(SignupFormBindingInterface::class, SignupFormBinding::class);
		$context->registerServiceAlias(SkillRequirementServiceInterface::class, SkillRequirementService::class);
		$context->registerServiceAlias(TicketChoiceServiceInterface::class, TicketChoiceService::class);
		$context->registerServiceAlias(TransferOffersInterface::class, TransferOffers::class);
		$context->registerServiceAlias(UniqueHolderConflictFinderInterface::class, UniqueHolderConflictFinder::class);
		$context->registerServiceAlias(WorldCopyServiceInterface::class, WorldCopyService::class);
		$context->registerServiceAlias(XpAwardBatchServiceInterface::class, XpAwardBatchService::class);
	}//end register()
}//end class
