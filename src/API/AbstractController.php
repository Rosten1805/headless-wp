<?php
/**
 * Controlador base del que heredan todos los controladores REST del plugin.
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
 * Class AbstractController
 *
 * Extiende WP_REST_Controller y añade:
 *   - Acceso al ResponseBuilder para envelope estandarizado.
 *   - Namespace y base URL del plugin pre-configurados.
 *   - Helpers de respuesta comunes a todos los controladores.
 *
 * Los subcontroladores DEBEN implementar register_routes() y pueden
 * apoyarse en los métodos protegidos de esta clase para construir
 * respuestas consistentes con el resto del API.
 */
abstract class AbstractController extends \WP_REST_Controller {

	/** @var ResponseBuilder Envelope builder compartido. */
	protected ResponseBuilder $responseBuilder;

	public function __construct() {
		$this->namespace      = Router::NS_V1;
		$this->responseBuilder = new ResponseBuilder();
	}

	// ─────────────────────────────────────────────
	// Helpers de respuesta
	// ─────────────────────────────────────────────

	/**
	 * Respuesta 404 estandarizada.
	 */
	protected function notFound( string $message = '' ): WP_REST_Response {
		return $this->responseBuilder->error(
			'hwp_not_found',
			$message ?: __( 'Resource not found.', 'headless-wp' ),
			404
		);
	}

	/**
	 * Respuesta 403 estandarizada.
	 */
	protected function forbidden( string $message = '' ): WP_REST_Response {
		return $this->responseBuilder->error(
			'hwp_forbidden',
			$message ?: __( 'You do not have permission to access this resource.', 'headless-wp' ),
			403
		);
	}

	/**
	 * Respuesta 500 estandarizada.
	 */
	protected function serverError( string $message = '' ): WP_REST_Response {
		return $this->responseBuilder->error(
			'hwp_server_error',
			$message ?: __( 'An unexpected server error occurred.', 'headless-wp' ),
			500
		);
	}
}
