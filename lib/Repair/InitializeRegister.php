<?php

/**
 * LarpingApp Initialize Register Repair Step.
 *
 * Repair step that initializes the LarpingApp register and schemas on install/upgrade.
 * Migrated from Application::boot() to avoid running the import on every HTTP request.
 *
 * @category  Repair
 * @package   OCA\LarpingApp\Repair
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Repair;

use OCA\LarpingApp\Service\SettingsService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

/**
 * Repair step that initializes LarpingApp configuration via SettingsService.
 *
 * This step runs once on install and on each upgrade, ensuring the register
 * and schemas are imported into OpenRegister without burdening every HTTP request.
 *
 * @category Repair
 * @package  OCA\LarpingApp\Repair
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud repair step runner.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-4
 */
class InitializeRegister implements IRepairStep
{
    /**
     * Constructor for InitializeRegister.
     *
     * @param SettingsService $settingsService The settings service.
     * @param LoggerInterface $logger          The logger interface.
     *
     * @return void
     */
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly LoggerInterface $logger,
    ) {
    }//end __construct()

    /**
     * Get the name of this repair step.
     *
     * @return string
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-4
     */
    public function getName(): string
    {
        return 'Initialize LarpingApp register and schemas via ConfigurationService';

    }//end getName()

    /**
     * Run the repair step to initialize LarpingApp configuration.
     *
     * @param IOutput $output The output interface for progress reporting.
     *
     * @return void
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-4
     */
    public function run(IOutput $output): void
    {
        $output->info('Initializing LarpingApp configuration...');

        try {
            $this->settingsService->loadSettings(force: false);
            $output->info('LarpingApp configuration imported successfully.');
        } catch (\RuntimeException $e) {
            // OpenRegister not available — skip gracefully.
            $output->warning('OpenRegister is not installed or enabled. Skipping auto-configuration.');
            $this->logger->warning(
                'LarpingApp: OpenRegister not available, skipping register initialization',
                ['exception' => $e->getMessage()]
            );
        } catch (\Throwable $e) {
            $output->warning('Could not auto-configure LarpingApp: '.$e->getMessage());
            $this->logger->error(
                'LarpingApp initialization failed',
                ['exception' => $e->getMessage()]
            );
        }//end try

    }//end run()
}//end class
