<?php
/**
 * Instalación y migración de tablas custom de la base de datos.
 *
 * @package HWP\Core
 */

namespace HWP\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Installer
 *
 * Gestiona la creación y actualización de las 7 tablas custom del plugin
 * usando dbDelta(), la API oficial de WordPress para DDL.
 *
 * Convenciones:
 *  - Charset/collation: utf8mb4 / utf8mb4_unicode_520_ci (soporte emoji completo)
 *  - Motor: InnoDB (transacciones, FK integrity)
 *  - Todas las tablas usan prefijo $wpdb->prefix
 *  - dbDelta() solo añade columnas e índices; nunca elimina (seguro para upgrades)
 */
final class Installer {

	private const DB_VERSION_OPTION = 'hwp_db_version';
	private const CURRENT_DB_VERSION = '1.0';

	private \wpdb $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	// ─────────────────────────────────────────────
	// Punto de entrada
	// ─────────────────────────────────────────────

	/**
	 * Ejecuta la instalación/actualización de tablas.
	 * Llamado en register_activation_hook() y en Upgrader cuando detecta versión desactualizada.
	 */
	public function install(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $this->wpdb->get_charset_collate();

		foreach ( $this->getTableSchemas( $charset ) as $sql ) {
			dbDelta( $sql );
		}

		update_option( self::DB_VERSION_OPTION, self::CURRENT_DB_VERSION );
	}

	/**
	 * Verifica si la instalación actual está al día.
	 */
	public function isUpToDate(): bool {
		return get_option( self::DB_VERSION_OPTION ) === self::CURRENT_DB_VERSION;
	}

	/**
	 * Elimina todas las tablas del plugin. Solo llamado desde uninstall.php.
	 */
	public function uninstall(): void {
		foreach ( array_reverse( $this->getTableNames() ) as $table ) {
			$this->wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		delete_option( self::DB_VERSION_OPTION );
	}

	// ─────────────────────────────────────────────
	// Schemas SQL
	// ─────────────────────────────────────────────

	/** @return string[] Lista de sentencias CREATE TABLE para dbDelta(). */
	private function getTableSchemas( string $charset ): array {
		$p = $this->wpdb->prefix;

		return [
			// ── 1. Endpoints ─────────────────────────────────────────────────
			"CREATE TABLE {$p}hwp_endpoints (
				id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
				slug          VARCHAR(255)     NOT NULL,
				label         VARCHAR(255)     NOT NULL,
				post_type     VARCHAR(100)     NOT NULL,
				methods       VARCHAR(100)     NOT NULL DEFAULT 'GET',
				path_override VARCHAR(255)     DEFAULT NULL,
				status        VARCHAR(20)      NOT NULL DEFAULT 'draft',
				config        LONGTEXT         NOT NULL,
				cache_ttl     INT UNSIGNED     NOT NULL DEFAULT 300,
				version       TINYINT UNSIGNED NOT NULL DEFAULT 1,
				created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY   uq_slug (slug),
				KEY          ix_post_type (post_type),
				KEY          ix_status (status)
			) $charset;",

			// ── 2. Endpoint fields ────────────────────────────────────────────
			"CREATE TABLE {$p}hwp_endpoint_fields (
				id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
				endpoint_id   BIGINT UNSIGNED  NOT NULL,
				field_key     VARCHAR(255)     NOT NULL,
				field_source  VARCHAR(20)      NOT NULL,
				response_key  VARCHAR(255)     NOT NULL,
				field_type    VARCHAR(100)     DEFAULT NULL,
				is_exposed    TINYINT(1)       NOT NULL DEFAULT 1,
				is_writable   TINYINT(1)       NOT NULL DEFAULT 0,
				is_required   TINYINT(1)       NOT NULL DEFAULT 0,
				is_filterable TINYINT(1)       NOT NULL DEFAULT 0,
				sort_order    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				KEY          ix_endpoint (endpoint_id)
			) $charset;",

			// ── 3. API Keys ───────────────────────────────────────────────────
			"CREATE TABLE {$p}hwp_api_keys (
				id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
				user_id      BIGINT UNSIGNED  DEFAULT NULL,
				label        VARCHAR(255)     NOT NULL,
				key_prefix   VARCHAR(12)      NOT NULL,
				key_hash     VARCHAR(255)     NOT NULL,
				scopes       LONGTEXT         DEFAULT NULL,
				last_used_at DATETIME         DEFAULT NULL,
				expires_at   DATETIME         DEFAULT NULL,
				revoked      TINYINT(1)       NOT NULL DEFAULT 0,
				created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY  uq_key_prefix (key_prefix),
				KEY         ix_user (user_id),
				KEY         ix_revoked (revoked)
			) $charset;",

