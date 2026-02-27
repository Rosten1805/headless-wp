<?php
/**
 * Registra todas las rutas REST del plugin con soporte de versionado.
 *
 * @package HWP\API
 */

namespace HWP\API;

use HWP\Endpoints\EndpointRegistry;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Router
 *
 * Diseño de versionado:
 * ─────────────────────────────────────────────────────────────────
 *  Namespace activo:      hwp/v1
 *  Namespace futuro:      hwp/v2   (stub, activo cuando se declare)
 *  Namespace admin:       hwp-admin/v1  (endpoints internos del dashboard)
 *  Namespace auth:        hwp/v1/auth   (sub-rutas de autenticación)
 *
 *  Rutas fijas del core:
 *    POST   /hwp/v1/auth/token          → Obtener JWT
 *    POST   /hwp/v1/auth/refresh        → Renovar token
 *    DELETE /hwp/v1/auth/token          → Revocar token (logout)
 *    GET    /hwp/v1/docs                → OpenAPI JSON spec
 *    POST   /hwp/v1/media/upload        → Upload de imágenes
 *    GET    /hwp/v1/status              → Health check público
 *
 *  Rutas dinámicas (generadas desde EndpointRegistry):
 *    {METHOD} /hwp/v1/{slug}            → Listado
 *    {METHOD} /hwp/v1/{slug}/(?P<id>\d+) → Individual
 *
 *  Rutas admin internas (requieren nonce de admin):
 *    GET|POST|PUT|DELETE /hwp-admin/v1/endpoints
 *    GET|POST|PUT|DELETE /hwp-admin/v1/api-keys
 *    GET|POST|PUT|DELETE /hwp-admin/v1/permissions
 *    GET                 /hwp-admin/v1/logs
 *    GET                 /hwp-admin/v1/fields/discover
 *    GET                 /hwp-admin/v1/status
 * ─────────────────────────────────────────────────────────────────
 *
 * Política de deprecación:
 *  - Un namespace permanece soportado durante 2 versiones mayor del plugin.
 *  - Las rutas deprecated añaden header: Deprecation: true, Sunset: {date}
 *  - El cliente puede detectarlo vía header sin romper el contrato.
 */
final class Router {

	public const NS_V1        = 'hwp/v1';
	public const NS_V2        = 'hwp/v2';      // Reservado para uso futuro.
	public const NS_ADMIN     = 'hwp-admin/v1';
	public const ACTIVE_NS    = self::NS_V1;    // Namespace principal activo.

	private EndpointRegistry $registry;
	private EndpointFactory  $factory;
	private AuthController   $authController;
	private MediaController  $mediaController;
	private DocsController   $docsController;
	private AdminRestProxy   $adminProxy;

	public function __construct(
		EndpointRegistry $registry,
		EndpointFactory $factory,
		AuthController $authController,
		MediaController $mediaController,
		DocsController $docsController,
		AdminRestProxy $adminProxy
	) {
		$this->registry        = $registry;
		$this->factory         = $factory;
		$this->authController  = $authController;
		$this->mediaController = $mediaController;
		$this->docsController  = $docsController;
		$this->adminProxy      = $adminProxy;
	}

	/**
	 * Registra el hook principal. Llamado en Plugin::init().
	 */
	public function init(): void {
		add_action( 'rest_api_init', [ $this, 'registerRoutes' ] );
	}

	/**
	 * Punto de entrada del registro. Sigue el orden:
	 * 1. Rutas de sistema (auth, docs, media, status)
	 * 2. Rutas dinámicas desde EndpointRegistry
	 * 3. Rutas admin internas
	 */
	public function registerRoutes(): void {
		$this->registerSystemRoutes();
		$this->registerDynamicRoutes();
		$this->registerAdminRoutes();

		do_action( 'hwp_rest_routes_registered', self::ACTIVE_NS );
	}

	// ─────────────────────────────────────────────
	// Sistema
	// ─────────────────────────────────────────────

	private function registerSystemRoutes(): void {

		// ── Health check (público, sin auth) ─────────────────────
		register_rest_route( self::NS_V1, '/status', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'handleStatus' ],
			'permission_callback' => '__return_true',
		] );

		// ── Autenticación ─────────────────────────────────────────
		register_rest_route( self::NS_V1, '/auth/token', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this->authController, 'issueToken' ],
				'permission_callback' => '__return_true',
				'args'                => $this->authTokenArgs(),
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this->authController, 'revokeToken' ],
				'permission_callback' => [ $this->authController, 'requireAuth' ],
			],
		] );

		register_rest_route( self::NS_V1, '/auth/refresh', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this->authController, 'refreshToken' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'refresh_token' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );

		// ── Documentación ─────────────────────────────────────────
		register_rest_route( self::NS_V1, '/docs', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this->docsController, 'serveSpec' ],
			'permission_callback' => [ $this->docsController, 'checkAccess' ],
		] );

		// ── Media ─────────────────────────────────────────────────
		register_rest_route( self::NS_V1, '/media/upload', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this->mediaController, 'upload' ],
			'permission_callback' => [ $this->mediaController, 'checkAccess' ],
		] );
	}

	// ─────────────────────────────────────────────
	// Dinámicas
	// ─────────────────────────────────────────────

	private function registerDynamicRoutes(): void {
		$activeEndpoints = $this->registry->getActive();

		foreach ( $activeEndpoints as $config ) {
			$controller = $this->factory->make( $config );
			$controller->register_routes();
		}
	}

	// ─────────────────────────────────────────────
	// Admin internas
	// ─────────────────────────────────────────────

	private function registerAdminRoutes(): void {
		$this->adminProxy->registerRoutes( self::NS_ADMIN );
	}

	// ─────────────────────────────────────────────
	// Health check callback
	// ─────────────────────────────────────────────

	/**
	 * GET /hwp/v1/status
	 *
	 * Retorna información básica del plugin sin datos sensibles.
	 * Útil para monitoreo y para que el frontend verifique disponibilidad.
	 */
	public function handleStatus( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'success'    => true,
				'data'       => [
					'plugin'     => 'headless-wp',
					'version'    => HEADLESS_WP_VERSION,
					'namespace'  => self::ACTIVE_NS,
					'wp_version' => get_bloginfo( 'version' ),
					'php_version'=> PHP_VERSION,
					'multisite'  => is_multisite(),
				],
				'timestamp' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'version'   => HEADLESS_WP_VERSION,
			],
			200
		);
	}

	// ─────────────────────────────────────────────
	// Args schemas
	// ─────────────────────────────────────────────

	/** @return array<string,array<string,mixed>> */
	private function authTokenArgs(): array {
		return [
			'username' => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_user',
			],
			'password' => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	// ─────────────────────────────────────────────
	// Helpers de versionado
	// ─────────────────────────────────────────────

	/**
	 * Añade headers de deprecación a una respuesta cuando una ruta está
	 * marcada como obsoleta pero todavía funcional.
	 *
	 * @param \WP_REST_Response $response    Respuesta a decorar.
	 * @param string            $sunsetDate  Fecha ISO 8601 a partir de la cual se elimina.
	 * @param string            $migrateUrl  URL con documentación de migración.
	 * @return \WP_REST_Response
	 */
	public static function markDeprecated(
		\WP_REST_Response $response,
		string $sunsetDate,
		string $migrateUrl = ''
	): \WP_REST_Response {
		$response->header( 'Deprecation', 'true' );
		$response->header( 'Sunset', $sunsetDate );

		if ( $migrateUrl ) {
			$response->header( 'Link', '<' . esc_url_raw( $migrateUrl ) . '>; rel="successor-version"' );
		}

		return $response;
	}
}
