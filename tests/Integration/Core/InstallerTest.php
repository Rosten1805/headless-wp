<?php

declare( strict_types=1 );

namespace HWP\Tests\Integration\Core;

use HWP\Core\Installer;
use PHPUnit\Framework\TestCase;

/**
 * Test de integración para Installer.
 *
 * Estos tests verifican que dbDelta() crea las tablas correctamente.
 * Requieren una instancia real de $wpdb (WP test suite o SQLite vía wp-sqlite-db).
 *
 * Para ejecutar solo los tests unitarios (sin DB):
 *   composer test:unit
 *
 * @covers \HWP\Core\Installer
 * @group  integration
 */
final class InstallerTest extends TestCase {

	private Installer $installer;
	private \wpdb $wpdb;

	protected function setUp(): void {
		parent::setUp();

		if ( ! isset( $GLOBALS['wpdb'] ) || ! ( $GLOBALS['wpdb'] instanceof \wpdb ) ) {
			$this->markTestSkipped( 'Test de integración requiere entorno WordPress con wpdb.' );
		}

		$this->wpdb      = $GLOBALS['wpdb'];
		$this->installer = new Installer( $this->wpdb );
	}

	protected function tearDown(): void {
		// Limpiamos las tablas después de cada test para aislamiento.
		$this->installer->uninstall();
		parent::tearDown();
	}

	// ── Creación de tablas ────────────────────────────────────────────────────

	public function test_install_creates_endpoints_table(): void {
		$this->installer->install();
		$table = $this->wpdb->prefix . 'hwp_endpoints';

		$this->assertTrue(
			$this->installer->tableExists( $table ),
			"La tabla {$table} debe existir tras install()."
		);
	}

	public function test_install_creates_endpoint_fields_table(): void {
		$this->installer->install();
		$this->assertTrue( $this->installer->tableExists( $this->wpdb->prefix . 'hwp_endpoint_fields' ) );
	}

	public function test_install_creates_api_keys_table(): void {
		$this->installer->install();
		$this->assertTrue( $this->installer->tableExists( $this->wpdb->prefix . 'hwp_api_keys' ) );
	}

	public function test_install_creates_tokens_table(): void {
		$this->installer->install();
		$this->assertTrue( $this->installer->tableExists( $this->wpdb->prefix . 'hwp_tokens' ) );
	}

	public function test_install_creates_permissions_table(): void {
		$this->installer->install();
		$this->assertTrue( $this->installer->tableExists( $this->wpdb->prefix . 'hwp_permissions' ) );
	}

	public function test_install_creates_logs_table(): void {
		$this->installer->install();
		$this->assertTrue( $this->installer->tableExists( $this->wpdb->prefix . 'hwp_logs' ) );
	}

	public function test_install_creates_rate_limits_table(): void {
		$this->installer->install();
		$this->assertTrue( $this->installer->tableExists( $this->wpdb->prefix . 'hwp_rate_limits' ) );
	}

	// ── Idempotencia ──────────────────────────────────────────────────────────

	public function test_install_is_idempotent(): void {
		$this->installer->install();
		$this->installer->install(); // Segunda ejecución no debe romper nada.

		$this->assertTrue( $this->installer->tableExists( $this->wpdb->prefix . 'hwp_endpoints' ) );
	}

	// ── Versión de DB ─────────────────────────────────────────────────────────

	public function test_install_stores_db_version(): void {
		$this->installer->install();

		$this->assertTrue( $this->installer->isUpToDate() );
	}

	// ── Desinstalación ────────────────────────────────────────────────────────

	public function test_uninstall_removes_all_tables(): void {
		$this->installer->install();
		$this->installer->uninstall();

		$this->assertFalse( $this->installer->tableExists( $this->wpdb->prefix . 'hwp_endpoints' ) );
		$this->assertFalse( $this->installer->tableExists( $this->wpdb->prefix . 'hwp_logs' ) );
	}
}
