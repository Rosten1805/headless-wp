<?php
/**
 * Tests del registro en memoria de endpoints activos.
 *
 * @package HWP\Tests\Unit\Endpoints
 */

declare( strict_types=1 );

namespace HWP\Tests\Unit\Endpoints;

use Brain\Monkey;
use HWP\Endpoints\EndpointConfig;
use HWP\Endpoints\Contracts\EndpointRepositoryInterface;
use HWP\Endpoints\EndpointRegistry;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HWP\Endpoints\EndpointRegistry
 */
class EndpointRegistryTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ─────────────────────────────────────────────
	// getActive() — primera llamada consulta DB
	// ─────────────────────────────────────────────

	public function test_get_active_returns_configs_from_repository(): void {
		$config = EndpointConfig::defaults( 'articles', 'post' );

		$repo = Mockery::mock( EndpointRepositoryInterface::class );
		$repo->shouldReceive( 'findActive' )
			->once()
			->andReturn( [ $config ] );

		$registry = new EndpointRegistry( $repo );
		$result   = $registry->getActive();

		$this->assertCount( 1, $result );
		$this->assertSame( $config, $result[0] );
		$this->addToAssertionCount( 1 );
	}

	public function test_get_active_returns_empty_array_when_no_active_endpoints(): void {
		$repo = Mockery::mock( EndpointRepositoryInterface::class );
		$repo->shouldReceive( 'findActive' )
			->once()
			->andReturn( [] );

		$registry = new EndpointRegistry( $repo );
		$result   = $registry->getActive();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
		$this->addToAssertionCount( 1 );
	}

	// ─────────────────────────────────────────────
	// Cache — segunda llamada no consulta DB
	// ─────────────────────────────────────────────

	public function test_second_call_to_get_active_uses_cache(): void {
		$config = EndpointConfig::defaults( 'articles', 'post' );

		$repo = Mockery::mock( EndpointRepositoryInterface::class );
		$repo->shouldReceive( 'findActive' )
			->once()                 // Solo UNA llamada, no dos.
			->andReturn( [ $config ] );

		$registry = new EndpointRegistry( $repo );
		$registry->getActive(); // primera — consulta DB
		$result = $registry->getActive(); // segunda — usa cache

		$this->assertCount( 1, $result );
		$this->addToAssertionCount( 1 );
	}

	// ─────────────────────────────────────────────
	// clearCache() — invalida el cache
	// ─────────────────────────────────────────────

	public function test_clear_cache_forces_re_query_on_next_get_active(): void {
		$config = EndpointConfig::defaults( 'articles', 'post' );

		$repo = Mockery::mock( EndpointRepositoryInterface::class );
		$repo->shouldReceive( 'findActive' )
			->twice()               // Dos consultas: antes y después del clearCache.
			->andReturn( [ $config ] );

		$registry = new EndpointRegistry( $repo );
		$registry->getActive(); // primera
		$registry->clearCache();
		$registry->getActive(); // segunda (re-query)

		$this->addToAssertionCount( 1 );
	}

	// ─────────────────────────────────────────────
	// findBySlug() — busca en cache primero
	// ─────────────────────────────────────────────

	public function test_find_by_slug_returns_config_from_cache(): void {
		$config = EndpointConfig::defaults( 'articles', 'post' );

		$repo = Mockery::mock( EndpointRepositoryInterface::class );
		$repo->shouldReceive( 'findActive' )
			->once()
			->andReturn( [ $config ] );
		// findBySlug del repositorio NO debería llamarse si el slug está en cache.
		$repo->shouldNotReceive( 'findBySlug' );

		$registry = new EndpointRegistry( $repo );
		$registry->getActive(); // precarga cache
		$found = $registry->findBySlug( 'articles' );

		$this->assertSame( $config, $found );
		$this->addToAssertionCount( 1 );
	}

	public function test_find_by_slug_returns_null_for_unknown_slug_in_cache(): void {
		$config = EndpointConfig::defaults( 'articles', 'post' );

		$repo = Mockery::mock( EndpointRepositoryInterface::class );
		$repo->shouldReceive( 'findActive' )
			->once()
			->andReturn( [ $config ] );
		$repo->shouldReceive( 'findBySlug' )
			->with( 'products' )
			->once()
			->andReturn( null );

		$registry = new EndpointRegistry( $repo );
		$registry->getActive();
		$found = $registry->findBySlug( 'products' );

		$this->assertNull( $found );
		$this->addToAssertionCount( 1 );
	}

	// ─────────────────────────────────────────────
	// register() — añade al cache si está cargado
	// ─────────────────────────────────────────────

	public function test_register_adds_active_config_to_loaded_cache(): void {
		$existing = EndpointConfig::defaults( 'articles', 'post' );
		$new      = EndpointConfig::defaults( 'products', 'product' );

		$repo = Mockery::mock( EndpointRepositoryInterface::class );
		$repo->shouldReceive( 'findActive' )
			->once()
			->andReturn( [ $existing ] );

		$registry = new EndpointRegistry( $repo );
		$registry->getActive(); // carga cache
		$registry->register( $new );

		$result = $registry->getActive();
		$this->assertCount( 2, $result );
		$this->addToAssertionCount( 1 );
	}

	public function test_register_does_not_add_inactive_config_to_cache(): void {
		$existing = EndpointConfig::defaults( 'articles', 'post' );
		$inactive = EndpointConfig::fromArray( 'draft', 'post', [ 'is_active' => false ] );

		$repo = Mockery::mock( EndpointRepositoryInterface::class );
		$repo->shouldReceive( 'findActive' )
			->once()
			->andReturn( [ $existing ] );

		$registry = new EndpointRegistry( $repo );
		$registry->getActive();
		$registry->register( $inactive );

		$result = $registry->getActive();
		$this->assertCount( 1, $result );
		$this->addToAssertionCount( 1 );
	}

	public function test_register_replaces_existing_slug_in_cache(): void {
		$original = EndpointConfig::fromArray( 'articles', 'post', [ 'cache_ttl' => 300 ] );
		$updated  = EndpointConfig::fromArray( 'articles', 'post', [ 'cache_ttl' => 600 ] );

		$repo = Mockery::mock( EndpointRepositoryInterface::class );
		$repo->shouldReceive( 'findActive' )
			->once()
			->andReturn( [ $original ] );

		$registry = new EndpointRegistry( $repo );
		$registry->getActive();
		$registry->register( $updated );

		$result = $registry->getActive();
		$this->assertCount( 1, $result );
		$this->assertSame( 600, $result[0]->cacheTtl );
		$this->addToAssertionCount( 1 );
	}
}
