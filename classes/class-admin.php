<?php
/**
 * DirewolfBlocks admin.
 *
 * @package direwolfblocks
 */

 if( ! defined( 'ABSPATH' ) ) wp_die( 'End of Line, Man' );

 /**
  * DirewolfBlocks_Admin class. Class to work with DirewolfBlocks Controls.
  */
 class DirewolfBlocks_Admin {
     /**
      * DirewolfBlocks_Admin constructor.
      */
     public function __construct() {
         add_action( 'admin_menu', array( $this, 'admin_menu' ), 11 );
         add_action( 'admin_menu', array( $this, 'maybe_hide_menu_item' ), 12 );

         add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
         add_action( 'enqueue_block_editor_assets', array( $this, 'constructor_enqueue_scripts' ) );
         add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_script_translations' ), 9 );

         add_action( 'in_admin_header', array( $this, 'in_admin_header' ) );
         add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ) );
     }

     /**
      * Admin menu.
      */
     public function admin_menu() {
         // Documentation menu link.
         add_submenu_page(
             'edit.php?post_type=direwolfblocks',
             esc_html__( 'Documentation', 'dwb' ),
             esc_html__( 'Documentation', 'dwb' ),
             'manage_options',
             'https://github.com/DirewolfDesign/DirewolfBlocks/wiki/'
         );
     }

     /**
      * Maybe hide admin menu item.
      */
     public function maybe_hide_menu_item() {
         $show_menu_item = apply_filters( 'dwb/show_admin_menu', true );

         if ( ! $show_menu_item )
             remove_menu_page( 'edit.php?post_type=direwolfblocks' );
     }

     /**
      * Enqueue admin styles and scripts.
      */
     public function admin_enqueue_scripts() {
         global $wp_locale;

         wp_enqueue_script( 'date_i18n', dwb()->plugin_url() . 'vendor/date_i18n/date_i18n.js', array(), '1.0.0', true );

         $month_names       = array_map( array( &$wp_locale, 'get_month' ), range( 1, 12 ) );
         $month_names_short = array_map( array( &$wp_locale, 'get_month_abbrev' ), $month_names );
         $day_names         = array_map( array( &$wp_locale, 'get_weekday' ), range( 0, 6 ) );
         $day_names_short   = array_map( array( &$wp_locale, 'get_weekday_abbrev' ), $day_names );

         wp_localize_script(
             'date_i18n',
             'DATE_I18N',
             array(
                 'month_names'       => $month_names,
                 'month_names_short' => $month_names_short,
                 'day_names'         => $day_names,
                 'day_names_short'   => $day_names_short,
             )
         );

         wp_enqueue_style( 'direwolfblocks-admin', dwb()->plugin_url() . 'assets/admin/css/style.min.css', '', '1.0.0' );
         wp_style_add_data( 'direwolfblocks-admin', 'rtl', 'replace' );
         wp_style_add_data( 'direwolfblocks-admin', 'suffix', '.min' );
     }

     /**
      * Enqueue constructor styles and scripts.
      */
     public function constructor_enqueue_scripts() {
         if ( 'direwolfblocks' === get_post_type() ) {
             wp_enqueue_script(
                 'direwolfblocks-constructor',
                 dwb()->plugin_url() . 'assets/admin/constructor/index.min.js',
                 array( 'wp-blocks', 'wp-editor', 'wp-block-editor', 'wp-i18n', 'wp-element', 'wp-components', 'lodash', 'jquery' ),
                 '2.3.0',
                 true
             );
             wp_localize_script(
                 'direwolfblocks-constructor',
                 'direwolfblocksConstructorData',
                 array(
                     'post_id'             => get_the_ID(),
                     'allowed_mime_types'  => get_allowed_mime_types(),
                     'controls'            => dwb()->controls()->get_controls(),
                     'controls_categories' => dwb()->controls()->get_controls_categories(),
                     'icons'               => dwb()->icons()->get_all(),
                 )
             );

             wp_enqueue_style( 'direwolfblocks-constructor', dwb()->plugin_url() . 'assets/admin/constructor/style.min.css', array(), '1.0.0' );
             wp_style_add_data( 'direwolfblocks-constructor', 'rtl', 'replace' );
             wp_style_add_data( 'direwolfblocks-constructor', 'suffix', '.min' );
         }
     }

     /**
      * Add script translations.
      */
     public function enqueue_script_translations() {
         if ( ! function_exists( 'wp_set_script_translations' ) ) return;

         wp_enqueue_script( 'direwolfblocks-translation', dwb()->plugin_url() . 'assets/js/translation.min.js', array(), '1.0.0', false );
         wp_set_script_translations( 'direwolfblocks-translation', 'dwb', dwb()->plugin_path() . 'languages' );
     }

     /**
      * Admin navigation.
      */
     public function in_admin_header() {
         if ( ! function_exists( 'get_current_screen' ) ) return;

         $screen = get_current_screen();

         // Determine if the current page being viewed is "Lazy Blocks" related.
         if ( ! isset( $screen->post_type ) || 'direwolfblocks' !== $screen->post_type ) return;

         global $submenu, $submenu_file, $plugin_page;

         $parent_slug = 'edit.php?post_type=direwolfblocks';
         $tabs        = array();

         // Generate array of navigation items.
         if ( isset( $submenu[ $parent_slug ] ) ) {
             foreach ( $submenu[ $parent_slug ] as $i => $sub_item ) {

                 // Check user can access page.
                 if ( ! current_user_can( $sub_item[1] ) ) continue;

                 // Ignore "Add New".
                 if ( 'post-new.php?post_type=direwolfblocks' === $sub_item[2] ) continue;

                 // Define tab.
                 $tab = array(
                     'text' => $sub_item[0],
                     'url'  => $sub_item[2],
                 );

                 // Convert submenu slug "test" to "$parent_slug&page=test".
                 if ( ! strpos( $sub_item[2], '.php' ) && 0 !== strpos( $sub_item[2], 'https://' ) )
                     $tab['url'] = add_query_arg( array( 'page' => $sub_item[2] ), $parent_slug );

                 // Detect active state.
                 if ( $submenu_file === $sub_item[2] || $plugin_page === $sub_item[2] )
                     $tab['is_active'] = true;

                 // Special case for "Add New" page.
                 if ( 0 === $i && 'post-new.php?post_type=direwolfblocks' === $submenu_file )
                     $tab['is_active'] = true;

                 $tabs[] = $tab;
             }
         }

         // Bail early if set to false.
         if ( false === $tabs ) return;

         // phpcs:ignore
         $logo_url = 'data:image/svg+xml;base64,' . base64_encode( file_get_contents( dwb()->plugin_path() . 'assets/svg/icon-direwolfblocks-black.svg' ) );

         if( file_exists( dwb()->plugin_path() . '/components/admin/dwb-admin-toolbar.php' ) )
            include( dwb()->plugin_path() . '/components/admin/dwb-admin-toolbar.php' );
     }

     /**
      * Admin footer text.
      *
      * @param string $text The admin footer text.
      *
      * @return string
      */
     public function admin_footer_text( $text ) {
         if ( ! function_exists( 'get_current_screen' ) ) return $text;

         $screen = get_current_screen();

         // Determine if the current page being viewed is "Direwolf Blocks" related.
         if ( isset( $screen->post_type ) && 'direwolfblocks' === $screen->post_type ) {
             // Use RegExp to append "Lazy Blocks" after the <a> element allowing translations to read correctly.
             return preg_replace( '/(<a[\S\s]+?\/a>)/', '$1 ' . esc_attr__( 'and', 'dwb' ) . ' <a href="https://github.com/DirewolfDesign/direwolf-blocks" target="_blank">Direwolf Blocks</a>', $text, 1 );
         }

         return $text;
     }
 }

 new DirewolfBlocks_Admin();
