<?php
/**
 * Controlador de subida de imágenes — stub Sprint 3, implementación completa Sprint posterior.
 *
 * @package HWP\API
 */

namespace HWP\API;

use HWP\Endpoints\EndpointConfig;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MediaController
 *
 * Controlador para endpoints con capacidad de upload de imágenes.
 * Instanciado por EndpointFactory cuando EndpointConfig::$imageUploadEnabled es true.
 *
 * Expone:
 *   GET  /hwp/v1/{slug}        → Hereda listado de DynamicController (Sprint futuro).
 *   POST /hwp/v1/media/upload  → Subida de imagen (route fija registrada por Router).
 *
 * Sprint 3: stub — GET devuelve 501, upload devuelve 501.
 *
 * Las rutas las registra Router::registerSystemRoutes() (upload)
 * y register_routes() de esta clase (listado del endpoint dinámico).
 */
final class MediaController extends AbstractController {

	public function __construct( private readonly EndpointConfig $config ) {
		parent::__construct();
		$this->rest_base = $config->slug;
	}

	// ─────────────────────────────────────────────
	// Registro de rutas
	// ─────────────────────────────────────────────

	/**
	 * Registra la ruta de listado del endpoint dinámico con capacidad de upload.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'getCollection' ],
				'permission_callback' => [ $this, 'permissionCallback' ],
			]
		);
	}

	// ─────────────────────────────────────────────
	// Handlers
	// ─────────────────────────────────────────────

	/**
	 * GET /hwp/v1/{slug}
	 * Sprint futuro: delegará en DynamicController o implementará su propia lógica.
	 */
	public function getCollection( WP_REST_Request $request ): WP_REST_Response {
		return $this->responseBuilder->error(
			'hwp_server_error',
			__( 'Media collection endpoints will be available in a future sprint.', 'headless-wp' ),
			501
		);
	}

	/**
	 * POST /hwp/v1/media/upload
	 * Recibe multipart/form-data con el archivo y lo adjunta al post indicado.
	 * Sprint futuro: implementará validación de MIME, tamaño y adjunto al post.
	 */
	public function upload( WP_REST_Request $request ): WP_REST_Response {
		return $this->responseBuilder->error(
			'hwp_server_error',
			__( 'Media upload will be available in a future sprint.', 'headless-wp' ),
			501
		);
	}

	// ─────────────────────────────────────────────
	// Permission callbacks
	// ─────────────────────────────────────────────

	/**
	 * @return bool|\WP_Error
	 */
	public function permissionCallback( WP_REST_Request $request ): bool|\WP_Error {
		return true;
	}

	/**
	 * @return bool|\WP_Error
	 */
	public function checkAccess( WP_REST_Request $request ): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'hwp_unauthorized',
				__( 'Authentication required to upload media.', 'headless-wp' ),
				[ 'status' => 401 ]
			);
		}

		return true;
	}
}
