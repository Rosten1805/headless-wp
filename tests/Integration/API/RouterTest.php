<?php
/**
 * Tests de integración del Router REST.
 * Requieren WordPress cargado con la suite WP_UnitTestCase.
 *
 * @package HWP\Tests\Integration\API
 */

declare( strict_types=1 );

namespace HWP\Tests\Integration\API;

use HWP\API\Router;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HWP\API\Router::registerRoutes
 */
class RouterTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'DB_HOST' ) ) {
			$this->markTestSkipped(
				'Integration test: requires a running WordPress environment (DB_HOST not defined).'
			);
		}
	}

	// ─────────────────────────────────────────────
	// Namespace público
	// ─────────────────────────────────────────────

	public function test_namespace_v1_is_registered(): void {
		$server    = rest_get_server();
		$namespaces = $server->get_namespaces();

		$this->assertContains( Router::NS_V1, $namespaces );
	}

	public function test_status_route_is_registered(): void {
		$routes = rest_get_server()->get_routes( Router::NS_V1 );

		$this->assertArrayHasKey( '/' . Router::NS_V1 . '/status', $routes );
	}

	public function test_auth_token_route_is_registered(): void {
		$routes = rest_get_server()->get_routes( Router::NS_V1 );

		$this->assertArrayHasKey( '/' . Router::NS_V1 . '/auth/token', $routes );
	}

	public function test_auth_refresh_route_is_registered(): void {
		$routes = rest_get_server()->get_routes( Router::NS_V1 );

		$this->assertArrayHasKey( '/' . Router::NS_V1 . '/auth/refresh', $routes );
	}

	public function test_docs_route_is_registered(): void {
		$routes = rest_get_server()->get_routes( Router::NS_V1 );

		$this->assertArrayHasKey( '/' . Router::NS_V1 . '/docs', $routes );
	}

	public function test_media_upload_route_is_registered(): void {
		$routes = rest_get_server()->get_routes( Router::NS_V1 );

		$this->assertArrayHasKey( '/' . Router::NS_V1 . '/media/upload', $routes );
	}

	// ─────────────────────────────────────────────
	// Namespace admin
	// ─────────────────────────────────────────────

	public function test_admin_namespace_is_registered(): void {
		$namespaces = rest_get_server()->get_namespaces();

		$this->assertContains( Router::NS_ADMIN, $namespaces );
	}

	public function test_admin_endpoints_route_is_registered(): void {
		$routes = rest_get_server()->get_routes( Router::NS_ADMIN );

		$this->assertArrayHasKey( '/' . Router::NS_ADMIN . '/endpoints', $routes );
	}
}
