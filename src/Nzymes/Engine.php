<?php
require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

require_once dirname( NZYMES_PRIMARY ) . '/vendor/Ando/Regex.php';
require_once dirname( NZYMES_PRIMARY ) . '/vendor/Ando/StarFunc.php';
require_once dirname( NZYMES_PRIMARY ) . '/vendor/Ando/ErrorFactory.php';
require_once dirname( NZYMES_PRIMARY ) . '/src/Nzymes/Stack.php';

class Nzymes_Engine {
    /**
     * When Nzymes_Engine::process() is called, this special filter tag is internally used.
     */
    const DIRECT_FILTER = '__nzymes__process';

    /**
     * Used internally for converting escaped injections '{{[..]}' to non matching strings '{-[..]}'.
     * If escaped injections are found they are converted and an additional last un-escaping filter is setup.
     */
    const ESCAPE_CHAR = '-';

    /**
     * This is not to be executed absolutely last, but only after all Nzymes_Engine::absorb() filter executions.
     */
    const UNESCAPE_PRIORITY = 1000;

    /**
     *  Use to make it explicit that the content belongs to the current post.
     */
    const GLOBAL_POST = null;

    /**
     * Use to make it explicit that the content belongs to no post at all.
     */
    const NO_POST = - 1;

    /**
     * If the content does not belong to any post, use this user ID when referring to the "author".
     */
    const NO_POST_AUTHOR = 1;

    /**
     * Plugin options.
     *
     * @var array
     */
    protected $options;

    /**
     * Current sequence.
     *
     * @var string
     */
    protected $current_injection;

    /**
     * Current enzyme.
     *
     * @var string
     */
    protected $current_enzyme;

    /**
     * The post which the content belongs to.
     *
     * @var WP_Post
     */
    protected $injection_post;

    /**
     * The content of the post, not modified by Nzymes.
     *
     * @var string
     */
    protected $content;

    /**
     * The content of the post, modified by Nzymes.
     *
     * @var string
     */
    protected $new_content;

    /**
     * Internal stack.
     *
     * @var Nzymes_Stack
     */
    protected $catalyzed;

    /**
     * True if eval recovered.
     *
     * @var bool
     */
    protected $has_eval_recovered;

    /**
     * Last error in eval.
     *
     * @var
     */
    protected $last_eval_error;

    /**
     * The code that is being evaluated.
     *
     * @var string
     */
    protected $evaluating_code;

    /**
     * Post used as origin of the current enzyme .
     *
     * @var WP_Post
     */
    protected $origin_post;

    /**
     * True if the processing needs to be undone.
     *
     * @var bool
     */
    protected $reject_injection;

    /**
     * Filter tag for which the absorb method is running.
     *
     * @var string
     */
    protected $current_filter;

    /**
     * Priority at which the absorb method is running.
     *
     * @var int
     */
    protected $current_priority;

    /**
     * Registry of attached handlers, by tag and priority.
     *
     * @var array
     */
    protected $attached_handlers;

    /**
     * Property for exchanging data between injections inside the same content.
     * It's unset before beginning and after finishing the processing of the content.
     *
     * @var mixed
     */
    protected $intra;

    /**
     * Property for exchanging data between injections outside the same content.
     * It's never unset nor it persists between page generation cycles.
     *
     * @var mixed
     */
    public $extra;

    /**
     * True means injections in the rest of the content will continue to be processed.
     *
     * @var bool
     */
    protected $continue_injecting;

    /**
     * Regular expression for matching "{[ .. ]}".
     *
     * @var Ando_Regex
     */
    protected $e_injection;

    /**
     * Regular expression for matching "(|enzyme)+".
     *
     * @var Ando_Regex
     */
    protected $e_sequence_valid;

    /**
     * Regular expression for matching "enzyme(rest?)".
     *
     * @var Ando_Regex
     */
    protected $e_sequence_start;

    /**
     * Regular expression for matching "= .. =".
     *
     * @var Ando_Regex
     */
    protected $e_string;

    /**
     * Regular expression for matching PHP multiple lines comment.
     *
     * @var Ando_Regex
     */
    protected $e_comment;

    /**
     * Regular expression for matching unbreakable space characters (\xC2\xA0).
     * These characters appear sporadically and unpredictably into WordPress.
     *
     * @var Ando_Regex
     */
    protected $e_unbreakable_space;

    /**
     * Regular expression for matching spaces and unbreakable space characters.
     *
     * @var Ando_Regex
     */
    protected $e_all_spaces;

    /**
     * Regular expression for matching "\{\[", "\]\}".
     *
     * @var Ando_Regex
     */
    protected $e_escaped_injection_delimiter;

    /**
     * Regular expression for matching "\=".
     *
     * @var Ando_Regex
     */
    protected $e_escaped_string_delimiter;

    /**
     * Grammar (top down).
     * ---
     * injection := "{[" sequence "]}"
     *   sequence := enzyme ("|" enzyme)*
     *   enzyme   := literal | transclusion | execution
     *
     * literal := integer | str_literal
     *   integer     := \d+
     *   str_literal := string
     *   string      := "=" <a string where "=", "|", "]}", "\"  are escaped by a prefixed "\"> "="
     *
     * transclusion := item | attr
     *   item        := post_item | author_item
     *   attr        := post_attr | author_attr
     *   post_item   := post "." field
     *   author_item := post "/author." field
     *   post_attr   := post ":" field
     *   author_attr := post "/author:" field
     *   post        := \d+ | "@@" slug | "@" slug | ""
     *   slug        := [\w+~-]+
     *   field       := [\w-]+ | string
     *
     * execution := ("array" | "assoc" | "defer" | item) "(" \d* ")"
     * ---
     *
     * These (key, value) pairs follow the pattern: "'rule_left' => '(?<rule_left>rule_right)';".
     *
     * @var Ando_Regex[]
     */
    protected $grammar;

