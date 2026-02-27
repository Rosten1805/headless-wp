<?php
/**
 * Registra los endpoints REST internos del dashboard de administración.
 *
 * @package HWP\API
 */

namespace HWP\API;

use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminRestProxy
 *
 * Gestiona el namespace hwp-admin/v1 reservado exclusivamente para
 * el dashboard React. Todos los endpoints requieren autenticación
 * WP estándar + nonce para prevenir CSRF.
 *
 * Rutas registradas:
 *   GET|POST|PUT|DELETE  /endpoints
 *   GET|POST|PUT|DELETE  /endpoints/{slug}
 *   GET|POST             /api-keys
 *   GET|PUT              /permissions
 *   GET                  /logs
 *   GET                  /fields/discover
 *   GET|PUT              /settings
 *   GET                  /status
 *
 * Sprint 3: estructura completa con stubs de handlers.
 * Sprint 4: handlers conectados al store React y a los repositorios.
 */
final class AdminRestProxy {

	// ─────────────────────────────────────────────
	// Registro de rutas
	// ─────────────────────────────────────────────

	/**
	 * Registra todas las rutas admin. Llamado desde Router::registerAdminRoutes().
	 *
	 * @param string $namespace hwp-admin/v1
	 */
	public function registerRoutes( string $namespace ): void {
		$this->registerEndpointRoutes( $namespace );
		$this->registerApiKeyRoutes( $namespace );
		$this->registerPermissionRoutes( $namespace );
		$this->registerLogRoutes( $namespace );
		$this->registerSettingsRoutes( $namespace );
		$this->registerUtilityRoutes( $namespace );
	}

	// ─────────────────────────────────────────────
	// Subrutas
	// ─────────────────────────────────────────────

	private function registerEndpointRoutes( string $ns ): void {
		register_rest_route( $ns, '/endpoints', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'listEndpoints' ],
				'permission_callback' => [ $this, 'requireAdminAccess' ],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'createEndpoint' ],
				'permission_callback' => [ $this, 'requireAdminAccess' ],
			],
		] );

		register_rest_route( $ns, '/endpoints/(?P<slug>[a-z0-9\-]+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'getEndpoint' ],
				'permission_callback' => [ $this, 'requireAdminAccess' ],
			],
			[
				'methods'             => 'PUT',
				'callback'            => [ $this, 'updateEndpoint' ],
				'permission_callback' => [ $this, 'requireAdminAccess' ],
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'deleteEndpoint' ],
				'permission_callback' => [ $this, 'requireAdminAccess' ],
			],
		] );
	}

	private function registerApiKeyRoutes( string $ns ): void {
		register_rest_route( $ns, '/api-keys', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'listApiKeys' ],
				'permission_callback' => [ $this, 'requireAdminAccess' ],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'createApiKey' ],
				'permission_callback' => [ $this, 'requireAdminAccess' ],
			],
		] );
	}

	private function registerPermissionRoutes( string $ns ): void {
		register_rest_route( $ns, '/permissions', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'getPermissions' ],
				'permission_callback' => [ $this, 'requireAdminAccess' ],
			],
			[
				'methods'             => 'PUT',
				'callback'            => [ $this, 'updatePermissions' ],
				'permission_callback' => [ $this, 'requireAdminAccess' ],
			],
		] );
	}

	private function registerLogRoutes( string $ns ): void {
		register_rest_route( $ns, '/logs', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'getLogs' ],
			'permission_callback' => [ $this, 'requireAdminAccess' ],
		] );
	}

	private function registerSettingsRoutes( string $ns ): void {
		register_rest_route( $ns, '/settings', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'getSettings' ],
				'permission_callback' => [ $this, 'requireAdminAccess' ],
			],
			[
				'methods'             => 'PUT',
				'callback'            => [ $this, 'updateSettings' ],
				'permission_callback' => [ $this, 'requireAdminAccess' ],
			],
		] );
	}

	private function registerUtilityRoutes( string $ns ): void {
		register_rest_route( $ns, '/fields/discover', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'discoverFields' ],
			'permission_callback' => [ $this, 'requireAdminAccess' ],
		] );

		register_rest_route( $ns, '/status', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'getStatus' ],
			'permission_callback' => [ $this, 'requireAdminAccess' ],
		] );
	}

	// ─────────────────────────────────────────────
	// Permission callback
	// ─────────────────────────────────────────────

	/**
	 * Requiere usuario autenticado con cap manage_options + nonce WordPress REST.
	 *
	 * @return bool|\WP_Error
	 */
	public function requireAdminAccess( WP_REST_Request $request ): bool|\WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'hwp_forbidden',
				__( 'Insufficient permissions.', 'headless-wp' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	// ─────────────────────────────────────────────
	// Stub handlers (implementación completa en Sprint 4)
	// ─────────────────────────────────────────────

	/** @return WP_REST_Response */
	public function listEndpoints( WP_REST_Request $r ): WP_REST_Response {
		return $this->stub();
	}

	/** @return WP_REST_Response */
	public function createEndpoint( WP_REST_Request $r ): WP_REST_Response {
		return $this->stub();
	}

	/** @return WP_REST_Response */
	public function getEndpoint( WP_REST_Request $r ): WP_REST_Response {
		return $this->stub();
	}

	/** @return WP_REST_Response */
	public function updateEndpoint( WP_REST_Request $r ): WP_REST_Response {
		return $this->stub();
	}

	/** @return WP_REST_Response */
	public function deleteEndpoint( WP_REST_Request $r ): WP_REST_Response {
		return $this->stub();
	}

	/** @return WP_REST_Response */
	public function listApiKeys( WP_REST_Request $r ): WP_REST_Response {
		return $this->stub();
	}

	/** @return WP_REST_Response */
	public function createApiKey( WP_REST_Request $r ): WP_REST_Response {
		return $this->stub();
	}

	/** @return WP_REST_Response */
	public function getPermissions( WP_REST_Request $r ): WP_REST_Response {
		return $this->stub();
	}

	/** @return WP_REST_Response */
	public function updatePermissions( WP_REST_Request $r ): WP_REST_Response {
		return $this->stub();
	}

	/** @return WP_REST_Response */
	public function getLogs( WP_REST_Request $r ): WP_REST_Response {
		return $this->stub();
	}

	/** @return WP_REST_Response */
	public function getSettings( WP_REST_Request $r ): WP_REST_Response {
		return $this->stub();
	}

	/** @return WP_REST_Response */
	public function updateSettings( WP_REST_Request $r ): WP_REST_Response {
		return $this->stub();
	}

	/** @return WP_REST_Response */
	public function discoverFields( WP_REST_Request $r ): WP_REST_Response {
		return $this->stub();
	}

	/** @return WP_REST_Response */
	public function getStatus( WP_REST_Request $r ): WP_REST_Response {
		return new WP_REST_Response(
			[
				'success'   => true,
				'data'      => [
					'version'  => HEADLESS_WP_VERSION,
					'namespace' => Router::NS_ADMIN,
				],
				'timestamp' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'version'   => HEADLESS_WP_VERSION,
			],
			200
		);
	}

	// ─────────────────────────────────────────────
	// Helper privado
	// ─────────────────────────────────────────────

	private function stub(): WP_REST_Response {
		return new WP_REST_Response(
			[
				'success'   => false,
				'data'      => null,
				'error'     => [
					'code'    => 'hwp_server_error',
					'message' => 'This admin endpoint will be available in Sprint 4 (v0.4.0).',
					'status'  => 501,
				],
				'timestamp' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'version'   => HEADLESS_WP_VERSION,
			],
			501
		);
	}
}
