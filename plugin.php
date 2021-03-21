<?php
/**
 * Plugin Name: Direwolf Custom Blocks
 * Description: Adds custom Gutenberg blocks and layout templates
 * Version:     1.0.0
 * Author:      Direwolf Design <developers@direwolfdesign.co>
 * Author URI:  https://direwolfdesign.co
 * License:     GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: dwb
 *
 * @package     direwolfblocks
 */

if( ! defined( 'ABSPATH' ) ) wp_die( 'End of Line, Man' );

if( ! class_exists( 'DirewolfBlocks' ) ) :

    /**
     * Direwolf Blocks Class
     */
     class DirewolfBlocks {
         /**
          * The single class instance.
          *
          * @var null
          */
         private static $instance = null;

         /**
          * Main Instance
          * Ensures only one instance of this class exists in memory at any one time.
          */
         public static function instance() {
             if ( is_null( self::$instance ) ) {
                 self::$instance = new self();
                 self::$instance->init();
             }
             return self::$instance;
         }

         /**
          * The base path to the plugin in the file system.
          *
          * @var string
          */
         public $plugin_path;

         /**
          * URL Link to plugin
          *
          * @var string
          */
         public $plugin_url;

         /**
          * Direwolf_Blocks constructor.
          */
         public function __construct() {
             /* We do nothing here! */
         }

         /**
          * Activation Hook
          */
         public function activation_hook() {
             /* We do nothing here! */
         }

         /**
          * Deactivation Hook
          */
         public function deactivation_hook() {
             /* We do nothing here! */
         }

         /**
          * Init.
          */
         public function init() {
             $this->plugin_path = plugin_dir_path( __FILE__ );
             $this->plugin_url  = plugin_dir_url( __FILE__ );

             $this->load_text_domain();
             $this->include_dependencies();

             $this->icons     = new DirewolfBlocks_Icons();
             $this->controls  = new DirewolfBlocks_Controls();
             $this->blocks    = new DirewolfBlocks_Blocks();
             $this->templates = new DirewolfBlocks_Templates();
             $this->tools     = new DirewolfBlocks_Tools();
         }

         /**
          * Get plugin_path.
          */
         public function plugin_path() {
             return apply_filters( 'dwb/plugin_path', $this->plugin_path );
         }

         /**
          * Get plugin_url.
          */
         public function plugin_url() {
             return apply_filters( 'dwb/plugin_url', $this->plugin_url );
         }

         /**
          * Sets the text domain with the plugin translated into other languages.
          */
         public function load_text_domain() {
             load_plugin_textdomain( 'dwb', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
         }

         /**
          * Set plugin Dependencies.
          */
         private function include_dependencies() {
             require_once $this->plugin_path() . '/classes/class-admin.php';
             require_once $this->plugin_path() . '/classes/class-icons.php';
             require_once $this->plugin_path() . '/classes/class-controls.php';
             require_once $this->plugin_path() . '/classes/class-blocks.php';
             require_once $this->plugin_path() . '/classes/class-templates.php';
             require_once $this->plugin_path() . '/classes/class-tools.php';
             require_once $this->plugin_path() . '/classes/class-rest.php';
             require_once $this->plugin_path() . '/classes/class-force-gutenberg.php';
         }

         /**
          * Get icons object.
          */
         public function icons() {
             return $this->icons;
         }

         /**
          * Get controls object.
          */
         public function controls() {
             return $this->controls;
         }

         /**
          * Get blocks object.
          */
         public function blocks() {
             return $this->blocks;
         }

         /**
          * Get templates object.
          */
         public function templates() {
             return $this->templates;
         }

         /**
          * Get tools object.
          */
         public function tools() {
             return $this->tools;
         }

         /**
          * Add direwolf block.
          *
          * @param array $data - block data.
          */
         public function add_block( $data ) {
             return $this->blocks()->add_block( $data );
         }

         /**
          * Add direwolf template.
          *
          * @param array $data - template data.
          */
         public function add_template( $data ) {
             return $this->templates()->add_template( $data );
         }
     }

     /**
      * The main cycle of the plugin.
      *
      * @return null|DirewolfBlocks
      */
     function dwb() {
         return DirewolfBlocks::instance();
     }

     // Initialize.
     dwb();

     register_deactivation_hook( __FILE__, array( dwb(), 'activation_hook' ) );
     register_activation_hook( __FILE__, array( dwb(), 'deactivation_hook' ) );

     /**
      * Function to get meta value with some improvements for DirewolfBlocks metas.
      *
      * @param string   $name - metabox name.
      * @param int|null $id - post id.
      *
      * @return array|mixed|object
      */
     // phpcs:ignore
     function get_dwb_meta( $name, $id = null ) {
         // global variable used to fix `get_dwb_meta` call inside block preview in editor.
         global $dwb_preview_block_data;

         $control_data = null;

         if ( null === $id ) {
             global $post;

             if ( isset( $post->ID ) ) {
                 $id = $post->ID;
             }
         }

         // Find control data by meta name.
         $blocks = dwb()->blocks()->get_blocks();
         foreach ( $blocks as $block ) {
             if ( isset( $block->controls ) && is_array( $block->controls ) ) {
                 foreach ( $block->controls as $control ) {
                     if ( $control_data || 'true' !== $control->save_in_meta ) continue;

                     $meta_name = ( $control->save_in_meta_name ) ? : $control->name;

                     if ( $meta_name && $meta_name === $name ) $control_data = $control;
                 }
             }
         }

         $result = null;

         if ( $id ) { $result = get_post_meta( $id, $name, true ); }
         elseif (
             isset( $dwb_preview_block_data ) &&
             ! is_null( $dwb_preview_block_data ) &&
             isset( $control_data->name ) &&
             isset( $dwb_preview_block_data->block_attributes[ $control_data->name ] )
         ) {
             $result = $dwb_preview_block_data->block_attributes[ $control_data->name ];
         }

         // set default.
         if ( ! $result && isset( $control_data->default ) && $control_data->default ) {
             $result = $control_data->default;
         }

         return apply_filters( 'dwb/get_meta', $result, $name, $id, $control_data );
     }

endif;
