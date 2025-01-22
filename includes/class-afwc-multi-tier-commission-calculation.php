<?php
/**
 * Class for rule group
 *
 * @package     affiliate-for-woocommerce/includes/commission-rules/
 * @since       8.17.0
 * @version     1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! trait_exists( 'AFWC_Multi_Tier_Commission_Calculation' ) ) {

	/**
	 * Traits for AFWC_Multi_Tier_Commission_Calculation
	 */
	trait AFWC_Multi_Tier_Commission_Calculation {

		/**
		 * Stores parent chain of affiliates.
		 *
		 * @var array
		 */
		public $parent_chains = array();

		/**
		 * Stores parent commissions.
		 *
		 * @var array
		 */
		public $parent_commissions = array();

		/**
		 * Constructor to initialize the parent chain.
		 *
		 * @param AFWC_Affiliate $affiliate Affiliate data.
		 */
		public function parent_commission_init( $affiliate = null ) {
			if ( ! empty( $affiliate ) ) {
				$this->set_parent_chain( $affiliate );
			}
		}

		/**
		 * Sets the parent chain if Multi-tier is enabled.
		 *
		 * @param AFWC_Affiliate $affiliate Affiliate data.
		 *
		 * @return void
		 */
		private function set_parent_chain( $affiliate = null ) {
			// Return if Multi-tier feature is disabled.
			if ( ! is_callable( array( 'AFWC_Multi_Tier', 'is_enabled' ) ) || ! AFWC_Multi_Tier::is_enabled() ) {
				return;
			}

			$affiliate_id = ! empty( $affiliate->affiliate_id ) ? intval( $affiliate->affiliate_id ) : 0;

			if ( empty( $affiliate_id ) ) {
				return;
			}

			$multi_tier = is_callable( array( 'AFWC_Multi_Tier', 'get_instance' ) )
				? AFWC_Multi_Tier::get_instance()
				: null;

			$this->parent_chains = is_callable( array( $multi_tier, 'get_parents_for_commissions' ) )
				? $multi_tier->get_parents_for_commissions( $affiliate_id )
				: array();
		}

		/**
		 * Tracks parent commissions.
		 *
		 * @param array $validated_plans Validated plans for the affiliate.
		 *
		 * @return void
		 */
		public function track_parent_commissions( $validated_plans = array() ) {
			if ( empty( $this->parent_chains ) || ! is_array( $this->parent_chains ) ) {
				return;
			}

			if ( ! empty( $validated_plans ) && is_array( $validated_plans ) ) {
				foreach ( $validated_plans as $plan_details ) {
					// Chain index is initialized from 2 as parent chain is started from 2nd tier.
					$chain_index       = 2;
					$chain_array_index = 0;

					foreach ( $this->parent_chains as $parent_id ) {
						if ( $chain_index > ( ! empty( $plan_details['no_of_tiers'] ) ? intval( $plan_details['no_of_tiers'] ) : 1 ) ) {
							break;
						}

						$this->process_commissions_for_plans( $chain_array_index, (int) $parent_id, $plan_details );
						$chain_index++;
						$chain_array_index++;
					}
				}
			} else {
				$this->apply_zero_parent_commissions();
			}
		}

		/**
		 * Process commissions for a specific plan and tier.
		 *
		 * @param int   $tier_key Tier index in the distribution.
		 * @param int   $affiliate_id Affiliate ID.
		 * @param array $plan_details Details of the commission plan.
		 *
		 * @return void
		 */
		public function process_commissions_for_plans( $tier_key = 0, $affiliate_id = 0, $plan_details = array() ) {
			if ( empty( $affiliate_id ) || empty( $plan_details ) ) {
				return;
			}

			$no_of_tiers         = ! empty( $plan_details['no_of_tiers'] ) ? intval( $plan_details['no_of_tiers'] ) : 1;
			$distribution        = ( ! empty( $plan_details['distribution'] ) && $no_of_tiers > 1 )
				? $plan_details['distribution']
				: array();
			$distribution_amount = ! empty( $distribution[ $tier_key ] ) ? floatval( $distribution[ $tier_key ] ) : 0;

			// Determine commission type and calculate based on the plan type.
			$current_commission_amt = floatval(
				! empty( $plan_details['is_default_plan'] ) && 'yes' === $plan_details['is_default_plan']
				? $this->calculate_storewide_plan( $distribution_amount, $plan_details )
				: $this->calculate_rule_based_plans( $distribution_amount, $plan_details )
			);

			$this->parent_commissions[ $affiliate_id ] = ! empty( $this->parent_commissions[ $affiliate_id ] )
				? ( floatval( $this->parent_commissions[ $affiliate_id ] ) + $current_commission_amt )
				: $current_commission_amt;
		}

		/**
		 * Calculate commission for storewide plans.
		 *
		 * @param float $amount Distribution amount.
		 * @param array $plan_details Plan details.
		 *
		 * @return float Calculated commission amount.
		 */
		public function calculate_storewide_plan( $amount = 0, $plan_details = array() ) {
			if ( empty( $plan_details ) ) {
				return 0;
			}

			$type           = ! empty( $plan_details['type'] ) ? $plan_details['type'] : '';
			$plan_total     = ! empty( $plan_details['total'] ) ? floatval( $plan_details['total'] ) : 0;
			$commission_amt = 0;

			if ( 'Flat' === $type ) {
				$commission_amt = $amount;
			} elseif ( 'Percentage' === $type ) {
				$commission_amt = ! empty( $amount )
					? ( floatval( $plan_total ) * floatval( $amount ) ) / 100
					: 0;
			}

			return $commission_amt;
		}

		/**
		 * Calculate commission for rule-based plans.
		 *
		 * @param float $amount Distribution amount.
		 * @param array $plan_details Plan details.
		 *
		 * @return float Calculated commission amount.
		 */
		public function calculate_rule_based_plans( $amount = 0, $plan_details = array() ) {
			if ( empty( $plan_details ) ) {
				return 0;
			}

			$type                  = ! empty( $plan_details['type'] ) ? $plan_details['type'] : '';
			$validate_item_details = ! empty( $plan_details['validated_items_data'] ) ? $plan_details['validated_items_data'] : array();

			if ( empty( $validate_item_details ) || ! is_array( $validate_item_details ) ) {
				return 0;
			}

			$commission_amt = 0;

			foreach ( $validate_item_details as $item_details ) {
				if ( 'Flat' === $type ) {
					// Apply a flat commission, multiplied by quantity if applicable.
					$quantity = ! empty( $item_details['quantity'] ) ? floatval( $item_details['quantity'] ) : 1;
					$apply_to = ! empty( $plan_details['apply_to'] ) ? $plan_details['apply_to'] : '';

					$commission_amt += ( ( 'all' === $apply_to && $quantity > 1 )
						? ( $amount * $quantity )
						: $amount );
				} elseif ( 'Percentage' === $type ) {
					// Apply a percentage commission based on the line total.
					$line_total      = ! empty( $item_details['line_total'] ) ? floatval( $item_details['line_total'] ) : 0;
					$commission_amt += ( ( $line_total > 0 )
						? ( $line_total * $amount ) / 100
						: 0 );
				}
			}

			return $commission_amt;
		}

		/**
		 * Apply zero commission to all parent affiliates.
		 *
		 * @return void
		 */
		public function apply_zero_parent_commissions() {
			if ( empty( $this->parent_chains ) || ! is_array( $this->parent_chains ) ) {
				return;
			}

			$default_plan_details = afwc_get_default_plan_details();
			$no_of_tiers          = ! empty( $default_plan_details['no_of_tiers'] )
				? intval( $default_plan_details['no_of_tiers'] )
				: 1;

			$tier_index = 2; // Start assigning from the parent distribution.

			foreach ( $this->parent_chains as $affiliate_id ) {
				if ( $tier_index > $no_of_tiers ) {
					break;
				}

				$this->parent_commissions[ $affiliate_id ] = 0;
				$tier_index++;
			}
		}
	}
}
