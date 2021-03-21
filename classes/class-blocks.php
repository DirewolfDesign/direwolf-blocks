<?php
/**
 * DirewolfBlocks blocks.
 *
 * @package direwolfblocks
 */

if( ! defined( 'ABSPATH' ) ) wp_die( 'End of Line, Man' );


/**
 * DirewolfBlocks_Blocks class. Class to work with DireowlfBlocks CPT.
 */
class DirewolfBlocks_Blocks {
    /**
     * Handlebars engine.
     *
     * @var null|object
     */
    private $handlebars = null;

    /**
     * Rules to sanitize SVG
     *
     * @var array
     */
    private $kses_svg = array(
        'svg'   => array(
            'class'           => true,
            'aria-hidden'     => true,
            'aria-labelledby' => true,
            'role'            => true,
            'xmlns'           => true,
            'width'           => true,
            'height'          => true,
            'viewbox'         => true,   // <= Must be lower case!
        ),
        'g'     => array( 'fill' => true ),
        'title' => array( 'title' => true ),
        'path'  => array(
            'd'    => true,
            'fill' => true,
        ),
        'rect'  => array(
            'fill'      => true,
            'opacity'   => true,
            'width'     => true,
            'height'    => true,
            'rx'        => true,
            'transform' => true,
        ),
    );

    /**
     * direwolfblocks_Blocks constructor.
     */
    public function __construct() {
        // add_action( 'init', array( $this, 'prepare_handlebars' ) );

        add_action( 'init', array( $this, 'register_post_type' ) );

        add_action( 'init', array( $this, 'remove_custom_fields_support' ), 150 );

        add_filter( 'allowed_block_types', array( $this, 'allowed_block_types' ), 10, 2 );

        // custom post roles.
        add_action( 'admin_init', array( $this, 'add_role_caps' ) );

        // additional elements in blocks list table.
        add_filter( 'disable_months_dropdown', array( $this, 'disable_months_dropdown' ), 10, 2 );
        add_filter( 'post_row_actions', array( $this, 'post_row_actions' ), 10, 2 );
        add_filter( 'bulk_actions-edit-direwolfblocks', array( $this, 'bulk_actions_edit' ) );
        add_filter( 'handle_bulk_actions-edit-direwolfblocks', array( $this, 'handle_bulk_actions_edit' ), 10, 3 );
        add_filter( 'manage_direwolfblocks_posts_columns', array( $this, 'manage_posts_columns' ) );
        add_filter( 'manage_direwolfblocks_posts_custom_column', array( $this, 'manage_posts_custom_column' ), 10, 2 );

        // add meta.
        add_action( 'init', array( $this, 'register_block_meta' ) );

        // add gutenberg blocks assets.
        if ( function_exists( 'register_block_type' ) ) {
            // add custom block categories.
            add_filter( 'block_categories', array( $this, 'block_categories' ), 100 );

            add_action( 'enqueue_block_editor_assets', array( $this, 'register_block' ) );
            add_action( 'init', array( $this, 'register_block_render' ), 20 );
        }
    }

