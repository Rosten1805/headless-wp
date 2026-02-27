<?php
/**
 * Value Object inmutable que representa la configuración completa de un endpoint dinámico.
 *
 * @package HWP\Endpoints
 */

namespace HWP\Endpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EndpointConfig
 *
 * Objeto de valor (Value Object) de solo lectura. Todos sus campos son readonly
 * para garantizar inmutabilidad en tiempo de ejecución (PHP 8.1+).
 *
 * Se construye mediante EndpointBuilder o EndpointConfig::fromArray().
 * No contiene lógica de negocio: solo transporte tipado de configuración.
 */
final class EndpointConfig {

	/**
	 * Valores por defecto de los allowed_query_params.
	 */
	private const DEFAULT_ALLOWED_PARAMS = [ 'page', 'per_page', 'search', 'orderby', 'order' ];

	/**
	 * MIME types permitidos por defecto en image_upload.
	 */
	private const DEFAULT_IMAGE_MIME = [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ];

	/**
	 * @param string        $slug                  Slug único del endpoint (ej: "articles").
	 * @param string        $postType              Post type de WordPress que expone este endpoint.
	 * @param bool          $isActive              Si el endpoint responde peticiones.
	 * @param int           $postsPerPage          Ítems por página (query_defaults.posts_per_page).
	 * @param string        $orderby               Campo de ordenación WP_Query (query_defaults.orderby).
	 * @param string        $order                 Dirección de orden: 'ASC' | 'DESC'.
	 * @param array<string> $postStatus            Estados de post permitidos.
	 * @param array<string> $allowedQueryParams     Params que el cliente puede enviar.
	 * @param array<string,string> $paramMap       Mapeo query_param → argumento WP_Query.
	 * @param bool          $singleMode            Si se registra /{slug}/{id} además de /{slug}.
	 * @param string|null   $authOverride          Estrategia de auth forzada, o null para global.
	 * @param int           $cacheTtl              Segundos de cache (0 = desactivado).
	 * @param bool          $schemaValidation      Validar body POST/PUT contra schema.
	 * @param int           $rateLimitMax          Máximo de requests en la ventana.
	 * @param int           $rateLimitWindow       Duración de la ventana en segundos.
	 * @param string        $rateLimitStrategy     'fixed_window' | 'sliding_window'.
	 * @param bool          $imageUploadEnabled    Si el endpoint acepta uploads.
	 * @param int           $imageUploadMaxSizeKb  Tamaño máximo en KB del archivo subido.
	 * @param array<string> $imageUploadAllowedMime MIMEs aceptados.
	 * @param bool          $imageUploadAutoAttach  Asociar adjunto al post del body.
	 * @param bool          $includeLinks          Incluir bloque HATEOAS en el envelope.
	 * @param bool          $includeMeta           Incluir bloque meta en el envelope.
	 * @param string        $dataKey               Nombre de la clave de datos en el envelope.
	 * @param string|null   $pathOverride          URL personalizada (rewrite rule override).
	 */
	public function __construct(
		// Identidad
		public readonly string $slug,
		public readonly string $postType,
		public readonly bool $isActive = true,
		// query_defaults
		public readonly int $postsPerPage = 10,
		public readonly string $orderby = 'date',
		public readonly string $order = 'DESC',
		public readonly array $postStatus = [ 'publish' ],
		// Parámetros admitidos
		public readonly array $allowedQueryParams = self::DEFAULT_ALLOWED_PARAMS,
		public readonly array $paramMap = [],
		// Comportamiento
		public readonly bool $singleMode = true,
		public readonly ?string $authOverride = null,
		public readonly int $cacheTtl = 300,
		public readonly bool $schemaValidation = true,
		// Rate limiting
		public readonly int $rateLimitMax = 60,
		public readonly int $rateLimitWindow = 60,
		public readonly string $rateLimitStrategy = 'fixed_window',
		// Image upload
		public readonly bool $imageUploadEnabled = false,
		public readonly int $imageUploadMaxSizeKb = 2048,
		public readonly array $imageUploadAllowedMime = self::DEFAULT_IMAGE_MIME,
		public readonly bool $imageUploadAutoAttach = true,
		// Envelope de respuesta
		public readonly bool $includeLinks = true,
		public readonly bool $includeMeta = true,
		public readonly string $dataKey = 'data',
		// Path override
		public readonly ?string $pathOverride = null,
	) {}

	// ─────────────────────────────────────────────
	// Factory methods
	// ─────────────────────────────────────────────

