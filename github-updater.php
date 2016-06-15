<?php

if ( ! class_exists( 'GHU_Core' ) ) {
    class GHU_Core
    {
        public $plugins;
        public $update_data;


        function __construct() {
            $now = strtotime( 'now' );
            $last_checked = (int) get_option( 'ghu_last_checked' );
            $this->update_data = (array) get_option( 'ghu_update_data' );
            $check_interval = apply_filters( 'ghu_check_interval', ( 60 * 60 * 12 ) );

            if ( ( $now - $last_checked ) > $check_interval ) {
                add_action( 'init', array( $this, 'init' ), 99 );
            }

            add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
            add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'set_update_data' ) );
            add_filter( 'upgrader_source_selection', array( $this, 'upgrader_source_selection' ), 10, 4 );
        }


        function init() {
            $data = array();
            $this->plugins = apply_filters( 'ghu_plugins', array() );
            foreach ( $this->plugins as $plugin ) {
                $response = $this->check_for_update( $plugin );
                if ( $response ) {
                    $data[] = $response;
                }
            }

            update_option( 'ghu_update_data', $data );
            update_option( 'ghu_last_checked', strtotime( 'now' ) );
        }


        /**
         * Get plugin info for the "View Details" popup
         */
        function plugins_api( $default = false, $action, $args ) {
            if ( 'plugin_information' == $action ) {
                foreach ( $this->update_data as $plugin ) {
                    if ( $plugin['slug'] == $args->slug ) {
                        return (object) array(
                            'name'          => $plugin['display_name'],
                            'slug'          => $plugin['slug'],
                            'version'       => $plugin['new_version'],
                            'requires'      => '4.4',
                            'tested'        => get_bloginfo( 'version' ),
                            'last_updated'  => date( 'Y-m-d' ),
                            'sections' => array(
                                'changelog'     => ''
                            )
                        );
                    }
                }
            }
            return $default;
        }


        function set_update_data( $transient ) {
            if ( empty( $transient->checked ) ) {
                return $transient;
            }

            foreach ( $this->update_data as $plugin ) {
                $path = $plugin['plugin'];
                $transient->response[ $path ] = (object) $plugin;
            }

            return $transient;
        }


        function check_for_update( $plugin ) {

            // get current version
            $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin['slug'] );
            $current_version = $plugin_data['Version'];
            $display_name = $plugin_data['Name'];

            // get plugin tags
            list( $owner, $repo ) = explode( '/', $plugin['repo'] );
            $request = wp_remote_get( "https://api.github.com/repos/$owner/$repo/tags" );

            // validate response
            if ( is_wp_error( $request ) || 200 != wp_remote_retrieve_response_code( $request ) ) {
                return false;
            }

            $json = json_decode( $request['body'], true );
            $latest_tag = $json[0];

            // if an update is available, set WP transients
            if ( version_compare( $current_version, $latest_tag['name'], '<' ) ) {
                return array(
                    'slug'          => $repo,
                    'plugin'        => $plugin['slug'],
                    'display_name'  => $display_name,
                    'new_version'   => $latest_tag['name'],
                    'url'           => "https://github.com/$owner/$repo/",
                    'package'       => $latest_tag['zipball_url'],
                );
            }

            return false;
        }


        /**
         * Rename the plugin folder
         */
        function upgrader_source_selection( $source, $remote_source, $upgrader, $hook_extra = null ) {
            global $wp_filesystem;

            if ( isset( $hook_extra['plugin'] ) ) {
                foreach ( $this->update_data as $plugin ) {
                    if ( $hook_extra['plugin'] == $plugin['plugin'] ) {
                        $new_source = trailingslashit( $remote_source ) . dirname( $plugin['plugin'] );
                        $wp_filesystem->move( $source, $new_source );
                        return trailingslashit( $new_source );
                    }
                }
            }

            return $source;
        }
    }

    new GHU_Core();
}
