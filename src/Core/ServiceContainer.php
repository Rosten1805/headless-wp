<?php
/**
 * Contenedor de dependencias (DI) mínimo compatible con PSR-11.
 *
 * @package HWP\Core
 */

namespace HWP\Core;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ServiceContainer
 *
 * Implementación deliberadamente mínima: bindings explícitos sin reflection.
 * Esto garantiza rendimiento predecible en cada request de WordPress y
 * hace los tests triviales (sin magia de fondo).
 *
 * Uso:
 *   $container->bind(MyService::class, fn($c) => new MyService($c->make(Dep::class)));
 *   $service = $container->make(MyService::class);   // singleton por defecto
 *
 * Extensión:
 *   Plugins externos pueden añadir bindings via el hook 'hwp_container_bindings'.
 */
final class ServiceContainer {

	/** @var array<string, array{factory: callable, singleton: bool}> */
	private array $bindings = [];

	/** @var array<string, mixed> Instancias singleton resueltas. */
	private array $instances = [];

	// ─────────────────────────────────────────────
	// Registro
	// ─────────────────────────────────────────────

	/**
	 * Registra un binding en el contenedor.
	 *
	 * @param string   $abstract  Identificador (FQCN o alias de string).
	 * @param callable $factory   Callable que recibe el container y retorna la instancia.
	 * @param bool     $singleton Si true (default), la instancia se reutiliza.
	 */
	public function bind( string $abstract, callable $factory, bool $singleton = true ): void {
		$this->bindings[ $abstract ] = [
			'factory'   => $factory,
			'singleton' => $singleton,
		];

		// Si ya existía una instancia, la invalidamos al re-registrar.
		unset( $this->instances[ $abstract ] );
	}

	/**
	 * Registra una instancia ya construida como singleton.
	 *
	 * @param string $abstract
	 * @param mixed  $instance
	 */
	public function instance( string $abstract, mixed $instance ): void {
		$this->instances[ $abstract ] = $instance;
	}

	// ─────────────────────────────────────────────
	// Resolución (PSR-11)
	// ─────────────────────────────────────────────

	/**
	 * Resuelve y retorna una instancia del identificador dado.
	 *
	 * @param string $id FQCN o alias registrado.
	 * @return mixed
	 * @throws RuntimeException Si no existe binding para $id.
	 */
	public function get( string $id ): mixed {
		return $this->make( $id );
	}

	/**
	 * Verifica si existe un binding o instancia para el identificador.
	 *
	 * @param string $id
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->bindings[ $id ] ) || isset( $this->instances[ $id ] );
	}

	/**
	 * Alias semántico de get() con nombre más explícito para uso interno.
	 *
	 * @param string $abstract
	 * @return mixed
	 * @throws RuntimeException
	 */
	public function make( string $abstract ): mixed {
		if ( isset( $this->instances[ $abstract ] ) ) {
			return $this->instances[ $abstract ];
		}

		if ( ! isset( $this->bindings[ $abstract ] ) ) {
			throw new RuntimeException(
				sprintf( "No binding found in ServiceContainer for '%s'.", htmlspecialchars( $abstract, ENT_QUOTES ) )
			);
		}

		$binding  = $this->bindings[ $abstract ];
		$instance = ( $binding['factory'] )( $this );

		if ( $binding['singleton'] ) {
			$this->instances[ $abstract ] = $instance;
		}

		return $instance;
	}

	/**
	 * Fuerza la resolución de una nueva instancia ignorando el singleton cacheado.
	 *
	 * @param string $abstract
	 * @return mixed
	 */
	public function fresh( string $abstract ): mixed {
		unset( $this->instances[ $abstract ] );
		return $this->make( $abstract );
	}
}
