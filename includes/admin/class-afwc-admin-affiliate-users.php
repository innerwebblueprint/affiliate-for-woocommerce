<?php
/**
 * Main class for Affiliate tags.
 *
 * @package     affiliate-for-woocommerce/includes/admin/
 * @since       1.4.0
 * @version     1.2.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Admin_Affiliate_Users' ) ) {

	/**
	 * Class for Admin Affiliate User Filter
	 */
	class AFWC_Admin_Affiliate_Users {

		/**
		 * Variable to hold instance of AFWC_Admin_Affiliate_Users
		 *
		 * @var self $instance
		 */
		private static $instance = null;

		/**
		 * Constructor
		 */
		private function __construct() {
			add_action( 'admin_menu', array( $this, 'afwc_add_user_tags_admin_page' ) );
			add_filter( 'parent_file', array( $this, 'afwc_set_submenu_active' ) );
		}

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Admin_Affiliate_Users Singleton object of this class
		 */
		public static function get_instance() {
			// Check if instance is already exists.
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Function to add page for affiliate tags
		 */
		public function afwc_add_user_tags_admin_page() {
			$taxonomy = get_taxonomy( 'afwc_user_tags' );
			add_submenu_page( 'users', esc_attr( $taxonomy->labels->menu_name ), esc_attr( $taxonomy->labels->menu_name ), $taxonomy->cap->manage_terms, 'edit-tags.php?taxonomy=' . $taxonomy->name );
		}

		/**
		 * Function to set Affiliates submenu active.
		 *
		 * @param string $parent_file file reference for menu.
		 */
		public function afwc_set_submenu_active( $parent_file = '' ) {
			global $current_screen;

			$id = ! empty( $current_screen->id ) ? $current_screen->id : '';
			if ( empty( $id ) || 'edit-afwc_user_tags' !== $id ) {
				return $parent_file;
			}

			$parent_file = 'woocommerce';
			?>
			<script type="text/javascript">
				jQuery( function(){
					jQuery('#toplevel_page_woocommerce').find('a[href$="admin.php?page=affiliate-for-woocommerce"]').addClass('current');
					jQuery('#toplevel_page_woocommerce').find('a[href$="admin.php?page=affiliate-for-woocommerce"]').parent().addClass('current');
				});
			</script>
			<?php
			return $parent_file;
		}
	}
}

AFWC_Admin_Affiliate_Users::get_instance();
