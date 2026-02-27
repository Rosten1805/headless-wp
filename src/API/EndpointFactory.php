<?php
/**
 * Fábrica que crea el controlador adecuado según la configuración del endpoint.
 *
 * @package HWP\API
 */

namespace HWP\API;

use HWP\Endpoints\EndpointConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EndpointFactory
 *
 * Decide qué implementación de AbstractController instanciar:
 *
 *  - EndpointConfig::$imageUploadEnabled = true  → MediaController
 *  - En cualquier otro caso                       → DynamicController
 *
 * Al centralizar esta decisión en un solo lugar, el Router y el Registry
 * no necesitan conocer las clases concretas de los controladores.
 */
final class EndpointFactory {

	/**
	 * Crea el controlador correcto para la configuración dada.
	 *
	 * @param EndpointConfig $config Configuración del endpoint.
	 * @return AbstractController   Instancia lista para llamar a register_routes().
	 */
	public function make( EndpointConfig $config ): AbstractController {
		if ( $config->imageUploadEnabled ) {
			return new MediaController( $config );
		}

		return new DynamicController( $config );
	}
}