    /**
     * Init the grammar.
     *
     * Notice that $grammar rules are sorted bottom up here to allow complete interpolation.
     */
    protected
    function init_grammar() {
//@formatter:off
        $grammar = array(
            'integer'      => '(?<integer>\d+)',
            'string'       => '(?<string>' . Ando_Regex::pattern_quoted_string('=', '=') . ')',  // @=[^=\\]*(?:\\.[^=\\]*)*=@
            'str_literal'  => '(?<str_literal>$string)',
            'literal'      => '(?<literal>$integer|$str_literal)',
            'slug'         => '(?<slug>[\w+~-]+)',
            'post'         => '(?<post>\d+|@@$slug|@$slug|)',
            'field'        => '(?<field>[^|.=\]}]+|$string)',  // REM: spaces outside of strings are stripped out.
            'post_item'    => '(?<post_item>$post\.$field)',
            'author_item'  => '(?<author_item>$post/author\.$field)',
            'item'         => '(?<item>$post_item|$author_item)',
            'post_attr'    => '(?<post_attr>$post:$field)',
            'author_attr'  => '(?<author_attr>$post/author:$field)',
            'attr'         => '(?<attr>$post_attr|$author_attr)',
            'transclusion' => '(?<transclusion>$item|$attr)',
            'execution'    => '(?<execution>(?:\b(?:array|assoc|defer)\b|$item)\((?<num_args>\d*)\))',
            'enzyme'       => '(?<enzyme>(?:$execution|$transclusion|$literal))',
            'sequence'     => '(?<sequence>$enzyme(\|$enzyme)*)',
            'injection'    => '(?<injection>{[$sequence]})',
        );
//@formatter:on
        $result = array();
        foreach ( $grammar as $symbol => $rule ) {
            $regex             = new Ando_Regex( $rule );
            $result[ $symbol ] = $regex->interpolate( $result );
        }
        $this->grammar = $result;
    }

    /**
     * Init the regular expression for matching the injection of a sequence.
     */
    protected
    function init_e_injection() {
        $before             = new Ando_Regex( '(?<before>.*?)' );
        $could_be_injection = new Ando_Regex( '\{\[(?<could_be_sequence>.*?)\]\}' );
        $after              = new Ando_Regex( '(?<after>.*)' );
        $content            = new Ando_Regex( '^$before$could_be_injection$after$', '@@s' );
        $content->interpolate( array(
            'before'             => $before,
            'could_be_injection' => $could_be_injection,
            'after'              => $after,
        ) );
        $this->e_injection = $content;
    }

    /**
     * Init the regular expression for matching a valid sequence.
     */
    protected
    function init_e_sequence_valid() {
        // Notice that sequence_valid matches all the enzymes of the sequence at once.
        $sequence_valid = new Ando_Regex( Ando_Regex::option_same_name() . '^(?:\|$enzyme)+$', '@@' );
        $sequence_valid->interpolate( array(
            'enzyme' => $this->grammar['enzyme'],
        ) );
        $this->e_sequence_valid = $sequence_valid;
    }

    /**
     * Init the regular expression for matching the start of a sequence.
     */
    protected
    function init_e_sequence_start() {
        $rest           = new Ando_Regex( '(?:\|(?<rest>.+))' );
        $sequence_start = new Ando_Regex( Ando_Regex::option_same_name() . '^$enzyme$rest?$', '@@' );
        $sequence_start->interpolate( array(
            'enzyme' => $this->grammar['enzyme'],
            'rest'   => $rest,
        ) );
        $this->e_sequence_start = $sequence_start;
    }

    /**
     * Init the regular expression for matching strings.
     */
    protected
    function init_e_string() {
        $maybe_quoted = new Ando_Regex( '(?<before_string>.*?)$string|(?<anything_else>.+)', '@@s' );
        $maybe_quoted->interpolate( array(
            'string' => $this->grammar['string'],
        ) );
        $this->e_string = $maybe_quoted;
    }

    /**
     * Init regular expressions.
     */
    protected
    function init_expressions() {
        $this->init_e_injection();
        $this->init_e_sequence_valid();
        $this->init_e_sequence_start();
        $this->init_e_string();

        $this->e_comment = new Ando_Regex( '\/\*.*?\*\/', '@@s' );
        // for some reason WP introduces some C2 (hex) chars when writing a post...
        $this->e_unbreakable_space           = new Ando_Regex( '\xC2\xA0', '@@' );
        $this->e_all_spaces                  = new Ando_Regex( '(?:\s|\xC2\xA0)+', '@@' );
        $this->e_escaped_injection_delimiter = new Ando_Regex( '\\\\([{[\]}])', '@@' );
        $this->e_escaped_string_delimiter    = new Ando_Regex( '\\\\=', '@@' );
    }

