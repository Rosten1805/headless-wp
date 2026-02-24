<?php
/**
 * Orquestador principal del plugin (Singleton).
 *
 * @package HWP\Core
 */

namespace HWP\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * Punto de entrada a toda la lógica del plugin. Responsable de:
 *  1. Construir el ServiceContainer con todos los bindings.
 *  2. Registrar los hooks en el Loader.
 *  3. Invocar Loader::run() para que WordPress tome el control.
 *
 * El constructor es privado: uso exclusivo vía Plugin::getInstance().
 * Esto garantiza una única instancia por request (Singleton).
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private ServiceContainer $container;
	private Loader $loader;

	// ─────────────────────────────────────────────
	// Singleton
	// ─────────────────────────────────────────────

	/**
	 * Retorna la instancia única del plugin.
	 */
	public static function getInstance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Solo para tests: resetea la instancia singleton.
	 * No llamar en producción.
	 *
	 * @internal
	 */
	public static function resetInstance(): void {
		self::$instance = null;
	}

	private function __construct() {
		$this->container = new ServiceContainer();
		$this->loader    = new Loader();
	}

	// ─────────────────────────────────────────────
	// Inicialización
	// ─────────────────────────────────────────────

	/**
	 * Arranca el plugin. Llamado desde el hook 'plugins_loaded'.
	 */
	public function init(): void {
		$this->registerBindings();
		$this->registerHooks();
		$this->loader->run();

		do_action( 'hwp_init', $this );
	}

	// ─────────────────────────────────────────────
	// Bindings del container
	// ─────────────────────────────────────────────

	private function registerBindings(): void {
		global $wpdb;

		// Core
		$this->container->instance( \wpdb::class, $wpdb );

		$this->container->bind( Installer::class, static fn( $c ) =>
			new Installer( $c->make( \wpdb::class ) )
		);

		$this->container->bind( Upgrader::class, static fn( $c ) =>
			new Upgrader( $c->make( Installer::class ) )
		);

		/**
		 * Hook para que módulos externos (o sprints futuros) añadan bindings.
		 *
		 * @param ServiceContainer $container
		 */
		do_action( 'hwp_container_bindings', $this->container );
	}

	// ─────────────────────────────────────────────
	// Hooks
	// ─────────────────────────────────────────────

	private function registerHooks(): void {
		// Upgrade check en cada carga del plugin.
		$this->loader->addAction(
			'plugins_loaded',
			fn() => $this->container->make( Upgrader::class )->maybeUpgrade(),
			20
		);

		/**
		 * Hook para que módulos externos registren sus propios hooks
		 * usando el Loader antes de que se ejecute run().
		 *
		 * @param Loader           $loader
		 * @param ServiceContainer $container
		 */
		do_action( 'hwp_register_hooks', $this->loader, $this->container );
	}

	// ─────────────────────────────────────────────
	// Accesores
	// ─────────────────────────────────────────────

	public function container(): ServiceContainer {
		return $this->container;
	}

	public function loader(): Loader {
		return $this->loader;
	}

	/**
	 * Shortcut para resolver servicios desde el container.
	 *
	 * @param string $abstract
	 * @return mixed
	 */
	public function make( string $abstract ): mixed {
		return $this->container->make( $abstract );
	}
}
