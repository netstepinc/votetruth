<?php
/**
 * Freedom Index by Sam Mittelstaedt <smittelstaedt@jbs.org>
 *
 * Account profile + address: AJAX handlers (same pattern as PDF contacts).
 */

namespace FI\Public {
	if ( ! defined( 'ABSPATH' ) ) { exit; }

	trait AjaxHandlersAccountTrait {

		/**
		 * Profile update (AJAX): email, display name, password.
		 */
		public function handle_update_profile_ajax() {
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
			}
			check_ajax_referer( 'fi_update_profile', 'nonce' );

			$user_id     = get_current_user_id();
			$update_data = array();
			if ( isset( $_POST['user_email'] ) ) {
				$update_data['user_email'] = sanitize_email( wp_unslash( $_POST['user_email'] ) );
			}
			if ( isset( $_POST['display_name'] ) ) {
				$update_data['display_name'] = sanitize_text_field( wp_unslash( $_POST['display_name'] ) );
			}
			if ( ! empty( $_POST['user_pass'] ) ) {
				$update_data['user_pass'] = $_POST['user_pass'];
				$update_data['user_pass_confirm'] = isset( $_POST['user_pass_confirm'] ) ? $_POST['user_pass_confirm'] : '';
			}

			$result = fi_user_profile_update( $user_id, $update_data );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ) );
			}
			wp_send_json_success( array( 'message' => 'Profile updated.' ) );
		}

		/**
		 * Address update (AJAX).
		 */
		public function handle_update_address_ajax() {
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
			}
			check_ajax_referer( 'fi_update_address', 'nonce' );

			$user_id = get_current_user_id();
			$address_data = array(
				'first_name' => sanitize_text_field( $_POST['fi_first_name'] ?? $_POST['shipping_first_name'] ?? '' ),
				'last_name'  => sanitize_text_field( $_POST['fi_last_name'] ?? $_POST['shipping_last_name'] ?? '' ),
				'address_1'  => sanitize_text_field( $_POST['fi_address_1'] ?? $_POST['shipping_address_1'] ?? '' ),
				'address_2'  => sanitize_text_field( $_POST['fi_address_2'] ?? $_POST['shipping_address_2'] ?? '' ),
				'city'       => sanitize_text_field( $_POST['fi_city'] ?? $_POST['shipping_city'] ?? '' ),
				'state'      => sanitize_text_field( $_POST['fi_state'] ?? $_POST['shipping_state'] ?? '' ),
				'postcode'   => sanitize_text_field( $_POST['fi_postcode'] ?? $_POST['shipping_postcode'] ?? '' ),
				'country'    => sanitize_text_field( $_POST['fi_country'] ?? $_POST['shipping_country'] ?? '' ),
			);
			// Do not array_filter: address_2 (and other optional fields) may be empty; keep all keys so save succeeds and empty values are stored
			$result = fi_user_meta_save( $user_id, $address_data );
			if ( ! $result ) {
				wp_send_json_error( array( 'message' => 'Failed to save address.' ) );
			}
			wp_send_json_success( array( 'message' => 'Address saved.' ) );
		}
	}
}

