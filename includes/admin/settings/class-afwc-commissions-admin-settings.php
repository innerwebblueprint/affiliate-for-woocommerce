<?php
/**
 * Class to handle commission related settings
 *
 * @package     affiliate-for-woocommerce/includes/admin/settings/
 * @since       7.18.0
 * @version     1.0.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Commissions_Admin_Settings' ) ) {

	/**
	 * Main class for commissions section settings
	 */
	class AFWC_Commissions_Admin_Settings {

		/**
		 * Variable to hold instance of AFWC_Commissions_Admin_Settings
		 *
		 * @var self $instance
		 */
		private static $instance = null;

		/**
		 * Section name
		 *
		 * @var string $section
		 */
		private $section = 'commissions';

		/**
		 * Get single instance of this class
		 *
		 * @return AFWC_Commissions_Admin_Settings Singleton object of this class
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor
		 */
		private function __construct() {
			add_filter( "afwc_{$this->section}_section_admin_settings", array( $this, 'get_section_settings' ) );

			// Lifetime comissions.
			add_action( 'woocommerce_admin_field_afwc_ltc_excludes_list', array( $this, 'render_ltc_exclude_list_input' ) );
			add_filter( 'woocommerce_admin_settings_sanitize_option_afwc_lifetime_commissions_excludes', array( $this, 'sanitize_ltc_exclude_list' ) );

			// Ajax action for Lifetime commissions.
			add_action( 'wp_ajax_afwc_search_ltc_excludes_list', array( $this, 'afwc_json_search_exclude_ltc_list' ) );
		}

		/**
		 * Method to get commission section settings
		 *
		 * @return array
		 */
		public function get_section_settings() {

			$default_plan      = afwc_get_default_plan_details();
			$default_plan_name = ( ! empty( $default_plan ) && is_array( $default_plan ) && ! empty( $default_plan['name'] ) ) ? $default_plan['name'] : 'Storewide Default Commission';

			$afwc_commissions_admin_settings = array(
				array(
					'title' => _x( 'Commissions', 'Commissions setting section title', 'affiliate-for-woocommerce' ),
					'type'  => 'title',
					'id'    => 'afwc_commissions_admin_settings',
				),
				array(
					'name'     => _x( 'Referral commission', 'Storewide commission setting title', 'affiliate-for-woocommerce' ),
					'id'       => 'afwc_storewide_commission',
					'type'     => 'text',
					'desc'     => sprintf(
						/* translators: Link to the plan back link */
						esc_html_x( 'Default commission plan: %s', 'Title for displaying the default commission plan link', 'affiliate-for-woocommerce' ),
						'<strong><a target="_blank" href="' . esc_url( admin_url( 'admin.php?page=affiliate-for-woocommerce#!/plans' ) ) . '">' . esc_attr( $default_plan_name ) . '</a></strong>'
					),
					'autoload' => false,
				),
				array(
					'name'     => _x( 'Lifetime commissions', 'Admin setting name for lifetime commissions', 'affiliate-for-woocommerce' ),
					'desc'     => _x( 'Allow affiliates to receive lifetime commissions', 'Admin setting description for lifetime commissions', 'affiliate-for-woocommerce' ),
					'id'       => 'afwc_enable_lifetime_commissions',
					'type'     => 'checkbox',
					'default'  => 'no',
					'desc_tip' => _x( 'Affiliates will receive commissions for every sale made by the same customer linked to this affiliate - without using referral link or coupon.', 'Admin setting description tooltip for lifetime commissions', 'affiliate-for-woocommerce' ),
				),
				array(
					'name'              => _x( 'Lifetime commissions exclude affiliates', 'Admin setting name for lifetime commissions excludes', 'affiliate-for-woocommerce' ),
					'desc'              => _x( 'Exclude the affiliates either by individual affiliates or affiliate tags to not give them lifetime commissions.', 'Admin setting description for affiliates to exclude for lifetime commissions', 'affiliate-for-woocommerce' ),
					'id'                => 'afwc_lifetime_commissions_excludes',
					'type'              => 'afwc_ltc_excludes_list',
					'class'             => 'afwc-lifetime-commission-excludes-search wc-enhanced-select',
					'placeholder'       => _x( 'Search by affiliates or affiliate tags', 'Admin setting placeholder for lifetime commissions excludes', 'affiliate-for-woocommerce' ),
					'options'           => get_option( 'afwc_lifetime_commissions_excludes', array() ),
					'row_class'         => 'no' === get_option( 'afwc_enable_lifetime_commissions', 'no' ) ? 'afwc-hide' : '',
					'custom_attributes' => array(
						'data-afwc-hide-if' => 'afwc_enable_lifetime_commissions',
					),
				),
				array(
					'type' => 'sectionend',
					'id'   => "afwc_{$this->section}_admin_settings",
				),
			);

			return $afwc_commissions_admin_settings;
		}

		/**
		 * Method to rendering the exclude list input field.
		 *
		 * @param array $value The value.
		 *
		 * @return void
		 */
		public function render_ltc_exclude_list_input( $value = array() ) {

			if ( empty( $value ) ) {
				return;
			}

			$id                = ! empty( $value['id'] ) ? $value['id'] : '';
			$options           = ! empty( $value['options'] ) ? $value['options'] : array();
			$field_description = is_callable( array( 'WC_Admin_Settings', 'get_field_description' ) ) ? WC_Admin_Settings::get_field_description( $value ) : array();
			?>	
				<tr valign="top" class="<?php echo ! empty( $value['row_class'] ) ? esc_attr( $value['row_class'] ) : ''; ?>">
					<th scope="row" class="titledesc">
						<label for="<?php echo esc_attr( $id ); ?>"><?php echo ( ! empty( $value['title'] ) ? esc_html( $value['title'] ) : '' ); ?></label>
					</th>
					<td class="forminp">
						<select
							name="<?php echo esc_attr( ! empty( $value['field_name'] ) ? $value['field_name'] : $id ); ?>[]"
							id="<?php echo esc_attr( $id ); ?>"
							style="<?php echo ! empty( $value['css'] ) ? esc_attr( $value['css'] ) : ''; ?>"
							class="<?php echo ! empty( $value['class'] ) ? esc_attr( $value['class'] ) : ''; ?>"
							data-placeholder="<?php echo ! empty( $value['placeholder'] ) ? esc_attr( $value['placeholder'] ) : ''; ?>"
							multiple="multiple"
							<?php echo is_callable( array( 'AFWC_Admin_Settings', 'get_html_attributes_string' ) ) ? wp_kses_post( AFWC_Admin_Settings::get_html_attributes_string( $value ) ) : ''; ?>
						>
						<?php
						foreach ( $options as $group => $ids ) {
							if ( 'affiliates' === $group ) {
								$group_title = _x( 'Affiliates', 'The group name for lifetime commission affiliates excluded list', 'affiliate-for-woocommerce' );
							} elseif ( 'tags' === $group ) {
								$group_title = _x( 'Affiliate Tags', 'The group name for lifetime commission affiliate tags excluded list', 'affiliate-for-woocommerce' );
							} else {
								$group_title = $group;
							}

							$exclude_list = $this->get_excluded_ltc_list( $ids, (array) $group );
							$current_list = ! empty( $exclude_list ) && ! empty( $exclude_list[ $group ] ) ? $exclude_list[ $group ] : array();
							if ( ! empty( $current_list ) ) {
								?>
								<optgroup label=<?php echo esc_attr( $group_title ); ?>>
									<?php foreach ( $current_list as $id => $text ) { ?>
										<option value="<?php echo esc_attr( $group . '-' . $id ); ?>" selected='selected'>
											<?php echo ! empty( $text ) ? esc_html( $text ) : ''; ?>
										</option>
									<?php } ?>
								</optgroup>
								<?php
							}
						}
						?>
					</select>
					<?php echo ! empty( $field_description['description'] ) ? wp_kses_post( $field_description['description'] ) : ''; ?>
				</td>
			</tr>
			<?php
		}

		/**
		 * Method to get the formatted lifetime commission exclude list.
		 *
		 * @param string|array $term The value.
		 * @param array        $group The group name.
		 * @param bool         $for_search Whether the method will be used for searching or fetching the details by id.
		 *
		 * @return array
		 */
		public function get_excluded_ltc_list( $term = '', $group = array(), $for_search = false ) {

			if ( empty( $term ) ) {
				return array();
			}

			global $affiliate_for_woocommerce;

			$values = array();

			if ( ! is_array( $group ) ) {
				$group = (array) $group;
			}

			if ( true === in_array( 'affiliates', $group, true ) ) {
				if ( true === $for_search ) {
					$affiliate_search = array(
						'search'         => '*' . $term . '*',
						'search_columns' => array( 'ID', 'user_nicename', 'user_login', 'user_email' ),
					);
				} else {
					$affiliate_search = array(
						'include' => ! is_array( $term ) ? (array) $term : $term,
					);
				}

				$values['affiliates'] = is_callable( array( $affiliate_for_woocommerce, 'get_affiliates' ) ) ? $affiliate_for_woocommerce->get_affiliates( $affiliate_search ) : array();
			}

			if ( true === in_array( 'tags', $group, true ) ) {
				$tag_search = array(
					'taxonomy'   => 'afwc_user_tags', // taxonomy name.
					'hide_empty' => false,
					'fields'     => 'id=>name',
				);
				if ( true === $for_search ) {
					$tag_search['search'] = $term;
				} else {
					$tag_search['include'] = $term;
				}

				$tags = get_terms( $tag_search );

				if ( ! empty( $tags ) ) {
					$values['tags'] = $tags;
				}
			}

			return $values;
		}

		/**
		 * Method to sanitize and format the value for ltc exclude list.
		 *
		 * @param array $value The value.
		 *
		 * @return array
		 */
		public function sanitize_ltc_exclude_list( $value = array() ) {

			// Return empty array if the value is empty.
			if ( empty( $value ) ) {
				return array();
			}

			$list = array();

			foreach ( $value as $list_id ) {
				// Separate the group name and id.
				$list_id_parts = explode( '-', $list_id, 2 );
				if ( ! empty( $list_id_parts ) ) {
					// Get the group name from the first place.
					$group = current( $list_id_parts );

					// Get the id from the last place.
					$id = end( $list_id_parts );

					// Add the ids to the each group for formatting the value to store in DB.
					$list[ $group ][] = absint( $id );
				} else {
					$list[] = $list_id;
				}
			}
			return $list;
		}

		/**
		 * Ajax callback function to search the affiliates and affiliate tag.
		 */
		public function afwc_json_search_exclude_ltc_list() {

			check_admin_referer( 'afwc-search-exclude-ltc-list', 'security' );

			$term = ( ! empty( $_GET['term'] ) ) ? (string) urldecode( wp_strip_all_tags( wp_unslash( $_GET['term'] ) ) ) : '';

			if ( empty( $term ) ) {
				wp_die();
			}

			$searched_list = $this->get_excluded_ltc_list( $term, array( 'affiliates', 'tags' ), true );

			if ( empty( $searched_list ) ) {
				wp_die();
			}

			$data = array();

			if ( ! empty( $searched_list['affiliates'] ) ) {
				$data[] = array(
					'title'    => _x( 'Affiliates', 'The group name for lifetime commission affiliates excluded list', 'affiliate-for-woocommerce' ),
					'group'    => 'affiliates',
					'children' => $searched_list['affiliates'],
				);
			}

			if ( ! empty( $searched_list['tags'] ) ) {
				$data[] = array(
					'title'    => _x( 'Affiliate Tags', 'The group name for lifetime commission affiliate tags excluded list', 'affiliate-for-woocommerce' ),
					'group'    => 'tags',
					'children' => $searched_list['tags'],
				);
			}

			wp_send_json( $data );
		}

	}

}

AFWC_Commissions_Admin_Settings::get_instance();
