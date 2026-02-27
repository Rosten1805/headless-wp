<?php
/**
 * CRUD de endpoints contra las tablas hwp_endpoints y hwp_endpoint_fields.
 *
 * @package HWP\Endpoints
 */

namespace HWP\Endpoints;

use HWP\Endpoints\Contracts\EndpointRepositoryInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EndpointRepository
 *
 * Abstrae el acceso a la base de datos para endpoints dinámicos.
 * Utiliza $wpdb directamente (sin ORM) para mantener la dependencia
 * al mínimo y preservar compatibilidad con todas las versiones de WP 6.x.
 *
 * Esquema relevante (creado por Installer):
 *
 *   hwp_endpoints (
 *     id          BIGINT UNSIGNED AUTO_INCREMENT,
 *     slug        VARCHAR(100) NOT NULL UNIQUE,
 *     post_type   VARCHAR(50)  NOT NULL,
 *     is_active   TINYINT(1)   NOT NULL DEFAULT 1,
 *     config      LONGTEXT,            -- JSON del EndpointConfig
 *     created_at  DATETIME,
 *     updated_at  DATETIME
 *   )
 *
 *   hwp_endpoint_fields (
 *     id          BIGINT UNSIGNED AUTO_INCREMENT,
 *     endpoint_id BIGINT UNSIGNED NOT NULL,
 *     field_key   VARCHAR(100),
 *     field_label VARCHAR(255),
 *     field_type  VARCHAR(50),
 *     is_active   TINYINT(1) DEFAULT 1
 *   )
 */
final class EndpointRepository implements EndpointRepositoryInterface {

	private const TABLE      = 'hwp_endpoints';
	private const TABLE_FIELDS = 'hwp_endpoint_fields';

	// ─────────────────────────────────────────────
	// Lectura
	// ─────────────────────────────────────────────

	/**
	 * Retorna todos los endpoints activos ordenados por slug.
	 *
	 * @return EndpointConfig[]
	 */
	public function findActive(): array {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM `{$table}` WHERE is_active = %d ORDER BY slug ASC",
				1
			)
		);

		if ( ! $rows ) {
			return [];
		}

		return array_map( [ $this, 'rowToConfig' ], $rows );
	}

	/**
	 * Retorna todos los endpoints (activos e inactivos) ordenados por slug.
	 *
	 * @return EndpointConfig[]
	 */
	public function findAll(): array {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY slug ASC" );

		if ( ! $rows ) {
			return [];
		}

		return array_map( [ $this, 'rowToConfig' ], $rows );
	}

	/**
	 * Busca un endpoint por slug exacto.
	 */
	public function findBySlug( string $slug ): ?EndpointConfig {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM `{$table}` WHERE slug = %s LIMIT 1",
				$slug
			)
		);

		if ( ! $row ) {
			return null;
		}

		return $this->rowToConfig( $row );
	}

	// ─────────────────────────────────────────────
	// Escritura
	// ─────────────────────────────────────────────

	/**
	 * Inserta o actualiza un endpoint (upsert por slug).
	 *
	 * @return bool true si la operación fue exitosa.
	 */
	public function save( EndpointConfig $config ): bool {
		global $wpdb;

		$table    = $wpdb->prefix . self::TABLE;
		$existing = $this->findBySlug( $config->slug );

		$data   = [
			'slug'       => $config->slug,
			'post_type'  => $config->postType,
			'is_active'  => (int) $config->isActive,
			'config'     => wp_json_encode( $config->toArray() ),
			'updated_at' => current_time( 'mysql', true ),
		];
		$format = [ '%s', '%s', '%d', '%s', '%s' ];

		if ( $existing ) {
			$result = $wpdb->update( $table, $data, [ 'slug' => $config->slug ], $format, [ '%s' ] );
		} else {
			$data['created_at'] = current_time( 'mysql', true );
			$format[]           = '%s';
			$result             = $wpdb->insert( $table, $data, $format );
		}

		return false !== $result;
	}

	/**
	 * Elimina un endpoint por slug.
	 * Los campos asociados se eliminan por CASCADE (FK en hwp_endpoint_fields).
	 *
	 * @return bool true si se eliminó al menos una fila.
	 */
	public function delete( string $slug ): bool {
		global $wpdb;

		$table  = $wpdb->prefix . self::TABLE;
		$result = $wpdb->delete( $table, [ 'slug' => $slug ], [ '%s' ] );

		return (bool) $result;
	}

	/**
	 * Activa o desactiva un endpoint sin alterar su configuración.
	 */
	public function setActive( string $slug, bool $active ): bool {
		global $wpdb;

		$table  = $wpdb->prefix . self::TABLE;
		$result = $wpdb->update(
			$table,
			[
				'is_active'  => (int) $active,
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'slug' => $slug ],
			[ '%d', '%s' ],
			[ '%s' ]
		);

		do_action( 'hwp_endpoint_status_changed', $slug, $active );

		return false !== $result;
	}

	// ─────────────────────────────────────────────
	// Helper
	// ─────────────────────────────────────────────

	/**
	 * Convierte una fila de $wpdb->get_row() en un EndpointConfig.
	 *
	 * @param object $row Fila de la DB con propiedades públicas.
	 */
	private function rowToConfig( object $row ): EndpointConfig {
		$config             = json_decode( $row->config ?? '{}', true ) ?? [];
		$config['is_active'] = (bool) $row->is_active;

		return EndpointConfig::fromArray( $row->slug, $row->post_type, $config );
	}
}
