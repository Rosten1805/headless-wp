<?php
/**
 * Tests del envelope de error producido por ResponseBuilder.
 *
 * @package HWP\Tests\Unit\API
 */

declare( strict_types=1 );

namespace HWP\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use HWP\API\ResponseBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HWP\API\ResponseBuilder::error
 * @covers \HWP\API\ResponseBuilder::validationError
 */
class ResponseBuilderErrorTest extends TestCase {

	private ResponseBuilder $builder;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// __ devuelve el primer argumento (el texto sin traducir).
		Functions\when( '__' )->returnArg( 1 );
		$this->builder = new ResponseBuilder();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ─────────────────────────────────────────────
	// Estructura base del envelope de error
	// ─────────────────────────────────────────────

	public function test_error_envelope_has_required_keys(): void {
		$response = $this->builder->error( 'hwp_not_found', 'Resource not found', 404 );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'success', $data );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'error', $data );
		$this->assertArrayHasKey( 'timestamp', $data );
		$this->assertArrayHasKey( 'version', $data );
	}

	public function test_error_success_is_false(): void {
		$response = $this->builder->error( 'hwp_not_found', 'Resource not found', 404 );

		$this->assertFalse( $response->get_data()['success'] );
	}

	public function test_error_data_is_null(): void {
		$response = $this->builder->error( 'hwp_not_found', 'Resource not found', 404 );

		$this->assertNull( $response->get_data()['data'] );
	}

	// ─────────────────────────────────────────────
	// Bloque error.*
	// ─────────────────────────────────────────────

	public function test_error_block_contains_code_message_status(): void {
		$response = $this->builder->error( 'hwp_forbidden', 'Access denied', 403 );
		$error    = $response->get_data()['error'];

		$this->assertSame( 'hwp_forbidden', $error['code'] );
		$this->assertSame( 'Access denied', $error['message'] );
		$this->assertSame( 403, $error['status'] );
	}

	public function test_error_has_no_details_when_empty(): void {
		$response = $this->builder->error( 'hwp_unauthorized', 'Unauthorized', 401 );

		$this->assertArrayNotHasKey( 'details', $response->get_data()['error'] );
	}

	public function test_error_includes_details_when_provided(): void {
		$details  = [
			[ 'field' => 'email', 'message' => 'Invalid format', 'code' => 'hwp_validation_error' ],
		];
		$response = $this->builder->error( 'hwp_validation_error', 'Validation failed', 400, $details );

		$this->assertArrayHasKey( 'details', $response->get_data()['error'] );
		$this->assertCount( 1, $response->get_data()['error']['details'] );
	}

	// ─────────────────────────────────────────────
	// Todos los códigos de error estandarizados
	// ─────────────────────────────────────────────

	/**
	 * @dataProvider errorCodeProvider
	 */
	public function test_error_code_sets_correct_http_status( string $code, int $expectedStatus ): void {
		$response = $this->builder->error( $code, 'Test error', $expectedStatus );

		$this->assertSame( $expectedStatus, $response->get_status() );
		$this->assertSame( $code, $response->get_data()['error']['code'] );
	}

	/** @return array<string, array{string, int}> */
	public static function errorCodeProvider(): array {
		return [
			'unauthorized'      => [ 'hwp_unauthorized',       401 ],
			'token_expired'     => [ 'hwp_token_expired',       401 ],
			'token_revoked'     => [ 'hwp_token_revoked',       401 ],
			'invalid_token'     => [ 'hwp_invalid_token',       401 ],
			'forbidden'         => [ 'hwp_forbidden',           403 ],
			'not_found'         => [ 'hwp_not_found',           404 ],
			'method_not_allowed'=> [ 'hwp_method_not_allowed',  405 ],
			'validation_error'  => [ 'hwp_validation_error',    400 ],
			'rate_limit'        => [ 'hwp_rate_limit_exceeded', 429 ],
			'endpoint_disabled' => [ 'hwp_endpoint_disabled',   503 ],
			'server_error'      => [ 'hwp_server_error',        500 ],
		];
	}

	// ─────────────────────────────────────────────
	// validationError shortcut
	// ─────────────────────────────────────────────

	public function test_validation_error_returns_400(): void {
		$response = $this->builder->validationError( [] );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_validation_error_code_is_hwp_validation_error(): void {
		$response = $this->builder->validationError( [] );

		$this->assertSame( 'hwp_validation_error', $response->get_data()['error']['code'] );
	}

	public function test_validation_error_includes_field_errors(): void {
		$fieldErrors = [
			[ 'field' => 'title',  'message' => 'Required',       'code' => 'hwp_validation_error' ],
			[ 'field' => 'status', 'message' => 'Invalid status', 'code' => 'hwp_validation_error' ],
		];
		$response    = $this->builder->validationError( $fieldErrors );

		$this->assertCount( 2, $response->get_data()['error']['details'] );
	}
}
