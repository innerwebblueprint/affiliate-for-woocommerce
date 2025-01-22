<?php
/**
 * Main class for Affiliate Merge tags.
 *
 * @package    affiliate-for-woocommerce/includes/
 * @since      6.24.0
 * @version    1.1.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AFWC_Merge_Tags' ) ) {

	/**
	 * Class to handle the Affiliate Merge tags.
	 */
	class AFWC_Merge_Tags {

		/**
		 * Array to hold merge tags.
		 *
		 * @var array
		 */
		public $merge_tags = array();

		/**
		 * Singleton instance of AFWC_Merge_Tags.
		 *
		 * @var AFWC_Merge_Tags|null
		 */
		private static $instance = null;

		/**
		 * Get the singleton instance of this class
		 *
		 * @return AFWC_Merge_Tags Singleton instance of this class
		 */
		public static function get_instance() {
			// Check if instance already exists.
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor
		 */
		private function __construct() {
			// Set the merge tags.
			$this->set_tags();

			// Hook to decode the merge tags for post content.
			add_filter( 'the_content', array( $this, 'parse_content' ), 9, 2 );

			// Hook to decode the merge tags for post excerpt.
			add_filter( 'get_the_excerpt', array( $this, 'parse_content' ) );

			// Hook to decode the merge tags for product short description.
			add_filter( 'woocommerce_short_description', array( $this, 'parse_content' ) );
		}

		/**
		 * Set the predefined merge tags.
		 */
		public function set_tags() {
			$this->merge_tags = apply_filters(
				'afwc_merge_tags',
				array(
					'afwc_affiliate_link',
					'afwc_affiliate_coupon',
				)
			);
		}

		/**
		 * Get the value of a merge tag.
		 *
		 * @param string             $tag       The merge tag to retrieve.
		 * @param AFWC_Affiliate|int $affiliate An instance of AFWC_Affiliate or affiliate ID.
		 * @param array              $attrs     The attributes.
		 *
		 * @return mixed Value of the merge tag.
		 */
		public function get_value( $tag = '', $affiliate = 0, $attrs = array() ) {
			if ( empty( $tag ) || empty( $affiliate ) ) {
				return '';
			}

			// If the affiliate parameter is an ID, create an AFWC_Affiliate instance.
			$affiliate = is_numeric( $affiliate ) ? new AFWC_Affiliate( intval( $affiliate ) ) : $affiliate;

			if ( ! $affiliate instanceof AFWC_Affiliate || empty( $affiliate->ID ) ) {
				return '';
			}

			$value = '';

			switch ( $tag ) {
				case 'afwc_affiliate_link':
					$value = is_callable( array( $affiliate, 'get_affiliate_link' ) ) ? $affiliate->get_affiliate_link( ! empty( $attrs['link'] ) ? $attrs['link'] : '' ) : '';
					break;
				case 'afwc_affiliate_coupon':
					$value = ''; // Currently value is not available for all scopes.
					break;
			}

			// Apply filters to the merge tag value before returning.
			return apply_filters(
				"afwc_get_merge_tag_value_{$tag}",
				$value,
				array(
					'affiliate' => $affiliate,
					'attrs'     => $attrs,
					'source'    => $this,
				)
			);
		}

		/**
		 * Get the merge tags from the contents.
		 *
		 * @param string $content Content to check for merge tags.
		 *
		 * @return array Array of merge tags & their attributes exist in the given content.
		 */
		public function get_tags_by_content( $content = '' ) {
			if ( empty( $content ) || empty( $this->merge_tags ) || ! is_array( $this->merge_tags ) ) {
				return array();
			}

			preg_match_all( '/\{([^{}\s]+)(\s+[^{}]+)?\}/', $content, $matches, PREG_SET_ORDER );

			return ! empty( $matches ) ? array_filter( array_map( array( $this, 'get_matched_merge_tags' ), $matches ) ) : array();
		}

		/**
		 * Structure the merge tags with it's attributes.
		 *
		 * @param array $matches {
		 *     Regular expression match array.
		 *
		 *     @type string $0 Entire matched text.
		 *     @type string $1 Merge tag name.
		 *     @type string $2 Merge tag attributes.
		 * }
		 *
		 * @return array The modified data.
		 */
		public function get_matched_merge_tags( $matches = array() ) {

			if ( empty( $this->merge_tags ) || ! is_array( $this->merge_tags ) || empty( $matches ) || empty( $matches[1] ) || ! in_array( $matches[1], $this->merge_tags, true ) ) {
				return array();
			}

			return array(
				'matched_content' => ! empty( $matches[0] ) ? $matches[0] : '',
				'name'            => $matches[1],
				'attrs'           => ! empty( $matches[2] ) ? shortcode_parse_atts( $matches[2] ) : array(),
			);
		}

		/**
		 * Method to decode the merge tags of the provided content.
		 *
		 * @param string $content Content to decode the merge tags.
		 * @param array  $args The arguments for merge tags.
		 *
		 * @return string Return the formatted content.
		 */
		public function parse_content( $content = '', $args = array() ) {
			if ( empty( $content ) || ! is_array( $args ) ) {
				return $content;
			}

			// Merge tags that are present in the content.
			$merge_tags = $this->get_tags_by_content( $content );

			// Return if no merge tag is available in the content.
			if ( empty( $merge_tags ) || ! is_array( $merge_tags ) ) {
				return $content;
			}

			$affiliate = ! empty( $args['affiliate'] ) ? $args['affiliate'] : $this->get_affiliate_id_for_merge_tag_content();

			// If the affiliate is an ID, create an AFWC_Affiliate instance.
			$affiliate_obj = ( is_numeric( $affiliate ) && $affiliate > 0 ) ? new AFWC_Affiliate( intval( $affiliate ) ) : $affiliate;

			$tag_values = array();
			foreach ( $merge_tags as $merge_tag ) {
				if ( empty( $merge_tag['name'] ) || empty( $merge_tag['matched_content'] ) ) {
					continue;
				}
				$matched_content = $merge_tag['matched_content'];
				// Set a blank value if the provided affiliate object is not an AFWC_Affiliate.
				$tag_values[ $matched_content ] = ! empty( $affiliate_obj ) && $affiliate_obj instanceof AFWC_Affiliate ? $this->get_value(
					$merge_tag['name'],
					$affiliate_obj,
					! empty( $merge_tag['attrs'] ) ? $merge_tag['attrs'] : array()
				) : '';
			}

			return ! empty( $tag_values ) ? str_replace( array_keys( $tag_values ), array_values( $tag_values ), $content ) : $content;
		}

		/**
		 * Method to get the affiliate ID for merge tag content.
		 *
		 * Checks if the landing page feature is enabled. If so, it attempts to retrieve the affiliate ID associated with the current landing page.
		 * If no affiliate ID is found, it checks if the current user is an affiliate and returns their ID if applicable.
		 *
		 * @return int $affiliate_id Affiliate ID if found, otherwise returns zero.
		 */
		public function get_affiliate_id_for_merge_tag_content() {
			// Check if the landing page feature is enabled & retrieve the affiliate ID based from the current page, if available.
			if ( is_callable( array( 'AFWC_Landing_Page', 'get_instance' ) ) && is_callable( array( 'AFWC_Landing_Page', 'is_enabled' ) ) && AFWC_Landing_Page::is_enabled() ) {
				$landing_page              = AFWC_Landing_Page::get_instance();
				$landing_page_affiliate_id = $landing_page->get_affiliate_id_by_current_singular_post();
				if ( ! empty( $landing_page_affiliate_id ) ) {
					return $landing_page_affiliate_id;
				}
			}

			// Check if the current user is an affiliate.
			$current_user_id = get_current_user_id();
			if ( ! empty( $current_user_id ) && 'yes' === afwc_is_user_affiliate( $current_user_id ) ) {
				return $current_user_id;
			}

			return 0;
		}

	}

}

AFWC_Merge_Tags::get_instance();
