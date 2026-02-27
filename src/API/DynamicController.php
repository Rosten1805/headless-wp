<?php
/**
 * Controlador genérico para endpoints dinámicos configurados desde el dashboard.
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
 * Class DynamicController
 *
 * Controlador CRUD genérico que se instancia una vez por endpoint activo.
 * Delega en EndpointConfig para saber qué post_type consultar, qué campos
 * exponer y qué parámetros acepta el cliente.
 *
 * Sprint 3 implementa lectura (GET collection + GET single).
 * Las operaciones de escritura (POST, PUT, DELETE) se añadirán en Sprints posteriores
 * cuando estén disponibles PermissionManager y SchemaValidator integrados.
 */
final class DynamicController extends AbstractController {

	public function __construct( private readonly EndpointConfig $config ) {
		parent::__construct();
		$this->rest_base = $config->slug;
	}

	// ─────────────────────────────────────────────
	// Registro de rutas
	// ─────────────────────────────────────────────

	/**
	 * Registra las rutas REST para este endpoint dinámico.
	 * Llamado por Router dentro del hook `rest_api_init`.
	 */
	public function register_routes(): void {
		// ── Colección: GET /hwp/v1/{slug} ─────────────────────────
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'getCollection' ],
				'permission_callback' => [ $this, 'permissionCallback' ],
				'args'                => $this->collectionArgs(),
			]
		);

		// ── Individual: GET /hwp/v1/{slug}/(?P<id>\d+) ────────────
		if ( $this->config->singleMode ) {
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/(?P<id>[\d]+)',
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getSingle' ],
					'permission_callback' => [ $this, 'permissionCallback' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						],
					],
				]
			);
		}
	}

	// ─────────────────────────────────────────────
	// Permission callback
	// ─────────────────────────────────────────────

	/**
	 * Valida acceso al endpoint.
	 *
	 * Sprint 3: abierto si auth_override = 'none', de lo contrario devuelve true
	 * (permiso real se implementa en Sprint 6 — PermissionManager).
	 *
	 * @return bool|\WP_Error
	 */
	public function permissionCallback( WP_REST_Request $request ): bool|\WP_Error {
		if ( 'none' === $this->config->authOverride ) {
			return true;
		}

		// Placeholder: Sprint 5 añadirá AuthManager y Sprint 6 PermissionMatrix.
		return true;
	}

	// ─────────────────────────────────────────────
	// Handlers
	// ─────────────────────────────────────────────

	/**
	 * GET /hwp/v1/{slug}
	 * Retorna una colección paginada de posts según la configuración del endpoint.
	 */
	public function getCollection( WP_REST_Request $request ): WP_REST_Response {
		$page    = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = (int) ( $request->get_param( 'per_page' ) ?? $this->config->postsPerPage );
		$perPage = max( 1, min( $perPage, 100 ) );

		$queryArgs = [
			'post_type'      => $this->config->postType,
			'post_status'    => $this->config->postStatus,
			'posts_per_page' => $perPage,
			'paged'          => $page,
			'orderby'        => $this->config->orderby,
			'order'          => $this->config->order,
		];

		// Mapea query params del cliente a argumentos WP_Query.
		foreach ( $this->config->allowedQueryParams as $param ) {
			$value = $request->get_param( $param );
			if ( null !== $value && isset( $this->config->paramMap[ $param ] ) ) {
				$queryArgs[ $this->config->paramMap[ $param ] ] = $value;
			}
		}

		$query = new \WP_Query( $queryArgs );

		$items = array_map( [ $this, 'serializePost' ], $query->posts );

		return $this->responseBuilder->collection(
			$items,
			(int) $query->found_posts,
			$page,
			$perPage,
			$request
		);
	}

	/**
	 * GET /hwp/v1/{slug}/(?P<id>\d+)
	 * Retorna un recurso individual por ID.
	 */
	public function getSingle( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if (
			! $post instanceof \WP_Post
			|| $post->post_type !== $this->config->postType
			|| ! in_array( $post->post_status, $this->config->postStatus, true )
		) {
			return $this->responseBuilder->error(
				'hwp_not_found',
				__( 'Resource not found.', 'headless-wp' ),
				404
			);
		}

		return $this->responseBuilder->single( $this->serializePost( $post ) );
	}

	// ─────────────────────────────────────────────
	// Serialización
	// ─────────────────────────────────────────────

	/**
	 * Convierte un WP_Post al formato de recurso del envelope.
	 *
	 * Sprint 3 expone los campos base. Sprints posteriores añadirán
	 * resolución de campos custom, taxonomías y media según EndpointConfig.
	 *
	 * @return array<string,mixed>
	 */
	private function serializePost( \WP_Post $post ): array {
		return [
			'id'         => $post->ID,
			'type'       => $post->post_type,
			'slug'       => $post->post_name,
			'status'     => $post->post_status,
			'created_at' => mysql2date( 'c', $post->post_date_gmt, false ),
			'updated_at' => mysql2date( 'c', $post->post_modified_gmt, false ),
			'fields'     => [],
			'taxonomies' => [],
			'media'      => null,
		];
	}

	// ─────────────────────────────────────────────
	// Args schemas
	// ─────────────────────────────────────────────

	/** @return array<string,array<string,mixed>> */
	private function collectionArgs(): array {
		return [
			'page'     => [
				'default'          => 1,
				'type'             => 'integer',
				'minimum'          => 1,
				'sanitize_callback' => 'absint',
			],
			'per_page' => [
				'default'          => $this->config->postsPerPage,
				'type'             => 'integer',
				'minimum'          => 1,
				'maximum'          => 100,
				'sanitize_callback' => 'absint',
			],
		];
	}
}
