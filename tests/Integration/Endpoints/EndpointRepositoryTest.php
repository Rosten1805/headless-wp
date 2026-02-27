<?php
/**
 * Tests de integración del repositorio de endpoints contra DB real.
 *
 * @package HWP\Tests\Integration\Endpoints
 */

declare( strict_types=1 );

namespace HWP\Tests\Integration\Endpoints;

use HWP\Endpoints\EndpointConfig;
use HWP\Endpoints\EndpointRepository;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HWP\Endpoints\EndpointRepository
 */
class EndpointRepositoryTest extends TestCase {

	private EndpointRepository $repo;

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'DB_HOST' ) ) {
			$this->markTestSkipped(
				'Integration test: requires a running WordPress environment (DB_HOST not defined).'
			);
		}

		$this->repo = new EndpointRepository();
	}

	// ─────────────────────────────────────────────
	// findActive
	// ─────────────────────────────────────────────

	public function test_find_active_returns_array(): void {
		$result = $this->repo->findActive();

		$this->assertIsArray( $result );
	}

	public function test_find_active_returns_only_active_endpoints(): void {
		$active   = EndpointConfig::fromArray( 'active-ep', 'post', [ 'is_active' => true ] );
		$inactive = EndpointConfig::fromArray( 'inactive-ep', 'post', [ 'is_active' => false ] );

		$this->repo->save( $active );
		$this->repo->save( $inactive );

		$results = $this->repo->findActive();
		$slugs   = array_map( fn ( $c ) => $c->slug, $results );

		$this->assertContains( 'active-ep', $slugs );
		$this->assertNotContains( 'inactive-ep', $slugs );

		$this->repo->delete( 'active-ep' );
		$this->repo->delete( 'inactive-ep' );
	}

	// ─────────────────────────────────────────────
	// save + findBySlug (round-trip)
	// ─────────────────────────────────────────────

	public function test_save_and_find_by_slug_round_trip(): void {
		$config = EndpointConfig::fromArray( 'test-ep', 'post', [
			'cache_ttl'      => 600,
			'auth_override'  => 'jwt',
			'query_defaults' => [ 'posts_per_page' => 20 ],
		] );

		$this->repo->save( $config );

		$found = $this->repo->findBySlug( 'test-ep' );

		$this->assertNotNull( $found );
		$this->assertSame( 'test-ep', $found->slug );
		$this->assertSame( 600, $found->cacheTtl );
		$this->assertSame( 'jwt', $found->authOverride );
		$this->assertSame( 20, $found->postsPerPage );

		$this->repo->delete( 'test-ep' );
	}

	// ─────────────────────────────────────────────
	// update (save existente)
	// ─────────────────────────────────────────────

	public function test_save_updates_existing_endpoint(): void {
		$original = EndpointConfig::fromArray( 'update-ep', 'post', [ 'cache_ttl' => 300 ] );
		$this->repo->save( $original );

		$updated = EndpointConfig::fromArray( 'update-ep', 'post', [ 'cache_ttl' => 900 ] );
		$this->repo->save( $updated );

		$found = $this->repo->findBySlug( 'update-ep' );
		$this->assertSame( 900, $found->cacheTtl );

		$this->repo->delete( 'update-ep' );
	}

	// ─────────────────────────────────────────────
	// delete
	// ─────────────────────────────────────────────

	public function test_delete_removes_endpoint(): void {
		$config = EndpointConfig::defaults( 'delete-ep', 'post' );
		$this->repo->save( $config );

		$this->repo->delete( 'delete-ep' );

		$found = $this->repo->findBySlug( 'delete-ep' );
		$this->assertNull( $found );
	}

	// ─────────────────────────────────────────────
	// setActive
	// ─────────────────────────────────────────────

	public function test_set_active_false_deactivates_endpoint(): void {
		$config = EndpointConfig::fromArray( 'toggle-ep', 'post', [ 'is_active' => true ] );
		$this->repo->save( $config );

		$this->repo->setActive( 'toggle-ep', false );

		$found = $this->repo->findBySlug( 'toggle-ep' );
		$this->assertFalse( $found->isActive );

		$this->repo->delete( 'toggle-ep' );
	}

	// ─────────────────────────────────────────────
	// findBySlug miss
	// ─────────────────────────────────────────────

	public function test_find_by_slug_returns_null_for_nonexistent(): void {
		$found = $this->repo->findBySlug( 'does-not-exist-' . uniqid() );

		$this->assertNull( $found );
	}
}
