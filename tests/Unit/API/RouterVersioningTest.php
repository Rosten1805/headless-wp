<?php
/**
 * Tests del sistema de deprecación de versiones del Router.
 *
 * @package HWP\Tests\Unit\API
 */

declare( strict_types=1 );

namespace HWP\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use HWP\API\Router;
use PHPUnit\Framework\TestCase;
use WP_REST_Response;

/**
 * @covers \HWP\API\Router::markDeprecated
 */
class RouterVersioningTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// esc_url_raw devuelve la URL sin modificar en tests.
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ─────────────────────────────────────────────
	// Headers de deprecación
	// ─────────────────────────────────────────────

	public function test_mark_deprecated_sets_deprecation_header_to_true(): void {
		$response = new WP_REST_Response( [], 200 );
		$result   = Router::markDeprecated( $response, '2027-01-01' );

		$this->assertSame( 'true', $result->get_headers()['deprecation'] );
	}

	public function test_mark_deprecated_sets_sunset_date_header(): void {
		$response = new WP_REST_Response( [], 200 );
		$result   = Router::markDeprecated( $response, '2027-06-30' );

		$this->assertSame( '2027-06-30', $result->get_headers()['sunset'] );
	}

	public function test_mark_deprecated_sets_link_header_with_successor_version(): void {
		$response = new WP_REST_Response( [], 200 );
		$result   = Router::markDeprecated( $response, '2027-01-01', 'https://example.com/migrate' );

		$headers = $result->get_headers();
		$this->assertArrayHasKey( 'link', $headers );
		$this->assertStringContainsString( 'successor-version', $headers['link'] );
		$this->assertStringContainsString( 'https://example.com/migrate', $headers['link'] );
	}

	public function test_mark_deprecated_no_link_header_when_url_is_empty(): void {
		$response = new WP_REST_Response( [], 200 );
		$result   = Router::markDeprecated( $response, '2027-01-01' );

		$this->assertArrayNotHasKey( 'link', $result->get_headers() );
	}

	// ─────────────────────────────────────────────
	// Retorna el mismo objeto (decoración in-place)
	// ─────────────────────────────────────────────

	public function test_mark_deprecated_returns_same_response_object(): void {
		$response = new WP_REST_Response( [], 200 );
		$result   = Router::markDeprecated( $response, '2027-01-01' );

		$this->assertSame( $response, $result );
	}

	public function test_mark_deprecated_does_not_alter_http_status(): void {
		$response = new WP_REST_Response( [ 'data' => 'ok' ], 200 );
		Router::markDeprecated( $response, '2027-01-01' );

		$this->assertSame( 200, $response->get_status() );
	}

	// ─────────────────────────────────────────────
	// Constantes del Router
	// ─────────────────────────────────────────────

	public function test_active_namespace_is_v1(): void {
		$this->assertSame( 'hwp/v1', Router::ACTIVE_NS );
	}

	public function test_admin_namespace_constant(): void {
		$this->assertSame( 'hwp-admin/v1', Router::NS_ADMIN );
	}
}
