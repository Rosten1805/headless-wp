<?php
/**
 * Plugin Name:       Headless WP
 * Plugin URI:        https://github.com/Rosten1805/headless-wp
 * Description:       Expone los datos de WordPress como una API REST personalizada y configurable desde el admin. Soporta JWT, API Keys, permisos granulares y documentación OpenAPI automática.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            Rosten1805
 * Author URI:        https://github.com/Rosten1805
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       headless-wp
 * Domain Path:       /languages
 *
 * @package HeadlessWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Autoload ──────────────────────────────────────────────────────────────────
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// ── Constantes ────────────────────────────────────────────────────────────────
define( 'HEADLESS_WP_VERSION',     '0.1.0' );
define( 'HEADLESS_WP_PLUGIN_FILE', __FILE__ );
define( 'HEADLESS_WP_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'HEADLESS_WP_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'HEADLESS_WP_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// ── Bootstrap ─────────────────────────────────────────────────────────────────
/**
 * Inicializa el plugin tras cargar todos los plugins activos.
 * Prioridad 15 para asegurar que ACF, JetEngine y similares ya están activos.
 */
add_action( 'plugins_loaded', static function (): void {
	load_plugin_textdomain(
		'headless-wp',
		false,
		dirname( HEADLESS_WP_PLUGIN_BASE ) . '/languages'
	);

	\HWP\Core\Plugin::getInstance()->init();
}, 15 );

// ── Lifecycle hooks ───────────────────────────────────────────────────────────
register_activation_hook( __FILE__, static function (): void {
	if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
		deactivate_plugins( HEADLESS_WP_PLUGIN_BASE );
		wp_die(
			esc_html__( 'Headless WP requiere PHP 8.2 o superior.', 'headless-wp' ),
			esc_html__( 'Error de activación', 'headless-wp' ),
			[ 'back_link' => true ]
		);
	}

	// Crear tablas en la activación.
	global $wpdb;
	( new \HWP\Core\Installer( $wpdb ) )->install();

	// En multisite: crear tablas para todos los blogs existentes.
	if ( is_multisite() ) {
		foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $blogId ) {
			switch_to_blog( $blogId );
			( new \HWP\Core\Installer( $wpdb ) )->install();
			restore_current_blog();
		}
	}

	// Marcar flush de rewrite rules para el siguiente request.
	update_option( 'hwp_flush_rewrite_needed', 1 );
} );

register_deactivation_hook( __FILE__, static function (): void {
	wp_clear_scheduled_hook( 'hwp_cron_purge_logs' );
	wp_clear_scheduled_hook( 'hwp_cron_purge_rate_limits' );
	delete_option( 'hwp_flush_rewrite_needed' );
} );

// ── Multisite: nuevos blogs ───────────────────────────────────────────────────
add_action( 'wpmu_new_blog', static function ( int $blogId ): void {
	switch_to_blog( $blogId );
	global $wpdb;
	( new \HWP\Core\Installer( $wpdb ) )->install();
	restore_current_blog();
} );
