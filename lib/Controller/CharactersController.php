<?php

declare(strict_types=1);

/**
 * Characters controller implementation
 *
 * @category  Controller
 * @package   OCA\LarpingApp\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @version   Release: 0.1.0
 * @link      https://larpingapp.com
 *
 * @phpversion 8.2
 */

namespace OCA\LarpingApp\Controller;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use OCA\LarpingApp\Service\ObjectService;
use OCA\LarpingApp\Service\SearchService;
use OCA\LarpingApp\Service\CharacterService;
use OCA\LarpingApp\Db\Character;
use OCA\LarpingApp\Db\CharacterMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\IAppConfig;
use OCP\IRequest;

/**
 * Controller for handling character-related operations
 *
 * This controller provides endpoints for managing character data
 * and generating character sheets.
 *
 * @category Controller
 * @package  OCA\LarpingApp\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 */
class CharactersController extends Controller
{
    /**
     * The object type this controller handles
     * 
     * @var string
     */
    const objectType = 'character';

    /**
     * Constructor for the CharactersController
     *
     * @param string           $appName          The name of the app
     * @param IRequest         $request          The request object
     * @param ObjectService    $objectService    The object service
     * @param CharacterService $characterService The character service
     * 
     * @return void
     */
    public function __construct(
        $appName,
        IRequest $request,
    private readonly ObjectService $objectService,
    private readonly CharacterService $characterService
    ) {
        parent::__construct($appName, $request);
    }
    
    /**
     * Downloads a character PDF using a specific template
     * 
     * This method generates and downloads a PDF for a specific character 
     * using the provided template.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id       The ID of the character to download as PDF
     * @param string $template The ID of the template to use for PDF generation
     * 
     * @return DataDownloadResponse|JSONResponse A response containing the PDF file for download or an error response
     * 
     * @psalm-return   DataDownloadResponse|JSONResponse
     * @phpstan-return DataDownloadResponse|JSONResponse
     */
    public function downloadPdf(string $id, string $template): DataDownloadResponse|JSONResponse
    {
        try {
            // Fetch the character object by its ID
            $character = $this->objectService->getObject('character', $id);
            $template  = $this->objectService->getObject('template', $template);
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(
                data: ['error' => 'Character Not Found'], 
                statusCode: 404
            );
        } 
            
        // Generate PDF using the specified template
        $pdfContent = $this->characterService->createCharacterPdf($character, $template);
        
        // Output the PDF
        $pdfContent->Output();
        
        // This code is commented out but would be the proper way to return a downloadable PDF
        // return new DataDownloadResponse(
        //     $pdfContent,
        //     $character->getName() . '_character_sheet.pdf',
        //     'application/pdf'
        // );
    }
}