<?php
/**
 * Controlador de autenticación — stub Sprint 3, implementación completa Sprint 5.
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
 * Class AuthController
 *
 * Gestiona los endpoints de autenticación:
 *   POST   /hwp/v1/auth/token    → issueToken()
 *   POST   /hwp/v1/auth/refresh  → refreshToken()
 *   DELETE /hwp/v1/auth/token    → revokeToken()
 *
 * Sprint 3: stub — devuelve 501 Not Implemented con mensaje informativo.
 * Sprint 5: implementación completa con JwtCodec, AuthManager y TokenRepository.
 *
 * Las rutas las registra Router::registerSystemRoutes(),
 * no este controlador directamente.
 */
final class AuthController extends AbstractController {

	private const MSG_NOT_YET = 'Authentication endpoints will be available in v0.5.0.';

	/**
	 * Las rutas se registran en Router, no aquí.
	 * Este método queda vacío intencionadamente.
	 */
	public function register_routes(): void {}

	// ─────────────────────────────────────────────
	// Handlers
	// ─────────────────────────────────────────────

	/**
	 * POST /hwp/v1/auth/token
	 * Emite un par access_token + refresh_token (JWT HS256).
	 * Sprint 5 implementará la emisión real.
	 */
	public function issueToken( WP_REST_Request $request ): WP_REST_Response {
		return $this->responseBuilder->error( 'hwp_server_error', self::MSG_NOT_YET, 501 );
	}

	/**
	 * POST /hwp/v1/auth/refresh
	 * Rota el refresh_token y emite un nuevo par de tokens.
	 * Sprint 5 implementará la rotación real.
	 */
	public function refreshToken( WP_REST_Request $request ): WP_REST_Response {
		return $this->responseBuilder->error( 'hwp_server_error', self::MSG_NOT_YET, 501 );
	}

	/**
	 * DELETE /hwp/v1/auth/token
	 * Revoca el refresh_token (logout).
	 * Sprint 5 implementará la revocación real.
	 */
	public function revokeToken( WP_REST_Request $request ): WP_REST_Response {
		return $this->responseBuilder->error( 'hwp_server_error', self::MSG_NOT_YET, 501 );
	}

	// ─────────────────────────────────────────────
	// Permission callbacks
	// ─────────────────────────────────────────────

	/**
	 * Requiere que la petición venga autenticada.
	 * Usado por DELETE /auth/token para proteger la revocación.
	 * Sprint 5 implementará la verificación real via AuthManager.
	 *
	 * @return bool|\WP_Error
	 */
	public function requireAuth( WP_REST_Request $request ): bool|\WP_Error {
		return new \WP_Error(
			'hwp_unauthorized',
			__( 'Authentication required.', 'headless-wp' ),
			[ 'status' => 401 ]
		);
	}
}
