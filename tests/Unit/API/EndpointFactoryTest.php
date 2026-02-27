<?php
/**
 * Tests de la fábrica de controladores.
 *
 * @package HWP\Tests\Unit\API
 */

declare( strict_types=1 );

namespace HWP\Tests\Unit\API;

use Brain\Monkey;
use HWP\API\DynamicController;
use HWP\API\EndpointFactory;
use HWP\API\MediaController;
use HWP\Endpoints\EndpointConfig;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HWP\API\EndpointFactory
 */
class EndpointFactoryTest extends TestCase {

	private EndpointFactory $factory;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->factory = new EndpointFactory();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ─────────────────────────────────────────────
	// Tipo de controlador creado
	// ─────────────────────────────────────────────

	public function test_make_returns_dynamic_controller_for_normal_config(): void {
		$config     = EndpointConfig::defaults( 'articles', 'post' );
		$controller = $this->factory->make( $config );

		$this->assertInstanceOf( DynamicController::class, $controller );
	}

	public function test_make_returns_media_controller_when_image_upload_enabled(): void {
		$config = EndpointConfig::fromArray( 'gallery', 'attachment', [
			'image_upload' => [ 'enabled' => true ],
		] );

		$controller = $this->factory->make( $config );

		$this->assertInstanceOf( MediaController::class, $controller );
	}

	public function test_make_returns_dynamic_controller_when_upload_explicitly_disabled(): void {
		$config = EndpointConfig::fromArray( 'articles', 'post', [
			'image_upload' => [ 'enabled' => false ],
		] );

		$controller = $this->factory->make( $config );

		$this->assertInstanceOf( DynamicController::class, $controller );
	}

	// ─────────────────────────────────────────────
	// Instancias independientes
	// ─────────────────────────────────────────────

	public function test_make_returns_distinct_instances_for_same_config(): void {
		$config = EndpointConfig::defaults( 'articles', 'post' );

		$c1 = $this->factory->make( $config );
		$c2 = $this->factory->make( $config );

		$this->assertNotSame( $c1, $c2 );
	}

	public function test_dynamic_controller_has_correct_rest_base(): void {
		$config     = EndpointConfig::defaults( 'products', 'product' );
		$controller = $this->factory->make( $config );

		$this->assertSame( 'products', $controller->get_rest_base() );
	}

	public function test_media_controller_has_correct_rest_base(): void {
		$config = EndpointConfig::fromArray( 'media', 'attachment', [
			'image_upload' => [ 'enabled' => true ],
		] );
		$controller = $this->factory->make( $config );

		$this->assertSame( 'media', $controller->get_rest_base() );
	}
}
