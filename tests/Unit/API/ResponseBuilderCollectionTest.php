<?php
/**
 * Tests del envelope de colección paginada producido por ResponseBuilder.
 *
 * @package HWP\Tests\Unit\API
 */

declare( strict_types=1 );

namespace HWP\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use HWP\API\ResponseBuilder;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

/**
 * @covers \HWP\API\ResponseBuilder::collection
 * @covers \HWP\API\ResponseBuilder::fromConfig
 */
class ResponseBuilderCollectionTest extends TestCase {

	private ResponseBuilder $builder;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Stubs de funciones WordPress usadas en buildPaginationLinks()
		Functions\when( 'rest_url' )->returnArg( 1 );
		Functions\when( 'add_query_arg' )->justReturn( 'http://example.com/wp-json/hwp/v1/articles?page=1' );

		$this->builder = new ResponseBuilder();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ─────────────────────────────────────────────
	// HTTP status
	// ─────────────────────────────────────────────

	public function test_collection_returns_200_status(): void {
		$request  = new WP_REST_Request( 'GET', '/hwp/v1/articles' );
		$response = $this->builder->collection( [], 0, 1, 10, $request );

		$this->assertSame( 200, $response->get_status() );
	}

	// ─────────────────────────────────────────────
	// Estructura del envelope
	// ─────────────────────────────────────────────

	public function test_collection_envelope_has_required_keys(): void {
		$request  = new WP_REST_Request( 'GET', '/hwp/v1/articles' );
		$response = $this->builder->collection( [], 0, 1, 10, $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'success', $data );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'meta', $data );
		$this->assertArrayHasKey( 'links', $data );
		$this->assertArrayHasKey( 'timestamp', $data );
		$this->assertArrayHasKey( 'version', $data );
	}

	public function test_collection_success_is_true(): void {
		$request  = new WP_REST_Request( 'GET', '/hwp/v1/articles' );
		$response = $this->builder->collection( [], 0, 1, 10, $request );

		$this->assertTrue( $response->get_data()['success'] );
	}

	// ─────────────────────────────────────────────
	// Meta
	// ─────────────────────────────────────────────

	public function test_collection_meta_has_correct_values(): void {
		$items   = [ [ 'id' => 1 ], [ 'id' => 2 ] ];
		$request = new WP_REST_Request( 'GET', '/hwp/v1/articles' );

		$response = $this->builder->collection( $items, 50, 3, 10, $request );
		$meta     = $response->get_data()['meta'];

		$this->assertSame( 50, $meta['total'] );
		$this->assertSame( 3, $meta['page'] );
		$this->assertSame( 10, $meta['per_page'] );
		$this->assertSame( 5, $meta['total_pages'] );
	}

	public function test_collection_meta_cached_false_by_default(): void {
		$request  = new WP_REST_Request( 'GET', '/hwp/v1/articles' );
		$response = $this->builder->collection( [], 0, 1, 10, $request );

		$this->assertFalse( $response->get_data()['meta']['cached'] );
	}

	public function test_collection_meta_cached_true_when_from_cache(): void {
		$request  = new WP_REST_Request( 'GET', '/hwp/v1/articles' );
		$response = $this->builder->collection( [], 0, 1, 10, $request, true );

		$this->assertTrue( $response->get_data()['meta']['cached'] );
	}

	// ─────────────────────────────────────────────
	// Headers X-HWP-*
	// ─────────────────────────────────────────────

	public function test_collection_sets_x_hwp_total_header(): void {
		$request  = new WP_REST_Request( 'GET', '/hwp/v1/articles' );
		$response = $this->builder->collection( [], 42, 1, 10, $request );
		$headers  = $response->get_headers();

		$this->assertSame( '42', $headers['x-hwp-total'] );
	}

	public function test_collection_sets_x_hwp_total_pages_header(): void {
		$request  = new WP_REST_Request( 'GET', '/hwp/v1/articles' );
		$response = $this->builder->collection( [], 25, 1, 10, $request );
		$headers  = $response->get_headers();

		$this->assertSame( '3', $headers['x-hwp-total-pages'] );
	}

	public function test_collection_cache_miss_header_when_not_cached(): void {
		$request  = new WP_REST_Request( 'GET', '/hwp/v1/articles' );
		$response = $this->builder->collection( [], 0, 1, 10, $request, false );

		$this->assertSame( 'MISS', $response->get_headers()['x-hwp-cache'] );
	}

	public function test_collection_cache_hit_header_when_cached(): void {
		$request  = new WP_REST_Request( 'GET', '/hwp/v1/articles' );
		$response = $this->builder->collection( [], 0, 1, 10, $request, true );

		$this->assertSame( 'HIT', $response->get_headers()['x-hwp-cache'] );
	}

	// ─────────────────────────────────────────────
	// fromConfig: data_key personalizado
	// ─────────────────────────────────────────────

	public function test_collection_uses_custom_data_key_from_config(): void {
		$builder  = ResponseBuilder::fromConfig( [ 'data_key' => 'articles', 'include_meta' => true, 'include_links' => true ] );
		$request  = new WP_REST_Request( 'GET', '/hwp/v1/articles' );
		$response = $builder->collection( [ [ 'id' => 1 ] ], 1, 1, 10, $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'articles', $data );
		$this->assertArrayNotHasKey( 'data', $data );
	}

	// ─────────────────────────────────────────────
	// fromConfig: sin meta y sin links
	// ─────────────────────────────────────────────

	public function test_collection_without_meta_when_disabled(): void {
		$builder  = ResponseBuilder::fromConfig( [ 'include_meta' => false, 'include_links' => true, 'data_key' => 'data' ] );
		$request  = new WP_REST_Request( 'GET', '/hwp/v1/articles' );
		$response = $builder->collection( [], 0, 1, 10, $request );

		$this->assertArrayNotHasKey( 'meta', $response->get_data() );
	}

	public function test_collection_without_links_when_disabled(): void {
		$builder  = ResponseBuilder::fromConfig( [ 'include_links' => false, 'include_meta' => true, 'data_key' => 'data' ] );
		$request  = new WP_REST_Request( 'GET', '/hwp/v1/articles' );
		$response = $builder->collection( [], 0, 1, 10, $request );

		$this->assertArrayNotHasKey( 'links', $response->get_data() );
	}
}