    /**
     * Handlebars php.
     */
    public function prepare_handlebars() {
        // var_dump( class_exists( '\Handlebars\Autoloader' ) );

        // if( ! class_exists( 'Handlebars' ) ) {
            // require_once dwb()->plugin_path() . 'vendor/Handlebars/Autoloader.php';
            Handlebars\Autoloader::register();
        // }

        $this->handlebars = new Handlebars\Handlebars();

        // phpcs:ignore
        // truncate
        // {{truncate 'string' 2 'true'}}.
        $this->handlebars->registerHelper(
            'truncate',
            function( $str, $len, $ellipsis = 'true' ) {
                if ( $str && $len && mb_strlen( $str, 'UTF-8' ) > $len ) {
                    $new_str = mb_substr( $str, 0, $len + 1, 'UTF-8' );
                    $count   = mb_strlen( $new_str, 'UTF-8' );

                    while ( $count > 0 ) {
                        $ch      = mb_substr( $new_str, -1, null, 'UTF-8' );
                        $new_str = mb_substr( $new_str, 0, -1, 'UTF-8' );

                        $count--;

                        if ( ' ' === $ch ) {
                            break;
                        }
                    }

                    if ( '' === $new_str ) {
                        $new_str = mb_substr( $str, 0, $len, 'UTF-8' );
                    }

                    return new \Handlebars\SafeString( $new_str . ( 'true' === $ellipsis ? '...' : '' ) );
                }
                return $str;
            }
        );

        // compare.
        // {{#compare 1 '===' 2}} Show if true {{/compare}}
        // slightly changed https://gist.github.com/doginthehat/1890659.
        $this->handlebars->registerHelper(
            'compare',
            function( $lvalue, $operator, $rvalue = null, $options = null ) {
                if ( null === $rvalue ) {
                    return $options['inverse']();
                }

                if ( null === $options ) {
                    $options  = $rvalue;
                    $rvalue   = $operator;
                    $operator = '===';
                }

                $result = false;

                switch ( $operator ) {
                    case '==':
                        // phpcs:ignore
                        $result = $lvalue == $rvalue;
                        break;
                    case '===':
                        $result = $lvalue === $rvalue;
                        break;
                    case '!=':
                        // phpcs:ignore
                        $result = $lvalue != $rvalue;
                        break;
                    case '!==':
                        $result = $lvalue !== $rvalue;
                        break;
                    case '<':
                        $result = $lvalue < $rvalue;
                        break;
                    case '>':
                        $result = $lvalue > $rvalue;
                        break;
                    case '<=':
                        $result = $lvalue <= $rvalue;
                        break;
                    case '>=':
                        $result = $lvalue >= $rvalue;
                        break;
                    case '&&':
                        $result = $lvalue && $rvalue;
                        break;
                    case '||':
                        $result = $lvalue || $rvalue;
                        break;
                    case 'typeof':
                        $result = gettype( $lvalue ) === $rvalue;
                        break;
                }

                if ( $result ) {
                    return $options['fn']();
                }

                return $options['inverse']();
            }
        );

        // math.
        // {{math 1 '+' 2}}
        // https://stackoverflow.com/questions/33059203/error-missing-helper-in-handlebars-js/46317662#46317662.
        $this->handlebars->registerHelper(
            'math',
            function( $lvalue, $operator, $rvalue ) {
                $result = '';

                switch ( $operator ) {
                    case '+':
                        $result = $lvalue + $rvalue;
                        break;
                    case '-':
                        $result = $lvalue - $rvalue;
                        break;
                    case '*':
                        $result = $lvalue * $rvalue;
                        break;
                    case '/':
                        $result = $lvalue / $rvalue;
                        break;
                    case '%':
                        $result = $lvalue % $rvalue;
                        break;
                }

                return $result;
            }
        );

        // phpcs:ignore
        // do_shortcode.
        // {{{do_shortcode 'my_shortcode' this}}}.
        $this->handlebars->registerHelper(
            'do_shortcode',
            function( $shortcode_name, $attributes ) {
                $result = '[' . $shortcode_name;

                // prepare attributes.
                if ( isset( $attributes ) && ! empty( $attributes ) ) {
                    foreach ( $attributes as $name => $val ) {
                        if (
                        'content' === $name
                        || 'direwolfblock_code_frontend_html' === $name
                        || 'direwolfblock_code_backend_html' === $name
                        || 'data' === $name
                        || 'hash' === $name
                        ) {
                            continue;
                        }

                        if ( is_array( $val ) ) {
                            $val = wp_json_encode( $val );
                        }

                        if (
                        ! is_numeric( $val )
                        && ! is_string( $val )
                        && ! is_bool( $val )
                        ) {
                            continue;
                        }

                        if ( is_bool( $val ) ) {
                            $val = $val ? '1' : '0';
                        }

                        $result .= ' ' . esc_attr( $name ) . '="' . esc_attr( $val ) . '"';
                    }

                    // content.
                    if ( isset( $attributes['content'] ) ) {
                        $result .= ']' . $attributes['content'] . '[/' . $shortcode_name;
                    }
                }

                $result .= ']';

                return do_shortcode( $result );
            }
        );

        // phpcs:ignore
        // date_i18n.
        // {{date_i18n 'F j, Y H:i' '2018-09-16 15:35'}}.
        $this->handlebars->registerHelper(
            'date_i18n',
            function( $format, $time ) {
                return date_i18n( $format, strtotime( $time ) );
            }
        );

        // phpcs:ignore
        // var_dump.
        // {{var_dump 'test'}}.
        $this->handlebars->registerHelper(
            'var_dump',
            function( $val ) {
                ob_start();
                // phpcs:ignore
                var_dump( $val );
                return ob_get_clean();
            }
        );

        // custom action for extending default helpers by 3rd-party.
        do_action( 'dwb/handlebars/object', $this->handlebars );
        do_action( 'dwb_handlebars_object', $this->handlebars );
    }

    /**
     * Register CPT.
     */
    public function register_post_type() {
        register_post_type(
            'direwolfblocks',
            array(
                'labels'            => array(
                    'menu_name'     => __( 'Direwolf Blocks', 'dwb' ),
                    'name'          => __( 'Blocks', 'dwb' ),
                    'all_items'     => __( 'Blocks', 'dwb' ),
                    'singular_name' => __( 'Block', 'dwb' ),
                ),
                'public'            => false,
                'has_archive'       => false,
                'show_ui'           => true,

                // adding to custom menu manually.
                'show_in_menu'      => true,
                'show_in_admin_bar' => true,
                'show_in_rest'      => true,
                // phpcs:ignore
                'menu_icon'         => 'data:image/svg+xml;base64,' . base64_encode( file_get_contents( dwb()->plugin_path() . 'assets/svg/icon-direwolfblocks.svg' ) ),
                'menu_position'     => 80,
                'capabilities'      => array(
                    'edit_post'          => 'edit_direwolfblock',
                    'edit_posts'         => 'edit_direwolfblocks',
                    'edit_others_posts'  => 'edit_other_direwolfblocks',
                    'publish_posts'      => 'publish_direwolfblocks',
                    'read_post'          => 'read_direwolfblock',
                    'read_private_posts' => 'read_private_direwolfblocks',
                    'delete_posts'       => 'delete_direwolfblocks',
                    'delete_post'        => 'delete_direwolfblock',
                ),
                'rewrite'           => true,
                'supports'          => array(
                    'title',
                    'editor',
                    'revisions',
                ),
                'template'          => array(
                    array(
                        'dwb-constructor/main',
                    ),
                ),
                // we can't use template_lock = 'all' since blocks don't insert in some posts.
                // 'template_lock' => 'all',.
            )
        );
    }

    /**
     * Remove custom fields support from direwolf blocks constructor page.
     * Some plugins add support for custom fields to all post types, but we don't need it in our constructor.
     *
     * @link https://github.com/nk-o/dwb/issues/141
     */
    public function remove_custom_fields_support() {
        remove_post_type_support( 'direwolfblocks', 'custom-fields' );
    }

    /**
     * Allowed blocks for direwolfblocks post type.
     *
     * @param array  $allowed_block_types - blocks.
     * @param object $post - post object.
     * @return array
     */
    public function allowed_block_types( $allowed_block_types, $post ) {
        if ( 'direwolfblocks' !== $post->post_type ) return $allowed_block_types;
        return array( 'dwb-constructor/main' );
    }

