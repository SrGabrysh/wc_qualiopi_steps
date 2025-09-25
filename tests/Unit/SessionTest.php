<?php
namespace WcQualiopiSteps\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WcQualiopiSteps\Utils\WCQS_Session;

/**
 * Tests unitaires pour WCQS_Session
 * 
 * Note: Ces tests nécessitent un environnement WordPress avec WooCommerce
 */
class SessionTest extends TestCase {

	private $mock_wc_session;

	protected function setUp(): void {
		parent::setUp();
		
		// Mock WooCommerce session si pas disponible
		if ( ! function_exists( 'WC' ) ) {
			$this->markTestSkipped( 'WooCommerce not available for session tests' );
		}
	}

	/**
	 * Test set et is_solved basique
	 */
	public function test_set_and_is_solved(): void {
		$product_id = 123;

		// Initialement non résolu
		$this->assertFalse( WCQS_Session::is_solved( $product_id ) );

		// Marquer comme résolu
		$result = WCQS_Session::set_solved( $product_id );
		$this->assertTrue( $result );

		// Vérifier qu'il est maintenant résolu
		$this->assertTrue( WCQS_Session::is_solved( $product_id ) );
	}

	/**
	 * Test TTL personnalisé
	 */
	public function test_custom_ttl(): void {
		$product_id = 456;
		$short_ttl = 1; // 1 seconde

		// Marquer comme résolu avec TTL court
		WCQS_Session::set_solved( $product_id, $short_ttl );
		$this->assertTrue( WCQS_Session::is_solved( $product_id ) );

		// Attendre expiration
		sleep( 2 );

		// Doit être expiré maintenant
		$this->assertFalse( WCQS_Session::is_solved( $product_id ) );
	}

	/**
	 * Test unset_solved
	 */
	public function test_unset_solved(): void {
		$product_id = 789;

		// Marquer comme résolu
		WCQS_Session::set_solved( $product_id );
		$this->assertTrue( WCQS_Session::is_solved( $product_id ) );

		// Désactiver
		$result = WCQS_Session::unset_solved( $product_id );
		$this->assertTrue( $result );

		// Vérifier qu'il n'est plus résolu
		$this->assertFalse( WCQS_Session::is_solved( $product_id ) );
	}

	/**
	 * Test get_session_details
	 */
	public function test_get_session_details(): void {
		$product_id = 101;

		// Pas de détails initialement
		$details = WCQS_Session::get_session_details( $product_id );
		$this->assertNull( $details );

		// Marquer comme résolu
		WCQS_Session::set_solved( $product_id );

		// Obtenir détails
		$details = WCQS_Session::get_session_details( $product_id );
		$this->assertIsArray( $details );
		$this->assertTrue( $details['solved'] );
		$this->assertIsInt( $details['timestamp'] );
		$this->assertIsInt( $details['expires'] );
		$this->assertFalse( $details['is_expired'] );
		$this->assertArrayHasKey( 'age', $details );
		$this->assertArrayHasKey( 'remaining_ttl', $details );
	}

	/**
	 * Test cleanup_expired
	 */
	public function test_cleanup_expired(): void {
		// Créer plusieurs sessions avec TTL différents
		WCQS_Session::set_solved( 201, 3600 ); // Valide
		WCQS_Session::set_solved( 202, 1 );    // Expire rapidement
		WCQS_Session::set_solved( 203, 3600 ); // Valide

		// Attendre expiration
		sleep( 2 );

		// Nettoyer
		$cleaned = WCQS_Session::cleanup_expired();
		$this->assertGreaterThanOrEqual( 1, $cleaned );

		// Vérifier état
		$this->assertTrue( WCQS_Session::is_solved( 201 ) );
		$this->assertFalse( WCQS_Session::is_solved( 202 ) );
		$this->assertTrue( WCQS_Session::is_solved( 203 ) );
	}

