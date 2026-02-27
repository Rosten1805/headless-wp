<?php
/**
 * Bootstrap para PHPUnit — configura el entorno de tests sin WordPress real.
 *
 * Los tests unitarios usan Brain\Monkey para mockear funciones globales de WP.
 * Los tests de integración requieren WP_PHPUNIT_DIR configurado (ver README).
 */

declare( strict_types=1 );

// Autoloading de Composer (incluye el código del plugin y los dev deps).
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// ── Constantes que el plugin espera en todo momento ───────────────────────────
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/wordpress/' );
}

if ( ! defined( 'HEADLESS_WP_VERSION' ) ) {
	define( 'HEADLESS_WP_VERSION', '0.1.0' );
}

if ( ! defined( 'HEADLESS_WP_PLUGIN_DIR' ) ) {
	define( 'HEADLESS_WP_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'HEADLESS_WP_PLUGIN_URL' ) ) {
	define( 'HEADLESS_WP_PLUGIN_URL', 'http://localhost/wp-content/plugins/headless-wp/' );
}

if ( ! defined( 'HEADLESS_WP_PLUGIN_BASE' ) ) {
	define( 'HEADLESS_WP_PLUGIN_BASE', 'headless-wp/headless-wp.php' );
}

// ── Stubs ligeros de clases WordPress usadas en tests unitarios ───────────────
// Permiten instanciar WP_REST_Request / WP_REST_Response sin cargar WordPress.
require_once __DIR__ . '/stubs/wp-classes.php';
