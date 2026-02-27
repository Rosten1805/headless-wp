<?php
/**
 * Registro en memoria de endpoints activos durante el ciclo de vida del request.
 *
 * @package HWP\Endpoints
 */

namespace HWP\Endpoints;

use HWP\Endpoints\Contracts\EndpointRepositoryInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EndpointRegistry
 *
 * Carga los endpoints activos desde la base de datos (via EndpointRepository)
 * una sola vez por request y los mantiene en cache de instancia.
 *
 * Esta estrategia evita múltiples queries a DB cuando el Router,
 * la PermissionMatrix y el RewriteManager necesitan la misma lista.
 *
 * Ciclo de vida:
 *   1. Router llama getActive() → primera llamada → query DB → cache.
 *   2. Cualquier otro componente que llame getActive() usa el cache.
 *   3. Al guardar/borrar un endpoint → clearCache() invalida para el próximo request.
 *   4. Si se llama register() con un nuevo EndpointConfig activo mientras el cache
 *      está cargado, se añade al cache sin necesidad de re-query.
 */
final class EndpointRegistry {

	/** @var EndpointConfig[]|null null = no cargado aún. */
	private ?array $activeCache = null;

	public function __construct( private readonly EndpointRepositoryInterface $repository ) {}

	// ─────────────────────────────────────────────
	// Consultas
	// ─────────────────────────────────────────────

	/**
	 * Retorna los endpoints activos.
	 * La primera llamada consulta DB; las siguientes usan cache de instancia.
	 *
	 * @return EndpointConfig[]
	 */
	public function getActive(): array {
		if ( null !== $this->activeCache ) {
			return $this->activeCache;
		}

		$this->activeCache = $this->repository->findActive();

		return $this->activeCache;
	}

	/**
	 * Busca un endpoint por slug (primero en cache, luego en DB).
	 */
	public function findBySlug( string $slug ): ?EndpointConfig {
		// Buscar en cache si ya está cargado.
		if ( null !== $this->activeCache ) {
			foreach ( $this->activeCache as $config ) {
				if ( $config->slug === $slug ) {
					return $config;
				}
			}
		}

		return $this->repository->findBySlug( $slug );
	}

	// ─────────────────────────────────────────────
	// Mutaciones
	// ─────────────────────────────────────────────

	/**
	 * Añade un EndpointConfig al cache si ya está cargado y el endpoint está activo.
	 * No persiste en DB — usa EndpointRepository::save() para eso.
	 */
	public function register( EndpointConfig $config ): void {
		if ( null !== $this->activeCache && $config->isActive ) {
			// Reemplaza si ya existía con el mismo slug; añade si es nuevo.
			$slugs = array_column(
				array_map(
					static fn ( EndpointConfig $c ) => [ 'slug' => $c->slug ],
					$this->activeCache
				),
				null,
				'slug'
			);

			if ( isset( $slugs[ $config->slug ] ) ) {
				$this->activeCache = array_map(
					static fn ( EndpointConfig $c ) => $c->slug === $config->slug ? $config : $c,
					$this->activeCache
				);
			} else {
				$this->activeCache[] = $config;
			}
		}
	}

	/**
	 * Invalida el cache. El próximo getActive() volverá a consultar la DB.
	 * Debe llamarse al guardar o eliminar un endpoint.
	 */
	public function clearCache(): void {
		$this->activeCache = null;
	}
}
