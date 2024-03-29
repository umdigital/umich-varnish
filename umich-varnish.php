<?php
/**
 * Plugin Name: U-M: Varnish Cache
 * Plugin URI: https://github.com/umdigital/umich-varnish/
 * Description: Provides varnish cache purging functionality.
 * Version: 1.3.4
 * Author: U-M: Digital
 * Author URI: http://vpcomm.umich.edu
 */

define( 'UMVARNISH_PATH', dirname( __FILE__ ) . DIRECTORY_SEPARATOR );

include UMVARNISH_PATH .'includes'. DIRECTORY_SEPARATOR .'override.php';

class UMVarnish {
    static public function init()
    {
        // UPDATER SETUP
        if( !class_exists( 'WP_GitHub_Updater' ) ) {
            include_once UMVARNISH_PATH .'includes'. DIRECTORY_SEPARATOR .'updater.php';
        }
        if( isset( $_GET['force-check'] ) && $_GET['force-check'] && !defined( 'WP_GITHUB_FORCE_UPDATE' ) ) {
            define( 'WP_GITHUB_FORCE_UPDATE', true );
        }
        if( is_admin() ) {
            new WP_GitHub_Updater(array(
                // this is the slug of your plugin
                'slug' => plugin_basename(__FILE__),
                // this is the name of the folder your plugin lives in
                'proper_folder_name' => dirname( plugin_basename( __FILE__ ) ),
                // the github API url of your github repo
                'api_url' => 'https://api.github.com/repos/umdigital/umich-varnish',
                // the github raw url of your github repo
                'raw_url' => 'https://raw.githubusercontent.com/umdigital/umich-varnish/master',
                // the github url of your github repo
                'github_url' => 'https://github.com/umdigital/umich-varnish',
                 // the zip url of the github repo
                'zip_url' => 'https://github.com/umdigital/umich-varnish/zipball/master',
                // wether WP should check the validity of the SSL cert when getting an update, see https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2 and https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/4 for details
                'sslverify' => true,
                // which version of WordPress does your plugin require?
                'requires' => '3.0',
                // which version of WordPress is your plugin tested up to?
                'tested' => '3.9.1',
                // which file to use as the readme for the version number
                'readme' => 'README.md',
                // Access private repositories by authorizing under Appearance > Github Updates when this example plugin is installed
                'access_token' => '',
            ));
        }

        // IF LOGGED IN COOKIE AND COOKIE STALE (not logged in), LOGOUT
        add_action( 'init', function(){
            if( isset( $_COOKIE[ LOGGED_IN_COOKIE ] ) && !is_user_logged_in() ) {
                add_action( 'wp_logout', function(){
                    wp_redirect( $_SERVER['REQUEST_URI'] );
                    exit;
                });

                setcookie( TEST_COOKIE, '', -3600, SITECOOKIEPATH, COOKIE_DOMAIN );
                wp_logout();

                wp_redirect( $_SERVER['REQUEST_URI'] );
                exit;
            }
        });

        /** GLOBAL CHANGES: FULL SITE PURGE **/
        // Theme Updates
        add_action( 'switch_theme',         array( __CLASS__, 'onThemeChange' ) );
        add_filter( 'customize_save_after', array( __CLASS__, 'onCustomizerSave' ) );

        // Widget Updates
        add_filter( 'widget_update_callback', array( __CLASS__, 'onWidgetUpdate' ) );

        /** SPECIFIC PAGE UPDATES: SINGLE PAGE PURGE **/
        // Post Updates
        add_action( 'save_post', array( __CLASS__, 'onPostUpdate' ), 10, 2 );

        // New Comment OR Status Change
        add_action( 'comment_post',          array( __CLASS__, 'onCommentUpdate' ) );
        add_action( 'wp_set_comment_status', array( __CLASS__, 'onCommentUpdate' ) );


        /** ADMIN **/
        add_action( 'wp_before_admin_bar_render',     array( __CLASS__, 'adminBarRender' ) );
        add_action( 'wp_ajax_umvarnish_clear',        array( __CLASS__, 'ajaxOnPurge' ) );
        add_action( 'wp_ajax_nopriv_umvarnish_clear', array( __CLASS__, 'ajaxOnPurge' ) );
        add_action( 'wp_footer',                      array( __CLASS__, 'ajaxCode' ) );
        add_action( 'admin_footer',                   array( __CLASS__, 'ajaxCode' ) );
    }

