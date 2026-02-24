<?php
/**
 * Fachada pública del sistema de logging del plugin.
 *
 * @package HWP\Logging
 */

namespace HWP\Logging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Logger
 *
 * Centraliza el registro de eventos del plugin. En Sprint 1 escribe
 * en la tabla hwp_logs de forma diferida (shutdown hook) para no
 * impactar el tiempo de respuesta del endpoint.
 *
 * Métodos principales:
 *   Logger::request()  → log de request completado
 *   Logger::error()    → log de error de aplicación
 *   Logger::auth()     → log de intento de autenticación
 *
 * Los logs solo se persisten si 'logging_enabled' está activo en hwp_settings.
 * El buffer se vuelca al final del request (shutdown) para máximo rendimiento.
 */
final class Logger {

	/** @var array<int, array<string,mixed>> Buffer en memoria del request actual. */
	private array $buffer = [];

	private bool $enabled;

	public function __construct() {
		$settings      = get_option( 'hwp_settings', [] );
		$this->enabled = (bool) ( $settings['logging_enabled'] ?? true );

		if ( $this->enabled ) {
			add_action( 'shutdown', [ $this, 'flush' ], 999 );
		}
	}

	// ─────────────────────────────────────────────
	// API pública
	// ─────────────────────────────────────────────

	/**
	 * Registra los datos de un request completado.
	 *
	 * @param array<string,mixed> $data
	 *   - endpoint_slug    string
	 *   - method           string  (GET, POST, etc.)
	 *   - user_id          int|null
	 *   - auth_type        string|null
	 *   - status_code      int
	 *   - ip_address       string
	 *   - user_agent       string|null
	 *   - query_params     array|null
	 *   - response_time_ms int|null
	 */
	public function request( array $data ): void {
		if ( ! $this->enabled ) {
			return;
		}

		$this->buffer[] = array_merge(
			$this->defaults(),
			$data,
			[ 'log_type' => 'request' ]
		);
	}

	/**
	 * Registra un error de aplicación.
	 *
	 * @param string $code    Código del error (hwp_*).
	 * @param string $message Mensaje descriptivo.
	 * @param array  $context Contexto adicional.
	 */
	public function error( string $code, string $message, array $context = [] ): void {
		if ( ! $this->enabled ) {
			return;
		}

		$this->buffer[] = array_merge(
			$this->defaults(),
			[
				'log_type'   => 'error',
				'error_code' => $code,
				'error_msg'  => $message,
				'context'    => $context,
			]
		);
	}

	/**
	 * Registra un intento de autenticación.
	 *
	 * @param string      $strategy  Nombre de la estrategia usada.
	 * @param string      $outcome   'success' | 'failed'.
	 * @param string|null $errorCode Código de error si outcome = 'failed'.
	 */
	public function auth( string $strategy, string $outcome, ?string $errorCode = null ): void {
		if ( ! $this->enabled ) {
			return;
		}

		$this->buffer[] = array_merge(
			$this->defaults(),
			[
				'log_type'   => 'auth',
				'auth_type'  => $strategy,
				'outcome'    => $outcome,
				'error_code' => $errorCode,
			]
		);
	}

	// ─────────────────────────────────────────────
	// Flush (shutdown hook)
	// ─────────────────────────────────────────────

	/**
	 * Vuelca el buffer a la base de datos.
	 * Llamado automáticamente en 'shutdown' si logging está habilitado.
	 *
	 * @internal
	 */
	public function flush(): void {
		if ( empty( $this->buffer ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'hwp_logs';

		foreach ( $this->buffer as $entry ) {
			$wpdb->insert(
				$table,
				[
					'endpoint_slug'    => $entry['endpoint_slug']    ?? null,
					'method'           => $entry['method']           ?? 'UNKNOWN',
					'user_id'          => $entry['user_id']          ?? null,
					'auth_type'        => $entry['auth_type']        ?? null,
					'status_code'      => $entry['status_code']      ?? 0,
					'ip_address'       => $entry['ip_address']       ?? '',
					'user_agent'       => $entry['user_agent']       ?? null,
					'query_params'     => isset( $entry['query_params'] )
						? wp_json_encode( $entry['query_params'] )
						: null,
					'response_time_ms' => $entry['response_time_ms'] ?? null,
					'error_code'       => $entry['error_code']       ?? null,
					'created_at'       => current_time( 'mysql', true ),
				],
				[ '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s' ]
			);
		}

		$this->buffer = [];
	}

	// ─────────────────────────────────────────────
	// Helpers
	// ─────────────────────────────────────────────

	/** @return array<string,mixed> Valores por defecto de una entrada de log. */
	private function defaults(): array {
		return [
			'ip_address' => $this->resolveClientIp(),
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] )
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
				: null,
			'user_id'    => get_current_user_id() ?: null,
			'method'     => isset( $_SERVER['REQUEST_METHOD'] )
				? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
				: 'UNKNOWN',
		];
	}

	/**
	 * Resuelve la IP real del cliente respetando proxies confiables.
	 */
	private function resolveClientIp(): string {
		$settings       = get_option( 'hwp_settings', [] );
		$trustedProxies = $settings['trusted_proxies'] ?? [];

		$remoteAddr = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

		if ( ! empty( $trustedProxies ) && in_array( $remoteAddr, $trustedProxies, true ) ) {
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '' ) );
			if ( $forwarded ) {
				return trim( explode( ',', $forwarded )[0] );
			}
		}

		return $remoteAddr;
	}

	/**
	 * Retorna el buffer actual (sin vaciarlo). Útil para tests.
	 *
	 * @return array<int, array<string,mixed>>
	 * @internal
	 */
	public function getBuffer(): array {
		return $this->buffer;
	}
}