			// ── 4. JWT Refresh Tokens ─────────────────────────────────────────
			"CREATE TABLE {$p}hwp_tokens (
				id          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
				user_id     BIGINT UNSIGNED  NOT NULL,
				token_hash  VARCHAR(255)     NOT NULL,
				family      CHAR(36)         NOT NULL,
				used        TINYINT(1)       NOT NULL DEFAULT 0,
				expires_at  DATETIME         NOT NULL,
				created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY         ix_user (user_id),
				KEY         ix_family (family),
				KEY         ix_expires (expires_at)
			) $charset;",

			// ── 5. Permissions ────────────────────────────────────────────────
			"CREATE TABLE {$p}hwp_permissions (
				id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
				endpoint_id     BIGINT UNSIGNED  NOT NULL,
				role            VARCHAR(100)     NOT NULL,
				allowed_methods VARCHAR(100)     NOT NULL DEFAULT 'GET',
				denied_fields   LONGTEXT         DEFAULT NULL,
				PRIMARY KEY    (id),
				UNIQUE KEY     uq_ep_role (endpoint_id, role),
				KEY            ix_endpoint (endpoint_id)
			) $charset;",

			// ── 6. Request Logs ───────────────────────────────────────────────
			"CREATE TABLE {$p}hwp_logs (
				id               BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
				endpoint_slug    VARCHAR(255)      DEFAULT NULL,
				method           VARCHAR(10)       NOT NULL,
				user_id          BIGINT UNSIGNED   DEFAULT NULL,
				auth_type        VARCHAR(50)       DEFAULT NULL,
				status_code      SMALLINT UNSIGNED NOT NULL,
				ip_address       VARCHAR(45)       NOT NULL,
				user_agent       VARCHAR(500)      DEFAULT NULL,
				query_params     LONGTEXT          DEFAULT NULL,
				response_time_ms INT UNSIGNED      DEFAULT NULL,
				error_code       VARCHAR(100)      DEFAULT NULL,
				created_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY     (id),
				KEY             ix_endpoint (endpoint_slug),
				KEY             ix_status (status_code),
				KEY             ix_created (created_at),
				KEY             ix_user (user_id)
			) $charset;",

			// ── 7. Rate Limits ────────────────────────────────────────────────
			"CREATE TABLE {$p}hwp_rate_limits (
				id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
				identifier      VARCHAR(255)     NOT NULL,
				identifier_type VARCHAR(20)      NOT NULL,
				endpoint_slug   VARCHAR(255)     DEFAULT NULL,
				window_start    DATETIME         NOT NULL,
				request_count   INT UNSIGNED     NOT NULL DEFAULT 1,
				PRIMARY KEY    (id),
				UNIQUE KEY     uq_identifier_window (identifier, endpoint_slug, window_start),
				KEY            ix_window (window_start)
			) $charset;",
		];
	}

	/** @return string[] Nombres de tablas en orden de FK-safe delete (child primero). */
	private function getTableNames(): array {
		$p = $this->wpdb->prefix;
		return [
			"{$p}hwp_rate_limits",
			"{$p}hwp_logs",
			"{$p}hwp_permissions",
			"{$p}hwp_tokens",
			"{$p}hwp_api_keys",
			"{$p}hwp_endpoint_fields",
			"{$p}hwp_endpoints",
		];
	}

	/**
	 * Verifica si una tabla específica existe en la base de datos.
	 * Utilidad para tests de integración.
	 *
	 * @param string $tableName Nombre completo de la tabla (con prefijo).
	 */
	public function tableExists( string $tableName ): bool {
		$result = $this->wpdb->get_var(
			$this->wpdb->prepare( 'SHOW TABLES LIKE %s', $tableName )
		);
		return $result === $tableName;
	}
}
