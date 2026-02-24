<?php

declare( strict_types=1 );

namespace HWP\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use HWP\Core\Loader;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HWP\Core\Loader
 */
final class LoaderTest extends TestCase {

	private Loader $loader;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->loader = new Loader();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ── Registro en cola ──────────────────────────────────────────────────────

	public function test_add_action_stores_entry_in_queue(): void {
		$callback = fn() => null;
		$this->loader->addAction( 'init', $callback, 10, 1 );

		$actions = $this->loader->getActions();

		$this->assertCount( 1, $actions );
		$this->assertSame( 'init', $actions[0]['hook'] );
		$this->assertSame( $callback, $actions[0]['callback'] );
		$this->assertSame( 10, $actions[0]['priority'] );
		$this->assertSame( 1, $actions[0]['accepted_args'] );
	}

	public function test_add_filter_stores_entry_in_queue(): void {
		$callback = fn( $v ) => $v;
		$this->loader->addFilter( 'the_title', $callback, 20, 2 );

		$filters = $this->loader->getFilters();

		$this->assertCount( 1, $filters );
		$this->assertSame( 'the_title', $filters[0]['hook'] );
		$this->assertSame( 20, $filters[0]['priority'] );
		$this->assertSame( 2, $filters[0]['accepted_args'] );
	}

	public function test_multiple_hooks_accumulate_independently(): void {
		$this->loader->addAction( 'init',          fn() => null );
		$this->loader->addAction( 'wp_loaded',     fn() => null );
		$this->loader->addAction( 'rest_api_init', fn() => null );
		$this->loader->addFilter( 'the_content',   fn( $v ) => $v );

		$this->assertCount( 3, $this->loader->getActions() );
		$this->assertCount( 1, $this->loader->getFilters() );
	}

	// ── has_action / has_filter ───────────────────────────────────────────────

	public function test_has_action_returns_true_when_registered(): void {
		$this->loader->addAction( 'save_post', fn() => null );

		$this->assertTrue( $this->loader->hasAction( 'save_post' ) );
	}

	public function test_has_action_returns_false_when_not_registered(): void {
		$this->assertFalse( $this->loader->hasAction( 'non_existent_hook' ) );
	}

	public function test_has_filter_returns_true_when_registered(): void {
		$this->loader->addFilter( 'rest_pre_serve_request', fn( $v ) => $v );

		$this->assertTrue( $this->loader->hasFilter( 'rest_pre_serve_request' ) );
	}

	public function test_has_filter_returns_false_for_action_only_hook(): void {
		$this->loader->addAction( 'init', fn() => null );

		// 'init' está solo como action, no como filter.
		$this->assertFalse( $this->loader->hasFilter( 'init' ) );
	}

	// ── run() delega en WordPress ─────────────────────────────────────────────

	/**
	 * Verifica que run() registra el número correcto de actions.
	 * Usamos Brain\Monkey para mockear las funciones globales de WP y
	 * addToAssertionCount para satisfacer el risky-test detector de PHPUnit 11.
	 */
	public function test_run_calls_add_action_for_each_registered_action(): void {
		Functions\expect( 'add_action' )->twice()->withAnyArgs();
		Functions\expect( 'add_filter' )->never();

		$this->loader->addAction( 'init',      fn() => null, 10, 1 );
		$this->loader->addAction( 'wp_loaded', fn() => null, 5, 1 );
		$this->loader->run();

		// Brain\Monkey verifica en tearDown — contabilizamos la expectativa.
		$this->addToAssertionCount( 1 );
	}

	public function test_run_calls_add_filter_for_each_registered_filter(): void {
		Functions\expect( 'add_action' )->never();
		Functions\expect( 'add_filter' )
			->once()
			->with( 'the_content', \Mockery::type( 'callable' ), 10, 1 );

		$this->loader->addFilter( 'the_content', fn( $v ) => $v, 10, 1 );
		$this->loader->run();

		$this->addToAssertionCount( 1 );
	}

	public function test_run_passes_correct_priority_to_wordpress(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'rest_api_init', \Mockery::any(), 5, 2 );

		$this->loader->addAction( 'rest_api_init', fn() => null, 5, 2 );
		$this->loader->run();

		$this->addToAssertionCount( 1 );
	}

	// ── run() con queues vacías ───────────────────────────────────────────────

	public function test_run_with_empty_queues_does_not_call_wp_functions(): void {
		Functions\expect( 'add_action' )->never();
		Functions\expect( 'add_filter' )->never();

		$this->loader->run(); // No debe explotar ni llamar nada.

		$this->assertTrue( true ); // Llegar aquí es pasar el test.
	}
}
