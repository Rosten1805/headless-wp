<?php
/**
 * Stubs mínimos de clases WordPress para tests unitarios.
 *
 * Solo se cargan cuando las clases reales no están disponibles
 * (entorno de tests sin WordPress instalado).
 */

declare( strict_types=1 );

// ─────────────────────────────────────────────────────────────────────────────
// WP_REST_Request
// ─────────────────────────────────────────────────────────────────────────────
if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Stub de WP_REST_Request para tests unitarios.
	 * Implementa únicamente los métodos usados por ResponseBuilder.
	 */
	class WP_REST_Request { // phpcs:ignore
		/** @var array<string,mixed> */
		private array $queryParams;

		public function __construct(
			private string $method = 'GET',
			private string $route  = '',
			array          $queryParams = []
		) {
			$this->queryParams = $queryParams;
		}

		public function get_route(): string {
			return $this->route;
		}

		public function get_method(): string {
			return $this->method;
		}

		/** @return array<string,mixed> */
		public function get_query_params(): array {
			return $this->queryParams;
		}

		public function get_param( string $key ): mixed {
			return $this->queryParams[ $key ] ?? null;
		}
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// WP_REST_Response
// ─────────────────────────────────────────────────────────────────────────────
if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Stub de WP_REST_Response para tests unitarios.
	 * Implementa únicamente los métodos usados por ResponseBuilder.
	 */
	class WP_REST_Response { // phpcs:ignore
		/** @var array<string,string> */
		private array $headers = [];

		public function __construct(
			private mixed $data   = null,
			private int   $status = 200
		) {}

		public function header( string $key, string $value ): void {
			$this->headers[ strtolower( $key ) ] = $value;
		}

		public function get_data(): mixed {
			return $this->data;
		}

		public function get_status(): int {
			return $this->status;
		}

		/** @return array<string,string> */
		public function get_headers(): array {
			return $this->headers;
		}
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// WP_REST_Server  (solo constantes usadas por los controladores)
// ─────────────────────────────────────────────────────────────────────────────
if ( ! class_exists( 'WP_REST_Server' ) ) {
	/**
	 * Stub de WP_REST_Server — expone únicamente las constantes HTTP.
	 */
	class WP_REST_Server { // phpcs:ignore
		public const READABLE  = 'GET';
		public const CREATABLE = 'POST';
		public const EDITABLE  = 'POST, PUT, PATCH';
		public const DELETABLE = 'DELETE';
		public const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// WP_REST_Controller
// ─────────────────────────────────────────────────────────────────────────────
if ( ! class_exists( 'WP_REST_Controller' ) ) {
	/**
	 * Stub de WP_REST_Controller para tests unitarios.
	 * Clase base de todos los controladores del plugin.
	 */
	class WP_REST_Controller { // phpcs:ignore
		/** @var string */
		protected $namespace = ''; // phpcs:ignore

		/** @var string */
		protected $rest_base = ''; // phpcs:ignore

		public function register_routes(): void {}

		public function get_namespace(): string {
			return $this->namespace;
		}

		public function get_rest_base(): string {
			return $this->rest_base;
		}
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// WP_Error
// ─────────────────────────────────────────────────────────────────────────────
if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Stub de WP_Error para tests unitarios.
	 */
	class WP_Error { // phpcs:ignore
		/** @var array<string,string[]> */
		private array $errors = [];

		/** @var array<string,mixed> */
		private array $error_data = []; // phpcs:ignore

		public function __construct(
			string $code    = '',
			string $message = '',
			mixed  $data    = ''
		) {
			if ( $code ) {
				$this->errors[ $code ][] = $message;
				if ( '' !== $data ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		public function get_error_code(): string {
			return (string) array_key_first( $this->errors );
		}

		public function get_error_message( string $code = '' ): string {
			if ( ! $code ) {
				$code = $this->get_error_code();
			}
			return $this->errors[ $code ][0] ?? '';
		}

		public function get_error_data( string $code = '' ): mixed {
			if ( ! $code ) {
				$code = $this->get_error_code();
			}
			return $this->error_data[ $code ] ?? null;
		}

		public function has_errors(): bool {
			return ! empty( $this->errors );
		}

		public function add( string $code, string $message, mixed $data = '' ): void {
			$this->errors[ $code ][] = $message;
			if ( '' !== $data ) {
				$this->error_data[ $code ] = $data;
			}
		}
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// WP_Query  (stub mínimo para tests de integración futuros)
// ─────────────────────────────────────────────────────────────────────────────
if ( ! class_exists( 'WP_Query' ) ) {
	/**
	 * Stub de WP_Query para tests.
	 * Las propiedades se pueden pre-cargar desde el test para simular resultados.
	 */
	class WP_Query { // phpcs:ignore
		/** @var \WP_Post[] */
		public array $posts = [];

		public int $found_posts   = 0; // phpcs:ignore
		public int $max_num_pages = 0; // phpcs:ignore

		public function __construct( array $args = [] ) {} // phpcs:ignore
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// WP_Post  (stub mínimo)
// ─────────────────────────────────────────────────────────────────────────────
if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Stub de WP_Post para tests.
	 */
	class WP_Post { // phpcs:ignore
		public int    $ID                  = 0;    // phpcs:ignore
		public string $post_type           = '';   // phpcs:ignore
		public string $post_name           = '';   // phpcs:ignore
		public string $post_status         = '';   // phpcs:ignore
		public string $post_date_gmt       = '';   // phpcs:ignore
		public string $post_modified_gmt   = '';   // phpcs:ignore
		public string $post_title          = '';   // phpcs:ignore
		public string $post_content        = '';   // phpcs:ignore
		public int    $post_author         = 0;    // phpcs:ignore
	}
}