    /**
     * Add Roles
     */
    public function add_role_caps() {
        global $wp_roles;

        if ( isset( $wp_roles ) ) {
            $wp_roles->add_cap( 'administrator', 'edit_direwolfblock' );
            $wp_roles->add_cap( 'administrator', 'edit_direwolfblocks' );
            $wp_roles->add_cap( 'administrator', 'edit_other_direwolfblocks' );
            $wp_roles->add_cap( 'administrator', 'publish_direwolfblocks' );
            $wp_roles->add_cap( 'administrator', 'read_direwolfblock' );
            $wp_roles->add_cap( 'administrator', 'read_private_direwolfblocks' );
            $wp_roles->add_cap( 'administrator', 'delete_direwolfblocks' );
            $wp_roles->add_cap( 'administrator', 'delete_direwolfblock' );

            $wp_roles->add_cap( 'editor', 'read_direwolfblock' );
            $wp_roles->add_cap( 'editor', 'read_private_direwolfblocks' );

            $wp_roles->add_cap( 'author', 'read_direwolfblock' );
            $wp_roles->add_cap( 'author', 'read_private_direwolfblocks' );

            $wp_roles->add_cap( 'contributor', 'read_direwolfblock' );
            $wp_roles->add_cap( 'contributor', 'read_private_direwolfblocks' );
        }
    }

    /**
     * Disable month dropdown.
     *
     * @param array  $return disabled dropdown or no.
     * @param object $post_type current post type name.
     *
     * @return array
     */
    public function disable_months_dropdown( $return, $post_type ) {
        return 'direwolfblocks' === $post_type ? true : $return;
    }

    /**
     * Add featured image in direwolfblocks list
     *
     * @param array  $actions actions for posts.
     * @param object $post current post data.
     *
     * @return array
     */
    public function post_row_actions( $actions = array(), $post ) {
        if ( 'direwolfblocks' !== $post->post_type ) return $actions;

        // remove quick edit link.
        if ( isset( $actions['inline hide-if-no-js'] ) )
            unset( $actions['inline hide-if-no-js'] );

        // add duplicate and export link.
        $actions = array_merge(
            array_slice( $actions, 0, 1 ),
            array(
                'duplicate' => sprintf(
                    '<a href="%1$s" aria-label="%2$s">%3$s</a>',
                    add_query_arg(
                        array(
                            'direwolfblocks_duplicate_block' => $post->ID,
                            'direwolfblocks_duplicate_block_nonce' => wp_create_nonce( 'dwb-duplicate-block-nonce' ),
                        )
                    ),
                    sprintf(
                        // translators: %1$ - post title.
                        esc_html__( 'Duplicate “%1$s”', 'dwb' ),
                        get_the_title( $post->ID )
                    ),
                    esc_html__( 'Duplicate', 'dwb' )
                ),
                'export' => sprintf(
                    '<a href="%1$s" aria-label="%2$s">%3$s</a>',
                    add_query_arg(
                        array(
                            'direwolfblocks_export_block' => $post->ID,
                        )
                    ),
                    sprintf(
                        // translators: %1$ - post title.
                        esc_html__( 'Export “%1$s”', 'dwb' ),
                        get_the_title( $post->ID )
                    ),
                    esc_html__( 'Export', 'dwb' )
                ),
            ),
            array_slice( $actions, 1 )
        );

        return $actions;
    }

    /**
     * Bulk actions.
     *
     * @param array $actions bulk actions for posts.
     *
     * @return array
     */
    public function bulk_actions_edit( $actions = array() ) {
        unset( $actions['edit'] );

        $actions['export'] = esc_html__( 'Export', 'dwb' );

        return $actions;
    }

    /**
     * Prepare to bulk export blocks.
     *
     * @param string $redirect redirect url after export.
     * @param string $action action name.
     * @param array  $post_ids post ids to export.
     *
     * @return string
     */
    public function handle_bulk_actions_edit( $redirect, $action, $post_ids ) {
        if ( 'export' !== $action ) {
            return $redirect;
        }

        dwb()->tools()->export_json( $post_ids, 'blocks' );

        return $redirect;
    }

    /**
     * Add featured image in direwolfblocks list
     *
     * @param array $columns columns of the table.
     *
     * @return array
     */
    public function manage_posts_columns( $columns = array() ) {
        $columns = array(
            'cb'                          => $columns['cb'],
            'direwolfblocks_post_icon'        => esc_html__( 'Icon', 'dwb' ),
            'title'                       => $columns['title'],
            'direwolfblocks_post_category'    => esc_html__( 'Category', 'dwb' ),
            'direwolfblocks_post_description' => esc_html__( 'Description', 'dwb' ),
        );

        return $columns;
    }

    /**
     * Add thumb to the column
     *
     * @param bool $column_name column name.
     */
    public function manage_posts_custom_column( $column_name = false ) {
        global $post;

        if ( 'direwolfblocks_post_icon' === $column_name ) {
            $icon      = $this->get_meta_value( 'direwolfblocks_icon' );
            $admin_url = get_edit_post_link( $post->ID );

            if ( $icon && strpos( $icon, 'dashicons' ) === 0 ) {
                echo '<a class="dwb-admin-block-icon" href="' . esc_url( $admin_url ) . '"><span class="dashicons ' . esc_attr( $icon ) . '"></span></a>';
            } elseif ( $icon ) {
                echo '<a class="dwb-admin-block-icon" href="' . esc_url( $admin_url ) . '">' . wp_kses( $icon, $this->kses_svg ) . '</a>';
            }
        }

        if ( 'direwolfblocks_post_category' === $column_name ) {
            $category = $this->get_meta_value( 'direwolfblocks_category' );
            if ( $category ) {
                $gutenberg_categories = array();
                if ( function_exists( 'get_block_categories' ) ) {
                    $gutenberg_categories = get_block_categories( $post );
                } elseif ( function_exists( 'gutenberg_get_block_categories' ) ) {
                    $gutenberg_categories = gutenberg_get_block_categories( $post );
                }

                foreach ( $gutenberg_categories as $cat ) {
                    if ( $cat['slug'] === $category ) {
                        $category = $cat['title'];
                        break;
                    }
                }

                echo esc_html( $category );
            }
        }

        if ( 'direwolfblocks_post_description' === $column_name ) {
            $description = $this->get_meta_value( 'direwolfblocks_description' );
            echo esc_html( $description );
        }
    }