    /****************************/
    /*** PURGE FUNCTIONALITY ****/
    /****************************/

    static public function onThemeChange()
    {
        // TRIGGER FULL SITE PURGE
        self::_purgeAll();
    }

    static public function onCustomizerSave()
    {
        // TRIGGER FULL SITE PURGE
        self::_purgeAll();
    }

    static public function onWidgetUpdate( $instance )
    {
        // TRIGGER FULL SITE PURGE
        self::_purgeAll();

        // @REQUIRED to return the instance
        return $instance;
    }

    static public function onPostUpdate( $pID, $post )
    {
        // Stop the script when doing autosave
        if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        // PURGE POST URL
        self::purgePage( get_the_permalink( $pID ) );

        if( $post ) {
            // Purge post type archive
            if( $pArchiveUrl = get_post_type_archive_link( $post->post_type ) ) {
                self::purgePage( $pArchiveUrl, true );
            }

            // Purge taxonomy archives
            foreach( get_object_taxonomies( $post ) as $tax ) {
                foreach( (get_the_terms( $pID, $tax ) ?: array()) as $term ) {
                    self::purgePage(
                        get_term_link( $term->term_id ),
                        true
                    );
                }
            }
        }
    }

    static public function onCommentUpdate( $cID )
    {
        if( $comment = get_comment( $cID ) ) {
            $post    = get_post( $comment->comment_post_ID );

            self::onPostUpdate( $comment->comment_post_ID, $post );
        }
    }

    static public function purgePage( $url, $children = false )
    {
        $type = 'page';

        $baseParts = parse_url(
            get_site_url( null, '/', 'http' )
        );

        $urlParts = parse_url( $url );
        $urlParts['path'] = @$urlParts['path'] ?: '/';

        // make sure $url path starts with baseUrl path
        if( strpos( $urlParts['path'], $baseParts['path'] ) !== 0 ) {
            $urlParts['path'] = rtrim( $baseParts['path'], '/' ) .'/'. ltrim( $urlParts['path'], '/' );
        }
        // cleanup path so that it starts and ends with a /
        $urlParts['path'] = trim( $urlParts['path'], '/' );
        if( preg_match( '#\..{2,3}$#', $urlParts['path'] ) ) {
            $urlParts['path'] = '/'. $urlParts['path'];
        }
        else {
            $urlParts['path'] = $urlParts['path'] ? "/{$urlParts['path']}/" : '/';
        }

        $url = $baseParts['scheme'] .'://'. $baseParts['host'] . $urlParts['path'];

        if( $children ) {
            $url .= '.*';

            $type = 'regex';
        }

        return self::_purgeURL( $url, $type );
    }

    static private function _purgeAll()
    {
        $url = get_site_url(
            null, '.*', 'http'
        );

        return self::_purgeURL( $url, 'regex' );
    }

    static private function _purgeURL( $url, $type = 'page' )
    {
        $ret = array(
            'url' => $url,
            'res' => 'unknown'
        );

        // if this request is already a purge request then don't performa another one
        if( @$_SERVER['REQUEST_METHOD'] == 'PURGE' ) {
            $ret['res'] = 'Already a PURGE request';
            return $ret;
        }

        // check for Wordpress MU Domain Mapping Plugin usage
        // force host to the live version
        if( function_exists( 'domain_mapping_siteurl' ) ) {
            $mapParts = parse_url( domain_mapping_siteurl( false ) );
            $urlParts = parse_url( $url );

            if( $urlParts['host'] != $mapParts['host'] ) {
                $url = rtrim( $urlParts['scheme'] .'://'. $mapParts['host'] . $urlParts['path'] .'?'. @$urlParts['query'], '?' );
            }
        }

        $res = wp_remote_request(
            $url,
            array(
                'method'         => 'PURGE',
                'X-Purge-Method' => $type
            )
        );

        if( $res instanceof WP_Error ) {
            if( $res->get_error_messages() ) {
                $html = '<ul>';
                foreach( $res->get_error_messages() as $err ) {
                    $html .= '<li>'. $err .'</li>';
                }
                $html .= '</ul>';

                $ret['res'] = $html;
            }
            else {
                $ret['res'] = 'Unknown WP Error';
            }
        }
        else {
            $ret['res'] = $res;
        }

        return $ret;
    }


