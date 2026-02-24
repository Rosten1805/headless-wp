<?php
/**
 * Registro centralizado de hooks de WordPress.
 *
 * @package HWP\Core
 */

namespace HWP\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Loader
 *
 * Acumula todas las llamadas a add_action() y add_filter() durante la
 * fase de inicialización del plugin y las registra de forma diferida
 * al llamar a run(). Esto permite:
 *
 *  1. Testear que los hooks correctos se registran sin ejecutar callbacks.
 *  2. Tener un inventario centralizado de todos los hooks del plugin.
 *  3. Desregistrar hooks de forma controlada (útil en tests).
 *
 * Uso:
 *   $loader->addAction('init', [$myClass, 'myMethod'], 10, 1);
 *   $loader->addFilter('the_content', [$myClass, 'filter'], 20, 1);
 *   $loader->run(); // registra todo en WordPress
 */
final class Loader {

	/** @var array<int, array{hook:string, callback:callable, priority:int, accepted_args:int}> */
	private array $actions = [];

	/** @var array<int, array{hook:string, callback:callable, priority:int, accepted_args:int}> */
	private array $filters = [];

	// ─────────────────────────────────────────────
	// Registro diferido
	// ─────────────────────────────────────────────

	/**
	 * Añade un action a la cola de registro.
	 *
	 * @param string   $hook         Nombre del hook de WordPress.
	 * @param callable $callback     Callable a ejecutar.
	 * @param int      $priority     Prioridad (default 10).
	 * @param int      $acceptedArgs Número de argumentos que acepta el callback.
	 */
	public function addAction(
		string $hook,
		callable $callback,
		int $priority = 10,
		int $acceptedArgs = 1
	): void {
		$this->actions[] = $this->buildEntry( $hook, $callback, $priority, $acceptedArgs );
	}

	/**
	 * Añade un filter a la cola de registro.
	 *
	 * @param string   $hook
	 * @param callable $callback
	 * @param int      $priority
	 * @param int      $acceptedArgs
	 */
	public function addFilter(
		string $hook,
		callable $callback,
		int $priority = 10,
		int $acceptedArgs = 1
	): void {
		$this->filters[] = $this->buildEntry( $hook, $callback, $priority, $acceptedArgs );
	}

	// ─────────────────────────────────────────────
	// Ejecución
	// ─────────────────────────────────────────────

	/**
	 * Registra todos los hooks acumulados en WordPress.
	 * Debe llamarse una única vez al final de la inicialización del plugin.
	 */
	public function run(): void {
		foreach ( $this->filters as $filter ) {
			add_filter(
				$filter['hook'],
				$filter['callback'],
				$filter['priority'],
				$filter['accepted_args']
			);
		}

		foreach ( $this->actions as $action ) {
			add_action(
				$action['hook'],
				$action['callback'],
				$action['priority'],
				$action['accepted_args']
			);
		}
	}

	// ─────────────────────────────────────────────
	// Inspección (útil en tests)
	// ─────────────────────────────────────────────

	/**
	 * Retorna todos los actions registrados (sin ejecutar).
	 *
	 * @return array<int, array{hook:string, callback:callable, priority:int, accepted_args:int}>
	 */
	public function getActions(): array {
		return $this->actions;
	}

	/**
	 * Retorna todos los filters registrados (sin ejecutar).
	 *
	 * @return array<int, array{hook:string, callback:callable, priority:int, accepted_args:int}>
	 */
	public function getFilters(): array {
		return $this->filters;
	}

	/**
	 * Verifica si existe un action registrado para el hook dado.
	 *
	 * @param string $hook
	 * @return bool
	 */
	public function hasAction( string $hook ): bool {
		foreach ( $this->actions as $action ) {
			if ( $action['hook'] === $hook ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Verifica si existe un filter registrado para el hook dado.
	 *
	 * @param string $hook
	 * @return bool
	 */
	public function hasFilter( string $hook ): bool {
		foreach ( $this->filters as $filter ) {
			if ( $filter['hook'] === $hook ) {
				return true;
			}
		}
		return false;
	}

	// ─────────────────────────────────────────────
	// Helpers
	// ─────────────────────────────────────────────

	/** @return array{hook:string, callback:callable, priority:int, accepted_args:int} */
	private function buildEntry(
		string $hook,
		callable $callback,
		int $priority,
		int $acceptedArgs
	): array {
		return [
			'hook'          => $hook,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $acceptedArgs,
		];
	}
}
