<?php
/**
 * Class for handling commission plan calculation.
 *
 * @package     affiliate-for-woocommerce/includes/
 * @since       8.17.0
 * @version     1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AFWC\Rules\Context;
use AFWC\Rules\Groups;

if ( ! class_exists( 'AFWC_Plans' ) ) {

	/**
	 * Class for AFWC_Plan of Affiliate For WooCommerce
	 */
	class AFWC_Plans {

		/**
		 * Use AFWC_Multi_Tier_Commission_Calculation to perform the parent commission calculation.
		 */
		use AFWC_Multi_Tier_Commission_Calculation;

		/**
		 * Holds the order.
		 *
		 * @var WC_Order
		 */
		public $order = null;

		/**
		 * Holds the affiliate.
		 *
		 * @var AFWC_Affiliate
		 */
		public $affiliate = null;

		/**
		 * Holds the context for validating plans.
		 *
		 * @var Context
		 */
		public $context = null;

		/**
		 * Holds the ordered item details for commission calculations.
		 *
		 * @var array
		 */
		public $ordered_items = array();

		/**
		 * Holds the ordered item IDs.
		 *
		 * @var array
		 */
		public $ordered_item_ids = array();

		/**
		 * Stores the validated plans.
		 *
		 * @var array
		 */
		public $validated_plans = array();

		/**
		 * Stores the commissions for each ordered item.
		 *
		 * @var array
		 */
		public $commissions = array();

		/**
		 * Constructor
		 *
		 * @param WC_Order       $order The order object.
		 * @param AFWC_Affiliate $affiliate Referred affiliate Object.
		 */
		public function __construct( $order = null, $affiliate = null ) {
			$this->order     = $order;
			$this->affiliate = $affiliate;

			$this->set_ordered_items();
			$this->set_context();

			// Initialize parent commissions if Multi-tier is enabled.
			if ( is_callable( array( 'AFWC_Multi_Tier', 'is_enabled' ) ) && AFWC_Multi_Tier::is_enabled() ) {
				$this->parent_commission_init( $affiliate );
			}
		}

		/**
		 * Set the context.
		 */
		private function set_context() {
			$this->context = new Context(
				array(
					'affiliate'           => $this->affiliate,
					'order'               => $this->order,
					'ordered_product_ids' => ! empty( $this->ordered_item_ids ) ? $this->ordered_item_ids : array(),
				)
			);
		}

		/**
		 * Set ordered items and their details from the order
		 *
		 * @return void
		 */
		private function set_ordered_items() {
			$items = $this->order instanceof WC_Order && is_callable( array( $this->order, 'get_items' ) ) ? $this->order->get_items() : array();

			// Return an empty array if order item is not provided.
			if ( empty( $items ) || ! is_array( $items ) ) {
				return;
			}

			foreach ( $items as $item ) {
				$variation_id = is_array( array( $item, 'get_variation_id' ) ) ? $item->get_variation_id() : 0;
				$product_id   = is_array( array( $item, 'get_product_id' ) ) ? $item->get_product_id() : 0;
				$item_id      = ! empty( $variation_id ) ? $variation_id : $product_id;

				if ( empty( $item_id ) ) {
					continue;
				}

				$item_id    = (int) $item_id;
				$quantity   = ! empty( $item['quantity'] ) ? floatval( $item['quantity'] ) : 0;
				$line_total = ! empty( $item['line_total'] ) ? floatval( $item['line_total'] ) : 0;

				// Map the quantity and line total of each product with preventing duplication of the items.
				$this->ordered_items[ $item_id ] = array(
					'quantity'   => ! empty( $this->ordered_items[ $item_id ] ['quantity'] )
						? ( floatval( $this->ordered_items[ $item_id ] ['quantity'] ) + $quantity )
						: $quantity,
					'line_total' => ! empty( $this->ordered_items[ $item_id ] ['line_total'] )
						? ( floatval( $this->ordered_items[ $item_id ] ['line_total'] ) + $line_total )
						: $line_total,
				);
			}

			// Set ordered item IDs from the key of ordered items data.
			$this->ordered_item_ids = ! empty( $this->ordered_items ) ? array_keys( $this->ordered_items ) : array();
		}

		/**
		 * Retrieve commission plans.
		 *
		 * @return array List of active plans excluding storewide plans.
		 */
		public function get_commission_plans() {
			$afwc_commission = is_callable( array( 'AFWC_Commission_Plans', 'get_instance' ) ) ? AFWC_Commission_Plans::get_instance() : null;
			return is_callable( array( $afwc_commission, 'get_plans' ) ) ? $afwc_commission->get_plans(
				array(
					'status'            => 'Active',
					'sorting'           => true,
					'exclude_storewide' => true,
				)
			) : array();
		}

		/**
		 * Arrange the rule based plan data with default values.
		 *
		 * @param array $plan Plan data.
		 *
		 * @return array Re-arranged plan data.
		 */
		public function parse_commission_rule_plan_data( $plan = array() ) {
			return array(
				'id'                   => ! empty( $plan['id'] ) ? (int) $plan['id'] : 0,
				'amount'               => ! empty( $plan['amount'] ) ? (float) $plan['amount'] : 0,
				'rules'                => ! empty( $plan['rules'] ) ? $plan['rules'] : array(),
				'type'                 => ! empty( $plan['type'] ) ? $plan['type'] : 'Percentage',
				'distribution'         => ! empty( $plan['distribution'] ) && is_array( $plan['distribution'] ) ? $plan['distribution'] : array(),
				'no_of_tiers'          => ! empty( $plan['no_of_tiers'] ) ? (int) $plan['no_of_tiers'] : 1,
				'apply_to'             => ! empty( $plan['apply_to'] ) ? $plan['apply_to'] : 'all',
				'action_for_remaining' => ! empty( $plan['action_for_remaining'] ) ? $plan['action_for_remaining'] : 'continue',
			);
		}

		/**
		 * Arrange the storewide plan data with default values.
		 *
		 * @param array $plan Plan data.
		 *
		 * @return array Re-arranged plan data.
		 */
		public function parse_storewide_plan_data( $plan = array() ) {
			return array(
				'id'              => ! empty( $plan['id'] ) ? intval( $plan['id'] ) : 0,
				'amount'          => ! empty( $plan['amount'] ) ? intval( $plan['amount'] ) : 0,
				'type'            => ! empty( $plan['type'] ) ? $plan['type'] : 'Percentage',
				'distribution'    => ! empty( $plan['distribution'] ) && is_array( $plan['distribution'] ) ? $plan['distribution'] : array(),
				'no_of_tiers'     => ! empty( $plan['no_of_tiers'] ) ? intval( $plan['no_of_tiers'] ) : 1,
				'is_default_plan' => 'yes',
			);
		}

		/**
		 * Validate the rules.
		 *
		 * @param array $rules The list of rules and their respective details.
		 *
		 * @return array Array of validated items if rules are validated.
		 */
		public function validate_rules( $rules = array() ) {
			// Return true if there is not any rule to validate.
			if ( empty( $rules ) ) {
				return true;
			}

			$remaining_items = $this->get_remaining_items_to_set_commission();

			if ( empty( $remaining_items ) ) {
				return array(); // Return an empty array as no remaining items to validate.
			}

			$groups = new Groups( $rules, $this->context );
			if ( is_callable( array( $groups, 'validate' ) ) ? $groups->validate() : false ) {
				return $groups->get_validated_values( array( 'product_id' => $remaining_items ) );
			}

			return array();
		}

		/**
		 * Track the commissions for each items.
		 *
		 * @return array|false
		 */
		public function track_commission() {

			// Return if there are not any items in the order.
			if ( empty( $this->ordered_item_ids ) || ! is_array( $this->ordered_item_ids ) ) {
				return false;
			}

			$this->apply_exclusions( $this->ordered_item_ids );

			// Return if there are not any remaining items to calculate commissions after exclusions.
			if ( empty( $this->get_remaining_items_to_set_commission() ) ) {
				return false;
			}

			$rule_based_plans = $this->get_commission_plans();

			// Validate the commission plan with rules if exists.
			if ( ! empty( $rule_based_plans ) && is_array( $rule_based_plans ) ) {
				foreach ( $rule_based_plans as $plan ) {

					$plan_details = $this->parse_commission_rule_plan_data( $plan );

					// Validate the rules if exist.
					if ( empty( $plan_details['rules'] ) ) {
						continue;
					}

					$validated_rules = $this->validate_rules( $plan_details['rules'] );

					if ( empty( $validated_rules['product_id'] ) ) {
						continue;
					}

					$this->process_commissions_for_rule_based_plans(
						$validated_rules['product_id'],
						$plan_details
					);

					// Break the loop if there are not any remaining items to calculate commissions.
					if ( empty( $this->get_remaining_items_to_set_commission() ) ) {
						break;
					}

					// Process the commissions for remaining items.
					if ( empty( $plan_details['action_for_remaining'] ) || 'continue' === $plan_details['action_for_remaining'] ) {
						continue;
					}

					// Break the ruled plan looping here to calculate the commission with default storewide plan for remaining items.
					if ( 'default' === $plan_details['action_for_remaining'] ) {
						break;
					}

					// Set the zero commissions to all remaining items.
					if ( 'zero' === $plan_details['action_for_remaining'] ) {
						$this->apply_zero_commissions( $this->get_remaining_items_to_set_commission() );
						break;
					}
				}
			}

			$this->process_commissions_for_storewide_plan();

			$this->track_parent_commissions( $this->validated_plans );

			return $this->save_commissions();
		}

		/**
		 * Apply the zero commission to provided items.
		 *
		 * @param array $item_ids List of item IDs.
		 *
		 * @return void
		 */
		public function apply_zero_commissions( $item_ids = array() ) {
			if ( empty( $item_ids ) || ! is_array( $item_ids ) ) {
				return;
			}

			foreach ( $item_ids as $item_id ) {
				$this->set_commission( $item_id, 0 );
			}
		}

		/**
		 * Set commission for the item.
		 *
		 * @param int   $item_id The item ID.
		 * @param float $amount The amount to set.
		 *
		 * @return void
		 */
		public function set_commission( $item_id = 0, $amount = 0 ) {
			if ( empty( $item_id ) || ! is_scalar( $amount ) ) {
				return;
			}

			$this->commissions[ $item_id ] = floatval( $amount );
		}

		/**
		 * Apply commission exclusions to provided products.
		 *
		 * @param array $product_ids List of product IDs.
		 */
		public function apply_exclusions( $product_ids = array() ) {

			if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
				return;
			}

			$product_ids = array_map( 'intval', $product_ids );

			$global_excluded_products = afwc_get_storewide_excluded_products();

			if ( empty( $global_excluded_products ) || ! is_array( $global_excluded_products ) ) {
				return;
			}

			$global_excluded_products = array_map( 'intval', $global_excluded_products );

			$current_excluded_products = array_intersect( $product_ids, $global_excluded_products );

			if ( empty( $current_excluded_products ) ) {
				return;
			}

			$this->apply_zero_commissions( $current_excluded_products );
		}

		/**
		 * Filter items in the order to calculate the commissions.
		 *
		 * @param array $item_ids The list of item IDs.
		 *
		 * @return array Return the array of item IDs.
		 */
		public function filter_items_for_commissions( $item_ids = array() ) {

			if ( empty( $item_ids ) || ! is_array( $item_ids ) ) {
				return array();
			}

			return array_map(
				'intval',
				! empty( $this->commissions ) && is_array( $this->commissions )
					? array_diff( $item_ids, array_keys( $this->commissions ) )
					: $item_ids
			);
		}

		/**
		 * Get the remaining items from the order to calculate the commissions.
		 *
		 * @return array Return the array of item IDs.
		 */
		public function get_remaining_items_to_set_commission() {

			if ( empty( $this->ordered_item_ids ) || ! is_array( $this->ordered_item_ids ) ) {
				return array();
			}

			return $this->filter_items_for_commissions( $this->ordered_item_ids );
		}

		/**
		 * Process commissions for the validated product IDs.
		 *
		 * @param array $item_ids The ordered item IDs.
		 * @param array $plan_data The Plan data.
		 *
		 * @return void
		 */
		private function process_commissions_for_rule_based_plans( $item_ids = array(), $plan_data = array() ) {

			if ( empty( $plan_data['id'] ) || empty( $item_ids ) ) {
				return;
			}

			$item_ids = $this->filter_items_for_commissions( $item_ids );

			if ( empty( $item_ids ) || ! is_array( $item_ids ) ) {
				return;
			}

			$validated_items_data = array();
			$plan_total           = 0;

			foreach ( $item_ids as $item_id ) {
				$item_details = ! empty( $this->ordered_items[ $item_id ] ) ? $this->ordered_items[ $item_id ] : array();
				$plan_total  += ( ! empty( $item_details['line_total'] ) ? $item_details['line_total'] : 0 );

				$this->set_commission(
					$item_id,
					$this->calculate_commissions( $plan_data, $item_details )
				);

				$validated_items_data[ $item_id ] = array(
					'quantity'   => ! empty( $item_details['quantity'] ) ? floatval( $item_details['quantity'] ) : 0,
					'line_total' => ! empty( $item_details['line_total'] ) ? floatval( $item_details['line_total'] ) : 0,
				);

				// Break the loop in first iterate if `Apply to` tells to apply commission on first item only.
				if ( ! empty( $plan_data['apply_to'] ) && 'first' === $plan_data['apply_to'] ) {
					break;
				}
			}

			$plan_data['total']                = $plan_total;
			$plan_data['validated_items_data'] = $validated_items_data;

			$this->validated_plans[ $plan_data['id'] ] = $plan_data;
		}

		/**
		 * Process storewide commission plan.
		 *
		 * @return void
		 */
		public function process_commissions_for_storewide_plan() {
			$item_ids = $this->get_remaining_items_to_set_commission();

			if ( empty( $item_ids ) || ! is_array( $item_ids ) ) {
				return;
			}

			$plan = afwc_get_default_plan_details();

			if ( empty( $plan ) || empty( $plan['id'] ) ) {
				return;
			}

			$plan_data = $this->parse_storewide_plan_data( $plan );

			$plan_type  = ! empty( $plan_data['type'] ) ? $plan_data['type'] : 'Percentage';
			$plan_total = 0;

			foreach ( $item_ids as $item_id ) {
				if ( 'Flat' === $plan_type ) {
					// Set commission to 0 for individual item for Flat type.
					// Question here to distribute the commission to each item.
					$this->set_commission( $item_id, 0 );
					$plan_total = 0;
				} elseif ( 'Percentage' === $plan_type ) {
					$item_details = ! empty( $this->ordered_items[ $item_id ] ) ? $this->ordered_items[ $item_id ] : array();
					$this->set_commission(
						$item_id,
						$this->calculate_commissions( $plan_data, $item_details )
					);
					$plan_total += ( ! empty( $item_details['line_total'] ) ? $item_details['line_total'] : 0 );
				}
			}

			if ( 'Flat' === $plan_type ) {
				$this->commissions['storewide'] = ! empty( $plan_data['amount'] ) ? floatval( $plan_data['amount'] ) : 0;
			}

			$plan_data['total'] = $plan_total;

			$this->validated_plans[ $plan['id'] ] = $plan_data;
		}

		/**
		 * Calculate the commission for each ordered items.
		 *
		 * @param array $plan_data The Plan data.
		 * @param array $item_details The ordered item details.
		 *
		 * @return float Return the calculated amount.
		 */
		public function calculate_commissions( $plan_data = array(), $item_details = array() ) {

			$amount = 0;

			if ( empty( $plan_data ) ) {
				return floatval( $amount );
			}

			$plan_type   = ! empty( $plan_data['type'] ) ? $plan_data['type'] : 'Percentage';
			$plan_amount = ! empty( $plan_data['amount'] ) ? floatval( $plan_data['amount'] ) : 0;

			if ( 'Percentage' === $plan_type ) {
				$item_total = ! empty( $item_details['line_total'] ) ? floatval( $item_details['line_total'] ) : 0;
				$amount     = ! empty( $item_total ) ? ( ( $item_total * $plan_amount ) / 100 ) : 0;
			} elseif ( 'Flat' === $plan_type ) {
				$item_quantity = ! empty( $item_details['quantity'] ) ? floatval( $item_details['quantity'] ) : 0;
				$amount        = ( ! empty( $item_quantity ) && ! empty( $plan_data['apply_to'] ) && 'all' === $plan_data['apply_to'] )
					? ( $plan_amount * $item_quantity )
					: $plan_amount;
			}

			return floatval( $amount );
		}

		/**
		 * Save commission records.
		 *
		 * @return array|false Return the commission record.
		 */
		public function save_commissions() {

			if ( ! $this->order instanceof WC_Order ) {
				return false;
			}

			$plan_meta = array(
				'product_commissions' => ! empty( $this->commissions ) ? $this->commissions : array(),
				'commissions_chain'   => array(
					'amount' => ! empty( $this->commissions ) ? array_sum( $this->commissions ) : 0,
				),
			);

			if ( ! empty( $this->parent_commissions ) ) {
				$plan_meta['commissions_chain'] = $plan_meta['commissions_chain'] + $this->parent_commissions;
				$this->order->update_meta_data( 'afwc_parent_commissions', $this->parent_commissions );
			}

			$this->order->update_meta_data( 'afwc_set_commission', $plan_meta );
			$this->order->update_meta_data( 'afwc_order_valid_plans', array_keys( $this->validated_plans ) );
			$this->order->save();

			return $plan_meta['commissions_chain'];
		}
	}
}