    /**
     * Default values of controls.
     *
     * @var array
     */
    private $defaults = array(
        'direwolfblocks_controls'                        => array(),

        'direwolfblocks_slug'                            => '',
        'direwolfblocks_icon'                            => '',
        'direwolfblocks_description'                     => '',
        'direwolfblocks_keywords'                        => '',
        'direwolfblocks_category'                        => 'text',

        'direwolfblocks_code_show_preview'               => 'always',
        'direwolfblocks_code_single_output'              => 'false',
        'direwolfblocks_code_output_method'              => 'html',

        'direwolfblocks_code_editor_html'                => '',
        'direwolfblocks_code_editor_callback'            => '',
        'direwolfblocks_code_editor_css'                 => '',
        'direwolfblocks_code_frontend_html'              => '',
        'direwolfblocks_code_frontend_callback'          => '',
        'direwolfblocks_code_frontend_css'               => '',

        'direwolfblocks_supports_multiple'               => 'true',
        'direwolfblocks_supports_classname'              => 'true',
        'direwolfblocks_supports_anchor'                 => 'false',
        'direwolfblocks_supports_html'                   => 'false',
        'direwolfblocks_supports_inserter'               => 'true',
        'direwolfblocks_supports_align'                  => array( 'wide', 'full' ),

        // Ghost Kit Extensions.
        'direwolfblocks_supports_ghostkit_spacings'      => 'false',
        'direwolfblocks_supports_ghostkit_display'       => 'false',
        'direwolfblocks_supports_ghostkit_scroll_reveal' => 'false',
        'direwolfblocks_supports_ghostkit_frame'         => 'false',
        'direwolfblocks_supports_ghostkit_custom_css'    => 'false',

        'direwolfblocks_condition_post_types'            => '',
    );

    /**
     * Get metabox value by name.
     *
     * @param string      $name - meta name.
     * @param int|boolean $id - post id.
     * @return mixed
     */
    private function get_meta_value( $name, $id = false ) {
        if ( ! $id ) {
            global $post;
            $id = $post->ID;
        }

        $result = get_post_meta( $id, $name, true );

        $default = null;
        if ( isset( $this->defaults[ $name ] ) ) {
            $default = $this->defaults[ $name ];
        }

        if ( '' === $result && null !== $default ) {
            $result = $default;
        }

        if ( 'true' === $result ) {
            $result = true;
        } elseif ( 'false' === $result ) {
            $result = false;
        }

        return $result;
    }

    /**
     * Sanitize block slug name.
     * Keep only alpha and numbers.
     * Make it lowercase.
     *
     * @param string $slug - slug name.
     *
     * @return string
     */
    public function sanitize_slug( $slug ) {
        return strtolower( preg_replace( '/[^a-zA-Z0-9\-]+/', '', $slug ) );
    }

    /**
     * Recursive sanitation for an array
     * Thanks: https://wordpress.stackexchange.com/questions/24736/wordpress-sanitize-array/26465
     *
     * @param array $array - array for sanitize.
     *
     * @return array
     */
    private function sanitize_array( $array ) {
        foreach ( $array as $key => &$value ) {
            if ( is_array( $value ) ) {
                $value = $this->sanitize_array( $value );
            } else {
                if ( 'choices' === $key || 'help' === $key ) {
                    $value = sanitize_textarea_field( $value );
                } else {
                    $value = sanitize_text_field( $value );
                }
            }
        }

        return $array;
    }

    /**
     * Save Format metabox
     *
     * @param int   $post_id The post ID.
     * @param array $data Metaboxes data for save.
     */
    public function save_meta_boxes( $post_id, $data ) {
        foreach ( $this->defaults as $meta => $default ) {
            $new_meta_value = '';

            if ( isset( $data[ $meta ] ) ) {
                // convert boolean to string.
                if ( is_bool( $data[ $meta ] ) ) {
                    $data[ $meta ] = $data[ $meta ] ? 'true' : 'false';
                }

                // icon and editors.
                if (
                    'direwolfblocks_icon' === $meta ||
                    'direwolfblocks_code_editor_html' === $meta ||
                    'direwolfblocks_code_editor_css' === $meta ||
                    'direwolfblocks_code_frontend_html' === $meta ||
                    'direwolfblocks_code_frontend_css' === $meta
                ) {
                    // phpcs:ignore
                    $new_meta_value = wp_slash( $data[ $meta ] );
                } else {
                    // Get the posted data and sanitize it for use as an HTML class.
                    if ( is_array( $data[ $meta ] ) ) {
                        // phpcs:ignore
                        $new_meta_value = $this->sanitize_array( wp_slash( $data[ $meta ] ) );
                    } else {
                        $new_meta_value = sanitize_text_field( wp_slash( $data[ $meta ] ) );
                    }
                }
            }

            // keep only alpha and numbers in slug.
            if ( 'direwolfblocks_slug' === $meta ) {
                $new_meta_value = $this->sanitize_slug( $new_meta_value );

                // generate slug from title.
                if ( ! $new_meta_value ) {
                    $new_meta_value = get_the_title();
                    $new_meta_value = $this->sanitize_slug( $new_meta_value );
                }

                // if no slug available.
                if ( ! $new_meta_value ) {
                    $new_meta_value = 'no-slug';
                }
            }

            /* Get the meta value of the custom field key. */
            $meta_value = get_post_meta( $post_id, $meta, true );

            $meta_value_to_check     = $meta_value;
            $new_meta_value_to_check = $new_meta_value;

            if ( is_array( $meta_value_to_check ) ) {
                $meta_value_to_check = wp_json_encode( $meta_value_to_check );
            }
            if ( is_array( $new_meta_value_to_check ) ) {
                $new_meta_value_to_check = wp_json_encode( $new_meta_value_to_check );
            }

            /* If a new meta value was added and there was no previous value, add it. */
            if ( $new_meta_value_to_check && '' === $meta_value_to_check ) {
                add_post_meta( $post_id, $meta, $new_meta_value, true );

                /* If the new meta value does not match the old value, update it. */
            } elseif ( $new_meta_value_to_check && $new_meta_value_to_check !== $meta_value_to_check ) {
                update_post_meta( $post_id, $meta, $new_meta_value );

                /* If there is no new meta value but an old value exists, delete it. */
            } elseif ( '' === $new_meta_value_to_check && $meta_value_to_check ) {
                delete_post_meta( $post_id, $meta, $meta_value );
            }
        }
    }

