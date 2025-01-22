<?php
/**
 * Class to update the ip fields in hits and referral table.
 *
 * @package     affiliate-for-woocommerce/includes/upgrades
 * @since       7.14.0
 * @version     1.0.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Background_Process', false ) ) {
	include_once AFWC_PLUGIN_DIRPATH . '/includes/abstracts/class-afwc-background-process.php';
}

if ( ! class_exists( 'AFWC_IP_Field_Updates' ) && class_exists( 'AFWC_Background_Process' ) ) {

	/**
	 * AFWC_Background_Process class.
	 */
	class AFWC_IP_Field_Updates extends AFWC_Background_Process {

		/**
		 * Variable to hold instance of this class.
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Get the single instance of this class
		 *
		 * @return AFWC_IP_Field_Updates
		 */
		public static function get_instance() {
			// Check if the instance already exists.
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor
		 */
		private function __construct() {
			// Set the batch limit.
			$this->batch_limit = 50;

			// Set the action name.
			$this->action = 'afwc_ip_address_update';

			// Initialize the parent class to execute background process.
			parent::__construct();
		}

		/**
		 * Execute the task for each batch.
		 *
		 * @param array $args The arguments to run the batch.
		 *
		 * @throws Exception If any problem during the process.
		 */
		public function task( $args = array() ) {
			if ( empty( $args['table'] ) || empty( $args['data'] ) || ! is_array( $args['data'] ) ) {
				throw new Exception(
					sprintf(
						/* translators: 1: Current task name */
						_x( 'Neither table nor data are available to run the task in %s', 'Error message for IP update task', 'affiliate-for-woocommerce' )
					),
					__CLASS__
				);
			}

			global $wpdb;
			$wpdb1 = $wpdb;

			$data  = $args['data'];
			$table = $args['table'];

			$set_query    = '';
			$index_ids    = array();
			$index_column = '';

			if ( 'afwc_hits' === $args['table'] ) {
				$index_col = 'id';
			} elseif ( 'afwc_referrals' === $args['table'] ) {
				$index_col = 'referral_id';
			}

			if ( empty( $index_col ) ) {
				throw new Exception(
					sprintf(
						/* translators: 1: Current task name */
						_x( 'Index column is not available to run the task in %s', 'Error message for IP update task', 'affiliate-for-woocommerce' )
					),
					__CLASS__
				);
			}

			foreach ( $data as $item ) {

				if ( empty( $item['id'] ) || empty( $item['ip'] ) ) {

					Affiliate_For_WooCommerce::get_instance()->log(
						'warning',
						sprintf(
							/* translators: 1: Current task name */
							_x( 'Neither ID nor IP are available to run the task in %s', 'Error message for IP update task', 'affiliate-for-woocommerce' ),
							__CLASS__
						)
					);

					continue;
				}

				$id     = intval( $item['id'] );
				$ip_raw = long2ip( $item['ip'] );

				if ( empty( $ip_raw ) ) {
					$ip_raw = '0.0.0.0';
				}

				$index_ids[] = $id;
				$set_query  .= $wpdb1->prepare( "WHEN {$index_col} = %d THEN %s ", $id, $ip_raw );
			}

			if ( empty( $index_ids ) || ! is_array( $index_ids ) || empty( $set_query ) ) {
				throw new Exception(
					sprintf(
						/* translators: 1: Current task name */
						_x( 'Query could not be prepared to run the task in %s', 'Error message for IP update task', 'affiliate-for-woocommerce' ),
						__CLASS__
					)
				);
			}

			$update_result = $wpdb1->query(
				$wpdb1->prepare(
					"UPDATE {$wpdb1->prefix}{$table}
					SET ip = (CASE " . $set_query . "ELSE ip END)
					WHERE {$index_col} IN (" . implode( ',', array_fill( 0, count( $index_ids ), '%d' ) ) . ')',
					$index_ids
				)
			);

			if ( is_wp_error( $update_result ) ) {
				throw new Exception( is_callable( array( $update_result, 'get_error_message' ) ) ? $update_result->get_error_message() : '' );
			}

			// Check process health before continuing.
			if ( ! $this->health_status() ) {
				throw new Exception(
					sprintf(
						/* translators: 1: The task class name */
						_x( 'Batch stopped due to health status in task: %s', 'Logger for batch stopped due to health status', 'affiliate-for-woocommerce' ),
						__CLASS__
					)
				);
			}
		}

		/**
		 * Get the remaining items for doing the action.
		 *
		 * @return array The array of hit ID and long formatted IP address.
		 */
		public function get_remaining_items() {
			$hit_data = $this->get_data_from_hits();

			if ( ! empty( $hit_data ) ) {
				return $hit_data;
			}

			$referral_data = $this->get_data_from_referrals();

			if ( ! empty( $referral_data ) ) {
				return $referral_data;
			}

			return array();
		}

		/**
		 * Trigger when completed the process.
		 */
		public function completed() {
			parent::completed();
			update_option( '_afwc_current_db_version', '1.3.5', 'no' );
		}

		/**
		 * Get the remaining items from afwc_hits table.
		 *
		 * @return array The array of hit ID and long formatted IP address.
		 */
		public function get_data_from_hits() {
			global $wpdb;

			$table = 'afwc_hits';

			$result = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT id, ip
                        FROM {$wpdb->prefix}afwc_hits
                    WHERE ip != 0 
						AND ip IS NOT NULL
						AND	ip NOT LIKE %s
						AND ip NOT LIKE %s
                    LIMIT %d",
					'%' . $wpdb->esc_like( '.' ) . '%',
					'%' . $wpdb->esc_like( ':' ) . '%',
					$this->batch_limit
				),
				'ARRAY_A'
			);

			if ( is_wp_error( $result ) ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					sprintf(
						/* translators: 1: Table name 2: Error message */
						_x( 'Error fetching IP address in %1$s table. Error message: %2$s', 'Error message for IP update task', 'affiliate-for-woocommerce' ),
						$table,
						is_callable( array( $result, 'get_error_message' ) ) ? $result->get_error_message() : ''
					)
				);
			}

			if ( empty( $result ) ) {
				return array();
			}

			return array(
				'table' => $table,
				'data'  => $result,
			);
		}

		/**
		 * Get the remaining items from afwc_referrals table.
		 *
		 * @return array The array of referral ID and long formatted IP address.
		 */
		public function get_data_from_referrals() {
			global $wpdb;

			$table = 'afwc_referrals';

			$result = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT referral_id AS id, ip
                        FROM {$wpdb->prefix}afwc_referrals
					WHERE ip != 0 
						AND ip IS NOT NULL
						AND	ip NOT LIKE %s
						AND ip NOT LIKE %s
                    LIMIT %d",
					'%' . $wpdb->esc_like( '.' ) . '%',
					'%' . $wpdb->esc_like( ':' ) . '%',
					$this->batch_limit
				),
				'ARRAY_A'
			);

			if ( is_wp_error( $result ) ) {
				Affiliate_For_WooCommerce::get_instance()->log(
					'error',
					sprintf(
						/* translators: 1: Table name 2: Error message */
						_x( 'Error fetching IP address in %1$s table. Error message: %2$s', 'Error message for IP update task', 'affiliate-for-woocommerce' ),
						$table,
						is_callable( array( $result, 'get_error_message' ) ) ? $result->get_error_message() : ''
					)
				);
				return array();
			}

			if ( empty( $result ) ) {
				return array();
			}

			return array(
				'table' => $table,
				'data'  => $result,
			);
		}
	}
}
