<?php
/**
 * Gestor de migraciones entre versiones del plugin.
 *
 * @package HWP\Core
 */

namespace HWP\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Upgrader
 *
 * Detecta si la versión almacenada en wp_options difiere de la versión
 * actual del plugin y ejecuta los pasos de migración necesarios.
 *
 * Diseño de migraciones:
 *  - Cada versión tiene su propio método privado migrate_X_Y_Z().
 *  - Las migraciones son idempotentes: ejecutarlas dos veces no rompe nada.
 *  - Se ejecutan en orden secuencial (de la versión instalada a la actual).
 *  - Si la DB no está al día, se vuelve a ejecutar Installer::install() antes.
 */
final class Upgrader {

	private const VERSION_OPTION = 'hwp_plugin_version';

	private Installer $installer;

	public function __construct( Installer $installer ) {
		$this->installer = $installer;
	}

	// ─────────────────────────────────────────────
	// Punto de entrada
	// ─────────────────────────────────────────────

	/**
	 * Verifica la versión instalada y ejecuta migraciones si es necesario.
	 * Llamado en 'plugins_loaded' con prioridad baja.
	 */
	public function maybeUpgrade(): void {
		$installedVersion = get_option( self::VERSION_OPTION, '0.0.0' );

		if ( version_compare( $installedVersion, HEADLESS_WP_VERSION, '>=' ) ) {
			return; // Ya está al día.
		}

		// Asegurar que las tablas existen y están actualizadas.
		$this->installer->install();

		// Ejecutar migraciones pendientes en orden.
		$this->runMigrations( $installedVersion );

		// Actualizar versión guardada.
		update_option( self::VERSION_OPTION, HEADLESS_WP_VERSION );

		do_action( 'hwp_plugin_upgraded', $installedVersion, HEADLESS_WP_VERSION );
	}

	/**
	 * Retorna la versión del plugin actualmente instalada (la guardada en DB).
	 */
	public function getInstalledVersion(): string {
		return (string) get_option( self::VERSION_OPTION, '0.0.0' );
	}

	// ─────────────────────────────────────────────
	// Migraciones
	// ─────────────────────────────────────────────

	/**
	 * Ejecuta las migraciones pendientes entre $fromVersion y HEADLESS_WP_VERSION.
	 *
	 * @param string $fromVersion Versión desde la que se actualiza.
	 */
	private function runMigrations( string $fromVersion ): void {
		$migrations = $this->getMigrationMap();

		foreach ( $migrations as $version => $callable ) {
			if ( version_compare( $fromVersion, $version, '<' ) ) {
				$callable();
			}
		}
	}

	/**
	 * Mapa ordenado de versión → callable de migración.
	 * Añadir aquí las migraciones futuras manteniendo el orden SemVer.
	 *
	 * @return array<string, callable>
	 */
	private function getMigrationMap(): array {
		return [
			'0.1.0' => [ $this, 'migrate010' ],
			// '0.2.0' => [ $this, 'migrate020' ],  // ejemplo sprint siguiente
		];
	}

	/**
	 * Migración v0.1.0 — instalación inicial.
	 * Registra las opciones por defecto del plugin.
	 */
	private function migrate010(): void {
		$defaults = [
			'allowed_origins'    => '',
			'jwt_ttl'            => 900,
			'refresh_token_ttl'  => 2592000,
			'logging_enabled'    => true,
			'log_retention_days' => 30,
			'basic_auth_enabled' => false,
			'rate_limit_global'  => [
				'max_requests'   => 60,
				'window_seconds' => 60,
			],
		];

		// add_option no sobreescribe si ya existe (idempotente).
		add_option( 'hwp_settings', $defaults );
	}
}
