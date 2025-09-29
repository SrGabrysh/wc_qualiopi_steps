<?php
/**
 * Bootstrap pour les tests PHPUnit de WC Qualiopi Steps
 */

// Définir ABSPATH pour éviter l'exit dans les classes WordPress
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

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
        // get_magic_quotes_gpc() supprimé en PHP 8.0+
        if ( function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc() ) {
            $array = stripslashes_deep( $array );
        }
    }
    
    function stripslashes_deep( $value ) {
        return is_array( $value ) ? array_map( 'stripslashes_deep', $value ) : stripslashes( $value );
    }
    
    if (!function_exists('hash_equals')) {
        function hash_equals( $a, $b ) {
            return hash_hmac( 'sha256', $a, '' ) === hash_hmac( 'sha256', $b, '' );
        }
    }
    
    function wp_upload_dir( $time = null, $create_dir = true, $refresh_cache = false ) {
        $upload_dir = sys_get_temp_dir() . '/wp-uploads-test';
        if ( $create_dir && ! is_dir( $upload_dir ) ) {
            mkdir( $upload_dir, 0755, true );
        }
        return [
            'path' => $upload_dir,
            'url' => 'http://example.com/wp-content/uploads',
            'subdir' => '',
            'basedir' => $upload_dir,
            'baseurl' => 'http://example.com/wp-content/uploads',
            'error' => false
        ];
    }
    
    function current_time( $type, $gmt = 0 ) {
        switch ( $type ) {
            case 'mysql':
                return date( 'Y-m-d H:i:s' );
            case 'timestamp':
                return time();
            case 'c':
                return date( 'c' );
            default:
                return date( $type );
        }
    }
    
    function get_current_user_id() {
        return 0;
    }
    
    function get_bloginfo( $show = '', $filter = 'raw' ) {
        switch ( $show ) {
            case 'version':
                return '6.4.0';
            case 'name':
                return 'Test WordPress Site';
            case 'url':
            case 'home':
                return 'http://example.com';
            case 'admin_email':
                return 'admin@example.com';
            default:
                return '';
        }
    }
    
    function is_admin() {
        return false;
    }
    
    function wp_mkdir_p( $target ) {
        $wrapper = null;
        
        // Strip the protocol.
        if ( wp_is_stream( $target ) ) {
            list( $wrapper, $target ) = explode( '://', $target, 2 );
        }
        
        // From php.net/mkdir user contributed notes.
        $target = str_replace( '//', '/', $target );
        
        // Put the wrapper back on the target.
        if ( $wrapper !== null ) {
            $target = $wrapper . '://' . $target;
        }
        
        /*
         * Safe mode fails with a trailing slash under certain PHP versions.
         * Use rtrim() instead of untrailingslashit to avoid formatting.php dependency.
         */
        $target = rtrim( $target, '/' );
        if ( empty( $target ) ) {
            $target = '/';
        }
        
        if ( file_exists( $target ) ) {
            return @is_dir( $target );
        }
        
        // Do not allow path traversals.
        if ( false !== strpos( $target, '../' ) || false !== strpos( $target, '..' . DIRECTORY_SEPARATOR ) ) {
            return false;
        }
        
        // We need to find the permissions of the parent folder that exists and inherit that.
        $target_parent = dirname( $target );
        while ( '.' !== $target_parent && ! is_dir( $target_parent ) && dirname( $target_parent ) !== $target_parent ) {
            $target_parent = dirname( $target_parent );
        }
        
        // Get the permission bits.
        $stat = @stat( $target_parent );
        if ( $stat ) {
            $dir_perms = $stat['mode'] & 0007777;
        } else {
            $dir_perms = 0755;
        }
        
        if ( @mkdir( $target, $dir_perms, true ) ) {
            /*
             * If a umask is set that modifies $dir_perms, we'll have to re-set
             * the $dir_perms correctly with chmod()
             */
            if ( ( $dir_perms & ~umask() ) != $dir_perms ) {
                $folder_parts = explode( '/', substr( $target, strlen( $target_parent ) + 1 ) );
                for ( $i = 1, $c = count( $folder_parts ); $i <= $c; $i++ ) {
                    chmod( $target_parent . '/' . implode( '/', array_slice( $folder_parts, 0, $i ) ), $dir_perms );
                }
            }
            
            return true;
        }
        
        return false;
    }
    
    function wp_is_stream( $path ) {
        $scheme_separator = strpos( $path, '://' );
        
        if ( false === $scheme_separator ) {
            // $path isn't a stream
            return false;
        }
        
        $stream = substr( $path, 0, $scheme_separator );
        
        return in_array( $stream, stream_get_wrappers(), true );
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

// Fonction helper pour créer un contexte de test
function testContext(array $overrides = []): array
{
    $defaults = [
        'flags' => [
            'enforce_checkout' => false,
            'logging' => true
        ],
        'cart' => [
            'product_id' => 123
        ],
        'user' => [
            'id' => 42
        ],
        'query' => [
            'tp_token' => null
        ],
        'session' => [
            'solved' => [123 => false]
        ],
        'usermeta' => [
            'ok' => [123 => false]
        ],
        'mapping' => [
            'active' => true,
            'test_page_url' => '/test-123'
        ]
    ];

    // Utiliser array_replace_recursive pour éviter les problèmes avec les valeurs null
    return array_replace_recursive($defaults, $overrides);
}

echo "Bootstrap WC Qualiopi Steps tests loaded.\n";
