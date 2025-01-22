<?php
/**
 * Main class for Multi Tier
 *
 * @package     affiliate-for-woocommerce/includes/
 * @since       5.4.0
 * @version     2.1.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Multi_Tier' ) ) {

	/**
	 * Class to handle Multi-Tier
	 */
	class AFWC_Multi_Tier {

		/**
		 * Variable to hold whether the multi tier is enabled or not
		 *
		 * @var $is_enabled
		 */
		public $is_enabled = true;

		/**
		 * Instance of AFWC_Multi_Tier
		 *
		 * @var self $instance
		 */
		private static $instance = null;

		/**
		 * Get single instance of AFWC_Multi_Tier
		 *
		 * @return AFWC_Multi_Tier Singleton object of AFWC_Multi_Tier
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Private constructor to prevent direct instantiation.
		 */
		private function __construct() {

			$this->is_enabled = self::is_enabled();

			if ( $this->is_enabled ) {
				// Filter to add the multi tier related fields to the affiliate registration core fields.
				add_filter( 'afwc_registration_core_form_fields', array( $this, 'add_fields_to_registration_form' ), 9 );

				// Action to handle the multi tier related updates when registration form fields updates.
				add_action( 'afwc_registration_additional_field_updates', array( $this, 'handle_registration_field_updates' ), 9, 2 );

				// Action to render the parent fields in admin user edit.
				add_action( 'afwc_admin_edit_user_profile_section', array( $this, 'render_parent_field' ), 9, 2 );

				// Action to handle the multi tier fields when affiliate profile is updated in admin side.
				add_action( 'afwc_admin_affiliate_profile_update', array( $this, 'handle_profile_update' ), 9, 3 );
			} else {
				// Filter to remove parent ID if submitted during affiliate registration.
				add_filter( 'afwc_registration_submitted_data', array( $this, 'remove_parent_inputs' ), 9 );
			}

			// Action to delete the parent chain meta when deleting a user.
			add_action( 'delete_user', array( $this, 'delete_parent_chain_meta' ) );
		}

		/**
		 * Method to check whether the multi tier is enabled.
		 *
		 * @return bool Return true if enabled otherwise false.
		 */
		public static function is_enabled() {
			return 'yes' === get_option( 'afwc_enable_multi_tier', 'yes' );
		}

		/**
		 * Method to get the parents by affiliate ID.
		 *
		 * @param int $affiliate_id The affiliate ID.
		 *
		 * @return array Return the array of parent IDs.
		 */
		public function get_parents( $affiliate_id = 0 ) {
			if ( empty( $affiliate_id ) || ! $this->is_enabled ) {
				return array();
			}
			$user_parents = get_user_meta( intval( $affiliate_id ), 'afwc_parent_chain', true );
			return ! empty( $user_parents ) ? array_filter( explode( '|', $user_parents ) ) : array();
		}

		/**
		 * Method to get children by affiliate ID.
		 * If is_nested is true, it will return the nested children.
		 *
		 * @param int  $affiliate_id  The affiliate ID.
		 * @param bool $is_nested     Whether to return nested children.
		 *
		 * @return array Return array of children.
		 */
		public function get_children( $affiliate_id = 0, $is_nested = false ) {
			if ( empty( $affiliate_id ) || ! $this->is_enabled ) {
				return array();
			}

			global $wpdb;

			$affiliate_id = intval( $affiliate_id );

			$children = $wpdb->get_col( // phpcs:ignore
				$wpdb->prepare(
					"SELECT DISTINCT um.user_id
                        FROM {$wpdb->prefix}usermeta as um
                        WHERE ( um.meta_key = %s AND um.meta_value LIKE %s )",
					esc_sql( 'afwc_parent_chain' ),
					esc_sql( '%' . $wpdb->esc_like( $affiliate_id . '|' ) . '%' )
				)
			);

			// Return the direct children if tree is not required.
			if ( false === $is_nested ) {
				return ! empty( $children ) ? $children : array();
			}

			$children_tree = array();

			if ( ! empty( $children ) && is_array( $children ) ) {
				foreach ( $children as $child ) {
					$parent_chain = $this->get_parents( intval( $child ) );
					// Double verify if current user_id exists in the parent chain.
					if ( ! empty( $parent_chain ) && in_array( strval( $affiliate_id ), $parent_chain, true ) ) {
						$children_tree[ $child ] = $parent_chain;
					}
				}
			}

			return apply_filters( 'afwc_get_children', $children_tree );
		}

		/**
		 * Method to handle the parent chain while user profile update.
		 *
		 * @param int   $user_id The user ID.
		 * @param array $inputs  Array of inputs.
		 * @param array $args    Array of arguments.
		 *
		 * @return void.
		 */
		public function handle_profile_update( $user_id = 0, $inputs = array(), $args = array() ) {
			if ( empty( $user_id ) ) {
				return;
			}

			$user_id = absint( $user_id );

			$new_status = ! empty( $args['new_status'] ) ? $args['new_status'] : '';
			$old_status = ! empty( $args['old_status'] ) ? $args['old_status'] : '';

			if ( 'yes' !== $new_status ) {

				$new_status = ( empty( $new_status ) && 'yes' === $old_status ) ? 'no' : $new_status;

				if ( ! empty( $new_status ) ) {

					// Delete parent chain if the user is assigned to an affiliate.
					if ( 'yes' === $old_status ) {
						$this->delete_parent_chain_meta( $user_id );
					}
				}
			}
		}

		/**
		 * Method to handle the registration field updates.
		 *
		 * @param int   $user_id The user ID.
		 * @param array $user_data    The user data.
		 *
		 * @return void.
		 */
		public function handle_registration_field_updates( $user_id = 0, $user_data = array() ) {
			if ( empty( $user_id ) ) {
				return;
			}

			// save parent child relation if parent cookie present.
			$parent_affiliate_id = ! empty( $user_data['afwc_parent_id'] ) ? $user_data['afwc_parent_id'] : afwc_get_referrer_id();

			if ( ! empty( $parent_affiliate_id ) && 'yes' === afwc_is_user_affiliate( absint( $parent_affiliate_id ) ) ) {
				$parent_chain = get_user_meta( $parent_affiliate_id, 'afwc_parent_chain', true );

				// Concat parent chain if exists and update to the parent chain meta.
				update_user_meta(
					$user_id,
					'afwc_parent_chain',
					sprintf( '%1$s|%2$s', $parent_affiliate_id, ! empty( $parent_chain ) ? $parent_chain : '' )
				);
			}
		}

		/**
		 * Method to delete the parent chain meta.
		 *
		 * @param int $user_id The user ID.
		 *
		 * @return void.
		 */
		public function delete_parent_chain_meta( $user_id = 0 ) {
			if ( empty( $user_id ) ) {
				return;
			}

			// Enable the multi tier function forcefully to update the parent-child relations with other affiliates.
			$this->is_enabled = true;
			$this->remove_parent( $user_id, array( 'excludes' => array( $user_id ) ) );
			// Update the value based on setting.
			$this->is_enabled = self::is_enabled();
		}

		/**
		 * Method to assign parent.
		 *
		 * @param int $affiliate_id    Affiliate ID.
		 * @param int $parent_id  Parent Id.
		 *
		 * @return void.
		 */
		public function assign_parent( $affiliate_id = 0, $parent_id = 0 ) {
			if ( empty( $affiliate_id ) || empty( $parent_id ) || ! $this->is_enabled ) {
				return;
			}

			$affiliate_id = intval( $affiliate_id );
			$parent_id    = intval( $parent_id );

			if ( 'yes' === afwc_is_user_affiliate( $affiliate_id ) && 'yes' === afwc_is_user_affiliate( $parent_id ) ) {

				$parent_chain = $this->get_parents( $parent_id );

				// Check if the user id is contains under parent chain.
				$user_pos = ( ! empty( $parent_chain ) && is_array( $parent_chain ) ) ? array_search( $affiliate_id, $parent_chain ) : false; // phpcs:ignore
				if ( false !== $user_pos ) {
					// Remove the parents after the position of user id.
					$parent_chain = array_splice( $parent_chain, 0, $user_pos );
					if ( ! empty( $parent_chain ) ) {
						// Update the parent chain of the parent.
						update_user_meta( $parent_id, 'afwc_parent_chain', implode( '|', $parent_chain ) . '|' );
					} else {
						delete_user_meta( $parent_id, 'afwc_parent_chain' );
					}
					// Update parent chain of the user.
					$this->update_parent_chain_of_children( $parent_id );
				}
				$parent_chain = ! empty( $parent_chain ) ? array_filter( $parent_chain ) : array();
				$parent_chain = ! empty( $parent_chain ) ? implode( '|', $parent_chain ) : '';
				// Concatenate parent id and parent's parent chain.
				$new_parent_chain = ( ! empty( $parent_chain ) ) ? $parent_id . '|' . $parent_chain : $parent_id;
				update_user_meta( $affiliate_id, 'afwc_parent_chain', $new_parent_chain . '|' );
				$this->update_parent_chain_of_children( $affiliate_id );
			}
		}

		/**
		 * Method to remove parents.
		 * Delete the parent chain of the user.
		 * Update the parent chain of the linked children.
		 *
		 * @param int   $affiliate_id  The affiliate  iD.
		 * @param array $args          The Arguments.
		 *
		 * @return void.
		 */
		public function remove_parent( $affiliate_id = 0, $args = array() ) {
			if ( empty( $affiliate_id ) || ! $this->is_enabled ) {
				return;
			}

			// Delete the parent chain of the user.
			delete_user_meta( $affiliate_id, 'afwc_parent_chain' );
			// Update the parent chain of all children.
			$this->update_parent_chain_of_children( $affiliate_id, $args );
		}

		/**
		 * Method to update parent chain of all children by affiliate ID.
		 *
		 * @param int   $affiliate_id  The affiliate ID.
		 * @param array $filters  The Filters.
		 *
		 * @return void.
		 */
		public function update_parent_chain_of_children( $affiliate_id = 0, $filters = array() ) {
			if ( empty( $affiliate_id ) || ! $this->is_enabled ) {
				return;
			}

			$affiliate_id = intval( $affiliate_id );
			// Prevent if the user is not an affiliate user.
			if ( 'yes' !== afwc_is_user_affiliate( $affiliate_id ) ) {
				return;
			}

			$children_tree = $this->get_children( $affiliate_id, true );

			if ( ! empty( $children_tree ) && is_array( $children_tree ) ) {
				$user_parents = $this->get_parents( $affiliate_id );
				foreach ( $children_tree as $child_id => $child_tree ) {
					if ( is_array( $child_tree ) ) {
						// Get the position of the user from the child tree.
						$user_pos = array_search( $affiliate_id, $child_tree ); // phpcs:ignore
						// Get parents till the user position.
						$parents_till_user_pos = array_splice( $child_tree, 0, $user_pos + 1 );
						// Merge parents with user's parents.
						$new_parent_chain = array_merge( $parents_till_user_pos, $user_parents );
						$new_parent_chain = $this->filter_parent_chain( $new_parent_chain, $filters );
						$new_parent_chain = is_array( $new_parent_chain ) ? implode( '|', $new_parent_chain ) : '';

						if ( ! empty( $new_parent_chain ) ) {
							$new_parent_chain = $new_parent_chain . '|';
							// Update the new parent chain.
							update_user_meta( $child_id, 'afwc_parent_chain', $new_parent_chain );
						} else {
							delete_user_meta( $child_id, 'afwc_parent_chain' );
						}
					}
				}
			}
		}

		/**
		 * Filter the parent chain array.
		 *
		 * @param array $chain   Array of parent chain.
		 * @param array $args    The arguments.
		 *
		 * @return array.
		 */
		public function filter_parent_chain( $chain = array(), $args = array() ) {
			if ( empty( $chain ) || ! is_array( $chain ) || empty( $args['excludes'] ) || ! is_array( $args['excludes'] ) ) {
				return $chain;
			}

			return array_diff( $chain, $args['excludes'] );
		}

		/**
		 * Method to remove the multi tier details from commission plans.
		 *
		 * @param array $plans Array of commission plans.
		 *
		 * @return array Return array of modified plan details.
		 */
		public function remove_multi_tier_data_from_plans( $plans = array() ) {
			if ( empty( $plans ) || ! is_array( $plans ) ) {
				return $plans;
			}

			return array_map(
				function ( $plan ) {
					if ( ! is_array( $plan ) ) {
						return $plan;
					}
					if ( isset( $plan['no_of_tiers'] ) ) {
						unset( $plan['no_of_tiers'] );
					}
					if ( isset( $plan['distribution'] ) ) {
						unset( $plan['distribution'] );
					}
					return $plan;
				},
				$plans
			);
		}

		/**
		 * Method to register the multi tier field to the affiliate registration form.
		 *
		 * @param array $fields Array of registration form fields.
		 *
		 * @return array Return modified array of registration form fields.
		 */
		public function add_fields_to_registration_form( $fields = array() ) {
			if ( empty( $fields ) || ! is_array( $fields ) ) {
				return $fields;
			}

			$fields['afwc_parent_id'] = _x( 'Referral affiliate user ID', 'Title for Affiliate registration parent user\'s id field', 'affiliate-for-woocommerce' );
			return $fields;
		}

		/**
		 * Method to remove the parent id input.
		 *
		 * @param array $inputs Array of input fields.
		 *
		 * @return array Return The modified array of inputs.
		 */
		public function remove_parent_inputs( $inputs = array() ) {
			if ( empty( $inputs ) || ! is_array( $inputs ) ) {
				return $inputs;
			}

			// Remove the parent fields from register form submission if exists.
			if ( isset( $inputs['afwc_parent_id'] ) ) {
				unset( $inputs['afwc_parent_id'] );
			}

			return $inputs;
		}


		/**
		 * Method to get the parents for distribute the commissions.
		 *
		 * @param int $affiliate_id The affiliate ID.
		 *
		 * @return array Return The modified array of inputs.
		 */
		public function get_parents_for_commissions( $affiliate_id = 0 ) {
			if ( ! $this->is_enabled ) {
				return array();
			}

			global $affiliate_for_woocommerce;

			$parents = $this->get_parents( $affiliate_id );
			if ( empty( $parents ) ) {
				return array();
			}

			$args = array(
				'include' => $parents,
			);

			$af_parents = is_callable( array( $affiliate_for_woocommerce, 'get_affiliates' ) ) ? $affiliate_for_woocommerce->get_affiliates( $args ) : array();

			if ( empty( $af_parents ) ) {
				return array();
			}

			$af_parents              = is_array( $af_parents ) ? array_filter( array_keys( $af_parents ), 'intval' ) : array();
			$parents_for_commissions = array();

			foreach ( $parents as $parent ) {
				// Break if the parents are not in parent chain.
				if ( ! in_array( intval( $parent ), $af_parents, true ) ) {
					break;
				}
				$parents_for_commissions[] = $parent;
			}

			return ! empty( $parents_for_commissions ) ? $parents_for_commissions : array();
		}

		/**
		 * Method to get children tree.
		 *
		 * @param int $affiliate_id The affiliate ID.
		 *
		 * @return array Return array of tree.
		 */
		public function get_children_tree( $affiliate_id = 0 ) {
			if ( empty( $affiliate_id ) || ! $this->is_enabled ) {
				return array();
			}

			$children = $this->get_children( $affiliate_id, true );

			if ( empty( $children ) || ! is_array( $children ) ) {
				return array();
			}

			$affiliate_immediate_parent = array();

			foreach ( $children as $child => $parent ) {
				$affiliate_immediate_parent[ $child ] = ( ! empty( $parent[0] ) ) ? intval( $parent[0] ) : 0;
			}

			if ( empty( $affiliate_immediate_parent ) || ! is_array( $affiliate_immediate_parent ) ) {
				return array();
			}

			$tree = $this->build_tree( $affiliate_immediate_parent, $affiliate_id );

			if ( ! empty( $tree ) && is_array( $tree ) ) {
				return array( $tree );
			}

			return array();
		}

		/**
		 * Method to build tree structure for multi tier chain data
		 *
		 * @param array $parent_child The array of child and immediate parent.
		 * @param int   $affiliate_id The affiliate user ID.
		 *
		 * @return array Children for an affiliate.
		 */
		private function build_tree( $parent_child = array(), $affiliate_id = 0 ) {
			$children = array();

			foreach ( $parent_child as $child_id => $parent_id ) {
				if ( ! empty( $parent_id ) ) {
					if ( intval( $parent_id ) !== intval( $affiliate_id ) ) {
						continue;
					}

					$children[] = $this->build_tree( $parent_child, $child_id );
				}
			}

			$affiliate_user = get_user_by( 'id', $affiliate_id );

			return array(
				'id'       => $affiliate_id,
				'name'     => $affiliate_user instanceof WP_User && ! empty( $affiliate_user->display_name ) ? html_entity_decode( $affiliate_user->display_name, ENT_QUOTES ) : $affiliate_id,
				'children' => $children,
				'status'   => afwc_is_user_affiliate( $affiliate_id ),
			);
		}

		/**
		 * Method to render the parent fields in admin user edit.
		 *
		 * @param int   $user_id   The user ID.
		 * @param array $args The arguments.
		 *
		 * @return void
		 */
		public function render_parent_field( $user_id = 0, $args = array() ) {
			if ( empty( $user_id ) ) {
				return;
			}

			$affiliate_status = ! empty( $args['status'] ) ? $args['status'] : '';
			if ( ! empty( $affiliate_status ) && in_array( $affiliate_status, array( 'not_registered', 'no' ), true ) ) {
				return;
			}

			$parent_chain  = $this->get_parents( $user_id );
			$parent_id     = ! empty( $parent_chain ) && is_array( $parent_chain ) ? intval( current( $parent_chain ) ) : 0;
			$parent_string = '';
			if ( ! empty( $parent_id ) ) {
				$parent        = get_user_by( 'id', $parent_id );
				$parent_string = $parent instanceof WP_User && ! empty( $parent->user_email ) ? sprintf( '%1$s (#%2$d &ndash; %3$s)', ( ! empty( $parent->display_name ) ? $parent->display_name : '' ), absint( $parent_id ), $parent->user_email ) : '';
			}

			if ( 'pending' === $affiliate_status && ! empty( $parent_string ) ) {
				?>
				<tr>
					<th>
						<label><?php echo esc_html_x( 'Parent affiliate', 'Input label for parent affiliate in user edit setting', 'affiliate-for-woocommerce' ); ?></label></th>
					<td>
						<?php echo esc_html( $parent_string ); ?>
					</td>
				</tr>
				<?php
			}
		}
	}
}

AFWC_Multi_Tier::get_instance();
