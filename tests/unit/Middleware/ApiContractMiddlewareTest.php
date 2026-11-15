<?php

/**
 * Unit tests for the API contract middleware.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Middleware
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Middleware;

use OCA\Larpinq\Middleware\ApiContractMiddleware;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Pins the `API-Version` header, the deprecation of unversioned paths and the
 * problem details shape of API errors.
 *
 * @covers \OCA\Larpinq\Middleware\ApiContractMiddleware
 * @uses   \OCA\Larpinq\Middleware\Support\ApiPathAliases
 * @uses   \OCA\Larpinq\Middleware\Support\StandardProtocolPaths
 */
class ApiContractMiddlewareTest extends TestCase {

	/**
	 * Run the middleware for a request on the given path.
	 *
	 * @param string|false $path     The request path info.
	 * @param Response     $response The controller response.
	 *
	 * @return Response The decorated response.
	 */
	private function decorate(string|false $path, Response $response): Response {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getPathInfo')->willReturn($path);
		$request->method('getRequestUri')->willReturn('/index.php'.(string) $path.'?q=1');
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('1.2.3');

		return (new ApiContractMiddleware(request: $request, appManager: $appManager))
			->afterController(controller: null, methodName: 'any', response: $response);
	}//end decorate()

	/**
	 * A successful API response carries the installed version and keeps its body.
	 *
	 * @return void
	 */
	public function testApiResponseCarriesTheVersionHeader(): void {
		$response = $this->decorate(path: '/apps/larpinq/api/things', response: new JSONResponse(data: ['id' => 1]));

		$this->assertSame(expected: '1.2.3', actual: $this->headersOf(response: $response)['API-Version']);
		$this->assertSame(expected: ['id' => 1], actual: $response->getData());
		$this->assertSame(expected: 'application/json; charset=utf-8', actual: $this->headersOf(response: $response)['Content-Type']);
	}//end testApiResponseCarriesTheVersionHeader()

	/**
	 * A page response outside the API is left alone.
	 *
	 * @return void
	 */
	public function testNonApiResponseIsUntouched(): void {
		$response = $this->decorate(path: '/apps/larpinq/', response: new JSONResponse(data: ['error' => 'x'], statusCode: 404));

		$this->assertArrayNotHasKey(key: 'API-Version', array: $this->headersOf(response: $response));
		$this->assertSame(expected: ['error' => 'x'], actual: $response->getData());

		$missing = $this->decorate(path: false, response: new JSONResponse(data: []));
		$this->assertArrayNotHasKey(key: 'API-Version', array: $this->headersOf(response: $missing));
	}//end testNonApiResponseIsUntouched()

	/**
	 * A JSON error becomes problem details and keeps the members it had.
	 *
	 * @return void
	 */
	public function testJsonErrorBecomesProblemDetails(): void {
		$response = $this->decorate(
			path: '/apps/larpinq/api/things/7',
			response: new JSONResponse(data: ['error' => 'Thing not found'], statusCode: 404)
		);

		$expected = [
			'type'   => 'about:blank',
			'title'  => 'Not Found',
			'status' => 404,
			'detail' => 'Thing not found',
			'error'  => 'Thing not found',
		];
		$this->assertSame(expected: $expected, actual: $response->getData());
		$this->assertSame(expected: ApiContractMiddleware::PROBLEM_CONTENT_TYPE, actual: $this->headersOf(response: $response)['Content-Type']);
		$this->assertSame(expected: '1.2.3', actual: $this->headersOf(response: $response)['API-Version']);
	}//end testJsonErrorBecomesProblemDetails()

	/**
	 * `message` is used as detail when there is no `detail`, and an unknown status still gets a title.
	 *
	 * @return void
	 */
	public function testMessageIsUsedAsDetailAndUnknownStatusHasATitle(): void {
		$response = $this->decorate(
			path: '/apps/larpinq/api/things',
			response: new JSONResponse(data: ['message' => 'Teapot', 'error' => 'ignored'], statusCode: 418)
		);

		$data = $response->getData();
		$this->assertSame(expected: 'Error', actual: $data['title']);
		$this->assertSame(expected: 'Teapot', actual: $data['detail']);
		$this->assertSame(expected: 418, actual: $data['status']);
	}//end testMessageIsUsedAsDetailAndUnknownStatusHasATitle()

