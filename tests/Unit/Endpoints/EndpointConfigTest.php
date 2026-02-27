<?php
/**
 * Tests del Value Object EndpointConfig.
 *
 * @package HWP\Tests\Unit\Endpoints
 */

declare( strict_types=1 );

namespace HWP\Tests\Unit\Endpoints;

use Brain\Monkey;
use HWP\Endpoints\EndpointConfig;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HWP\Endpoints\EndpointConfig
 */
class EndpointConfigTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ─────────────────────────────────────────────
	// Inmutabilidad
	// ─────────────────────────────────────────────

	public function test_properties_are_readonly(): void {
		$config = EndpointConfig::defaults( 'articles', 'post' );

		$reflection = new \ReflectionClass( $config );
		foreach ( $reflection->getProperties() as $prop ) {
			$this->assertTrue( $prop->isReadOnly(), "Property {$prop->getName()} must be readonly" );
		}
	}

	public function test_modifying_readonly_property_throws_error(): void {
		$config = EndpointConfig::defaults( 'articles', 'post' );

		$this->expectException( \Error::class );
		$config->slug = 'other'; // @phpstan-ignore-line
	}

	// ─────────────────────────────────────────────
	// defaults()
	// ─────────────────────────────────────────────

	public function test_defaults_sets_correct_slug_and_post_type(): void {
		$config = EndpointConfig::defaults( 'articles', 'post' );

		$this->assertSame( 'articles', $config->slug );
		$this->assertSame( 'post', $config->postType );
	}

	public function test_defaults_is_active(): void {
		$config = EndpointConfig::defaults( 'articles', 'post' );

		$this->assertTrue( $config->isActive );
	}

	public function test_defaults_has_correct_query_values(): void {
		$config = EndpointConfig::defaults( 'articles', 'post' );

		$this->assertSame( 10, $config->postsPerPage );
		$this->assertSame( 'date', $config->orderby );
		$this->assertSame( 'DESC', $config->order );
		$this->assertSame( [ 'publish' ], $config->postStatus );
	}

	public function test_defaults_cache_ttl_is_300(): void {
		$config = EndpointConfig::defaults( 'articles', 'post' );

		$this->assertSame( 300, $config->cacheTtl );
		$this->assertTrue( $config->isCacheEnabled() );
	}

	public function test_defaults_auth_override_is_null(): void {
		$config = EndpointConfig::defaults( 'articles', 'post' );

		$this->assertNull( $config->authOverride );
	}

	public function test_defaults_path_override_is_null(): void {
		$config = EndpointConfig::defaults( 'articles', 'post' );

		$this->assertNull( $config->pathOverride );
		$this->assertFalse( $config->hasPathOverride() );
	}

	// ─────────────────────────────────────────────
	// fromArray()
	// ─────────────────────────────────────────────

	public function test_from_array_overrides_query_defaults(): void {
		$config = EndpointConfig::fromArray( 'news', 'post', [
			'query_defaults' => [
				'posts_per_page' => 20,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => [ 'publish', 'draft' ],
			],
		] );

		$this->assertSame( 20, $config->postsPerPage );
		$this->assertSame( 'title', $config->orderby );
		$this->assertSame( 'ASC', $config->order );
		$this->assertSame( [ 'publish', 'draft' ], $config->postStatus );
	}

	public function test_from_array_sets_auth_override(): void {
		$config = EndpointConfig::fromArray( 'secure', 'post', [
			'auth_override' => 'jwt',
		] );

		$this->assertSame( 'jwt', $config->authOverride );
	}

	public function test_from_array_with_none_auth_override(): void {
		$config = EndpointConfig::fromArray( 'public', 'post', [
			'auth_override' => 'none',
		] );

		$this->assertSame( 'none', $config->authOverride );
	}

	public function test_from_array_sets_path_override(): void {
		$config = EndpointConfig::fromArray( 'articles', 'post', [
			'path_override' => 'api/noticias',
		] );

		$this->assertSame( 'api/noticias', $config->pathOverride );
		$this->assertTrue( $config->hasPathOverride() );
	}

	public function test_from_array_sets_rate_limit(): void {
		$config = EndpointConfig::fromArray( 'articles', 'post', [
			'rate_limit' => [
				'max_requests'   => 30,
				'window_seconds' => 120,
				'strategy'       => 'sliding_window',
			],
		] );

		$this->assertSame( 30, $config->rateLimitMax );
		$this->assertSame( 120, $config->rateLimitWindow );
		$this->assertSame( 'sliding_window', $config->rateLimitStrategy );
	}

	public function test_from_array_sets_response_envelope(): void {
		$config = EndpointConfig::fromArray( 'articles', 'post', [
			'response_envelope' => [
				'include_links' => false,
				'include_meta'  => false,
				'data_key'      => 'articles',
			],
		] );

		$this->assertFalse( $config->includeLinks );
		$this->assertFalse( $config->includeMeta );
		$this->assertSame( 'articles', $config->dataKey );
	}

	// ─────────────────────────────────────────────
	// isCacheEnabled()
	// ─────────────────────────────────────────────

	public function test_is_cache_disabled_when_ttl_is_zero(): void {
		$config = EndpointConfig::fromArray( 'articles', 'post', [ 'cache_ttl' => 0 ] );

		$this->assertFalse( $config->isCacheEnabled() );
	}

	// ─────────────────────────────────────────────
	// toArray() round-trip
	// ─────────────────────────────────────────────

	public function test_to_array_round_trip_preserves_values(): void {
		$original = EndpointConfig::fromArray( 'articles', 'post', [
			'query_defaults' => [ 'posts_per_page' => 25 ],
			'cache_ttl'      => 600,
			'auth_override'  => 'api_key',
		] );

		$exported  = $original->toArray();
		$roundTrip = EndpointConfig::fromArray( 'articles', 'post', $exported );

		$this->assertSame( $original->postsPerPage, $roundTrip->postsPerPage );
		$this->assertSame( $original->cacheTtl, $roundTrip->cacheTtl );
		$this->assertSame( $original->authOverride, $roundTrip->authOverride );
	}
}