    /**
     * Get metabox data
     *
     * @param int $post_id The post ID.
     *
     * @return array|null
     */
    public function get_meta_boxes( $post_id ) {
        $result_meta = array();

        foreach ( $this->defaults as $meta => $default ) {
            $result_meta[ $meta ] = $this->get_meta_value( $meta, $post_id );
        }

        return $result_meta;
    }

    /**
     * Blocks list.
     *
     * @var array|null
     */
    private $blocks = null;

    /**
     * Blocks list added by user using add_blocks method.
     *
     * @var null
     */
    private $user_blocks = null;

    /**
     * Add block.
     *
     * @param array $data - block data.
     */
    public function add_block( $data ) {
        if ( null === $this->user_blocks ) {
            $this->user_blocks = array();
        }

        // Fix deprecated 'use_php' and new 'output_method' code data.
        if ( isset( $data['code'] ) && ! isset( $data['code']['output_method'] ) ) {
            if ( isset( $data['code']['use_php'] ) && $data['code']['use_php'] ) {
                $data['code']['output_method'] = 'php';
            } else {
                $data['code']['output_method'] = 'html';
            }

            if ( isset( $data['code']['use_php'] ) ) {
                unset( $data['code']['use_php'] );
            }
        }

        $this->user_blocks[] = $data;
    }

    /**
     * Get all blocks array.
     *
     * @param bool $db_only - get blocks from database only.
     * @param bool $no_cache - get blocks without cache.
     * @param bool $keep_duplicates - get blocks with same slugs.
     *
     * @return array|null
     */
    public function get_blocks( $db_only = false, $no_cache = false, $keep_duplicates = false ) {
        // fetch blocks.
        if ( null === $this->blocks || $no_cache ) {
            $this->blocks = array();

            // get all direwolfblocks post types.
            // Don't use WP_Query on the admin side https://core.trac.wordpress.org/ticket/18408 .
            $all_blocks = get_posts(
                array(
                    'post_type'      => 'direwolfblocks',
                    // phpcs:ignore
                    'posts_per_page' => -1,
                    'showposts'      => -1,
                    'paged'          => -1,
                )
            );

            $all_controls = dwb()->controls()->get_controls();

            foreach ( $all_blocks as $block ) {
                $icon = $this->get_meta_value( 'direwolfblocks_icon', $block->ID );

                // add default icon.
                if ( ! $icon ) {
                    // phpcs:ignore
                    $icon = file_get_contents( dwb()->plugin_path() . 'assets/svg/icon-direwolfblocks.svg' );
                    $icon = str_replace( 'fill="white"', 'fill="currentColor"', $icon );
                }

                if ( $icon && strpos( $icon, 'dashicons' ) === 0 ) {
                    $icon = esc_attr( str_replace( 'dashicons-', 'dashicons dashicons-', $icon ) );
                } elseif ( $icon ) {
                    $icon = wp_kses( $icon, $this->kses_svg );
                }

                $keywords = esc_attr( $this->get_meta_value( 'direwolfblocks_keywords', $block->ID ) );
                if ( $keywords ) {
                    $keywords = explode( ',', $keywords );
                } else {
                    $keywords = array();
                }

                $controls = $this->get_meta_value( 'direwolfblocks_controls', $block->ID );

                // prepare default control data.
                foreach ( $controls as $k => $control ) {
                    if ( isset( $control['type'] ) && isset( $all_controls[ $control['type'] ] ) && isset( $all_controls[ $control['type'] ]['attributes'] ) ) {
                        $controls[ $k ] = array_merge(
                            $all_controls[ $control['type'] ]['attributes'],
                            $control
                        );
                    }
                }

                $align          = (array) $this->get_meta_value( 'direwolfblocks_supports_align', $block->ID );
                $align_none_key = array_search( 'none', $align, true );

                if ( false !== $align_none_key ) {
                    unset( $align[ $align_none_key ] );
                }

                $this->blocks[] = array(
                    'id'             => $block->ID,
                    'title'          => $block->post_title,
                    'icon'           => $icon,
                    'keywords'       => $keywords,
                    'slug'           => 'direwolfblock/' . esc_html( $this->get_meta_value( 'direwolfblocks_slug', $block->ID ) ),
                    'description'    => esc_html( $this->get_meta_value( 'direwolfblocks_description', $block->ID ) ),
                    'category'       => $this->sanitize_slug( esc_html( $this->get_meta_value( 'direwolfblocks_category', $block->ID ) ) ),
                    'category_label' => esc_html( $this->get_meta_value( 'direwolfblocks_category', $block->ID ) ),
                    'supports'       => array(
                        'customClassName' => $this->get_meta_value( 'direwolfblocks_supports_classname', $block->ID ),
                        'anchor'          => $this->get_meta_value( 'direwolfblocks_supports_anchor', $block->ID ),
                        'align'           => $align,
                        'html'            => $this->get_meta_value( 'direwolfblocks_supports_html', $block->ID ),
                        'multiple'        => $this->get_meta_value( 'direwolfblocks_supports_multiple', $block->ID ),
                        'inserter'        => $this->get_meta_value( 'direwolfblocks_supports_inserter', $block->ID ),
                    ),
                    'ghostkit'       => array(
                        'supports' => array(
                            'spacings'     => $this->get_meta_value( 'direwolfblocks_supports_ghostkit_spacings', $block->ID ),
                            'display'      => $this->get_meta_value( 'direwolfblocks_supports_ghostkit_display', $block->ID ),
                            'scrollReveal' => $this->get_meta_value( 'direwolfblocks_supports_ghostkit_scroll_reveal', $block->ID ),
                            'frame'        => $this->get_meta_value( 'direwolfblocks_supports_ghostkit_frame', $block->ID ),
                            'customCSS'    => $this->get_meta_value( 'direwolfblocks_supports_ghostkit_custom_css', $block->ID ),
                        ),
                    ),
                    'controls'       => $controls,
                    'code'           => array(
                        'output_method'     => $this->get_meta_value( 'direwolfblocks_code_output_method', $block->ID ),
                        'editor_html'       => $this->get_meta_value( 'direwolfblocks_code_editor_html', $block->ID ),
                        'editor_callback'   => '',
                        'editor_css'        => $this->get_meta_value( 'direwolfblocks_code_editor_css', $block->ID ),
                        'frontend_html'     => $this->get_meta_value( 'direwolfblocks_code_frontend_html', $block->ID ),
                        'frontend_callback' => '',
                        'frontend_css'      => $this->get_meta_value( 'direwolfblocks_code_frontend_css', $block->ID ),
                        'show_preview'      => $this->get_meta_value( 'direwolfblocks_code_show_preview', $block->ID ),
                        'single_output'     => $this->get_meta_value( 'direwolfblocks_code_single_output', $block->ID ),
                    ),
                    'condition'      => $this->get_meta_value( 'direwolfblocks_condition_post_types', $block->ID ) ? $this->get_meta_value( 'direwolfblocks_condition_post_types', $block->ID ) : array(),
                    'edit_url'       => get_edit_post_link( $block->ID ),
                );
            }
        }

        $result = $this->blocks;

        if ( ! $db_only && $this->user_blocks ) {
            $result = array_merge( $result, $this->user_blocks );
        }

        // unique only.
        if ( ! $keep_duplicates ) {
            $unique_result = array();
            $slug_array    = array();

            foreach ( $result as $item ) {
                if ( ! in_array( $item['slug'], $slug_array, true ) ) {
                    $slug_array[]    = $item['slug'];
                    $unique_result[] = $item;
                }
            }

            return $unique_result;
        }

        return $result;
    }

