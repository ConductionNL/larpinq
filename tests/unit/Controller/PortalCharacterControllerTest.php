<?php

/**
 * Unit tests for PortalCharacterController.
 *
 * The receiver of the portal `createCharacter` endpoint action: a verified
 * assertion's `ownerRef` claim becomes BOTH `ownerRef` and the required
 * `ocName` of the new character, so OpenRegister's required-field check
 * passes without waiting for PortalProfileListener. Assertions are minted
 * the way portaliq mints them and checked by the real verifier.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/specs/portal-contribution/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\PortalCharacterController;
use OCA\Larpinq\Kernel\Contracts\RegisterObjectFetcherInterface;
use OCA\Larpinq\Service\PortalAssertionVerifier;
use OCP\IRequest;
use OCP\Security\Bruteforce\IThrottler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Tests for PortalCharacterController.
 *
 * @covers \OCA\Larpinq\Controller\PortalCharacterController
 * @uses   \OCA\Larpinq\Service\PortalAssertionVerifier
 *
 * @spec openspec/specs/portal-contribution/spec.md
 */
class PortalCharacterControllerTest extends TestCase {

	/**
	 * The signing secret the verifier and the minter share.
	 */
	private const SECRET = 'larpinq-test-secret-0123456789';

	/**
	 * The linked player's uuid (the account's ownerRef claim).
	 */
	private const PLAYER = '5d0c6f0e-7a1b-4c3d-9e2f-1a2b3c4d5e01';

	/**
	 * The writer mock.
	 *
	 * @var RegisterObjectFetcherInterface&MockObject
	 */
	private RegisterObjectFetcherInterface $fetcher;

	/**
	 * The throttler mock.
	 *
	 * @var IThrottler&MockObject
	 */
	private IThrottler $throttler;

	/**
	 * Set up the collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->fetcher = $this->createMock(RegisterObjectFetcherInterface::class);
		$this->throttler = $this->createMock(IThrottler::class);
	}//end setUp()

	/**
	 * Mint an assertion the way portaliq's PortalJwtService::createAssertion() does.
	 *
	 * @param array<string, mixed> $overrides Claim overrides (null removes a claim).
	 * @param string $secret The signing secret.
	 *
	 * @return string The compact JWT.
	 */
	private function mint(array $overrides = [], string $secret = self::SECRET): string {
		$iat = time();
		$claims = [
			'sub' => 'portal-subject-ref-1',
			'audience' => 'player',
			'organisation' => '',
			'trust' => 'low',
			'jti' => 'sessionjti0000000000000000000000',
			'use' => 'assertion',
			'iat' => $iat,
			'exp' => ($iat + 60),
			'iss' => 'portaliq',
			'ownerRef' => self::PLAYER,
		];
		foreach ($overrides as $claim => $value) {
			if ($value === null) {
				unset($claims[$claim]);
				continue;
			}

			$claims[$claim] = $value;
		}

		$encode = static fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
		$hPart = $encode((string)json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
		$cPart = $encode((string)json_encode($claims, JSON_UNESCAPED_SLASHES));

		return $hPart . '.' . $cPart . '.' . $encode(hash_hmac('sha256', $hPart . '.' . $cPart, $secret, true));
	}//end mint()

	/**
	 * A controller over a request with this assertion header and these parameters.
	 *
	 * @param string $assertion The X-Portal-Subject header value.
	 * @param array<string, mixed> $params The request parameters.
	 *
	 * @return PortalCharacterController
	 */
	private function controller(string $assertion, array $params): PortalCharacterController {
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->willReturnCallback(
			static fn (string $name): string => ($name === 'X-Portal-Subject') ? $assertion : ''
		);
		$request->method('getParam')->willReturnCallback(
			static fn (string $key, mixed $default = null): mixed => ($params[$key] ?? $default)
		);
		$request->method('getRemoteAddress')->willReturn('127.0.0.1');

		return new PortalCharacterController(
			request: $request,
			verifier: new PortalAssertionVerifier(secretOverride: self::SECRET),
			fetcher: $this->fetcher,
			throttler: $this->throttler,
			logger: $this->createMock(LoggerInterface::class),
		);
	}//end controller()

	/**
	 * A verified player's character is written with ocName and ownerRef both
	 * set to the claimed player, whatever the body says about either.
	 *
	 * @return void
	 */
	public function testCreatesTheCharacterWithOcNameAndOwnerRefFromTheClaim(): void {
		$this->fetcher->expects($this->once())
			->method('saveObjectWithAppAuthority')
			->with(
				'character',
				[
					'name' => 'Aldric',
					'ownerRef' => self::PLAYER,
					'ocName' => self::PLAYER,
					'background' => 'A wandering smith.',
				]
			)
			->willReturn(['id' => 'c-1', 'name' => 'Aldric']);
		$this->throttler->expects($this->never())->method('registerAttempt');

		$response = $this->controller(
			assertion: $this->mint(),
			params: [
				'name' => '  Aldric ',
				'background' => 'A wandering smith.',
				'ocName' => '99999999-9999-4999-8999-999999999999',
				'ownerRef' => '99999999-9999-4999-8999-999999999999',
				'approved' => true,
			]
		)->create();

		$this->assertSame(expected: 201, actual: $response->getStatus());
		$this->assertSame(expected: ['id' => 'c-1', 'name' => 'Aldric'], actual: $response->getData());
	}//end testCreatesTheCharacterWithOcNameAndOwnerRefFromTheClaim()

	/**
	 * Without a background the character carries no background key, and the
	 * answer falls back to the uuid when the saved object has no id.
	 *
	 * @return void
	 */
	public function testABackgroundIsOptional(): void {
		$this->fetcher->expects($this->once())
			->method('saveObjectWithAppAuthority')
			->with('character', ['name' => 'Mira', 'ownerRef' => self::PLAYER, 'ocName' => self::PLAYER])
			->willReturn(['uuid' => 'u-2']);

		$response = $this->controller(assertion: $this->mint(), params: ['name' => 'Mira'])->create();

		$this->assertSame(expected: 201, actual: $response->getStatus());
		$this->assertSame(expected: ['id' => 'u-2', 'name' => 'Mira'], actual: $response->getData());
	}//end testABackgroundIsOptional()

	/**
	 * An assertion signed with another secret is refused 401, counted by the
	 * throttler, and nothing is written.
	 *
	 * @return void
	 */
	public function testAForgedAssertionIsRefused(): void {
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');
		$this->throttler->expects($this->once())->method('registerAttempt')->with('larpinq_portal_character', '127.0.0.1');

		$response = $this->controller(
			assertion: $this->mint(secret: 'attacker-secret-9876543210abcd'),
			params: ['name' => 'Aldric']
		)->create();

		$this->assertSame(expected: 401, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'unauthorized'], actual: $response->getData());
	}//end testAForgedAssertionIsRefused()

