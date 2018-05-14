<?php

class MCVarnish_Override {
    static public function init()
    {
        add_filter( 'wp_enqueue_scripts', array( __CLASS__, 'headers' ), 999 );

        add_action( 'add_meta_boxes', array( __CLASS__, 'metaBoxes' ) );
        add_action( 'save_post', array( __CLASS__, 'metaDetailsSave' ) );
    }

    static public function headers()
    {
        global $post;

        if( $post && $post instanceof WP_Post ) {
            if( $status = get_post_meta( $post->ID, 'mcvarnish_disable', true ) ) {
                header( 'X-Varnish-Disable: '. $status );
            }
            else if( $ttl = get_post_meta( $post->ID, 'mcvarnish_ttl', true ) ) {
                header( 'X-Varnish-TTL: '. $ttl );
            }
        }
    }

    static public function metaBoxes()
    {
        if( current_user_can( 'administrator' ) || current_user_can( 'editor' ) ) {
            add_meta_box(
                'umvarnish-override',
                __( 'Varnish Cache Settings', 'umvarnish' ),
                array( __CLASS__, 'metaVarnishAdmin' ),
                null,
                'side'
            );
        }
    }

    static public function metaVarnishAdmin()
    {
        wp_nonce_field( 'umvarnish-settings_nonce', 'umvarnish-settings_nonce' );

        echo '
        <style type="text/css">
        #umvarnish-override .form-table th,
        #umvarnish-override .form-table td {
            padding-top: 10px;
            padding-bottom: 10px;
        }

        #umvarnish-override .notes {
            font-style: italic;
            font-size: 12px;
            line-height: 1.2;
        }
        </style>';
        echo '<table class="form-table">';
        self::_checkbox( 'disable', 'Disable', 1, null, 'Check to NOT cache this page.' );
        self::_input( 'ttl', 'TTL', null, 'Max amount of time (in seconds) to hold page in cache.' );
        echo '</table>';
    }


    static public function metaDetailsSave( $pID )
    {
        // Stop the script when doing autosave
        if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        if( !current_user_can( 'administrator' ) && !current_user_can( 'editor' ) ) {
            return;
        }

        // Verify the nonce. If insn't there, stop the script
        if( !isset( $_POST['umvarnish-settings_nonce'] ) || !wp_verify_nonce( $_POST['umvarnish-settings_nonce'], 'umvarnish-settings_nonce' ) ) return;

        $metaVars = array(
            'disable',
            'ttl'
        );

        foreach( $metaVars as $var ) {
            $var = 'mcvarnish_'. $var;

            if( isset( $_POST[ $var ] ) && $_POST[ $var ] ) {
                update_post_meta(
                    $pID, $var, esc_attr( $_POST[ $var ] )
                );
            }
            else {
                delete_post_meta( $pID, $var );
            }
        }
    }


    static private function _input( $key, $name, $value = null, $notes = null )
    {
        $key = 'mcvarnish_'. $key;

        if( $_POST && isset( $_POST[ $key ] ) ) {
            $value = $_POST[ $key ];
        }
        else if( !$value && isset( $_GET['post'] ) ) {
            $value = get_post_meta( $_GET['post'], $key, true );
        }

        echo '
        <tr>
            <th class="form-field">
                <label for="'. $key .'">'. __( $name, 'umvarnish' ) .'</label>
            </th>
            <td>
                <input type="text" id="'. $key .'" name="'. $key .'" value="'. $value .'" />
                '. ($notes ? '<p class="notes">'. $notes .'</p>' : null) .'
            </td>
        </tr>
        ';
    }

    static private function _checkbox( $key, $name, $value = null, $checked = null, $notes = null )
    {
        $key = 'mcvarnish_'. $key;

        $currValue = null;
        if( $_POST && isset( $_POST[ $key ] ) ) {
            $currValue = $_POST[ $key ];
        }
        else if( isset( $_GET['post'] ) ) {
            $currValue = get_post_meta( $_GET['post'], $key, true );
        }

        if( !is_null( $currValue ) ) {
            if( $currValue == $value ) {
                $checked = true;
            }
        }

        echo '
        <tr>
            <th class="form-field">
                <label for="'. $key .'">'. __( $name, 'umvarnish' ) .'</label>
            </th>
            <td>
                <input type="checkbox" id="'. $key .'" name="'. $key .'" value="'. $value .'" '.( $checked ? ' checked="checked"' : null).' />
                '. ($notes ? '<p class="notes">'. $notes .'</p>' : null) .'
            </td>
        </tr>
        ';
    }
}

MCVarnish_Override::init();
