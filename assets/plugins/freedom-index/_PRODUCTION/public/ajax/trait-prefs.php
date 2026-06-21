<?php
/**
 * Freedom Index by Sam Mittelstaedt <smittelstaedt@jbs.org>
 *
 * AJAX handlers: user preferences
 */

namespace FI\Public {
	if ( ! defined( 'ABSPATH' ) ) { exit; }

	trait AjaxHandlersPrefsTrait {

		/**
		* Handle saving user preferences
		*/
		public function handle_save_prefs() {
			if (!is_user_logged_in()) {
				wp_send_json_error('Must be logged in');
			}
			
			check_ajax_referer('fi_ajax_nonce', 'nonce');
			
			$prefs = array(
				'name' => sanitize_text_field($_POST['name'] ?? ''),
				'phone' => sanitize_text_field($_POST['phone'] ?? ''),
				'email' => sanitize_email($_POST['email'] ?? ''),
				'zip' => sanitize_text_field($_POST['zip'] ?? '')
			);
			
			fi_user_prefs_save(get_current_user_id(), $prefs);
			
			wp_send_json_success();
		}

		/**
		* Handle syncing localStorage to user meta
		*/
		public function handle_sync_prefs() {
			if (!is_user_logged_in()) {
				wp_send_json_error('Must be logged in');
			}
			
			check_ajax_referer('fi_ajax_nonce', 'nonce');
			
			$prefs_json = sanitize_text_field($_POST['prefs'] ?? '');
			$prefs = json_decode($prefs_json, true);
			
			if ($prefs && is_array($prefs)) {
				fi_user_prefs_save(get_current_user_id(), $prefs);
			}
			
			wp_send_json_success();
		}
	}
}