    /**
     * Get specific block data by name.
     *
     * @param string $name - block name.
     * @param bool   $db_only - get blocks from database only.
     *
     * @return array|null
     */
    public function get_block( $name, $db_only = false ) {
        $blocks = $this->get_blocks( $db_only );

        foreach ( $blocks as $block ) {
            if ( $name === $block['slug'] ) {
                return $block;
            }
        }

        return false;
    }

    /**
     * Get all custom blocks categories array.
     *
     * @param bool $db_only - get blocks from database only.
     *
     * @return array|null
     */
    public function get_blocks_categories( $db_only = false ) {
        $blocks             = $this->get_blocks( $db_only );
        $default_categories = array(
            'text',
            'media',
            'design',
            'widgets',
            'embed',
            'reusable',
        );

        $custom_categories = array();

        foreach ( $blocks as $block ) {
            if (
                ! isset( $default_categories[ $block['category'] ] ) &&
                ! isset( $custom_categories[ $block['category'] ] ) &&
                ! in_array( $block['category'], $default_categories, true ) &&
                isset( $block['category_label'] )
            ) {
                $custom_categories[ $block['category'] ] = $block['category_label'];
            }
        }

        return $custom_categories;
    }

    /**
     * Register custom categories for blocks
     *
     * @param array $categories - available categories.
     * @return array
     */
    public function block_categories( $categories ) {
        // direwolfblocks core category.
        $categories[] = array(
            'slug'  => 'direwolfblocks',
            'title' => esc_html__( 'Lazy Blocks', 'dwb' ),
        );

        $new_categories = $this->get_blocks_categories();
        if ( ! empty( $new_categories ) ) {
            foreach ( $new_categories as $slug => $category ) {
                // no duplicates.
                $allow = true;

                foreach ( $categories as $existing_cat ) {
                    if ( isset( $existing_cat['slug'] ) && $slug === $existing_cat['slug'] ) {
                        $allow = false;
                    }
                }

                if ( $allow ) {
                    $categories[] = array(
                        'slug'  => $slug,
                        'title' => $category,
                    );
                }
            }
        }

        return $categories;
    }

    /**
     * Register blocks meta if exists.
     */
    public function register_block_meta() {
        $blocks       = $this->get_blocks();
        $all_controls = dwb()->controls()->get_controls();

        foreach ( $blocks as $block ) {
            $controls = $block['controls'];

            if ( isset( $controls ) && is_array( $controls ) && ! empty( $controls ) ) {
                foreach ( $controls as $control ) {
                    $type = 'string';

                    if ( isset( $control['type'] ) && isset( $all_controls[ $control['type'] ] ) ) {
                        $type = $all_controls[ $control['type'] ]['type'];
                    }

                    if ( isset( $control['save_in_meta'] ) && 'true' === $control['save_in_meta'] ) {
                        register_meta(
                            'post',
                            isset( $control['save_in_meta_name'] ) && $control['save_in_meta_name'] ? $control['save_in_meta_name'] : $control['name'],
                            array(
                                'show_in_rest' => true,
                                'single'       => true,
                                'type'         => $type,
                            )
                        );
                    }
                }
            }
        }
    }

