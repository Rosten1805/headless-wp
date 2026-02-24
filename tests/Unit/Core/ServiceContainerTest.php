<?php

declare( strict_types=1 );

namespace HWP\Tests\Unit\Core;

use Brain\Monkey;
use HWP\Core\ServiceContainer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \HWP\Core\ServiceContainer
 */
final class ServiceContainerTest extends TestCase {

	private ServiceContainer $container;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->container = new ServiceContainer();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ── bind + make ───────────────────────────────────────────────────────────

	public function test_make_returns_instance_from_binding(): void {
		$this->container->bind( 'my_service', fn() => new \stdClass() );

		$result = $this->container->make( 'my_service' );

		$this->assertInstanceOf( \stdClass::class, $result );
	}

	public function test_make_returns_singleton_by_default(): void {
		$this->container->bind( 'my_service', fn() => new \stdClass() );

		$a = $this->container->make( 'my_service' );
		$b = $this->container->make( 'my_service' );

		$this->assertSame( $a, $b, 'Singleton: debe retornar la misma instancia.' );
	}

	public function test_make_returns_new_instance_when_not_singleton(): void {
		$this->container->bind( 'transient', fn() => new \stdClass(), false );

		$a = $this->container->make( 'transient' );
		$b = $this->container->make( 'transient' );

		$this->assertNotSame( $a, $b, 'No-singleton: debe retornar instancias distintas.' );
	}

	public function test_make_throws_when_no_binding_exists(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessageMatches( "/No binding found.*'undefined_service'/" );

		$this->container->make( 'undefined_service' );
	}

	// ── has ───────────────────────────────────────────────────────────────────

	public function test_has_returns_true_when_binding_exists(): void {
		$this->container->bind( 'existing', fn() => null );

		$this->assertTrue( $this->container->has( 'existing' ) );
	}

	public function test_has_returns_false_when_binding_does_not_exist(): void {
		$this->assertFalse( $this->container->has( 'non_existing' ) );
	}

	public function test_has_returns_true_after_instance_registered(): void {
		$this->container->instance( 'obj', new \stdClass() );

		$this->assertTrue( $this->container->has( 'obj' ) );
	}

	// ── instance ──────────────────────────────────────────────────────────────

	public function test_instance_registers_prebuilt_object(): void {
		$obj = new \stdClass();
		$obj->value = 42;

		$this->container->instance( 'my_obj', $obj );

		$this->assertSame( $obj, $this->container->make( 'my_obj' ) );
	}

	// ── fresh ─────────────────────────────────────────────────────────────────

	public function test_fresh_returns_new_instance_even_if_singleton(): void {
		$this->container->bind( 'singleton_service', fn() => new \stdClass() );

		$first  = $this->container->make( 'singleton_service' );
		$second = $this->container->fresh( 'singleton_service' );

		$this->assertNotSame( $first, $second, 'fresh() debe ignorar el singleton cacheado.' );
	}

	// ── rebind ────────────────────────────────────────────────────────────────

	public function test_rebinding_invalidates_cached_singleton(): void {
		$this->container->bind( 'service', fn() => new \stdClass() );
		$first = $this->container->make( 'service' );

		// Re-registrar el binding invalida la instancia cacheada.
		$this->container->bind( 'service', fn() => new \stdClass() );
		$second = $this->container->make( 'service' );

		$this->assertNotSame( $first, $second );
	}

	// ── factory recibe el container ───────────────────────────────────────────

	public function test_factory_receives_container_as_argument(): void {
		$receivedContainer = null;

		$this->container->bind( 'aware_service', function ( $c ) use ( &$receivedContainer ) {
			$receivedContainer = $c;
			return new \stdClass();
		} );

		$this->container->make( 'aware_service' );

		$this->assertSame( $this->container, $receivedContainer );
	}

	// ── PSR-11 get() alias ────────────────────────────────────────────────────

	public function test_get_is_alias_for_make(): void {
		$this->container->bind( 'psr_service', fn() => new \stdClass() );

		$this->assertSame(
			$this->container->make( 'psr_service' ),
			$this->container->get( 'psr_service' )
		);
	}
}
