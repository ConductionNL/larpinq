<?php

/**
 * Unit tests for the RegisterResolverService path of RegisterObjectFetcher.
 *
 * The resolver class only exists when OpenRegister is installed, so the one
 * test defines a stand-in for it in its own process and pins the register and
 * schema ids, or the exception message, of every resolver outcome, plus the
 * order of the resolver calls.
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
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use ReflectionMethod;
use RuntimeException;

/**
 * Pins resolveRegisterAndSchema() when OpenRegister's resolver is available.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */
class RegisterObjectFetcherResolverTest extends TestCase {

	/**
	 * Every resolver outcome gives the recorded ids or message.
	 *
	 * @return void
	 */
	#[RunInSeparateProcess]
	public function testEveryResolverOutcome(): void {
		eval(
			'namespace OCA\OpenRegister\Service;'
			. ' class RegisterResolverService {'
			. ' public static array $answers = []; public static array $calls = [];'
			. ' public function resolveRegisterId(string $app, string $key) { return $this->answer($app, $key); }'
			. ' public function resolveSchemaId(string $app, string $key) { return $this->answer($app, $key); }'
			. ' private function answer(string $app, string $key) { self::$calls[] = $app . "." . $key;'
			. ' $answer = self::$answers[$key] ?? ""; if ($answer instanceof \Throwable) { throw $answer; } return $answer; } }'
		);
		$resolverClass = 'OCA\OpenRegister\Service\RegisterResolverService';
		$resolver = new $resolverClass();

		$cases = [
			'both configured' => ['character_register' => 'reg-1', 'character_schema' => 7],
			'register empty' => ['character_register' => '', 'character_schema' => 'sch-1'],
			'schema empty' => ['character_register' => 'reg-1', 'character_schema' => ''],
			'both empty' => [],
			'register lookup throws its own type' => ['character_register' => new RuntimeException('missing config')],
			'schema lookup throws a plain exception' => ['character_register' => 'reg-1', 'character_schema' => new Exception('boom')],
			'lookup throws a not-configured message' => ['character_register' => new Exception('Thing not configured for elsewhere')],
		];

		$actual = [];
		foreach ($cases as $name => $answers) {
			$resolverClass::$answers = $answers;
			$resolverClass::$calls = [];
			$actual[$name] = [$this->outcome(resolver: $resolver), $resolverClass::$calls];
		}

		$this->assertSame(
			expected: [
				'both configured' => [['reg-1', '7'], ['larpinq.character_register', 'larpinq.character_schema']],
				'register empty' => [['Exception', 'Register not configured for character', null], ['larpinq.character_register', 'larpinq.character_schema']],
				'schema empty' => [['Exception', 'Schema not configured for character', null], ['larpinq.character_register', 'larpinq.character_schema']],
				'both empty' => [['Exception', 'Register not configured for character', null], ['larpinq.character_register', 'larpinq.character_schema']],
				'register lookup throws its own type' => [
					['Exception', 'Register or schema not configured for character', 'missing config'],
					['larpinq.character_register'],
				],
				'schema lookup throws a plain exception' => [
					['Exception', 'Register or schema not configured for character', 'boom'],
					['larpinq.character_register', 'larpinq.character_schema'],
				],
				'lookup throws a not-configured message' => [['Exception', 'Thing not configured for elsewhere', null], ['larpinq.character_register']],
			],
			actual: $actual
		);
	}//end testEveryResolverOutcome()

	/**
	 * Resolve `character` through a fetcher whose container holds the resolver.
	 *
	 * @param object $resolver The resolver stand-in.
	 *
	 * @return array<int,mixed> The [register, schema] ids, or [exception class, message, previous message].
	 */
	private function outcome(object $resolver): array {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($resolver);
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->expects($this->never())->method('getValueString');
		$fetcher = new RegisterObjectFetcher(
			container: $container,
			appManager: $this->createMock(originalClassName: IAppManager::class),
			config: $config,
			logger: new NullLogger()
		);
		$resolve = new ReflectionMethod(objectOrMethod: $fetcher, method: 'resolveRegisterAndSchema');

		try {
			return $resolve->invoke($fetcher, 'character');
		} catch (Exception $e) {
			return [get_class($e), $e->getMessage(), $e->getPrevious()?->getMessage()];
		}
	}//end outcome()
}//end class