    /**
     * Add Gutenberg block assets.
     */
    public function register_block() {
        global $post_type;

        $blocks = $this->get_blocks();

        // enqueue block css.
        wp_enqueue_style(
            'direwolfblocks-gutenberg',
            dwb()->plugin_url() . 'assets/css/style.min.css',
            array(),
            '2.3.0'
        );
        wp_style_add_data( 'direwolfblocks-gutenberg', 'rtl', 'replace' );
        wp_style_add_data( 'direwolfblocks-gutenberg', 'suffix', '.min' );

        // enqueue block js.
        wp_enqueue_script(
            'direwolfblocks-gutenberg',
            dwb()->plugin_url() . 'assets/js/index.min.js',
            array( 'wp-blocks', 'wp-editor', 'wp-block-editor', 'wp-i18n', 'wp-element', 'wp-components' ),
            '2.3.0',
            true
        );

        // additional data for block js.
        wp_localize_script(
            'direwolfblocks-gutenberg',
            'direwolfblocksGutenberg',
            array(
                'post_type'          => $post_type,
                'blocks'             => $blocks,
                'controls'           => dwb()->controls()->get_controls(),
                'icons'              => dwb()->icons()->get_all(),
                'allowed_mime_types' => get_allowed_mime_types(),
            )
        );
    }

    /**
     * Prepare attributes.
     * The same function placed in block JSX file.
     *
     * @param array          $controls - controls list.
     * @param string|boolean $child_of - childOf control name.
     * @param array          $block - block data.
     *
     * @return array.
     */
    public function prepare_block_attributes( $controls, $child_of = '', $block ) {
        $all_controls = dwb()->controls()->get_controls();
        $attributes   = array();

        foreach ( $controls as $k => $control ) {
            if ( isset( $control['child_of'] ) && $control['child_of'] === $child_of ) {
                $attribute_data = array(
                    'type'    => 'string',
                    'default' => isset( $control['default'] ) ? $control['default'] : null,
                );

                if ( isset( $control['save_in_meta'] ) && 'true' === $control['save_in_meta'] ) {
                    $attribute_data['source'] = 'meta';
                    $attribute_data['meta']   = isset( $control['save_in_meta_name'] ) && $control['save_in_meta_name'] ? $control['save_in_meta_name'] : $control['name'];
                }

                // get attribute type from control data.
                if ( isset( $control['type'] ) && isset( $all_controls[ $control['type'] ] ) ) {
                    $attribute_data['type'] = $all_controls[ $control['type'] ]['type'];

                    if ( 'number' === $attribute_data['type'] && null !== $attribute_data['default'] ) {
                        $attribute_data['default'] = (float) $attribute_data['default'];
                    }
                }

                $attributes[ $control['name'] ] = apply_filters( 'dwb/prepare_block_attribute', $attribute_data, $control, $controls, $k, $block );
            }
        }

        // reserved attributes.
        $attributes['direwolfblock']        = array(
            'type'    => 'object',
            'default' => array(
                'slug' => $block['slug'],
            ),
        );
        $attributes['className']        = array(
            'type'    => 'string',
            'default' => '',
        );
        $attributes['align']            = array(
            'type'    => 'string',
            'default' => '',
        );
        $attributes['anchor']           = array(
            'type'    => 'string',
            'default' => '',
        );
        $attributes['blockId']          = array(
            'type'    => 'string',
            'default' => '',
        );
        $attributes['blockUniqueClass'] = array(
            'type'    => 'string',
            'default' => '',
        );

        // Ghost Kit.
        $attributes['ghostkitSpacings'] = array(
            'type'    => 'object',
            'default' => '',
        );
        $attributes['ghostkitSR']       = array(
            'type'    => 'string',
            'default' => '',
        );

        return $attributes;
    }

    /**
     * Eval custom user code and return as string.
     *
     * @param string $code - user code string.
     * @param array  $attributes - block attributes.
     *
     * @return string
     */
    // phpcs:disable
    public function php_eval( $code, $attributes ) {
        ob_start();

        eval( '?>' . $code );

        return ob_get_clean();
    }
    // phpcs:enable

    /**
     * Register block attributes and custom frontend render callback if exists.
     */
    public function register_block_render() {
        $blocks = $this->get_blocks();

        foreach ( $blocks as $block ) {
            $data = array(
                'attributes'      => $this->prepare_block_attributes( $block['controls'], '', $block ),
                'render_callback' => array( $this, 'render_callback' ),
            );

            register_block_type( $block['slug'], $data );
        }
    }