    /***************************/
    /*** ADMIN FUNCTIONALITY ***/
    /***************************/

    /**
     * Add admin bar options for cache purge
     **/
    static public function adminBarRender()
    {
        global $wp_admin_bar;

        if( current_user_can( 'administrator' ) || current_user_can( 'editor' ) ) {
            $wp_admin_bar->add_menu(array(
                'parent' => false,
                'id'     => 'umich-varnish-root',
                'title'  => 'Varnish Cache <img src="'. plugins_url( 'assets/working.svg', __FILE__ ) .'" style="visibility: hidden;"/>',
                'href'   => false
            ));
        }

        if( current_user_can( 'administrator' ) ) {
            $wp_admin_bar->add_menu(array(
                'parent' => 'umich-varnish-root',
                'id'     => 'umich-varnish-purge-site',
                'title'  => 'Purge All',
                'href'   => '#',
                'meta'   => array(
                    'onclick' => 'return umVarnishPurge("all");'
                )
            ));
        }

        if( current_user_can( 'administrator' ) || current_user_can( 'editor' ) ) {
            if( !is_admin() ) {
                $wp_admin_bar->add_menu(array(
                    'parent' => 'umich-varnish-root',
                    'id'     => 'umich-varnish-purge-page',
                    'title'  => 'Purge Page',
                    'href'   => '#',
                    'meta'   => array(
                        'onclick' => 'return umVarnishPurge("page");'
                    )
                ));

                if( get_option( 'permalink_structure' ) ) {
                    $wp_admin_bar->add_menu(array(
                        'parent' => 'umich-varnish-root',
                        'id'     => 'umich-varnish-purge-section',
                        'title'  => 'Purge Section',
                        'href'   => '#',
                        'meta'   => array(
                            'onclick' => 'return umVarnishPurge("section");'
                        )
                    ));
                }
            }
        }
    }

    static public function ajaxCode()
    {
        if( current_user_can( 'administrator' ) || current_user_can( 'editor' ) ) {
            echo '<script type="text/javascript">'."\n";
            echo "var umvNonce = '". wp_create_nonce( 'umich-varnish-nonce' ) ."';\n";
            echo "var umvAjaxUrl = '". admin_url( 'admin-ajax.php') ."';\n";
            echo "if( typeof ajaxurl === 'undefined' ) { var ajaxurl = umvAjaxUrl; }\n";
            echo file_get_contents( UMVARNISH_PATH .'umich-varnish.js' );
            echo '</script>'."\n";
        }
    }

    static public function ajaxOnPurge()
    {
        $url = isset( $_POST['url'] ) ? $_POST['url'] : false;

        $return = array(
            'status' => 'fail',
            'url'    => $url,
            'nonce'  => ''
        );

        if( check_ajax_referer( 'umich-varnish-nonce', 'nonce', false ) ) {
            switch( @$_POST['type'] ) {
                case 'all':
                    // TRIGGER FULL SITE PURGE
                    $return['status'] = self::_purgeAll();
                    break;

                case 'page':
                    if( $url ) {
                        // TRIGGER PAGE PURGE
                        $return['status'] = self::purgePage( $url );
                    }
                    break;

                case 'section':
                    if( $url && get_option( 'permalink_structure' ) ) {
                        // TRIGGER PAGE PURGE
                        $urlParts = parse_url( $url );

                        // get the first segement of the url
                        list( $path ) = explode( '/', trim( $urlParts['path'], '/' ) );
                        $path = $path ? "/{$path}/" : '/';

                        // update url so that its the path of the first segment instead of full url
                        $url = str_replace( $urlParts['path'], $path, $url );

                        $return['status'] = self::purgePage( $url, true );
                    }
                    break;

                default:
                    $return['status'] = 'unknown';
                    break;
            }
        }

        $return['nonce'] = wp_create_nonce( 'umich-varnish-nonce' );

        echo json_encode( $return );

        wp_die();
    }
}
UMVarnish::init();
