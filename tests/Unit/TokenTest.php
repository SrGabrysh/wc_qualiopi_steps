<?php
namespace WcQualiopiSteps\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WcQualiopiSteps\Security\WCQS_Token;

/**
 * Tests unitaires pour WCQS_Token
 */
class TokenTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		
		// Vider le cache avant chaque test
		WCQS_Token::clear_cache();
		
		// Nettoyer les options de test
		delete_option( 'wcqs_hmac_secret' );
		delete_option( 'wcqs_hmac_secret_prev' );
		delete_option( 'wcqs_hmac_key_version' );
	}

	protected function tearDown(): void {
		parent::tearDown();
		
		// Nettoyer après chaque test
		WCQS_Token::clear_cache();
		delete_option( 'wcqs_hmac_secret' );
		delete_option( 'wcqs_hmac_secret_prev' );
		delete_option( 'wcqs_hmac_key_version' );
	}

	/**
	 * Test création et vérification d'un token valide
	 */
	public function test_create_and_verify_valid_token(): void {
		$user_id = 123;
		$product_id = 456;
		$timestamp = time();
		$nonce = 'test_nonce';

		// Créer token
		$token = WCQS_Token::create( $user_id, $product_id, $timestamp, $nonce );
		$this->assertIsString( $token );
		$this->assertStringContainsString( '.', $token );

		// Vérifier token
		$result = WCQS_Token::verify( $token, $user_id, $product_id );
		$this->assertIsArray( $result );
		$this->assertEquals( $user_id, $result['user_id'] );
		$this->assertEquals( $product_id, $result['product_id'] );
		$this->assertEquals( $timestamp, $result['timestamp'] );
		$this->assertEquals( $nonce, $result['nonce'] );
		$this->assertArrayHasKey( 'age', $result );
	}

	/**
	 * Test token avec paramètres par défaut
	 */
	public function test_create_token_with_defaults(): void {
		$user_id = 789;
		$product_id = 101;

		$token = WCQS_Token::create( $user_id, $product_id );
		$this->assertIsString( $token );

		$result = WCQS_Token::verify( $token, $user_id, $product_id );
		$this->assertIsArray( $result );
		$this->assertEquals( $user_id, $result['user_id'] );
		$this->assertEquals( $product_id, $result['product_id'] );
		$this->assertIsInt( $result['timestamp'] );
		$this->assertIsString( $result['nonce'] );
	}

	/**
	 * Test token expiré
	 */
	public function test_expired_token(): void {
		$user_id = 111;
		$product_id = 222;
		$old_timestamp = time() - 8000; // Plus de 2h

		$token = WCQS_Token::create( $user_id, $product_id, $old_timestamp );
		
		// Vérification avec TTL par défaut (2h) - doit échouer
		$result = WCQS_Token::verify( $token, $user_id, $product_id );
		$this->assertFalse( $result );

		// Vérification avec TTL plus long - doit réussir
		$result = WCQS_Token::verify( $token, $user_id, $product_id, 10000 );
		$this->assertIsArray( $result );
	}

	/**
	 * Test token avec mauvais user_id
	 */
	public function test_token_wrong_user_id(): void {
		$user_id = 333;
		$product_id = 444;

		$token = WCQS_Token::create( $user_id, $product_id );
		
		// Vérifier avec mauvais user_id
		$result = WCQS_Token::verify( $token, 999, $product_id );
		$this->assertFalse( $result );
	}

	/**
	 * Test token avec mauvais product_id
	 */
	public function test_token_wrong_product_id(): void {
		$user_id = 555;
		$product_id = 666;

		$token = WCQS_Token::create( $user_id, $product_id );
		
		// Vérifier avec mauvais product_id
		$result = WCQS_Token::verify( $token, $user_id, 999 );
		$this->assertFalse( $result );
	}

	/**
	 * Test token corrompu
	 */
	public function test_corrupted_token(): void {
		$user_id = 777;
		$product_id = 888;

		$token = WCQS_Token::create( $user_id, $product_id );
		
		// Corrompre le token
		$corrupted_token = substr( $token, 0, -5 ) . 'xxxxx';
		
		$result = WCQS_Token::verify( $corrupted_token, $user_id, $product_id );
		$this->assertFalse( $result );
	}

	/**
	 * Test token malformé
	 */
	public function test_malformed_token(): void {
		$user_id = 999;
		$product_id = 111;

		// Token sans point
		$result = WCQS_Token::verify( 'invalidtoken', $user_id, $product_id );
		$this->assertFalse( $result );

		// Token avec trop de parties
		$result = WCQS_Token::verify( 'part1.part2.part3', $user_id, $product_id );
		$this->assertFalse( $result );
	}

	/**
	 * Test rotation de clé
	 */
	public function test_key_rotation(): void {
		$user_id = 123;
		$product_id = 456;

		// Créer token avec clé actuelle
		$token1 = WCQS_Token::create( $user_id, $product_id );
		$version1 = WCQS_Token::get_key_version();

		// Vérifier token
		$result = WCQS_Token::verify( $token1, $user_id, $product_id );
		$this->assertIsArray( $result );

		// Effectuer rotation
		$rotation_result = WCQS_Token::rotate_key();
		$this->assertTrue( $rotation_result );

		$version2 = WCQS_Token::get_key_version();
		$this->assertGreaterThan( $version1, $version2 );

		// L'ancien token doit encore fonctionner (clé précédente)
		$result = WCQS_Token::verify( $token1, $user_id, $product_id );
		$this->assertIsArray( $result );

		// Nouveau token avec nouvelle clé
		$token2 = WCQS_Token::create( $user_id, $product_id );
		$result = WCQS_Token::verify( $token2, $user_id, $product_id );
		$this->assertIsArray( $result );

		// Les deux tokens sont différents
		$this->assertNotEquals( $token1, $token2 );
	}

	/**
	 * Test base64url encoding/decoding
	 */
	public function test_base64url_encoding(): void {
		$user_id = 123;
		$product_id = 456;

		$token = WCQS_Token::create( $user_id, $product_id );
		
		// Le token ne doit pas contenir de caractères non URL-safe
		$this->assertStringNotContainsString( '+', $token );
		$this->assertStringNotContainsString( '/', $token );
		$this->assertStringNotContainsString( '=', $token );
	}

	/**
	 * Test génération de version de clé
	 */
	public function test_key_version_generation(): void {
		// Version initiale
		$version1 = WCQS_Token::get_key_version();
		$this->assertIsInt( $version1 );
		$this->assertGreaterThanOrEqual( 1, $version1 );

		// Après rotation
		WCQS_Token::rotate_key();
		$version2 = WCQS_Token::get_key_version();
		$this->assertEquals( $version1 + 1, $version2 );
	}
}