    /**
     * Render block custom frontend HTML.
     *
     * @param array  $attributes The block attributes.
     * @param string $content The block content.
     * @param string $context - block context [frontend, editor].
     *
     * @return string Returns the post content with latest posts added.
     */
    public function render_callback( $attributes, $content = null, $context = 'frontend' ) {
        if ( ! isset( $attributes['direwolfblock'] ) || ! isset( $attributes['direwolfblock']['slug'] ) ) {
            return null;
        }

        $block   = $this->get_block( $attributes['direwolfblock']['slug'] );
        $context = 'editor' === $context ? 'editor' : 'frontend';
        $result  = null;

        // apply filter for block attributes.
        $attributes = apply_filters( 'dwb/block_render/attributes', $attributes, $content, $block, $context );
        // phpcs:ignore
        $attributes = apply_filters( $block['slug'] . '/' . $context . '_attributes', $attributes, $content, $block );
        // phpcs:ignore
        $attributes = apply_filters( $block['slug'] . '/attributes', $attributes, $content, $block, $context );

        // apply filter for custom output callback.
        $result = apply_filters( 'dwb/block_render/callback', $result, $attributes, $context );
        // phpcs:ignore
        $result = apply_filters( $block['slug'] . '/' . $context . '_callback', $result, $attributes );
        // phpcs:ignore
        $result = apply_filters( $block['slug'] . '/callback', $result, $attributes, $context );

        // Custom output.
        if ( ! $result && isset( $block['code'] ) ) {
            // Theme template file.
            if ( isset( $block['code']['output_method'] ) && 'template' === $block['code']['output_method'] ) {
                ob_start();
                $template_slug        = str_replace( '/', '-', $attributes['direwolfblock']['slug'] );
                $template_path_editor = '/blocks/' . $template_slug . '/editor.php';
                $template_path        = '/blocks/' . $template_slug . '/block.php';
                $template_args        = array(
                    'attributes' => $attributes,
                    'block'      => $block,
                    'context'    => $context,
                );

                // Editor template.
                if ( 'editor' === $context && $this->template_exists( $template_path_editor, $template_args ) ) {
                    $this->include_template( $template_path_editor, $template_args );

                    // Frontend template.
                } elseif ( $this->template_exists( $template_path, $template_args ) ) {
                    $this->include_template( $template_path, $template_args );

                    // Template not found.
                } else {
                    $this->include_template( dwb()->plugin_path . 'templates/template-not-found.php', $template_args );
                }

                $result = ob_get_clean();

                // Callback function.
            } elseif ( isset( $block['code'][ $context . '_callback' ] ) && ! empty( $block['code'][ $context . '_callback' ] ) && is_callable( $block['code'][ $context . '_callback' ] ) ) {
                ob_start();
                call_user_func( $block['code'][ $context . '_callback' ], $attributes );
                $result = ob_get_clean();

                // Custom code.
            } elseif ( isset( $block['code'][ $context . '_html' ] ) && ! empty( $block['code'][ $context . '_html' ] ) ) {
                // PHP output.
                if ( isset( $block['code']['output_method'] ) && 'php' === $block['code']['output_method'] ) {
                    $result = $this->php_eval( $block['code'][ $context . '_html' ], $attributes );

                    // Handlebars.
                } else {
                    $result = $this->handlebars->render( $block['code'][ $context . '_html' ], $attributes );
                }
            }
        }

        // add wrapper.
        $allow_wrapper = apply_filters( 'dwb/block_render/allow_wrapper', $result && 'frontend' === $context, $attributes, $context );
        // phpcs:ignore
        $allow_wrapper = apply_filters( $block['slug'] . '/' . $context . '_allow_wrapper', $allow_wrapper, $attributes );
        // phpcs:ignore
        $allow_wrapper = apply_filters( $block['slug'] . '/allow_wrapper', $allow_wrapper, $attributes, $context );

        if ( $allow_wrapper ) {
            $html_atts = '';

            if ( ! isset( $attributes['className'] ) ) {
                $attributes['className'] = '';
            }

            $attributes['className'] .= ' wp-block-' . str_replace( '/', '-', $attributes['direwolfblock']['slug'] );

            if ( $attributes['blockUniqueClass'] ) {
                $attributes['className'] .= ' ' . $attributes['blockUniqueClass'];
            }

            if ( $attributes['align'] ) {
                $attributes['className'] .= ' align' . $attributes['align'];
            }

            if ( $attributes['className'] ) {
                $attributes['className'] = trim( $attributes['className'] );
                $html_atts              .= ' class="' . esc_attr( $attributes['className'] ) . '"';
            }
            if ( $attributes['anchor'] ) {
                $html_atts .= ' id="' . esc_attr( $attributes['anchor'] ) . '"';
            }

            if ( isset( $attributes['ghostkitSR'] ) && $attributes['ghostkitSR'] ) {
                $html_atts .= ' data-ghostkit-sr="' . esc_attr( $attributes['ghostkitSR'] ) . '"';
            }

            $result = '<div' . $html_atts . '>' . $result . '</div>';
        }

        // add filter for block output.
        $result = apply_filters( 'dwb/block_render/output', $result, $attributes, $context );
        // phpcs:ignore
        $result = apply_filters( $block['slug'] . '/' . $context . '_output', $result, $attributes );
        // phpcs:ignore
        $result = apply_filters( $block['slug'] . '/output', $result, $attributes, $context );

        return $result;
    }

    /**
     * Check if template exists.
     *
     * @param string $template_name file name.
     * @param array  $args args for template.
     */
    public function template_exists( $template_name, $args = array() ) {
        if ( ! empty( $args ) && is_array( $args ) ) {
	        // phpcs:ignore
            extract( $args );
        }

        // template in theme folder.
        $template = locate_template( array( $template_name ) );

        // Allow 3rd party plugin filter template file from their plugin.
        $template = apply_filters( 'dwb/block_render/template_exists', $template, $template_name, $args['attributes'], $args['block'], $args['context'] );
        // phpcs:ignore
        $template = apply_filters( $args['block']['slug'] . '/' . $args['context'] . '_template_exists', $template, $template_name, $args['attributes'], $args['block'] );
        // phpcs:ignore
        $template = apply_filters( $args['block']['slug'] . '/template_exists', $template, $template_name, $args['attributes'], $args['block'], $args['context'] );

        // DEPRECATED.
        $template = apply_filters( 'dwb/template_exists', $template, $template_name, $args );

        return file_exists( $template );
    }

    /**
     * Include template
     *
     * @param string $template_name file name.
     * @param array  $args args for template.
     */
    public function include_template( $template_name, $args = array() ) {
        if ( ! empty( $args ) && is_array( $args ) ) {
	        // phpcs:ignore
            extract( $args );
        }

        // template in theme folder.
        $template = locate_template( array( $template_name ) );

        // Allow 3rd party plugin filter template file from their plugin.
        $template = apply_filters( 'dwb/block_render/include_template', $template, $args['attributes'], $args['block'], $args['context'] );
        // phpcs:ignore
        $template = apply_filters( $args['block']['slug'] . '/' . $args['context'] . '_include_template', $template, $args['attributes'], $args['block'] );
        // phpcs:ignore
        $template = apply_filters( $args['block']['slug'] . '/include_template', $template, $args['attributes'], $args['block'], $args['context'] );

        // DEPRECATED.
        $template = apply_filters( 'dwb/include_template', $template, $template_name, $args );

        if ( file_exists( $template ) ) {
            include $template;
        }
    }
}
