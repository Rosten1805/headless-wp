<?php
/**
 * Tests del Fluent Builder de EndpointConfig.
 *
 * @package HWP\Tests\Unit\Endpoints
 */

declare( strict_types=1 );

namespace HWP\Tests\Unit\Endpoints;

use Brain\Monkey;
use HWP\Endpoints\EndpointBuilder;
use HWP\Endpoints\EndpointConfig;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HWP\Endpoints\EndpointBuilder
 */
class EndpointBuilderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ─────────────────────────────────────────────
	// build() produce EndpointConfig
	// ─────────────────────────────────────────────

	public function test_build_returns_endpoint_config_instance(): void {
		$config = ( new EndpointBuilder( 'articles', 'post' ) )->build();

		$this->assertInstanceOf( EndpointConfig::class, $config );
	}

	public function test_build_sets_slug_and_post_type(): void {
		$config = ( new EndpointBuilder( 'products', 'product' ) )->build();

		$this->assertSame( 'products', $config->slug );
		$this->assertSame( 'product', $config->postType );
	}

	// ─────────────────────────────────────────────
	// Inmutabilidad del builder (clones)
	// ─────────────────────────────────────────────

	public function test_each_setter_returns_new_instance(): void {
		$original = new EndpointBuilder( 'articles', 'post' );
		$modified = $original->perPage( 20 );

		$this->assertNotSame( $original, $modified );
	}

	public function test_original_builder_is_unaffected_after_chain(): void {
		$base     = new EndpointBuilder( 'articles', 'post' );
		$modified = $base->perPage( 50 )->orderBy( 'title', 'ASC' );

		$configBase     = $base->build();
		$configModified = $modified->build();

		$this->assertSame( 10, $configBase->postsPerPage );
		$this->assertSame( 50, $configModified->postsPerPage );
	}

	// ─────────────────────────────────────────────
	// Setters individuales
	// ─────────────────────────────────────────────

	public function test_active_false_sets_is_active_false(): void {
		$config = ( new EndpointBuilder( 'articles', 'post' ) )
			->active( false )
			->build();

		$this->assertFalse( $config->isActive );
	}

	public function test_per_page_sets_posts_per_page(): void {
		$config = ( new EndpointBuilder( 'articles', 'post' ) )
			->perPage( 25 )
			->build();

		$this->assertSame( 25, $config->postsPerPage );
	}

	public function test_order_by_sets_orderby_and_order(): void {
		$config = ( new EndpointBuilder( 'articles', 'post' ) )
			->orderBy( 'title', 'ASC' )
			->build();

		$this->assertSame( 'title', $config->orderby );
		$this->assertSame( 'ASC', $config->order );
	}

	public function test_post_status_sets_allowed_statuses(): void {
		$config = ( new EndpointBuilder( 'articles', 'post' ) )
			->postStatus( [ 'publish', 'draft' ] )
			->build();

		$this->assertSame( [ 'publish', 'draft' ], $config->postStatus );
	}

	public function test_auth_override_sets_strategy(): void {
		$config = ( new EndpointBuilder( 'articles', 'post' ) )
			->authOverride( 'jwt' )
			->build();

		$this->assertSame( 'jwt', $config->authOverride );
	}

	public function test_no_cache_sets_ttl_to_zero(): void {
		$config = ( new EndpointBuilder( 'articles', 'post' ) )
			->noCache()
			->build();

		$this->assertSame( 0, $config->cacheTtl );
		$this->assertFalse( $config->isCacheEnabled() );
	}

	public function test_cache_ttl_sets_seconds(): void {
		$config = ( new EndpointBuilder( 'articles', 'post' ) )
			->cacheTtl( 1800 )
			->build();

		$this->assertSame( 1800, $config->cacheTtl );
	}

	public function test_without_meta_disables_meta_block(): void {
		$config = ( new EndpointBuilder( 'articles', 'post' ) )
			->withoutMeta()
			->build();

		$this->assertFalse( $config->includeMeta );
	}

	public function test_without_links_disables_links_block(): void {
		$config = ( new EndpointBuilder( 'articles', 'post' ) )
			->withoutLinks()
			->build();

		$this->assertFalse( $config->includeLinks );
	}

	public function test_data_key_sets_custom_key(): void {
		$config = ( new EndpointBuilder( 'articles', 'post' ) )
			->dataKey( 'articles' )
			->build();

		$this->assertSame( 'articles', $config->dataKey );
	}

	public function test_path_override_strips_leading_slash(): void {
		$config = ( new EndpointBuilder( 'articles', 'post' ) )
			->pathOverride( '/api/noticias' )
			->build();

		$this->assertSame( 'api/noticias', $config->pathOverride );
		$this->assertTrue( $config->hasPathOverride() );
	}

	public function test_rate_limit_sets_all_three_params(): void {
		$config = ( new EndpointBuilder( 'articles', 'post' ) )
			->rateLimit( 30, 120, 'sliding_window' )
			->build();

		$this->assertSame( 30, $config->rateLimitMax );
		$this->assertSame( 120, $config->rateLimitWindow );
		$this->assertSame( 'sliding_window', $config->rateLimitStrategy );
	}

	// ─────────────────────────────────────────────
	// Encadenado completo
	// ─────────────────────────────────────────────

	public function test_full_chain_produces_correct_config(): void {
		$config = ( new EndpointBuilder( 'products', 'product' ) )
			->active()
			->perPage( 20 )
			->orderBy( 'title', 'ASC' )
			->authOverride( 'api_key' )
			->cacheTtl( 600 )
			->rateLimit( 60, 60 )
			->withoutMeta()
			->dataKey( 'products' )
			->pathOverride( '/shop/items' )
			->build();

		$this->assertSame( 'products', $config->slug );
		$this->assertSame( 'product', $config->postType );
		$this->assertSame( 20, $config->postsPerPage );
		$this->assertSame( 'title', $config->orderby );
		$this->assertSame( 'api_key', $config->authOverride );
		$this->assertSame( 600, $config->cacheTtl );
		$this->assertFalse( $config->includeMeta );
		$this->assertSame( 'products', $config->dataKey );
		$this->assertSame( 'shop/items', $config->pathOverride );
	}
}