    /**
     * Bootstrap the engine.
     */
    public
    function __construct() {
        $this->init_grammar();
        $this->init_expressions();

        $this->attached_handlers = array();

        $this->has_eval_recovered = true;
        register_shutdown_function( array( $this, 'echo_last_eval_error' ) );

        $this->extra = new stdClass();

        $this->options = array();
    }

    /**
     * Convert a grammar rule to a usable regex.
     *
     * @param string $rule
     * @param bool   $same_name
     *
     * @return string
     * @throws Ando_Exception
     */
    protected
    function grammar_rule( $rule, $same_name = true ) {
        $result = $this->grammar[ $rule ]->wrapper_set( '@@' )
                                         ->expression( true );
        if ( $same_name ) {
            $result = substr_replace( $result, Ando_Regex::option_same_name(), 1, 0 );
        }

        return $result;
    }

    /**
     * Echo a script HTML tag to write data to the javascript console of the browser.
     *
     * @param array $logs
     */
    protected
    function console_log( array $logs ) {
        if ( count( $logs ) == 0 ) {
            return;
        }
        $lines = array();
        foreach ( $logs as $data ) {
            $json    = json_encode( ( is_array( $data ) || is_object( $data ) )
                ? $data
                : trim( $data ) );
            $lines[] = "window.console.log($json);";
        }
        $lines  = implode( '', $lines );
        $output = "<script>if(window.console){if(window.console.log){$lines}}</script>";
        echo $output;
    }

    /**
     * Add a title and some context to the output.
     *
     * @param string $title
     * @param mixed  $output
     *
     * @return array
     */
    protected
    function decorate( $title, $output ) {
        $result   = array();
        $result[] = $title;
        $result[] = sprintf( __( 'Post: %1$s - Enzyme: %3$s - Injection: %2$s' ), $this->injection_post->ID,
            $this->current_injection, $this->current_enzyme );
        $result[] = $output;
        if ( $this->evaluating_code ) {
            // add line numbers
            $lines  = explode( "\n", $this->evaluating_code );
            $digits = strlen( '' . count( $lines ) );
            $format = '%' . $digits . 'd: %s';
            $code   = array();
            foreach ( $lines as $i => $line ) {
                $code[] = sprintf( $format, $i + 1, $line );
            }
            $code     = implode( "\n", $code );
            $result[] = $code;
        }

        return $result;
    }

    /**
     * Send the last eval error to the browser.
     */
    public
    function echo_last_eval_error() {
        // Only execute after a bad eval.
        if ( $this->has_eval_recovered ) {
            return;
        }
        // We are shutting down, so $error is really the last (fatal) error.
        $error = error_get_last();
        $this->console_log( $this->decorate( __( 'Nzymes SHUTDOWN ERROR' ),
            sprintf( __( '%1$s: %2$s on line %3$s.' ), Ando_ErrorFactory::to_str( $error['type'] ), $error['message'],
                $error['line'] ) ) );
    }

    /**
     * Handle an error in eval.
     *
     * @param int    $type
     * @param string $message
     * @param string $file
     * @param int    $line
     * @param array  $context
     *
     * @return bool
     */
    public
    function set_last_eval_error( $type = null, $message = null, $file = null, $line = null, $context = null ) {
        $this->last_eval_error = compact( 'type', 'message', 'file', 'line', 'context' );

        return true;  // True to consider the error handled and suppress bubbling.
    }

    /**
     * Check the syntax of a code snippet.
     *
     * @param $code
     *
     * @return mixed|null|string
     */
    protected
    function php_lint( $code ) {
        $result = null;
        if ( ! function_exists( 'shell_exec' ) ) {
            return $result;
        }
        $temp     = tmpfile();
        $meta     = stream_get_meta_data( $temp );
        $filename = $meta['uri'];
        fwrite( $temp, "<?php $code" );
        $result = shell_exec( "php -n -l $filename" );  // -n = no ini, -l = only lint
        fclose( $temp );

        $result = trim( $result );
        $result = str_replace( "in $filename on", 'on', $result );
        $result = str_replace( "\nErrors parsing $filename", '', $result );

        return $result;
    }


    /**
     * Evaluate code, putting arguments in the execution context ($this is always available).
     * Return an indexed array with the PHP returned value and possible error.
     *
     * Inside the code, the arguments can easily be accessed with an expression like this:
     *   list($some, $variables) = $arguments;
     *
     * @param string $code
     * @param array  $arguments
     *
     * @return array
     */
    protected
    function clean_eval( $code, array $arguments = array() ) {
        if ( ! is_string( $code ) ) {
            return array( null, 'Code to execute must be a string: ' . gettype( $code ) . ' given.', '' );
        }
        $code = trim( $code );
        if ( empty( $code ) ) {
            return array( null, 'No code to execute.', '' );
        }
        $this->evaluating_code = $code;

        $previous_ini = array();
        if ( function_exists( 'xdebug_is_enabled' ) && xdebug_is_enabled() ) {
            $previous_ini['xdebug.scream'] = ini_set( 'xdebug.scream', false );
        }
        $previous_ini['scream.enabled'] = ini_set( 'scream.enabled', false );
        set_error_handler( array( $this, 'set_last_eval_error' ) );
        ob_start();
        $this->has_eval_recovered = false;
        $this->last_eval_error    = null;
        // -------------------------------------------------------------------------------------------------------------
        try {
            $result = eval( $this->evaluating_code );
            $error  = $this->last_eval_error;
        } catch ( Exception $e ) {
            $result = false;  // Let's force the same error treatment
            $error  = $e;     // and take the exception as the error.
        } catch ( Throwable $t ) {
            $result = false;  // Let's force the same error treatment
            $error  = $t;     // and take the throwable as the error.
        }
        // -------------------------------------------------------------------------------------------------------------
        $this->last_eval_error    = null;
        $this->has_eval_recovered = true;
        $output                   = ob_get_clean();
        restore_error_handler();
        foreach ( $previous_ini as $setting => $value ) {
            ini_set( $setting, $value );
        }

        if ( false === $result ) {
            if ( ! $error instanceof Exception ) {
                // In VVV the error info is captured into $output, but not so in my remote site. Why?
                $error = $this->php_lint( $this->evaluating_code );
            }
        } else {
            $this->evaluating_code = null;
        }

        // Note that $error can be true, array, or exception.
        return array( $result, $error, $output );
    }