	/**
	 * A missing header is refused 401.
	 *
	 * @return void
	 */
	public function testAMissingAssertionIsRefused(): void {
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');

		$response = $this->controller(assertion: '', params: ['name' => 'Aldric'])->create();

		$this->assertSame(expected: 401, actual: $response->getStatus());
	}//end testAMissingAssertionIsRefused()

	/**
	 * Another audience, or an assertion without the ownerRef claim, is
	 * refused 403 and nothing is written.
	 *
	 * @return void
	 */
	public function testAnotherAudienceOrNoPlayerClaimIsForbidden(): void {
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');

		foreach ([['audience' => 'client'], ['ownerRef' => null], ['ownerRef' => '  '], ['ownerRef' => ['x']]] as $overrides) {
			$response = $this->controller(assertion: $this->mint(overrides: $overrides), params: ['name' => 'Aldric'])->create();
			$this->assertSame(expected: 403, actual: $response->getStatus(), message: (string)json_encode($overrides));
			$this->assertSame(expected: ['error' => 'forbidden'], actual: $response->getData());
		}
	}//end testAnotherAudienceOrNoPlayerClaimIsForbidden()

	/**
	 * An empty, non-string or too-long name, or a too-long background, is
	 * refused 400 and nothing is written.
	 *
	 * @return void
	 */
	public function testInvalidInputIsRefused(): void {
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');

		$cases = [
			[],
			['name' => '   '],
			['name' => ['Aldric']],
			['name' => str_repeat('a', 256)],
			['name' => 'Aldric', 'background' => str_repeat('b', 2556)],
		];
		foreach ($cases as $params) {
			$response = $this->controller(assertion: $this->mint(), params: $params)->create();
			$this->assertSame(expected: 400, actual: $response->getStatus());
			$this->assertSame(expected: ['error' => 'invalid_request'], actual: $response->getData());
		}
	}//end testInvalidInputIsRefused()

	/**
	 * The longest accepted name and background are written.
	 *
	 * @return void
	 */
	public function testTheLongestAcceptedValuesAreWritten(): void {
		$this->fetcher->expects($this->once())->method('saveObjectWithAppAuthority')->willReturn(['id' => 'c-3']);

		$response = $this->controller(
			assertion: $this->mint(),
			params: ['name' => str_repeat('a', 255), 'background' => str_repeat('b', 2555)]
		)->create();

		$this->assertSame(expected: 201, actual: $response->getStatus());
	}//end testTheLongestAcceptedValuesAreWritten()

	/**
	 * A save that fails answers 503 and does not leak the reason.
	 *
	 * @return void
	 */
	public function testAFailedSaveAnswersUnavailable(): void {
		$this->fetcher->method('saveObjectWithAppAuthority')->willThrowException(new RuntimeException('ocName is required'));

		$response = $this->controller(assertion: $this->mint(), params: ['name' => 'Aldric'])->create();

		$this->assertSame(expected: 503, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'unavailable'], actual: $response->getData());
	}//end testAFailedSaveAnswersUnavailable()
}//end class