	/**
	 * A body that uses a problem member for something else, or is a list, keeps its shape.
	 *
	 * @return void
	 */
	public function testConflictingOrListBodiesAreLeftAlone(): void {
		$conflict = $this->decorate(
			path: '/apps/larpinq/api/things',
			response: new JSONResponse(data: ['status' => 'error', 'message' => 'No'], statusCode: 400)
		);
		$this->assertSame(expected: ['status' => 'error', 'message' => 'No'], actual: $conflict->getData());
		$this->assertSame(expected: 'application/json; charset=utf-8', actual: $this->headersOf(response: $conflict)['Content-Type']);

		$list = $this->decorate(path: '/apps/larpinq/api/things', response: new JSONResponse(data: ['a', 'b'], statusCode: 400));
		$this->assertSame(expected: ['a', 'b'], actual: $list->getData());
	}//end testConflictingOrListBodiesAreLeftAlone()

	/**
	 * A body that already is problem details is kept, and an empty body gets the members.
	 *
	 * @return void
	 */
	public function testExistingProblemAndEmptyBodies(): void {
		$problem = ['type' => 'about:blank', 'title' => 'Forbidden', 'status' => 403, 'detail' => 'No access'];
		$kept    = $this->decorate(path: '/apps/larpinq/api/things', response: new JSONResponse(data: $problem, statusCode: 403));
		$this->assertSame(expected: $problem, actual: $kept->getData());

		$empty = $this->decorate(path: '/apps/larpinq/api/things', response: new JSONResponse(data: [], statusCode: 500));
		$this->assertSame(expected: ['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500], actual: $empty->getData());
	}//end testExistingProblemAndEmptyBodies()

	/**
	 * A non-JSON error response gets the header but no body rewrite.
	 *
	 * @return void
	 */
	public function testNonJsonErrorOnlyGetsTheHeader(): void {
		$response = $this->decorate(path: '/apps/larpinq/api/files', response: new DataDisplayResponse(data: 'gone', statusCode: 410));

		$this->assertSame(expected: '1.2.3', actual: $this->headersOf(response: $response)['API-Version']);
		$this->assertSame(expected: 'gone', actual: $response->getData());
	}//end testNonJsonErrorOnlyGetsTheHeader()

	/**
	 * An unversioned API path keeps answering and names its `/api/v1/` successor.
	 *
	 * @return void
	 */
	public function testUnversionedPathAnnouncesItsSuccessor(): void {
		$response = $this->decorate(path: '/apps/larpinq/api/things/7/parts', response: new JSONResponse(data: ['id' => 7]));
		$headers  = $this->headersOf(response: $response);

		$this->assertSame(expected: ['id' => 7], actual: $response->getData());
		$this->assertSame(expected: '@1794441600', actual: $headers['Deprecation']);
		$this->assertSame(expected: '</index.php/apps/larpinq/api/v1/things/7/parts>; rel="successor-version"', actual: $headers['Link']);
	}//end testUnversionedPathAnnouncesItsSuccessor()

	/**
	 * A versioned API path is not deprecated, and still carries the version.
	 *
	 * @return void
	 */
	public function testVersionedPathIsNotDeprecated(): void {
		$headers = $this->headersOf(response: $this->decorate(path: '/apps/larpinq/api/v1/things/7', response: new JSONResponse(data: [])));

		$this->assertSame(expected: '1.2.3', actual: $headers['API-Version']);
		$this->assertArrayNotHasKey(key: 'Deprecation', array: $headers);
		$this->assertArrayNotHasKey(key: 'Link', array: $headers);
	}//end testVersionedPathIsNotDeprecated()

	/**
	 * A path that speaks a protocol defined elsewhere keeps the API contract but is not deprecated.
	 *
	 * @return void
	 */
	public function testStandardProtocolPathIsNotDeprecated(): void {
		$response = $this->decorate(path: '/apps/larpinq/api/health', response: new JSONResponse(data: ['error' => 'x'], statusCode: 400));
		$headers  = $this->headersOf(response: $response);
		$data     = $response->getData();

		$this->assertSame(expected: 'x', actual: $data['error']);
		$this->assertSame(expected: 400, actual: $data['status']);
		$this->assertArrayHasKey(key: 'API-Version', array: $headers);
		$this->assertArrayNotHasKey(key: 'Deprecation', array: $headers);
		$this->assertArrayNotHasKey(key: 'Link', array: $headers);
	}//end testStandardProtocolPathIsNotDeprecated()


	/**
	 * When the request URI does not contain the path info, the successor link has no script prefix.
	 *
	 * @return void
	 */
	public function testSuccessorLinkWithoutAMatchingUriHasNoPrefix(): void {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getPathInfo')->willReturn('/apps/larpinq/api/things/7/parts');
		$request->method('getRequestUri')->willReturn('/rewritten/elsewhere');
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->expects($this->once())->method('getAppVersion')->with('larpinq')->willReturn('9.9.9');

		$response = (new ApiContractMiddleware(request: $request, appManager: $appManager))
			->afterController(controller: null, methodName: 'any', response: new JSONResponse(data: []));
		$headers  = $this->headersOf(response: $response);

		$this->assertSame(expected: '9.9.9', actual: $headers['API-Version']);
		$this->assertSame(expected: '</apps/larpinq/api/v1/things/7/parts>; rel="successor-version"', actual: $headers['Link']);
	}//end testSuccessorLinkWithoutAMatchingUriHasNoPrefix()

	/**
	 * Empty or non-string candidates are skipped when picking the detail.
	 *
	 * @return void
	 */
	public function testDetailSkipsEmptyAndNonStringCandidates(): void {
		$response = $this->decorate(
			path: '/apps/larpinq/api/v1/things',
			response: new JSONResponse(data: ['detail' => '', 'message' => ['nested'], 'error' => 'Bad input'], statusCode: 422)
		);

		$this->assertSame(
			expected: [
				'type'   => 'about:blank',
				'title'  => 'Unprocessable Content',
				'status' => 422,
				'detail' => '',
				'message' => ['nested'],
				'error'  => 'Bad input',
			],
			actual: $response->getData()
		);
	}//end testDetailSkipsEmptyAndNonStringCandidates()

	/**
	 * An error body without any detail candidate gets the problem members but no detail.
	 *
	 * @return void
	 */
	public function testErrorWithoutDetailCandidateGetsNoDetail(): void {
		$response = $this->decorate(
			path: '/apps/larpinq/api/v1/things',
			response: new JSONResponse(data: ['code' => 'E42'], statusCode: 429)
		);

		$this->assertSame(
			expected: ['type' => 'about:blank', 'title' => 'Too Many Requests', 'status' => 429, 'code' => 'E42'],
			actual: $response->getData()
		);
		$this->assertSame(expected: ApiContractMiddleware::PROBLEM_CONTENT_TYPE, actual: $this->headersOf(response: $response)['Content-Type']);
	}//end testErrorWithoutDetailCandidateGetsNoDetail()

	/**
	 * A non-error status below 400 keeps an `error` member untouched.
	 *
	 * @return void
	 */
	public function testStatusBelow400IsNotAProblem(): void {
		$response = $this->decorate(
			path: '/apps/larpinq/api/v1/things',
			response: new JSONResponse(data: ['error' => 'partial'], statusCode: 399)
		);

		$this->assertSame(expected: ['error' => 'partial'], actual: $response->getData());
		$this->assertSame(expected: 'application/json; charset=utf-8', actual: $this->headersOf(response: $response)['Content-Type']);
	}//end testStatusBelow400IsNotAProblem()

	/**
	 * The headers a response set, read without the server container that
	 * Response::getHeaders() needs.
	 *
	 * @param Response $response The response.
	 *
	 * @return array<string,string> The headers by name.
	 */
	private function headersOf(Response $response): array {
		$property = new ReflectionProperty(Response::class, 'headers');

		return $property->getValue($response);
	}//end headersOf()
}//end class
