<?php
/**
 * Tests del envelope de recurso individual producido por ResponseBuilder.
 *
 * @package HWP\Tests\Unit\API
 */

declare( strict_types=1 );

namespace HWP\Tests\Unit\API;

use Brain\Monkey;
use HWP\API\ResponseBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HWP\API\ResponseBuilder::single
 */
class ResponseBuilderSingleTest extends TestCase {

	private ResponseBuilder $builder;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->builder = new ResponseBuilder();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ─────────────────────────────────────────────
	// HTTP status
	// ─────────────────────────────────────────────

	public function test_single_returns_200_by_default(): void {
		$response = $this->builder->single( [ 'id' => 1, 'title' => 'Hello' ] );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_single_returns_201_when_specified(): void {
		$response = $this->builder->single( [ 'id' => 99 ], 201 );

		$this->assertSame( 201, $response->get_status() );
	}

	// ─────────────────────────────────────────────
	// Estructura del envelope
	// ─────────────────────────────────────────────

	public function test_single_envelope_has_required_keys(): void {
		$response = $this->builder->single( [ 'id' => 1 ] );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'success', $data );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'timestamp', $data );
		$this->assertArrayHasKey( 'version', $data );
	}

	public function test_single_success_is_true(): void {
		$response = $this->builder->single( [ 'id' => 1 ] );

		$this->assertTrue( $response->get_data()['success'] );
	}

	public function test_single_data_contains_the_item(): void {
		$item     = [ 'id' => 5, 'title' => 'Test Post', 'status' => 'publish' ];
		$response = $this->builder->single( $item );

		$this->assertSame( $item, $response->get_data()['data'] );
	}

	public function test_single_has_no_meta_block(): void {
		$response = $this->builder->single( [ 'id' => 1 ] );

		$this->assertArrayNotHasKey( 'meta', $response->get_data() );
	}

	public function test_single_has_no_links_block(): void {
		$response = $this->builder->single( [ 'id' => 1 ] );

		$this->assertArrayNotHasKey( 'links', $response->get_data() );
	}

	// ─────────────────────────────────────────────
	// Headers X-HWP-Cache
	// ─────────────────────────────────────────────

	public function test_single_cache_miss_header_by_default(): void {
		$response = $this->builder->single( [ 'id' => 1 ] );

		$this->assertSame( 'MISS', $response->get_headers()['x-hwp-cache'] );
	}

	public function test_single_cache_hit_header_when_from_cache(): void {
		$response = $this->builder->single( [ 'id' => 1 ], 200, true );

		$this->assertSame( 'HIT', $response->get_headers()['x-hwp-cache'] );
	}

	// ─────────────────────────────────────────────
	// version y timestamp
	// ─────────────────────────────────────────────

	public function test_single_version_matches_plugin_constant(): void {
		$response = $this->builder->single( [ 'id' => 1 ] );

		$this->assertSame( HEADLESS_WP_VERSION, $response->get_data()['version'] );
	}

	public function test_single_timestamp_is_iso8601(): void {
		$response  = $this->builder->single( [ 'id' => 1 ] );
		$timestamp = $response->get_data()['timestamp'];

		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
			$timestamp
		);
	}
}