    /**
     * Get the matched post object.
     *
     * @param array $matches
     *
     * @return null|WP_Post
     */
    protected
    function wp_post( array $matches ) {
        $post = $this->value( $matches, 'post' );
        $slug = $this->value( $matches, 'slug' );
        switch ( true ) {
            case ( $post == '' ):
                $result = $this->injection_post;
                break;
            case ( $post[0] == '@' && $post[1] == '@' ):
                $post_id = $this->post_id_from_slug( $slug, array() );
                if ( is_null( $post_id )
                    && has_filter( '__nzymes__missing_post' )
                    && $this->injection_author_can( Nzymes_Capabilities::create_dynamic_custom_fields ) ) {
                    // __nzymes__missing_post filters take a slug and return a WP_Post or null.
                    $result = apply_filters( '__nzymes__missing_post', $slug );
                } else {
                    $result = null;
                }
                break;
            case ( $post[0] == '@' ):
                $post_id = $this->post_id_from_slug( $slug );
                if (is_null( $post_id )) {
                    $result = null;
                } else {
                    $result = get_post( $post_id );
                }
                break;
            case ( is_numeric( $post ) ):
                $result = get_post( $post );
                break;
            default:
                $result = null;
                break;
        }
        if ( ! is_null( $result ) ) {
            $is_usable = 'publish' == $result->post_status || $this->injection_author_owns( $result );
            if ( ! $is_usable ) {
                $result = null;
            }
        }

        return $result;
    }

