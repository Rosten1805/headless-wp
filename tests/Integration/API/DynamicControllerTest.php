<?php
/**
 * Tests de integración del DynamicController (GET collection + GET single).
 * Requieren WordPress con base de datos real y posts de prueba.
 *
 * @package HWP\Tests\Integration\API
 */

declare( strict_types=1 );

namespace HWP\Tests\Integration\API;

use HWP\API\DynamicController;
use HWP\Endpoints\EndpointConfig;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

/**
 * @covers \HWP\API\DynamicController::getCollection
 * @covers \HWP\API\DynamicController::getSingle
 */
class DynamicControllerTest extends TestCase {

	private DynamicController $controller;

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'DB_HOST' ) ) {
			$this->markTestSkipped(
				'Integration test: requires a running WordPress environment (DB_HOST not defined).'
			);
		}

		$config           = EndpointConfig::defaults( 'test-articles', 'post' );
		$this->controller = new DynamicController( $config );
	}

	// ─────────────────────────────────────────────
	// GET collection
	// ─────────────────────────────────────────────

	public function test_get_collection_returns_200(): void {
		$request  = new WP_REST_Request( 'GET', '/hwp/v1/test-articles' );
		$response = $this->controller->getCollection( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_get_collection_envelope_has_data_key(): void {
		$request  = new WP_REST_Request( 'GET', '/hwp/v1/test-articles' );
		$response = $this->controller->getCollection( $request );

		$this->assertArrayHasKey( 'data', $response->get_data() );
	}

	public function test_get_collection_envelope_has_meta_key(): void {
		$request  = new WP_REST_Request( 'GET', '/hwp/v1/test-articles' );
		$response = $this->controller->getCollection( $request );

		$this->assertArrayHasKey( 'meta', $response->get_data() );
	}

	public function test_get_collection_respects_per_page_param(): void {
		$request  = new WP_REST_Request( 'GET', '/hwp/v1/test-articles', [ 'per_page' => 5 ] );
		$response = $this->controller->getCollection( $request );

		$this->assertSame( 5, $response->get_data()['meta']['per_page'] );
	}

	// ─────────────────────────────────────────────
	// GET single
	// ─────────────────────────────────────────────

	public function test_get_single_returns_404_for_nonexistent_id(): void {
		$request  = new WP_REST_Request( 'GET', '/hwp/v1/test-articles/999999' );
		$request->set_param( 'id', 999999 );
		$response = $this->controller->getSingle( $request );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'hwp_not_found', $response->get_data()['error']['code'] );
	}

	public function test_get_single_returns_200_for_existing_post(): void {
		// Crea un post de prueba via factory de WP test.
		$postId = wp_insert_post( [
			'post_title'  => 'Test Post',
			'post_status' => 'publish',
			'post_type'   => 'post',
		] );

		$request = new WP_REST_Request( 'GET', "/hwp/v1/test-articles/{$postId}" );
		$request->set_param( 'id', $postId );
		$response = $this->controller->getSingle( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $postId, $response->get_data()['data']['id'] );

		wp_delete_post( $postId, true );
	}
}
