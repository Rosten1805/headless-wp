<?php
/**
 * Contrato del repositorio de endpoints.
 *
 * @package HWP\Endpoints\Contracts
 */

namespace HWP\Endpoints\Contracts;

use HWP\Endpoints\EndpointConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface EndpointRepositoryInterface
 *
 * Define el contrato de persistencia para endpoints dinámicos.
 * Permite intercambiar la implementación real (DB) con un stub en tests.
 */
interface EndpointRepositoryInterface {

	/**
	 * Retorna todos los endpoints activos ordenados por slug.
	 *
	 * @return EndpointConfig[]
	 */
	public function findActive(): array;

	/**
	 * Retorna todos los endpoints (activos e inactivos) ordenados por slug.
	 *
	 * @return EndpointConfig[]
	 */
	public function findAll(): array;

	/**
	 * Busca un endpoint por slug exacto. Retorna null si no existe.
	 */
	public function findBySlug( string $slug ): ?EndpointConfig;

	/**
	 * Inserta o actualiza un endpoint (upsert por slug).
	 *
	 * @return bool true si la operación fue exitosa.
	 */
	public function save( EndpointConfig $config ): bool;

	/**
	 * Elimina un endpoint por slug.
	 *
	 * @return bool true si se eliminó al menos una fila.
	 */
	public function delete( string $slug ): bool;

	/**
	 * Activa o desactiva un endpoint sin alterar su configuración.
	 */
	public function setActive( string $slug, bool $active ): bool;
}
