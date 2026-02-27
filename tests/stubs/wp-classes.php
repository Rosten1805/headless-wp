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
