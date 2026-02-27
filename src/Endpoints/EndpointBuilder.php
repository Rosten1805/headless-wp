<?php
/**
 * Fluent builder para construir instancias de EndpointConfig.
 *
 * @package HWP\Endpoints
 */

namespace HWP\Endpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EndpointBuilder
 *
 * Permite construir un EndpointConfig de forma declarativa y legible,
 * encadenando métodos con nombre semántico antes de llamar a build().
 *
 * Ejemplo de uso:
 *
 *   $config = ( new EndpointBuilder( 'articles', 'post' ) )
 *       ->perPage( 20 )
 *       ->orderBy( 'title', 'ASC' )
 *       ->authOverride( 'jwt' )
 *       ->cacheTtl( 600 )
 *       ->build();
 */
final class EndpointBuilder {

	// ─────────────────────────────────────────────
	// Estado mutable interno (solo durante la construcción)
	// ─────────────────────────────────────────────

	private bool $isActive = true;

	// query_defaults
	private int    $postsPerPage = 10;
	private string $orderby      = 'date';
	private string $order        = 'DESC';
	/** @var array<string> */
	private array $postStatus = [ 'publish' ];

	// Parámetros de query
	/** @var array<string> */
	private array $allowedQueryParams = [ 'page', 'per_page', 'search', 'orderby', 'order' ];
	/** @var array<string,string> */
	private array $paramMap = [];

	// Comportamiento
	private bool    $singleMode       = true;
	private ?string $authOverride     = null;
	private int     $cacheTtl         = 300;
	private bool    $schemaValidation = true;

	// Rate limiting
	private int    $rateLimitMax      = 60;
	private int    $rateLimitWindow   = 60;
	private string $rateLimitStrategy = 'fixed_window';

	// Image upload
	private bool   $imageUploadEnabled     = false;
	private int    $imageUploadMaxSizeKb   = 2048;
	/** @var array<string> */
	private array $imageUploadAllowedMime = [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ];
	private bool   $imageUploadAutoAttach  = true;

	// Envelope de respuesta
	private bool   $includeLinks = true;
	private bool   $includeMeta  = true;
	private string $dataKey      = 'data';

	// Path override
	private ?string $pathOverride = null;

	// ─────────────────────────────────────────────
	// Constructor
	// ─────────────────────────────────────────────

	/**
	 * @param string $slug     Slug único del endpoint (requerido).
	 * @param string $postType Post type de WordPress (requerido).
	 */
	public function __construct(
		private readonly string $slug,
		private readonly string $postType,
	) {}

	// ─────────────────────────────────────────────
	// Fluent setters
	// ─────────────────────────────────────────────

	/** Activa o desactiva el endpoint. */
	public function active( bool $active = true ): self {
		$clone           = clone $this;
		$clone->isActive = $active;
		return $clone;
	}

	/** Número de ítems por página. */
	public function perPage( int $perPage ): self {
		$clone               = clone $this;
		$clone->postsPerPage = $perPage;
		return $clone;
	}

	/** Campo de ordenación y dirección. */
	public function orderBy( string $orderby, string $order = 'DESC' ): self {
		$clone          = clone $this;
		$clone->orderby = $orderby;
		$clone->order   = $order;
		return $clone;
	}

	/** Estados de post aceptados. */
	public function postStatus( array $statuses ): self {
		$clone             = clone $this;
		$clone->postStatus = $statuses;
		return $clone;
	}

	/** Sobreescribe la lista de query params admitidos. */
	public function allowedParams( array $params ): self {
		$clone                    = clone $this;
		$clone->allowedQueryParams = $params;
		return $clone;
	}

	/** Mapa query_param → argumento WP_Query. */
	public function paramMap( array $map ): self {
		$clone           = clone $this;
		$clone->paramMap = $map;
		return $clone;
	}

	/** Activa o desactiva el registro de /{slug}/{id}. */
	public function withSingleMode( bool $enable = true ): self {
		$clone             = clone $this;
		$clone->singleMode = $enable;
		return $clone;
	}

	/** Fuerza una estrategia de autenticación para este endpoint. */
	public function authOverride( ?string $strategy ): self {
		$clone               = clone $this;
		$clone->authOverride = $strategy;
		return $clone;
	}

	/** Tiempo de cache en segundos. */
	public function cacheTtl( int $seconds ): self {
		$clone           = clone $this;
		$clone->cacheTtl = $seconds;
		return $clone;
	}

	/** Deshabilita el cache para este endpoint. */
	public function noCache(): self {
		return $this->cacheTtl( 0 );
	}

	/** Activa o desactiva la validación de schema en body POST/PUT. */
	public function withSchemaValidation( bool $enable = true ): self {
		$clone                   = clone $this;
		$clone->schemaValidation = $enable;
		return $clone;
	}

	/** Configura el rate limiting. */
	public function rateLimit( int $max, int $window, string $strategy = 'fixed_window' ): self {
		$clone                   = clone $this;
		$clone->rateLimitMax      = $max;
		$clone->rateLimitWindow   = $window;
		$clone->rateLimitStrategy = $strategy;
		return $clone;
	}

	/** Habilita la subida de imágenes con tamaño máximo en KB. */
	public function imageUpload( bool $enabled, int $maxKb = 2048 ): self {
		$clone                      = clone $this;
		$clone->imageUploadEnabled  = $enabled;
		$clone->imageUploadMaxSizeKb = $maxKb;
		return $clone;
	}

	/** Cambia el nombre de la clave raíz del payload en el envelope. */
	public function dataKey( string $key ): self {
		$clone          = clone $this;
		$clone->dataKey = $key;
		return $clone;
	}

	/** Elimina el bloque 'meta' del envelope. */
	public function withoutMeta(): self {
		$clone              = clone $this;
		$clone->includeMeta = false;
		return $clone;
	}

	/** Elimina el bloque 'links' HATEOAS del envelope. */
	public function withoutLinks(): self {
		$clone               = clone $this;
		$clone->includeLinks = false;
		return $clone;
	}

	/** Establece una URL personalizada como rewrite rule override. */
	public function pathOverride( string $path ): self {
		$clone               = clone $this;
		$clone->pathOverride = ltrim( $path, '/' );
		return $clone;
	}

	// ─────────────────────────────────────────────
	// Construcción final
	// ─────────────────────────────────────────────

	/**
	 * Construye y retorna el EndpointConfig inmutable.
	 */
	public function build(): EndpointConfig {
		return new EndpointConfig(
			slug:                   $this->slug,
			postType:               $this->postType,
			isActive:               $this->isActive,
			postsPerPage:           $this->postsPerPage,
			orderby:                $this->orderby,
			order:                  $this->order,
			postStatus:             $this->postStatus,
			allowedQueryParams:     $this->allowedQueryParams,
			paramMap:               $this->paramMap,
			singleMode:             $this->singleMode,
			authOverride:           $this->authOverride,
			cacheTtl:               $this->cacheTtl,
			schemaValidation:       $this->schemaValidation,
			rateLimitMax:           $this->rateLimitMax,
			rateLimitWindow:        $this->rateLimitWindow,
			rateLimitStrategy:      $this->rateLimitStrategy,
			imageUploadEnabled:     $this->imageUploadEnabled,
			imageUploadMaxSizeKb:   $this->imageUploadMaxSizeKb,
			imageUploadAllowedMime: $this->imageUploadAllowedMime,
			imageUploadAutoAttach:  $this->imageUploadAutoAttach,
			includeLinks:           $this->includeLinks,
			includeMeta:            $this->includeMeta,
			dataKey:                $this->dataKey,
			pathOverride:           $this->pathOverride,
		);
	}
}