	/**
	 * Test get_all_active_sessions
	 */
	public function test_get_all_active_sessions(): void {
		// Nettoyer d'abord
		WCQS_Session::cleanup_expired();

		// Créer sessions actives
		WCQS_Session::set_solved( 301 );
		WCQS_Session::set_solved( 302 );
		WCQS_Session::set_solved( 303, 1 ); // Expire rapidement

		$active_sessions = WCQS_Session::get_all_active_sessions();
		$this->assertIsArray( $active_sessions );
		$this->assertArrayHasKey( 301, $active_sessions );
		$this->assertArrayHasKey( 302, $active_sessions );
		$this->assertArrayHasKey( 303, $active_sessions );

		// Attendre expiration d'une session
		sleep( 2 );

		$active_sessions = WCQS_Session::get_all_active_sessions();
		$this->assertArrayHasKey( 301, $active_sessions );
		$this->assertArrayHasKey( 302, $active_sessions );
		$this->assertArrayNotHasKey( 303, $active_sessions );
	}

	/**
	 * Test extend_session
	 */
	public function test_extend_session(): void {
		$product_id = 401;

		// Marquer avec TTL court
		WCQS_Session::set_solved( $product_id, 2 );
		
		$details1 = WCQS_Session::get_session_details( $product_id );
		$initial_expires = $details1['expires'];

		// Prolonger
		$result = WCQS_Session::extend_session( $product_id, 3600 );
		$this->assertTrue( $result );

		$details2 = WCQS_Session::get_session_details( $product_id );
		$new_expires = $details2['expires'];

		$this->assertGreaterThan( $initial_expires, $new_expires );
	}

	/**
	 * Test extend_session sur session inexistante
	 */
	public function test_extend_nonexistent_session(): void {
		$product_id = 999;

		$result = WCQS_Session::extend_session( $product_id );
		$this->assertFalse( $result );
	}

	/**
	 * Test get_session_stats
	 */
	public function test_get_session_stats(): void {
		// Nettoyer d'abord
		WCQS_Session::cleanup_expired();

		// Créer sessions de test
		WCQS_Session::set_solved( 501, 3600 ); // Active
		WCQS_Session::set_solved( 502, 3600 ); // Active
		WCQS_Session::set_solved( 503, 1 );    // Expire rapidement

		sleep( 2 ); // Laisser 503 expirer

		$stats = WCQS_Session::get_session_stats();
		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'total', $stats );
		$this->assertArrayHasKey( 'active', $stats );
		$this->assertArrayHasKey( 'expired', $stats );

		$this->assertGreaterThanOrEqual( 3, $stats['total'] );
		$this->assertGreaterThanOrEqual( 2, $stats['active'] );
		$this->assertGreaterThanOrEqual( 1, $stats['expired'] );
	}

	/**
	 * Test produits multiples
	 */
	public function test_multiple_products(): void {
		$products = array( 601, 602, 603 );

		// Marquer tous comme résolus
		foreach ( $products as $product_id ) {
			WCQS_Session::set_solved( $product_id );
		}

		// Vérifier tous
		foreach ( $products as $product_id ) {
			$this->assertTrue( WCQS_Session::is_solved( $product_id ) );
		}

		// Désactiver un seul
		WCQS_Session::unset_solved( 602 );

		// Vérifier états
		$this->assertTrue( WCQS_Session::is_solved( 601 ) );
		$this->assertFalse( WCQS_Session::is_solved( 602 ) );
		$this->assertTrue( WCQS_Session::is_solved( 603 ) );
	}

	protected function tearDown(): void {
		parent::tearDown();
		
		// Nettoyer les sessions de test si possible
		if ( function_exists( 'WC' ) && WC()->session ) {
			$test_products = array( 123, 456, 789, 101, 201, 202, 203, 301, 302, 303, 401, 501, 502, 503, 601, 602, 603, 999 );
			
			foreach ( $test_products as $product_id ) {
				WCQS_Session::unset_solved( $product_id );
			}
		}
	}
}