	/**
	 * Crea un EndpointConfig con todos los valores por defecto.
	 *
	 * Útil como punto de partida sin ningún JSON de configuración.
	 *
	 * @param string $slug     Slug del endpoint.
	 * @param string $postType Post type de WordPress.
	 */
	public static function defaults( string $slug, string $postType ): self {
		return new self( $slug, $postType );
	}

	/**
	 * Crea un EndpointConfig a partir del array JSON almacenado en hwp_endpoints.config.
	 *
	 * Los campos no presentes en el array usan los valores por defecto del constructor.
	 *
	 * @param string               $slug     Slug del endpoint.
	 * @param string               $postType Post type de WordPress.
	 * @param array<string,mixed>  $config   Array decodificado de la columna JSON.
	 */
	public static function fromArray( string $slug, string $postType, array $config ): self {
		$qd = $config['query_defaults']    ?? [];
		$rl = $config['rate_limit']        ?? [];
		$iu = $config['image_upload']      ?? [];
		$re = $config['response_envelope'] ?? [];

		return new self(
			slug:                    $slug,
			postType:                $postType,
			isActive:                $config['is_active']             ?? true,
			postsPerPage:            (int) ( $qd['posts_per_page']    ?? 10 ),
			orderby:                 $qd['orderby']                   ?? 'date',
			order:                   $qd['order']                     ?? 'DESC',
			postStatus:              $qd['post_status']               ?? [ 'publish' ],
			allowedQueryParams:      $config['allowed_query_params']  ?? self::DEFAULT_ALLOWED_PARAMS,
			paramMap:                $config['param_map']             ?? [],
			singleMode:              $config['single_mode']           ?? true,
			authOverride:            $config['auth_override']         ?? null,
			cacheTtl:                (int) ( $config['cache_ttl']     ?? 300 ),
			schemaValidation:        $config['schema_validation']     ?? true,
			rateLimitMax:            (int) ( $rl['max_requests']      ?? 60 ),
			rateLimitWindow:         (int) ( $rl['window_seconds']    ?? 60 ),
			rateLimitStrategy:       $rl['strategy']                  ?? 'fixed_window',
			imageUploadEnabled:      $iu['enabled']                   ?? false,
			imageUploadMaxSizeKb:    (int) ( $iu['max_size_kb']       ?? 2048 ),
			imageUploadAllowedMime:  $iu['allowed_mime']              ?? self::DEFAULT_IMAGE_MIME,
			imageUploadAutoAttach:   $iu['auto_attach']               ?? true,
			includeLinks:            $re['include_links']             ?? true,
			includeMeta:             $re['include_meta']              ?? true,
			dataKey:                 $re['data_key']                  ?? 'data',
			pathOverride:            $config['path_override']         ?? null,
		);
	}

	// ─────────────────────────────────────────────
	// Helpers de consulta
	// ─────────────────────────────────────────────

	/**
	 * Indica si el cache está habilitado para este endpoint.
	 */
	public function isCacheEnabled(): bool {
		return $this->cacheTtl > 0;
	}

	/**
	 * Indica si el endpoint tiene un path override configurado.
	 */
	public function hasPathOverride(): bool {
		return $this->pathOverride !== null && $this->pathOverride !== '';
	}

	/**
	 * Exporta la configuración al formato array equivalente al JSON almacenado en DB.
	 *
	 * @return array<string,mixed>
	 */
	public function toArray(): array {
		return [
			'is_active'            => $this->isActive,
			'query_defaults'       => [
				'posts_per_page' => $this->postsPerPage,
				'orderby'        => $this->orderby,
				'order'          => $this->order,
				'post_status'    => $this->postStatus,
			],
			'allowed_query_params' => $this->allowedQueryParams,
			'param_map'            => $this->paramMap,
			'single_mode'          => $this->singleMode,
			'auth_override'        => $this->authOverride,
			'cache_ttl'            => $this->cacheTtl,
			'schema_validation'    => $this->schemaValidation,
			'rate_limit'           => [
				'max_requests'   => $this->rateLimitMax,
				'window_seconds' => $this->rateLimitWindow,
				'strategy'       => $this->rateLimitStrategy,
			],
			'image_upload'         => [
				'enabled'      => $this->imageUploadEnabled,
				'max_size_kb'  => $this->imageUploadMaxSizeKb,
				'allowed_mime' => $this->imageUploadAllowedMime,
				'auto_attach'  => $this->imageUploadAutoAttach,
			],
			'response_envelope'    => [
				'include_links' => $this->includeLinks,
				'include_meta'  => $this->includeMeta,
				'data_key'      => $this->dataKey,
			],
			'path_override'        => $this->pathOverride,
		];
	}
}
