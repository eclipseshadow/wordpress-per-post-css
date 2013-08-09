<?php
/*
Plugin Name: Per Post CSS
Plugin URI: https://github.com/eclipseshadow/wordpress-per-post-css/
Description: Allows custom CSS to be added to any post (CPT also supported)
Version: 0.2.1
Author: Zach Lanich
Author URI: https://www.ZachLanich.com
License: Undecided
*/

class Per_Post_CSS {

	private $enabled_post_types = 'post, page';

	private $post_css_meta_key = 'pp_css_post_css';

	private $meta_box_id = 'pp_css_editor_box';

	private $meta_box_title = 'Custom CSS';

	private $meta_box_noncename = 'pp_css_nonce';

	private $editor_field_name = 'pp_css_styles';

	private $editor_field_id = 'pp_css_styles';

	private $editor_id = 'pp_css_editor';

	public function __construct() {

		$this->check_for_updates();

		if ( defined('PP_CSS_ENABLED_POST_TYPES') ) {
			$this->enabled_post_types = PP_CSS_ENABLED_POST_TYPES;
		}

		if ( is_admin() ) {
			// WP Admin

			// Create Meta Box

			add_action( 'add_meta_boxes', array( $this, '_add_meta_box' ));
			add_action( 'save_post', array( $this, '_save_meta_box' ) );

			// Load Scripts & Styles

			add_action( 'admin_enqueue_scripts', array( $this, '_load_editor_scripts' ));
			add_action( 'admin_enqueue_scripts', array( $this, '_load_editor_styles' ));
		}
		else {
			// Front End

			add_action('wp_head', array( $this, '_render_post_styles' ), 1000000);
		}

	}

	private function check_for_updates() {

		require_once 'lib/updater.php';

		define( 'WP_GITHUB_FORCE_UPDATE', true );

		if ( is_admin() ) { // note the use of is_admin() to double check that this is happening in the admin

			$config = array(
				'api_url' => 'https://api.github.com/repos/eclipseshadow/wordpress-per-post-css',
				'raw_url' => 'https://raw.github.com/eclipseshadow/wordpress-per-post-css/master',
				'github_url' => 'https://github.com/eclipseshadow/wordpress-per-post-css',
				'zip_url' => 'https://github.com/eclipseshadow/wordpress-per-post-css/archive/master.zip',
				'sslverify' => false,
				'requires' => '3.0',
				'tested' => '3.6',
				'readme' => 'README.md',
				'access_token' => '',
			);

			new WP_GitHub_Updater( $config );

		}

	}

	public function _load_editor_scripts() {

		// Check to see if current post type is enabled

		if ( !$this->is_enabled_post_type() ) {
			return;
		}

		wp_enqueue_script( 'es_ace_code_editor', WP_PLUGIN_URL .'/'. basename( dirname( __FILE__ ) ) .'/lib/ace_code_editor/src-min-noconflict/ace.js', array(), 1.0 );
		wp_enqueue_script('jquery');
		wp_enqueue_script( 'pp_css_admin', WP_PLUGIN_URL .'/'. basename( dirname( __FILE__ ) ) .'/lib/js/pp_css_admin.js', array('es_ace_code_editor', 'jquery'), 1.0 );

	}

	public function _load_editor_styles() {

		// Check to see if current post type is enabled

		if ( !$this->is_enabled_post_type() ) {
			return;
		}

		wp_enqueue_style( 'pp_css_admin', WP_PLUGIN_URL .'/'. basename( dirname( __FILE__ ) ) .'/lib/css/pp_css_admin.css', array(), 1.0 );

	}

	public function _render_post_styles() {

		global $post;

		// Check to see if current post type is enabled

		if ( !$this->is_enabled_post_type() ) {
			return;
		}

		$css = get_post_meta( $post->ID, $this->post_css_meta_key, true);

		echo '<style type="text/css">'. $css .'</style>';

	}

	public function _add_meta_box() {

		$screens = explode(',', $this->enabled_post_types);

		foreach ( $screens as $screen ) {
			add_meta_box(
				$this->meta_box_id,
				$this->meta_box_title,
				array( $this, '_render_meta_box' ),
				$screen
			);
		}

	}

	public function _render_meta_box( $post ) {

		echo '<p class="metabox_info">
		Here you can add custom CSS styles to your post or page. This is most useful for writing custom
		HTML content that needs styling without cluttering your theme&#8217;s stylesheet. <br />
		<span class="metabox_info_emphasis">* Note: Please use discretion -- If you find yourself using the
		same styles on multiple posts or pages, you may want to consider adding them to your theme&#8217;s stylesheet.</span>
		</p>';

		// Use nonce for verification
		wp_nonce_field( plugin_basename( __FILE__ ), $this->meta_box_noncename );

		$css = get_post_meta( $post->ID, $this->post_css_meta_key, true);

		echo '<input type="hidden" id="'. $this->editor_field_id .'" name="'. $this->editor_field_name .'" value="'. htmlspecialchars( $css ) .'"/>';//

		echo '<pre id="'. $this->editor_id .'"></pre>';

		// Render Editor

		echo '
		<script type="text/javascript">

			PP_CSS.render_editor( "'. $this->editor_id .'", "'. $this->editor_field_id .'" );

		</script>
		';

	}

	public function _save_meta_box( $post_id ) {

		// Check if the current user is authorised to do this action.
		if ( 'page' == $_REQUEST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) )
				return;
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) )
				return;
		}

		// Check if the user intended to change this value.
		if ( ! isset( $_POST[ $this->meta_box_noncename ] )
		|| ! wp_verify_nonce( $_POST[ $this->meta_box_noncename ], plugin_basename( __FILE__ ) ) ) {
			return;
		}

		// Save CSS to DB

		$post_id = $_POST['post_ID'];

		$css = $_POST[ $this->editor_field_name ];

		update_post_meta( $post_id, $this->post_css_meta_key, $css );

	}

	// Utility Methods

	private function is_enabled_post_type() {

		return in_array( $this->get_current_post_type(), array_map('trim', explode(',', $this->enabled_post_types) ) );

	}

	private function get_current_post_type() {

		global $post, $typenow, $current_screen;

		// We have a post so we can just get the post type from that
		if ( $post && $post->post_type ) {
			return $post->post_type;
		}

		// Check the global $typenow - set in admin.php
		elseif( $typenow ) {
			return $typenow;
		}

		// Check the global $current_screen object - set in screen.php
		elseif( $current_screen && $current_screen->post_type ) {
			return $current_screen->post_type;
		}

		// Lastly check the post_type querystring
		elseif( isset( $_REQUEST['post_type'] ) ) {
			return sanitize_key( $_REQUEST['post_type'] );
		}

		// We do not know the post type!
		return null;

	}

}

add_action('init', create_function('', '$pp_css_editor = new Per_Post_CSS();'));