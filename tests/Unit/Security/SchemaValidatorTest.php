<?php
/**
 * Tests del validador de JSON Schema.
 *
 * @package HWP\Tests\Unit\Security
 */

declare( strict_types=1 );

namespace HWP\Tests\Unit\Security;

use Brain\Monkey;
use HWP\Security\SchemaValidator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HWP\Security\SchemaValidator
 */
class SchemaValidatorTest extends TestCase {

	private SchemaValidator $validator;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->validator = new SchemaValidator();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ─────────────────────────────────────────────
	// Body válido pasa sin errores
	// ─────────────────────────────────────────────

	public function test_valid_body_passes_with_no_errors(): void {
		$schema = [
			'type'       => 'object',
			'required'   => [ 'title', 'status' ],
			'properties' => [
				'title'  => [ 'type' => 'string' ],
				'status' => [ 'type' => 'string', 'enum' => [ 'publish', 'draft' ] ],
			],
		];

		$errors = $this->validator->validate( [ 'title' => 'Hello', 'status' => 'publish' ], $schema );

		$this->assertEmpty( $errors );
	}

	public function test_is_valid_returns_true_for_valid_body(): void {
		$schema = [
			'type'       => 'object',
			'properties' => [
				'name' => [ 'type' => 'string' ],
			],
		];

		$this->assertTrue( $this->validator->isValid( [ 'name' => 'Alice' ], $schema ) );
	}

	// ─────────────────────────────────────────────
	// Campo extra rechazado (additionalProperties: false)
	// ─────────────────────────────────────────────

	public function test_extra_field_rejected_when_additional_properties_false(): void {
		$schema = [
			'type'                 => 'object',
			'properties'           => [
				'title' => [ 'type' => 'string' ],
			],
			'additionalProperties' => false,
		];

		$errors = $this->validator->validate( [ 'title' => 'Hello', 'unknown_field' => 'extra' ], $schema );

		$this->assertNotEmpty( $errors );
		$fieldNames = array_column( $errors, 'field' );
		$this->assertContains( 'unknown_field', $fieldNames );
	}

	public function test_extra_field_allowed_when_additional_properties_not_set(): void {
		$schema = [
			'type'       => 'object',
			'properties' => [
				'title' => [ 'type' => 'string' ],
			],
		];

		$errors = $this->validator->validate( [ 'title' => 'Hello', 'extra' => 'ok' ], $schema );

		$this->assertEmpty( $errors );
	}

	// ─────────────────────────────────────────────
	// Tipo incorrecto rechazado
	// ─────────────────────────────────────────────

	public function test_wrong_type_rejected(): void {
		$schema = [
			'type'       => 'object',
			'properties' => [
				'count' => [ 'type' => 'integer' ],
			],
		];

		$errors = $this->validator->validate( [ 'count' => 'not_an_integer' ], $schema );

		$this->assertNotEmpty( $errors );
		$this->assertSame( 'count', $errors[0]['field'] );
	}

	public function test_correct_type_passes(): void {
		$schema = [
			'type'       => 'object',
			'properties' => [
				'count' => [ 'type' => 'integer' ],
			],
		];

		$this->assertEmpty( $this->validator->validate( [ 'count' => 42 ], $schema ) );
	}

	// ─────────────────────────────────────────────
	// Campo requerido faltante
	// ─────────────────────────────────────────────

	public function test_missing_required_field_produces_error(): void {
		$schema = [
			'type'     => 'object',
			'required' => [ 'title' ],
			'properties' => [
				'title' => [ 'type' => 'string' ],
			],
		];

		$errors = $this->validator->validate( [], $schema );

		$this->assertNotEmpty( $errors );
		$this->assertSame( 'title', $errors[0]['field'] );
	}

	// ─────────────────────────────────────────────
	// enum
	// ─────────────────────────────────────────────

	public function test_value_not_in_enum_rejected(): void {
		$schema = [
			'type'       => 'object',
			'properties' => [
				'status' => [ 'type' => 'string', 'enum' => [ 'publish', 'draft' ] ],
			],
		];

		$errors = $this->validator->validate( [ 'status' => 'invalid_value' ], $schema );

		$this->assertNotEmpty( $errors );
		$this->assertSame( 'status', $errors[0]['field'] );
	}

	public function test_value_in_enum_passes(): void {
		$schema = [
			'type'       => 'object',
			'properties' => [
				'status' => [ 'type' => 'string', 'enum' => [ 'publish', 'draft' ] ],
			],
		];

		$this->assertEmpty( $this->validator->validate( [ 'status' => 'draft' ], $schema ) );
	}

	// ─────────────────────────────────────────────
	// minimum / maximum
	// ─────────────────────────────────────────────

	public function test_value_below_minimum_rejected(): void {
		$schema = [
			'type'       => 'object',
			'properties' => [
				'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ],
			],
		];

		$errors = $this->validator->validate( [ 'per_page' => 0 ], $schema );

		$this->assertNotEmpty( $errors );
	}

	public function test_value_above_maximum_rejected(): void {
		$schema = [
			'type'       => 'object',
			'properties' => [
				'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ],
			],
		];

		$errors = $this->validator->validate( [ 'per_page' => 200 ], $schema );

		$this->assertNotEmpty( $errors );
	}

	// ─────────────────────────────────────────────
	// Propiedades anidadas
	// ─────────────────────────────────────────────

	public function test_nested_property_error_uses_dot_notation_path(): void {
		$schema = [
			'type'       => 'object',
			'properties' => [
				'meta' => [
					'type'       => 'object',
					'properties' => [
						'page' => [ 'type' => 'integer' ],
					],
				],
			],
		];

		$errors = $this->validator->validate( [ 'meta' => [ 'page' => 'not_int' ] ], $schema );

		$this->assertNotEmpty( $errors );
		$this->assertSame( 'meta.page', $errors[0]['field'] );
	}

	// ─────────────────────────────────────────────
	// Códigos de error
	// ─────────────────────────────────────────────

	public function test_error_code_is_hwp_validation_error(): void {
		$schema = [
			'type'     => 'object',
			'required' => [ 'name' ],
			'properties' => [
				'name' => [ 'type' => 'string' ],
			],
		];

		$errors = $this->validator->validate( [], $schema );

		$this->assertSame( 'hwp_validation_error', $errors[0]['code'] );
	}

	// ─────────────────────────────────────────────
	// nullable
	// ─────────────────────────────────────────────

	public function test_null_value_passes_when_nullable(): void {
		$schema = [
			'type'       => 'object',
			'properties' => [
				'author' => [ 'type' => 'string', 'nullable' => true ],
			],
		];

		$this->assertEmpty( $this->validator->validate( [ 'author' => null ], $schema ) );
	}
}
