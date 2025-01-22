<?php
/**
 * Main class for Affiliate Details.
 *
 * @package     affiliate-for-woocommerce/includes/common/
 * @since       1.0.0
 * @version     2.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Affiliate' ) ) {

	/**
	 * Class to handle affiliate
	 */
	class AFWC_Affiliate extends WP_User {

		/**
		 * Variable to hold affiliate ID.
		 *
		 * @var array
		 */
		public $affiliate_id = 0;

		/**
		 * Initialize AFWC_Affiliate.
		 *
		 * @param WP_User|int $user WP User instance or ID.
		 */
		public function __construct( $user = 0 ) {
			parent::__construct( $user );
			$this->set_affiliate_id();
		}

		/**
		 * Checks if an affiliate id is from a currently valid affiliate.
		 *
		 * @return bool Return true if valid, otherwise false.
		 */
		public function is_valid() {
			return 'yes' === afwc_is_user_affiliate( $this );
		}

		/**
		 * Set the affiliate ID based on the user ID.
		 *
		 * @return void.
		 */
		public function set_affiliate_id() {
			// Return if the ID is empty or not valid.
			if ( empty( $this->ID ) || ! $this->is_valid() ) {
				return;
			}

			// Assign the affiliate ID based on the user ID.
			$this->affiliate_id = intval( afwc_get_affiliate_id_based_on_user_id( $this->ID ) );
		}

		/**
		 * Get the Linked customers for Lifetime commissions.
		 *
		 * @return array Array of linked customers.
		 */
		public function get_ltc_customers() {
			if ( empty( $this->ID ) ) {
				return array();
			}

			$customers = get_user_meta( $this->ID, 'afwc_ltc_customers', true );
			return ! empty( $customers ) ? array_filter( explode( ',', $customers ) ) : array();
		}

		/**
		 * Link the customer to the affiliate for Lifetime commissions.
		 *
		 * @param string|int $customer The customer email address or customer's user ID.
		 *
		 * @return bool Whether the customer is updated or not.
		 */
		public function add_ltc_customer( $customer = '' ) {
			if ( empty( $customer ) || empty( $this->ID ) ) {
				return false;
			}

			if ( ! $this->is_ltc_enabled() || afwc_get_ltc_affiliate_by_customer( $customer ) ) {
				return false;
			}

			$ltc_customers = $this->get_ltc_customers();

			$ltc_customers = ! empty( $ltc_customers ) ? $ltc_customers : array();

			$ltc_customers[] = $customer;

			return (bool) update_user_meta( $this->ID, 'afwc_ltc_customers', implode( ',', array_filter( $ltc_customers ) ) );
		}

		/**
		 * Unlink the customer from the Lifetime commission linked list.
		 *
		 * @param string|int $customer The customer email address or customer's user ID.
		 *
		 * @return bool Return true if successfully removed otherwise false.
		 */
		public function remove_ltc_customer( $customer = '' ) {
			if ( empty( $customer ) || empty( $this->ID ) ) {
				return false;
			}

			$ltc_customers = $this->get_ltc_customers();

			if ( empty( $ltc_customers ) || ! is_array( $ltc_customers ) ) {
				return false;
			}

			$key = array_search( $customer, $ltc_customers, true );

			if ( false !== $key ) {
				unset( $ltc_customers[ $key ] );
			}

			$value = ! empty( $ltc_customers ) ? ( implode( ',', array_filter( $ltc_customers ) ) ) : '';

			return (bool) ( empty( $value ) ? delete_user_meta( $this->ID, 'afwc_ltc_customers' ) : update_user_meta( $this->ID, 'afwc_ltc_customers', $value ) );
		}

		/**
		 * Check whether Lifetime commission feature is enabled of the affiliate.
		 *
		 * @return bool Return true if enabled otherwise false.
		 */
		public function is_ltc_enabled() {
			if ( empty( $this->ID ) ) {
				return false;
			}

			if ( 'no' === get_option( 'afwc_enable_lifetime_commissions', 'no' ) ) {
				return false;
			}

			$ltc_excluded_affiliates = get_option( 'afwc_lifetime_commissions_excludes', array() );

			// Check whether the affiliate id is selected for the lifetime commission exclude list.
			if ( ! empty( $ltc_excluded_affiliates['affiliates'] ) && in_array( intval( $this->ID ), $ltc_excluded_affiliates['affiliates'], true ) ) {
				return false;
			}

			// Check whether the affiliate tag is selected for the lifetime commission exclude list.
			if ( ! empty( $ltc_excluded_affiliates['tags'] ) ) {
				$tags = $this->get_tags();
				if ( ! empty( $tags ) && count( array_intersect( array_keys( $tags ), $ltc_excluded_affiliates['tags'] ) ) > 0 ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Get the assigned tags to the affiliate.
		 *
		 * @return array Array of tags having Id as key and tag name as value.
		 */
		public function get_tags() {
			if ( empty( $this->ID ) ) {
				return array();
			}

			$tags = wp_get_object_terms( $this->ID, 'afwc_user_tags', array( 'fields' => 'id=>name' ) );
			return ! empty( $tags ) && ! is_wp_error( $tags ) ? $tags : array();
		}

		/**
		 * Get the landing pages assigned to the affiliate.
		 *
		 * @return array Array of landing page links.
		 */
		public function get_landing_page_links() {
			if ( empty( $this->affiliate_id ) ) {
				return array();
			}

			// Check whether landing page feature is enabled.
			if ( is_callable( array( 'AFWC_Landing_Page', 'is_enabled' ) ) && ! AFWC_Landing_Page::is_enabled() ) {
				return array();
			}

			$landing_page = AFWC_Landing_Page::get_instance();

			$page_ids = is_callable( array( $landing_page, 'get_pages_by_affiliate_id' ) ) ? (array) $landing_page->get_pages_by_affiliate_id( $this->affiliate_id ) : array();
			if ( empty( $page_ids ) || ! is_array( $page_ids ) ) {
				return array();
			}

			$page_links = array();

			foreach ( $page_ids as $id ) {
				// fetch the URL of the published post's permalink.
				$link = 'publish' === get_post_status( $id ) ? get_permalink( $id ) : '';
				if ( empty( $link ) ) {
					continue;
				}
				$page_links[ $id ] = $link;
			}

			return apply_filters(
				'afwc_landing_page_links',
				$page_links,
				array(
					'affiliate_id' => $this->affiliate_id,
					'source'       => $this,
				)
			);
		}

		/**
		 * Get the affiliate link.
		 *
		 * @param string $url Page URL, if value is empty or invalid use home_url().
		 *
		 * @return string The referral link.
		 */
		public function get_affiliate_link( $url = '' ) {
			$parsed_url = '';
			if ( ! empty( $url ) ) {
				$parsed_url = trim( $url );
				$parsed_url = wc_is_valid_url( $parsed_url ) ? $parsed_url : filter_var( current( wp_extract_urls( $parsed_url ) ), FILTER_SANITIZE_URL );
			}

			if ( ! empty( $parsed_url ) ) {
				$parse_url = wp_parse_url( $parsed_url );
				$url       = ( ! empty( $parse_url ) && is_array( $parse_url ) && ! empty( $parse_url['query'] ) ) ? untrailingslashit( $parsed_url ) : trailingslashit( $parsed_url );
			} else {
				$url = trailingslashit( home_url() );
			}

			$affiliate_identifier = $this->get_identifier();

			// Generate the affiliate link.
			return ( ! empty( $affiliate_identifier ) ) ? afwc_get_affiliate_url( $url, '', $affiliate_identifier ) : '';
		}

		/**
		 * Get the affiliate identifier.
		 *
		 * @return int|string Return the affiliate identifier.
		 */
		public function get_identifier() {
			if ( empty( $this->ID ) || empty( $this->affiliate_id ) ) {
				return '';
			}

			// Get the affiliate's reference URL ID from user meta.
			$ref_url_id = ( 'yes' === get_option( 'afwc_allow_custom_affiliate_identifier', 'yes' ) ) ? get_user_meta( $this->ID, 'afwc_ref_url_id', true ) : '';

			// Determine the affiliate identifier to use for the link.
			return ( ! empty( $ref_url_id ) ) ? $ref_url_id : $this->affiliate_id;
		}

		/**
		 * Set the affiliate signup date in user meta with GMT and `Y-m-d H:i:s` format.
		 * If the date is provided, It consider that otherwise store the current date time.
		 *
		 * @param string $date The signup date with GMT timezone.
		 *
		 * @return int|bool Return true on successfully set or false on failure.
		 */
		public function set_signup_date( $date = '' ) {

			if ( empty( $this->ID ) || empty( $this->affiliate_id ) ) {
				return false;
			}

			if ( ! empty( get_user_meta( $this->ID, 'afwc_signup_date', true ) ) ) {
				// Do not update signup date if already present in the user meta.
				return false;
			}

			$format = 'Y-m-d H:i:s';

			$date = ! empty( $date ) ? gmdate( $format, strtotime( $date ) ) : current_time( $format, true );

			return ! empty( $date ) ? (bool) update_user_meta( $this->ID, 'afwc_signup_date', $date ) : false;
		}

		/**
		 * Get the affiliate signup date from user meta.
		 * Set signup date in the user meta first if not found from user meta.
		 *
		 * @param string $format Date format.
		 * @param bool   $gmt Whether to return GMT or site timezone.
		 *
		 * @return string Return the affiliate signup date.
		 */
		public function get_signup_date( $format = '', $gmt = false ) {

			$format = ! empty( $format ) ? $format : get_option( 'date_format', 'd-M-Y' );

			if ( empty( $this->ID ) || empty( $this->affiliate_id ) ) {
				return '';
			}

			$signup_date = get_user_meta( $this->ID, 'afwc_signup_date', true );

			if ( empty( $signup_date ) ) {
				return '';
			}

			return ! empty( $gmt ) ? gmdate( $format, strtotime( $signup_date ) ) : get_date_from_gmt( $signup_date, $format );
		}

		/**
		 * Get the affiliate registration form data.
		 *
		 * @return array|bool
		 */
		public function get_registration_form_data() {
			if ( empty( $this->ID ) ) {
				return false;
			}

			$additional_data = get_user_meta( $this->ID, 'afwc_additional_fields', true );
			if ( is_array( $additional_data ) ) {
				foreach ( $additional_data as $i => &$field ) {
					if ( ! isset( $field['value'] ) || empty( $field['value'] ) ) {
						unset( $additional_data[ $i ] );
						continue;
					}

					$label   = ! empty( $field['label'] ) ? esc_html( $field['label'] ) : '';
					$value   = wp_kses_post( $field['value'] );
					$has_url = false;

					if ( ! empty( $field['type'] ) && in_array( $field['type'], array( 'file', 'url' ), true ) ) {
						$data_urls = explode( ',', $field['value'] );
						$value     = implode(
							', ',
							array_map(
								function ( $url ) {
									return esc_url( trim( $url ) );
								},
								$data_urls
							)
						);
						$has_url   = true;
					}

					$additional_data[ $i ] = array(
						'label'   => $label,
						'value'   => $value,
						'has_url' => $has_url,
					);
				}
				$additional_data = array_values( $additional_data );
			} else {
				$additional_data = array();
			}

			return array(
				'first_name'      => ! empty( $this->first_name ) ? $this->first_name : '',
				'last_name'       => ! empty( $this->last_name ) ? $this->last_name : '',
				'user_url'        => ! empty( $this->user_url ) ? $this->user_url : '',
				'contact'         => get_user_meta( $this->ID, 'afwc_affiliate_contact', true ),
				'skype'           => get_user_meta( $this->ID, 'afwc_affiliate_skype', true ),
				'description'     => get_user_meta( $this->ID, 'afwc_affiliate_desc', true ),
				'additional_data' => $additional_data,
			);
		}

		/**
		 * Method to get the payout method.
		 *
		 * @return string Return the payout method if selected otherwise empty.
		 */
		public function get_payout_method() {
			if ( empty( $this->ID ) ) {
				return '';
			}

			$payout_method = get_user_meta( $this->ID, 'afwc_payout_method', true );
			if ( empty( $payout_method ) ) {
				return '';
			}

			$available_payout_methods = afwc_get_available_payout_methods();
			if ( empty( $available_payout_methods ) || ! is_array( $available_payout_methods ) ) {
				return '';
			}

			return in_array( $payout_method, array_keys( $available_payout_methods ), true ) ? $payout_method : '';
		}

		/**
		 * Method to get payout meta for affiliate based on saved payout method.
		 *
		 * @param string $payout_method Payout method name.
		 * @return string
		 */
		public function get_payout_meta_for_payouts( $payout_method = '' ) {
			if ( empty( $this->ID ) ) {
				return '';
			}

			if ( empty( $payout_method ) ) {
				return '';
			}

			$ap_payout_methods = afwc_get_automatic_payout_methods();
			if ( empty( $ap_payout_methods ) || ! is_array( $ap_payout_methods ) ) {
				return '';
			}
			if ( ! in_array( $payout_method, $ap_payout_methods, true ) ) {
				return '';
			}

			$class = ( 'paypal' === $payout_method ) ? 'AFWC_PayPal_API' : ( 'stripe' === $payout_method ? 'AFWC_Stripe_API' : '' );
			if ( empty( $class ) ) {
				return '';
			}

			$class_check = is_callable( array( $class, 'get_instance' ) ) ? $class::get_instance() : null;
			if ( ! empty( $class_check ) && is_callable( array( $class_check, 'get_payout_meta' ) ) ) {
				$payout_meta = $class_check->get_payout_meta( $this->ID );
			}

			if ( empty( $payout_meta ) ) {
				return '';
			} else {
				return $payout_meta;
			}
		}
	}
}
