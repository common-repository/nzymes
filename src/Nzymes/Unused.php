<?php

/**
 * This is a collection of snippets that I developed and couldn't decide to delete.
 */
class Nzymes_Unused {

    protected
    function get_header_data( $package_dir, $type ) {
        $properties = array(
            'plugin' => array(
                'Author',
                'Author URI',
                'Description',
                'Domain Path',
                'Network',
                'Plugin Name',
                'Plugin URI',
                'Site Wide Only',
                'Text Domain',
                'Version',
            ),
            'theme'  => array(
                'Author',
                'Author URI',
                'Description',
                'Domain Path',
                'Status',
                'Tags',
                'Template',
                'Text Domain',
                'Theme Name',
                'Theme URI',
                'Version',
            ),
        );
        $data       = array_combine( $properties[ $type ], $properties[ $type ] );
        $result     = get_file_data( $package_dir, $data, $type );

        return $result;
    }

    /**
     * Add a filter in the correct position, without changing the internal pointer of $wp_filter[ $tag ].
     *
     * @param string $tag
     * @param string $function_to_add
     * @param int    $priority
     * @param int    $accepted_args
     */
    protected
    function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
        if ( ! doing_filter( $tag ) ) {
            add_filter( $tag, $function_to_add, $priority, $accepted_args );

            return;
        }

        global $wp_filter;

        // apply_filters() is currently iterating over $wp_filter[ $tag ], so its internal pointer is at a
        // certain key in the middle now and we do not want to have that priority changed after exiting.

        // If $wp_filter[ $tag ][ $priority ] was correctly sorted, then it will be still sorted after add_filter().
        if ( isset( $wp_filter[ $tag ][ $priority ] ) ) {
            add_filter( $tag, $function_to_add, $priority, $accepted_args );

            return;
        }

        // Surely add_filter() will append $wp_filter[ $tag ][ $priority ] at the end of $wp_filter[ $tag ].
        add_filter( $tag, $function_to_add, $priority, $accepted_args );

        // It's quite possible that $wp_filter[ $tag ][ $priority ] is out of place, so we sort again.
        $this->ksort_fixed( $wp_filter[ $tag ] );
    }

    /**
     * ksort without resetting the internal pointer.
     *
     * @param array $array
     */
    protected
    function ksort_fixed( &$array ) {
        $original = key( $array );
        ksort( $array );
        foreach ( $array as $key => $value ) {
            if ( $key == $original ) {
                break;
            }
        }
        prev( $array );
    }

}
