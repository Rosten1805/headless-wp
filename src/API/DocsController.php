<?php
/**
 * Controlador de documentación OpenAPI — stub Sprint 3.
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
 * Class DocsController
 *
 * Sirve la especificación OpenAPI 3.0 del plugin en formato JSON.
 * Accesible en: GET /wp-json/hwp/v1/docs
 *
 * Sprint 3: stub — devuelve un documento OpenAPI vacío con los metadatos básicos.
 * Sprint futuro: generación dinámica a partir de los endpoints activos registrados.
 *
 * Las rutas las registra Router::registerSystemRoutes().
 */
final class DocsController extends AbstractController {

	/**
	 * Las rutas se registran en Router, no aquí.
	 */
	public function register_routes(): void {}

	// ─────────────────────────────────────────────
	// Handlers
	// ─────────────────────────────────────────────

	/**
	 * GET /hwp/v1/docs
	 * Retorna la especificación OpenAPI 3.0 del API.
	 */
	public function serveSpec( WP_REST_Request $request ): WP_REST_Response {
		$spec = [
			'openapi' => '3.0.3',
			'info'    => [
				'title'       => 'Headless WP REST API',
				'version'     => HEADLESS_WP_VERSION,
				'description' => 'Dynamic REST API for headless WordPress. Documentation auto-generated from active endpoints.',
				'contact'     => [
					'name' => 'Headless WP',
					'url'  => site_url(),
				],
			],
			'servers' => [
				[ 'url' => rest_url( Router::NS_V1 ), 'description' => 'Active namespace' ],
			],
			'paths'      => [],  // Sprint futuro: generado dinámicamente.
			'components' => [],
		];

		return new WP_REST_Response( $spec, 200 );
	}

	// ─────────────────────────────────────────────
	// Permission callbacks
	// ─────────────────────────────────────────────

	/**
	 * Controla el acceso a la documentación.
	 * Por defecto pública; puede restringirse por opción global del plugin.
	 *
	 * @return bool|\WP_Error
	 */
	public function checkAccess( WP_REST_Request $request ): bool|\WP_Error {
		$public = (bool) get_option( 'hwp_settings_docs_public', true );

		if ( ! $public && ! is_user_logged_in() ) {
			return new \WP_Error(
				'hwp_unauthorized',
				__( 'API documentation requires authentication.', 'headless-wp' ),
				[ 'status' => 401 ]
			);
		}

		return true;
	}
}
