<?php
/**
 * Bootstrap pour les tests PHPUnit de WC Qualiopi Steps
 */

// Charger Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Simuler les fonctions WordPress essentielles pour les tests unitaires
if ( ! function_exists( 'get_option' ) ) {
    $test_options = array();
    
    function get_option( $option, $default = false ) {
        global $test_options;
        return isset( $test_options[ $option ] ) ? $test_options[ $option ] : $default;
    }
    
    function update_option( $option, $value, $autoload = null ) {
        global $test_options;
        $test_options[ $option ] = $value;
        return true;
    }
    
    function delete_option( $option ) {
        global $test_options;
        unset( $test_options[ $option ] );
        return true;
    }
    
    function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ( $special_chars ) {
            $chars .= '!@#$%^&*()';
        }
        if ( $extra_special_chars ) {
            $chars .= '-_ []{}<>~`+=,.;:/?|';
        }
        
        $password = '';
        for ( $i = 0; $i < $length; $i++ ) {
            $password .= substr( $chars, wp_rand( 0, strlen( $chars ) - 1 ), 1 );
        }
        return $password;
    }
    
    function wp_rand( $min = 0, $max = 0 ) {
        return mt_rand( $min, $max );
    }
    
    function wp_parse_args( $args, $defaults = '' ) {
        if ( is_object( $args ) ) {
            $parsed_args = get_object_vars( $args );
        } elseif ( is_array( $args ) ) {
            $parsed_args =& $args;
        } else {
            wp_parse_str( $args, $parsed_args );
        }

        if ( is_array( $defaults ) ) {
            return array_merge( $defaults, $parsed_args );
        }
        return $parsed_args;
    }
    
    function wp_parse_str( $string, &$array ) {
        parse_str( $string, $array );
        if ( get_magic_quotes_gpc() ) {
            $array = stripslashes_deep( $array );
        }
    }
    
    function stripslashes_deep( $value ) {
        return is_array( $value ) ? array_map( 'stripslashes_deep', $value ) : stripslashes( $value );
    }
    
    function hash_equals( $a, $b ) {
        return hash_hmac( 'sha256', $a, '' ) === hash_hmac( 'sha256', $b, '' );
    }
    
    function __( $text, $domain = 'default' ) {
        return $text;
    }
}

// Mock WooCommerce pour les tests de session si nécessaire
if ( ! function_exists( 'WC' ) ) {
    class MockWCSession {
        private $data = array();
        
        public function set( $key, $value ) {
            $this->data[ $key ] = $value;
        }
        
        public function get( $key, $default = null ) {
            return isset( $this->data[ $key ] ) ? $this->data[ $key ] : $default;
        }
        
        public function __unset( $key ) {
            unset( $this->data[ $key ] );
        }
        
        public function get_session_data() {
            return $this->data;
        }
    }
    
    class MockWC {
        public $session;
        
        public function __construct() {
            $this->session = new MockWCSession();
        }
    }
    
    function WC() {
        static $wc = null;
        if ( $wc === null ) {
            $wc = new MockWC();
        }
        return $wc;
    }
}

echo "Bootstrap WC Qualiopi Steps tests loaded.\n";
