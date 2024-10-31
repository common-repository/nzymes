<?php
require_once dirname( NZYMES_PRIMARY ) . '/src/Nzymes/Capabilities.php';
require_once dirname( NZYMES_PRIMARY ) . '/src/Nzymes/Options.php';
require_once dirname( NZYMES_PRIMARY ) . '/vendor/Ando/ErrorFactory.php';

class Nzymes_Plugin {
    /**
     * Use a higher priority than Enzymes 2.
     */
    const PRIORITY = 9;

    /**
     * @var Nzymes_Options
     */
    static public $options;

    /**
     * Singleton
     *
     * @var Nzymes_Engine
     */
    static protected $engine;

    /**
     * @return Nzymes_Engine
     */
    static public
    function engine() {
        if ( is_null( self::$engine ) ) {
            self::$engine = new Nzymes_Engine();
        }

        return self::$engine;
    }

    public
    function __construct() {
        self::$options = new Nzymes_Options();

        if ( is_admin() ) {
            register_activation_hook( NZYMES_PRIMARY, array( 'Nzymes_Plugin', 'on_activation' ) );
            register_deactivation_hook( NZYMES_PRIMARY, array( 'Nzymes_Plugin', 'on_deactivation' ) );

            register_uninstall_hook(NZYMES_PRIMARY, array( 'Nzymes_Plugin', 'on_uninstall') );

            add_filter( 'editable_roles', array( 'Nzymes_Plugin', 'on_editable_roles' ) );
        } else {
            add_action( 'init', array( 'Nzymes_Plugin', 'on_init' ) );
        }
    }

    /**
     * Attach filters to tags.
     */
    static public
    function on_init() {
        global $wp_version;
        if ( version_compare( $wp_version, '4.7', '<' ) ) {
            throw new Ando_Exception('Invalid WordPress version. Required at least 4.7.');
        }

        require_once dirname( NZYMES_PRIMARY ) . '/src/Nzymes/Engine.php';
        $engine = self::engine();  // singleton
//@formatter:off
        $engine->absorb_later('wp_title',        self::PRIORITY);
        $engine->absorb_later('the_title',       self::PRIORITY);
        $engine->absorb_later('the_title_rss',   self::PRIORITY);
        $engine->absorb_later('the_excerpt',     self::PRIORITY);
        $engine->absorb_later('the_excerpt_rss', self::PRIORITY);
        $engine->absorb_later('the_content',     self::PRIORITY);
//@formatter:on
    }

    /**
     * Callback used when the plugin is activated by the user.
     *
     * @return boolean
     */
    static public
    function on_activation() {
        self::add_roles_and_capabilities();
        self::add_options();

        return true;
    }

    /**
     * Callback used when the plugin is deactivated by the user.
     *
     * In general, remember not to undo anything that on_activation() would not be able to restore intact.
     *
     * @return boolean
     */
    static public
    function on_deactivation() {
        self::remove_roles_and_capabilities();

        return true;
    }

    /**
     * Uninstalls this plugin, cleaning up all data.
     */
    static public
    function on_uninstall() {
        self::$options->remove_all();
    }

    /**
     * Remove Nzymes roles from the user-edit screen because they are not meant to be primary roles.
     *
     * @param array $all_roles
     *
     * @return array
     */
    public static
    function on_editable_roles( $all_roles ) {
        $screen = get_current_screen();
        if ( in_array($screen->id, ['user-new', 'user-edit', 'users']) ) {
            foreach ( $all_roles as $slug => $role ) {
                if ( 0 === strpos( $slug, Nzymes_Capabilities::PREFIX ) ) {
                    unset( $all_roles[ $slug ] );
                }
            }
        }

        return $all_roles;
    }

    //------------------------------------------------------------------------------------------------------------------

    static protected
    function add_roles_and_capabilities() {
        self::remove_roles_and_capabilities();
//@formatter:off
        add_role(Nzymes_Capabilities::User,           __('Nzymes User'),            Nzymes_Capabilities::for_User() );
        add_role(Nzymes_Capabilities::PrivilegedUser, __('Nzymes Privileged User'), Nzymes_Capabilities::for_PrivilegedUser() );
        add_role(Nzymes_Capabilities::TrustedUser,    __('Nzymes Trusted User'),    Nzymes_Capabilities::for_TrustedUser() );
        add_role(Nzymes_Capabilities::Coder,          __('Nzymes Coder'),           Nzymes_Capabilities::for_Coder() );
        add_role(Nzymes_Capabilities::TrustedCoder,   __('Nzymes Trusted Coder'),   Nzymes_Capabilities::for_TrustedCoder() );
//@formatter:on

        $administrator = get_role('administrator');
        foreach ( Nzymes_Capabilities::all() as $cap ) {
            $administrator->add_cap( $cap );
        }
    }

    /**
     * Remove roles and capabilities by prefix.
     */
    static protected
    function remove_roles_and_capabilities() {
        foreach ( wp_roles()->get_names() as $slug => $name ) {
            if ( 0 === strpos( $slug, Nzymes_Capabilities::PREFIX ) ) {
                remove_role( $slug );
            }
        }

        $administrator = get_role('administrator');
        foreach ( Nzymes_Capabilities::all() as $cap ) {
            $administrator->remove_cap( $cap );
        }
    }

    /**
     * Add needed options and initialize them.
     */
    static protected 
    function add_options() {
        $options = self::$options->get();
        if (empty($options['installation-time'])) {
            $time = new DateTime();
            $time->setTimestamp(filemtime(NZYMES_PRIMARY));
            $options['installation-time'] = $time->format(DateTime::ATOM);
        }
        if (empty($options['process-posts-after'])) {
            $options['process-posts-after'] = $options['installation-time'];
        }
        if (empty($options['process-also-posts'])) {
            $options['process-also-posts'] = array();
        }
        $options['version'] = NZYMES_VERSION;
        self::$options->set($options);
    }

}
