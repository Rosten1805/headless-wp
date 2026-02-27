<?php
/**
 * Construye el envelope JSON estándar de todas las respuestas hwp/v1.
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
 * Class ResponseBuilder
 *
 * Centraliza la construcción del envelope de respuesta para garantizar
 * un formato consistente en todos los endpoints dinámicos.
 *
 * Formato de éxito (listado):
 * {
 *   "success": true,
 *   "data": [...],
 *   "meta": { "total": 100, "page": 1, "per_page": 10, "total_pages": 10 },
 *   "links": { "self": "...", "next": "...", "prev": null, ... },
 *   "timestamp": "2026-01-01T00:00:00Z",
 *   "version": "1.0.0"
 * }
 *
 * Formato de error:
 * {
 *   "success": false,
 *   "data": null,
 *   "error": { "code": "hwp_unauthorized", "message": "...", "status": 401 },
 *   "timestamp": "...",
 *   "version": "1.0.0"
 * }
 */
final class ResponseBuilder {

	/** @var bool Incluir bloque 'meta' (desactivable por endpoint). */
	private bool $includeMeta = true;

	/** @var bool Incluir bloque 'links' HATEOAS. */
	private bool $includeLinks = true;

	/** @var string Clave raíz para el payload ('data' por defecto, override por config). */
	private string $dataKey = 'data';

	// ─────────────────────────────────────────────
	// Factory methods
	// ─────────────────────────────────────────────

	/**
	 * Crea instancia con opciones del EndpointConfig.
	 *
	 * @param array $envelopeConfig Sección 'response_envelope' del config JSON.
	 */
	public static function fromConfig( array $envelopeConfig ): self {
		$builder               = new self();
		$builder->includeMeta  = $envelopeConfig['include_meta']  ?? true;
		$builder->includeLinks = $envelopeConfig['include_links'] ?? true;
		$builder->dataKey      = $envelopeConfig['data_key']      ?? 'data';
		return $builder;
	}

	// ─────────────────────────────────────────────
	// Respuestas de colección
	// ─────────────────────────────────────────────

	/**
	 * Construye una respuesta de listado paginado.
	 *
	 * @param array<int,array> $items       Items ya resueltos y filtrados.
	 * @param int              $total       Total de registros sin paginar.
	 * @param int              $page        Página actual.
	 * @param int              $perPage     Ítems por página.
	 * @param WP_REST_Request  $request     Request original (para construir links).
	 * @param bool             $fromCache   Si la respuesta proviene de cache.
	 * @return WP_REST_Response
	 */
	public function collection(
		array $items,
		int $total,
		int $page,
		int $perPage,
		WP_REST_Request $request,
		bool $fromCache = false
	): WP_REST_Response {
		$totalPages = $perPage > 0 ? (int) ceil( $total / $perPage ) : 0;

		$body = [
			'success'        => true,
			$this->dataKey   => $items,
		];

		if ( $this->includeMeta ) {
			$body['meta'] = [
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $perPage,
				'total_pages' => $totalPages,
				'filtered'    => $this->requestHasFilters( $request ),
				'cached'      => $fromCache,
			];
		}

		if ( $this->includeLinks ) {
			$body['links'] = $this->buildPaginationLinks( $request, $page, $totalPages );
		}

		$body['timestamp'] = gmdate( 'Y-m-d\TH:i:s\Z' );
		$body['version']   = HEADLESS_WP_VERSION;

		$response = new WP_REST_Response( $body, 200 );
		$response->header( 'X-HWP-Total',       (string) $total );
		$response->header( 'X-HWP-Total-Pages', (string) $totalPages );

		if ( $fromCache ) {
			$response->header( 'X-HWP-Cache', 'HIT' );
		} else {
			$response->header( 'X-HWP-Cache', 'MISS' );
		}

		return $response;
	}

	// ─────────────────────────────────────────────
	// Respuestas de recurso individual
	// ─────────────────────────────────────────────

	/**
	 * Construye una respuesta de recurso único.
	 *
	 * @param array<string,mixed> $item      Recurso resuelto.
	 * @param int                 $status    HTTP status code (200, 201).
	 * @param bool                $fromCache Si viene de cache.
	 * @return WP_REST_Response
	 */
	public function single( array $item, int $status = 200, bool $fromCache = false ): WP_REST_Response {
		$body = [
			'success'      => true,
			$this->dataKey => $item,
			'timestamp'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'version'      => HEADLESS_WP_VERSION,
		];

		$response = new WP_REST_Response( $body, $status );
		$response->header( 'X-HWP-Cache', $fromCache ? 'HIT' : 'MISS' );

		return $response;
	}

	// ─────────────────────────────────────────────
	// Respuestas de error
	// ─────────────────────────────────────────────

	/**
	 * Construye una respuesta de error estandarizada.
	 *
	 * @param string            $code    Código hwp_* del error.
	 * @param string            $message Mensaje legible.
	 * @param int               $status  HTTP status code.
	 * @param array<int,array>  $details Lista de errores de validación (campo a campo).
	 * @return WP_REST_Response
	 */
	public function error(
		string $code,
		string $message,
		int $status,
		array $details = []
	): WP_REST_Response {
		$error = [
			'code'    => $code,
			'message' => $message,
			'status'  => $status,
		];

		if ( ! empty( $details ) ) {
			$error['details'] = $details;
		}

		$body = [
			'success'   => false,
			'data'      => null,
			'error'     => $error,
			'timestamp' => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'version'   => HEADLESS_WP_VERSION,
		];

		return new WP_REST_Response( $body, $status );
	}

	/**
	 * Errores de validación (400) con lista detallada de campos.
	 *
	 * @param array<int,array{field:string,message:string,code:string}> $fieldErrors
	 * @return WP_REST_Response
	 */
	public function validationError( array $fieldErrors ): WP_REST_Response {
		return $this->error(
			'hwp_validation_error',
			__( 'The request contains invalid or missing parameters.', 'headless-wp' ),
			400,
			$fieldErrors
		);
	}

	// ─────────────────────────────────────────────
	// Helpers privados
	// ─────────────────────────────────────────────

	/**
	 * Construye links de paginación HATEOAS.
	 *
	 * @param WP_REST_Request $request
	 * @param int             $currentPage
	 * @param int             $totalPages
	 * @return array<string,string|null>
	 */
	private function buildPaginationLinks(
		WP_REST_Request $request,
		int $currentPage,
		int $totalPages
	): array {
		$base = rest_url( $request->get_route() );
		$params = $request->get_query_params();
		unset( $params['page'] );

		$pageUrl = static function ( int $page ) use ( $base, $params ): string {
			return add_query_arg( array_merge( $params, [ 'page' => $page ] ), $base );
		};

		return [
			'self'  => $pageUrl( $currentPage ),
			'first' => $totalPages > 0 ? $pageUrl( 1 ) : null,
			'prev'  => $currentPage > 1 ? $pageUrl( $currentPage - 1 ) : null,
			'next'  => $currentPage < $totalPages ? $pageUrl( $currentPage + 1 ) : null,
			'last'  => $totalPages > 0 ? $pageUrl( $totalPages ) : null,
		];
	}

	/**
	 * Detecta si la request tiene query params de filtrado activos.
	 */
	private function requestHasFilters( WP_REST_Request $request ): bool {
		$reserved = [ 'page', 'per_page', 'orderby', 'order', '_fields', '_embed' ];
		$params   = array_keys( $request->get_query_params() );

		return ! empty( array_diff( $params, $reserved ) );
	}
}