    /**
     * Get the id of the post corresponding to the slug.
     *
     * @param string $slug
     * @param array $post_types  Array of post types to restrict lookup to. Types are searched in the same order.
     *
     * @return null|integer
     */
    protected
    function post_id_from_slug( $slug, $post_types = array( 'page', 'post' ) ) {
        if (empty($post_types)) {
            $post_types = array('');
        }
        // __nzymes__post_types filters take and return an array of post types to restrict lookup of slugs to.
        $post_types = apply_filters( '__nzymes__post_types', $post_types );
        $post_types = array_map('esc_sql', $post_types );
        $types = implode("', '", $post_types);
        $order = implode(',', $post_types);  // No spaces around commas here, please!
        global $wpdb;
        $result = $wpdb->get_var( "
                    SELECT `ID` 
                    FROM $wpdb->posts 
                    WHERE `post_type` IN ('$types') 
                      AND `post_name` = '$slug'
                    ORDER BY FIND_IN_SET(`post_type`, '$order'), `ID` 
                    LIMIT 1
                " );
        return $result;
    }

    /**
     * Unwrap an enzyme from its quotes, while also un-escaping escaped quotes.
     *
     * @param string $string
     *
     * @return mixed|string
     */
    protected
    function unquote( $string ) {
        $result = substr( $string, 1, - 1 );  // unwrap from quotes
        $result = str_replace( '\\=', '=', $result );  // revert escaped quotes
        return $result;
    }

    /**
     * Get the matched custom field from the post object.
     *
     * @param WP_Post $post_object
     * @param array   $matches
     *
     * @return mixed
     */
    protected
    function wp_post_field( $post_object, array $matches ) {
        $field  = $this->value( $matches, 'field' );
        $string = $this->value( $matches, 'string' );
        if ( $string ) {
            $field = $this->unquote( $field );
        }
        $values = get_post_meta( $post_object->ID, $field );
        $result = count( $values ) == 1
            ? $values[0]
            : ( count( $values ) == 0
                ? null
                : $values );

        return $result;
    }

    /**
     * Get the matched attribute from the post object.
     *
     * @param WP_Post $post_object
     * @param array   $matches
     *
     * @return mixed
     */
    protected
    function wp_post_attribute( $post_object, array $matches ) {
        $field  = $this->value( $matches, 'field' );
        $string = $this->value( $matches, 'string' );
        if ( $string ) {
            $field = $this->unquote( $field );
        }
        $result = @$post_object->$field;

        return $result;
    }

    /**
     * Get the matched custom field from the user object.
     *
     * @param WP_User $user_object
     * @param array   $matches
     *
     * @return mixed
     */
    protected
    function wp_user_field( $user_object, array $matches ) {
        $field  = $this->value( $matches, 'field' );
        $string = $this->value( $matches, 'string' );
        if ( $string ) {
            $field = $this->unquote( $field );
        }
        $values = get_user_meta( $user_object->ID, $field );
        $result = count( $values ) == 1
            ? $values[0]
            : ( count( $values ) == 0
                ? null
                : $values );

        return $result;
    }

    /**
     * Get the matched attribute from the user object.
     *
     * @param WP_User $user_object
     * @param array   $matches
     *
     * @return mixed
     */
    protected
    function wp_user_attribute( $user_object, array $matches ) {
        $field  = $this->value( $matches, 'field' );
        $string = $this->value( $matches, 'string' );
        if ( $string ) {
            $field = $this->unquote( $field );
        }
        $result = @$user_object->$field;

        return $result;
    }

    /**
     * Get the author of the post.
     *
     * @param WP_Post $post_object
     *
     * @return WP_User
     */
    protected
    function wp_author( $post_object ) {
        $id     = $post_object->post_author;
        $result = get_user_by( 'id', $id );

        return $result;
    }

    /**
     * True if the current post's author can exercise the capability.
     *
     * @param string $capability
     *
     * @return bool
     */
    protected
    function injection_author_can( $capability ) {
        if ( is_null( $this->injection_post ) ) {
            $result = user_can( self::NO_POST_AUTHOR, $capability );
        } else {
            $result = author_can( $this->injection_post, $capability );
        }

        return $result;
    }

    /**
     * True if the post belongs to the current post's author.
     *
     * @param WP_Post $post_object
     *
     * @return bool
     */
    protected
    function injection_author_owns( $post_object ) {
        if ( is_null( $this->injection_post ) ) {
            $result = self::NO_POST_AUTHOR == $post_object->post_author;
        } else {
            $result = $this->injection_post->post_author == $post_object->post_author;
        }

        return $result;
    }

    /**
     * Execute code according to authors capabilities.
     *
     * @param string  $code
     * @param array   $arguments
     * @param WP_Post $post_object
     *
     * @return null
     */
    protected
    function execute_code( $code, $arguments, $post_object ) {
        if ( author_can( $post_object, Nzymes_Capabilities::create_dynamic_custom_fields ) &&
             ( $this->injection_author_owns( $post_object ) ||
               author_can( $post_object, Nzymes_Capabilities::share_dynamic_custom_fields ) &&
               $this->injection_author_can( Nzymes_Capabilities::use_others_custom_fields ) )
        ) {
            $this->origin_post = $post_object;
            list( $result, $error, $output ) = $this->clean_eval( $code, $arguments );
            if ( $error ) {
                $this->console_log( $this->decorate( __( 'Nzymes ERROR' ), $error ) );
                $this->evaluating_code = null;  // Log the code only once.
                $result                = null;
            }
            if ( $output ) {
                $this->console_log( $this->decorate( __( 'Nzymes OUTPUT' ), $output ) );
            }
        } else {
            $result = null;
        }

        return $result;
    }

    /**
     * Execute a custom field from a post.
     *
     * @param string  $post_item
     * @param integer $num_args
     *
     * @return mixed
     * @throws Ando_Exception
     */
    protected
    function execute_post_item( $post_item, $num_args ) {
        // match again to be able to access groups by name...
        preg_match( $this->grammar_rule( 'post_item' ), $post_item, $matches );
        $post_object = $this->wp_post( $matches );
        if ( ! $post_object instanceof WP_Post ) {
            return null;
        }
        $code      = $this->wp_post_field( $post_object, $matches );
        $arguments = $num_args > 0
            ? $this->catalyzed->pop( $num_args )
            : array();
        $result    = $this->execute_code( $code, $arguments, $post_object );

        return $result;
    }

    /**
     * Execute a custom field from a user.
     *
     * @param string  $author_item
     * @param integer $num_args
     *
     * @return mixed
     * @throws Ando_Exception
     */
    protected
    function execute_author_item( $author_item, $num_args ) {
        preg_match( $this->grammar_rule( 'author_item' ), $author_item, $matches );
        $post_object = $this->wp_post( $matches );
        if ( ! $post_object instanceof WP_Post ) {
            return null;
        }
        $user_object = $this->wp_author( $post_object );
        $code        = $this->wp_user_field( $user_object, $matches );
        $arguments   = $num_args > 0
            ? $this->catalyzed->pop( $num_args )
            : array();
        $result      = $this->execute_code( $code, $arguments, $post_object );

        return $result;
    }

    /**
     * Spy on the current filters for the given tag. Used for debugging.
     *
     * @param string $tag
     *
     * @return array
     */
    protected
    function current_filters( $tag ) {
        $result = array();
        global $wp_filter;
        $the_content = $wp_filter[ $tag ];
        foreach ( $the_content as $pri => $han ) {
            $result[ $pri ] = array_keys( $han );
        }

        return $result;
    }

    /**
     * Attach a handler for absorbing later at a certain priority.
     *
     * @param string $tag
     * @param int    $priority
     *
     * @return void
     */
    public
    function absorb_later( $tag, $priority ) {
        if ( empty( $tag ) ) {
            return;
        }
        if ( isset( $this->attached_handlers["absorb $tag $priority"] ) ) {
            return;
        }
        $this->attached_handlers["absorb $tag $priority"] = array( $this, 'absorb' );

        add_filter( $tag, $this->attached_handlers["absorb $tag $priority"], $priority, 2 );
    }

    /**
     * Attach a handler for unescaping later at a certain priority.
     *
     * @param string $tag
     * @param int    $priority
     *
     * @return void
     */
    public
    function unescape_later( $tag, $priority ) {
        if ( empty( $tag ) ) {
            return;
        }
        if ( isset( $this->attached_handlers["unescape $tag $priority"] ) ) {
            return;
        }
        $this->attached_handlers["unescape $tag $priority"] = array( $this, 'unescape' );

        add_filter( $tag, $this->attached_handlers["unescape $tag $priority"], $priority, 1 );
    }

    /**
     * Execute the matched enzyme.
     *
     * @param string $execution
     *
     * @return array|null
     */
    protected
    function do_execution( $execution ) {
        $this->current_enzyme = $execution;
        preg_match( $this->grammar_rule( 'execution' ), $execution, $matches );
        $post_item   = $this->value( $matches, 'post_item' );
        $author_item = $this->value( $matches, 'author_item' );
        $num_args    = (int) $this->value( $matches, 'num_args' );
        $result      = null;
        switch ( true ) {
            case ( strpos( $execution, 'array(' ) === 0 && $num_args > 0 ):
                $result = $this->catalyzed->pop( $num_args );
                break;
            case ( strpos( $execution, 'assoc(' ) === 0 && $num_args > 0 ):
                $result    = array();
                $arguments = $this->catalyzed->pop( 2 * $num_args );
                for ( $i = 0, $i_top = 2 * $num_args; $i < $i_top; $i += 2 ) {
                    $key            = $arguments[ $i ];
                    $value          = $arguments[ $i + 1 ];
                    $result[ $key ] = $value;
                }
                break;
            case ( strpos( $execution, 'defer(' ) === 0 ):
                $priority = $num_args;
                if ( $this->current_priority < $priority ) {
                    $this->absorb_later( $this->current_filter, $priority );
                    $this->reject_injection = true;
                }
                break;
            case ( $post_item != '' ):
                $result = $this->execute_post_item( $post_item, $num_args );
                break;
            case ( $author_item != '' ):
                $result = $this->execute_author_item( $author_item, $num_args );
                break;
            default:
                break;
        }

        return $result;
    }

    /**
     * Transclude code according to authors capabilities.
     *
     * @param string  $code
     * @param WP_Post $post_object
     *
     * @return string
     */
    protected
    function transclude_code( $code, $post_object ) {
        if ( author_can( $post_object, Nzymes_Capabilities::create_static_custom_fields ) &&
             ( $this->injection_author_owns( $post_object ) ||
               author_can( $post_object, Nzymes_Capabilities::share_static_custom_fields ) &&
               $this->injection_author_can( Nzymes_Capabilities::use_others_custom_fields ) )
        ) {
            $result = $code;
        } else {
            $result = '';
        }

        return $result;
    }

    /**
     * Transclude a custom field from a post.
     *
     * @param string  $post_item
     * @param WP_Post $post_object
     *
     * @return string
     * @throws Ando_Exception
     */
    protected
    function transclude_post_item( $post_item, $post_object ) {
        preg_match( $this->grammar_rule( 'post_item' ), $post_item, $matches );
        $code   = $this->wp_post_field( $post_object, $matches );
        $result = $this->transclude_code( $code, $post_object );

        return $result;
    }

    /**
     * Transclude a custom field from a user.
     *
     * @param string  $author_item
     * @param WP_Post $post_object
     *
     * @return string
     * @throws Ando_Exception
     */
    protected
    function transclude_author_item( $author_item, $post_object ) {
        preg_match( $this->grammar_rule( 'author_item' ), $author_item, $matches );
        $user_object = $this->wp_author( $post_object );
        $code        = $this->wp_user_field( $user_object, $matches );
        $result      = $this->transclude_code( $code, $post_object );

        return $result;
    }

    /**
     * Transclude an attribute from a post.
     *
     * @param string  $post_attr
     * @param WP_Post $post_object
     *
     * @return mixed
     * @throws Ando_Exception
     */
    protected
    function transclude_post_attr( $post_attr, $post_object ) {
        $same_author = $this->injection_author_owns( $post_object );
        if ( $same_author && author_can( $post_object, Nzymes_Capabilities::use_own_attributes ) ||
             ! $same_author &&
             $this->injection_author_can( Nzymes_Capabilities::use_others_attributes )
        ) {
            preg_match( $this->grammar_rule( 'post_attr' ), $post_attr, $matches );
            $result = $this->wp_post_attribute( $post_object, $matches );
        } else {
            $result = null;
        }

        return $result;
    }

    /**
     * Transclude an attribute from a user.
     *
     * @param string  $author_attr
     * @param WP_Post $post_object
     *
     * @return mixed
     * @throws Ando_Exception
     */
    protected
    function transclude_author_attr( $author_attr, $post_object ) {
        $same_author = $this->injection_author_owns( $post_object );
        if ( $same_author && author_can( $post_object, Nzymes_Capabilities::use_own_attributes ) ||
             ! $same_author &&
             $this->injection_author_can( Nzymes_Capabilities::use_others_attributes )
        ) {
            preg_match( $this->grammar_rule( 'author_attr' ), $author_attr, $matches );
            $user_object = $this->wp_author( $post_object );
            $result      = $this->wp_user_attribute( $user_object, $matches );
        } else {
            $result = null;
        }

        return $result;
    }

    /**
     * Transclude the matched enzyme.
     *
     * @param string $transclusion
     *
     * @return null|string
     */
    protected
    function do_transclusion( $transclusion ) {
        $this->current_enzyme = $transclusion;
        preg_match( $this->grammar_rule( 'transclusion' ), $transclusion, $matches );
        $post_item   = $this->value( $matches, 'post_item' );
        $post_attr   = $this->value( $matches, 'post_attr' );
        $author_item = $this->value( $matches, 'author_item' );
        $author_attr = $this->value( $matches, 'author_attr' );
        $post_object = $this->wp_post( $matches );
        if ( ! $post_object instanceof WP_Post ) {
            return null;
        }
        switch ( true ) {
            case ( $post_item != '' ):
                $result = $this->transclude_post_item( $post_item, $post_object );
                break;
            case ( $post_attr != '' ):
                $result = $this->transclude_post_attr( $post_attr, $post_object );
                break;
            case ( $author_item != '' ):
                $result = $this->transclude_author_item( $author_item, $post_object );
                break;
            case ( $author_attr != '' ):
                $result = $this->transclude_author_attr( $author_attr, $post_object );
                break;
            default:
                $result = null;
                break;
        }

        return $result;
    }

    /**
     * Remove white space from the matched sequence.
     *
     * @param array $matches
     *
     * @return string
     */
    protected
    function strip_blanks( array $matches ) {
        $before_string = $this->value( $matches, 'before_string' );
        $string        = $this->value( $matches, 'string' );
        $anything_else = $this->value( $matches, 'anything_else' );
        $outside       = $string
            ? $before_string
            : $anything_else;
        $result        = preg_replace( $this->e_all_spaces, '', $outside ) .
                         preg_replace( $this->e_unbreakable_space, ' ',
                             $string );  // normal spaces are meaningful in $string
        return $result;
    }

    /**
     * Remove noise from a sequence.
     *
     * @param string $sequence
     *
     * @return string
     */
    protected
    function clean_up( $sequence ) {
        $result = $sequence;

        // erase comments
        $result = preg_replace( $this->e_comment, '', $result );

        // erase blanks (except inside strings)
        $result = preg_replace_callback( $this->e_string, array( $this, 'strip_blanks' ), $result );

        // erase backslashes from escaped injection delimiters
        $result = preg_replace( $this->e_escaped_injection_delimiter, '$1', $result );

        // erase WordPress HTML tags
        $result = strip_tags( $result );

        return $result;
    }

    /**
     * Process the enzymes in the matched sequence.
     *
     * @param string $could_be_sequence
     *
     * @return array|null|string
     */
    public
    function catalyze( $could_be_sequence ) {
        $sequence                       = $this->clean_up( $could_be_sequence );
        $there_are_only_chained_enzymes = preg_match( $this->e_sequence_valid, '|' . $sequence );
        if ( ! $there_are_only_chained_enzymes ) {
            $result = '{[' . $could_be_sequence . ']}';  // skip this injection AS IS
        } else {
            $this->current_injection = "{[$could_be_sequence]}";
            $this->catalyzed         = new Nzymes_Stack();
            $rest                    = $sequence;
            while ( ! $this->reject_injection && preg_match( $this->e_sequence_start, $rest, $matches ) ) {
                $execution    = $this->value( $matches, 'execution' );
                $transclusion = $this->value( $matches, 'transclusion' );
                $literal      = $this->value( $matches, 'literal' );
                $str_literal  = $this->value( $matches, 'str_literal' );
                $integer      = $this->value( $matches, 'integer' );
                $rest         = $this->value( $matches, 'rest' );
                switch ( true ) {
                    case $execution != '':
                        $argument = $this->do_execution( $execution );
                        break;
                    case $transclusion != '':
                        $argument = $this->do_transclusion( $transclusion );
                        break;
                    case $literal != '':
                        $argument = $str_literal
                            ? $this->unquote( $str_literal )
                            : intval( $integer );
                        break;
                    default:
                        $argument = null;
                        break;
                }
                $this->catalyzed->push( $argument );
            }
            list( $result ) = $this->catalyzed->peek();
        }

        return $result;
    }

    /**
     * True if there is an injected sequence.
     *
     * @param string $content
     * @param array  $matches
     *
     * @return bool
     */
    protected
    function there_is_an_injection( $content, &$matches ) {
        $result = false !== strpos( $content, '{[' ) && preg_match( $this->e_injection, $content, $matches );

        return $result;
    }

    /**
     * Get the post the injection belongs to.
     * It can be null when forced to NO_POST.
     *
     * @param int|WP_Post $post_id
     *
     * @return mixed
     */
    protected
    function get_injection_post( $post_id ) {
        if ( $post_id instanceof WP_Post ) {
            return $post_id;
        }
        if ( $post_id == self::NO_POST ) {
            return null;
        }
        $post = get_post( $post_id );
        if ( is_null( $post ) ) {
            // Consider this an error, because the developer didn't force no post.
            return false;
        }

        return $post;
    }

    /**
     * Unescape escaped injections, so the viewer can see them as they are meant to be.
     *
     * @param string $content
     *
     * @return string
     */
    public
    function unescape( $content ) {
        $result = str_replace( '{' . self::ESCAPE_CHAR . '[', '{[', $content );

        return $result;
    }

    /**
     * Process all the injections in the $content, not necessarily in the context of apply_filters().
     *
     * @param                  $content
     * @param null|int|WP_Post $post_id  Null means Nzymes_Engine::GLOBAL_POST.
     * @param null|string      $filter   Null means Nzymes_Engine::DIRECT_FILTER.
     * @param null|int         $priority Null means Nzymes_Plugin::PRIORITY.
     *
     * @return mixed
     */
    public
    function process( $content, $post_id = null, $filter = null, $priority = null ) {
        $post_id  = ! is_null( $post_id )
            ? $post_id
            : self::GLOBAL_POST;
        $filter   = ! is_null( $filter )
            ? strval( $filter )
            : self::DIRECT_FILTER;
        $priority = ! is_null( $priority )
            ? intval( $priority )
            : Nzymes_Plugin::PRIORITY;

        $this->absorb_later( $filter, $priority );
        $result = null;
        if ( self::DIRECT_FILTER == $filter ) {
            $result = apply_filters( $filter, $content, $post_id );
        }

        return $result;
    }

    /**
     * True if the plugin can process the injection post, according to site options.
     *
     * @return bool
     */
    public
    function plugin_can_process_injection_post() {
        if (empty($this->options)) {
            $options = Nzymes_Plugin::$options->get();
            $this->options['process-posts-after'] = DateTime::createFromFormat(DATE_ATOM, $options['process-posts-after']);
            $this->options['process-also-posts'] = $options['process-also-posts'];
        }

        $result = false;
        $post_time = new DateTime($this->injection_post->post_date);
        if ($post_time >= $this->options['process-posts-after']) {
            $result = true;
        } elseif (in_array($this->injection_post->ID, $this->options['process-also-posts'])) {
            $result = true;
        }
        if ($result) {
            global $enzymes;  // Enzymes plugin
            $enzymes_priority = has_filter($this->current_filter, array( &$enzymes, 'metabolism' ));
            if ($enzymes_priority && $this->current_priority <= $enzymes_priority) {
                remove_filter($this->current_filter, array( &$enzymes, 'metabolism' ), $enzymes_priority);
            }
        }

        return $result;
    }

    /**
     * Process all the injections applied to the $content, always in the context of apply_filters().
     *
     * @param string      $content
     * @param int|WP_Post $post_id
     *
     * @return string
     */
    public
    function absorb( $content, $post_id = self::GLOBAL_POST ) {
        if ( ! doing_filter() ) {
            $this->debug_print('absorb exit: not in a filter');
            return $content;
        }
        $this->current_filter   = current_filter();
        global $wp_filter;
        $this->current_priority = $wp_filter[ $this->current_filter ]->current_priority();
        if ( 'wp_title' == $this->current_filter ) {
            $post_id = null;  // 'wp_title' pass a string separator into $post_id
        }
        $this->injection_post = $this->get_injection_post( $post_id );
        if ( false === $this->injection_post ) {
            $this->debug_print('absorb exit: no post');
            return $content;
        }
        if ( ! $this->plugin_can_process_injection_post() ) {
            $this->debug_print('absorb exit: post excluded from Nzymes');
            return $content;
        }
        if ( ! $this->injection_author_can( Nzymes_Capabilities::inject ) ) {
            $this->debug_print('absorb exit: not authorized');
            return $content;
        }
        if ( ! $this->there_is_an_injection( $content, $matches ) ) {
            $this->debug_print('absorb exit: no injection');
            return $content;
        }
        $this->intra              = new stdClass();
        $this->content            = $content;
        $this->new_content        = '';
        $this->continue_injecting = true;
        do {
            $before            = $this->value( $matches, 'before' );
            $could_be_sequence = $this->value( $matches, 'could_be_sequence' );
            $after             = $this->value( $matches, 'after' );
            $this->new_content .= $before;

            $was_escaped = '{' == substr( $before, - 1 );  // True if it was "{{[..]}".
            if ( $was_escaped ) {  // keep always escaped from Nzymes, even in case of later runs due to deferrals
                $result = self::ESCAPE_CHAR . "[$could_be_sequence]}";  // It will be "{-[..]}".
                $this->unescape_later( $this->current_filter, self::UNESCAPE_PRIORITY );
            } else {
                $this->reject_injection = false;
                $result                 = $this->catalyze( $could_be_sequence );
                if ( $this->reject_injection ) {  // defer is being taken into account
                    $result = "{[$could_be_sequence]}";  // no need to escape from Enzymes because it was removed by
                                                         // plugin_can_process_injection_post
                }
            }
            $this->new_content .= $result;
        } while ( $this->continue_injecting && $this->there_is_an_injection( $after, $matches ) );
        $result      = $this->new_content . $after;
        $this->intra = null;

        return $result;
    }

    /**
     * Cleanly get a default for undefined keys and the set value otherwise.
     *
     * Prefixing the error suppression operator (@) accomplishes the same result,
     * but I wanted to get rid of all these notices still present while debugging.
     *
     * @param array  $matches
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    protected
    function value( $matches, $key, $default = null ) {
        return isset( $matches[ $key ] )
            ? $matches[ $key ]
            : $default;
    }

    // -----------------------------------------------------------------------------------------------------------------

    /**
     * @var bool
     */
    public $debug_on = false;

    /**
     * @param mixed $something
     */
    public
    function debug_print( $something = '(debug_print)' ) {
        if ( ! $this->debug_on ) {
            return;
        }
        fwrite( STDERR, "\n" . print_r( $something, true ) . "\n" );
    }
}
