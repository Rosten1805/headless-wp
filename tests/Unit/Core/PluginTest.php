<?php

declare( strict_types=1 );

namespace HWP\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use HWP\Core\Plugin;
use HWP\Core\ServiceContainer;
use HWP\Core\Loader;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HWP\Core\Plugin
 */
final class PluginTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Plugin::resetInstance();
	}

	protected function tearDown(): void {
		Plugin::resetInstance();
		Monkey\tearDown();
		parent::tearDown();
	}

	// ── Singleton ─────────────────────────────────────────────────────────────

	public function test_get_instance_returns_same_object_on_consecutive_calls(): void {
		$first  = Plugin::getInstance();
		$second = Plugin::getInstance();

		$this->assertSame( $first, $second, 'getInstance() debe retornar siempre el mismo objeto.' );
	}

	public function test_get_instance_returns_plugin_instance(): void {
		$plugin = Plugin::getInstance();

		$this->assertInstanceOf( Plugin::class, $plugin );
	}

	public function test_reset_instance_allows_creating_fresh_instance(): void {
		$first = Plugin::getInstance();
		Plugin::resetInstance();
		$second = Plugin::getInstance();

		$this->assertNotSame( $first, $second, 'resetInstance() debe invalidar el singleton.' );
	}

	// ── Accesores ─────────────────────────────────────────────────────────────

	public function test_container_returns_service_container(): void {
		$plugin = Plugin::getInstance();

		$this->assertInstanceOf( ServiceContainer::class, $plugin->container() );
	}

	public function test_loader_returns_loader_instance(): void {
		$plugin = Plugin::getInstance();

		$this->assertInstanceOf( Loader::class, $plugin->loader() );
	}

	// ── init() registra los hooks esperados ───────────────────────────────────

	public function test_init_registers_plugins_loaded_action(): void {
		// Mockear funciones WP que Plugin::init() necesita.
		Functions\expect( 'add_action' )->atLeast()->once()->withAnyArgs();
		Functions\expect( 'add_filter' )->zeroOrMoreTimes()->withAnyArgs();
		Functions\expect( 'do_action' )->zeroOrMoreTimes()->withAnyArgs();
		Functions\expect( 'get_option' )->zeroOrMoreTimes()->andReturn( [] );

		$plugin = Plugin::getInstance();
		$plugin->init();

		$this->assertTrue(
			$plugin->loader()->hasAction( 'plugins_loaded' ),
			'init() debe registrar el hook plugins_loaded para el Upgrader.'
		);
	}

	// ── make() shortcut ───────────────────────────────────────────────────────

	public function test_make_resolves_from_container(): void {
		$plugin = Plugin::getInstance();
		$plugin->container()->bind( 'test_service', fn() => new \stdClass() );

		$resolved = $plugin->make( 'test_service' );

		$this->assertInstanceOf( \stdClass::class, $resolved );
	}
}
