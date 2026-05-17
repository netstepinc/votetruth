<?php
/**
 * Freedom Index by Sam Mittelstaedt <smittelstaedt@jbs.org>
 *
 * AJAX handlers: user lists
 */

namespace FI\Public {
	if ( ! defined( 'ABSPATH' ) ) { exit; }

	trait AjaxHandlersListsTrait {

		/**
		 * Handle create list (account lists page: nonce fi_list_manage; legislator_ids array or none).
		 */
		public function handle_create_list() {
			if (!is_user_logged_in()) {
				wp_send_json_error(['message' => 'Must be logged in']);
			}
			check_ajax_referer('fi_list_manage', 'nonce');
			$name = sanitize_text_field($_POST['name'] ?? '');
			$result = fi_list_create($name);
			if (isset($result['list_id'])) {
				wp_send_json_success($result);
			}
			wp_send_json_error($result);
		}

		/**
		 * Modal-only: create list from legislator modal. Uses direct INSERT to avoid wpdb->insert charset issues on custom table.
		 * Does not share code with account create (fi_create_list); safe to change without affecting account CRUD.
		 */
		public function handle_modal_create_list() {
			if (!is_user_logged_in()) {
				wp_send_json_error(['message' => 'Must be logged in']);
			}

			check_ajax_referer('fi_list_nonce', 'nonce');
			$name = sanitize_text_field($_POST['name'] ?? '');

			// Specifically handle legislator modal create, where 'legislators' is a JSON string like "[102345]"
			$legislator_ids = [];
			if (isset($_POST['legislators']) && is_string($_POST['legislators']) && $_POST['legislators'] !== '') {
				$decoded = json_decode($_POST['legislators'], true);
				if (is_array($decoded)) {
					$legislator_ids = array_values(array_map('intval', array_filter($decoded)));
				}
			}

			if (strlen($name) === 0) {
				wp_send_json_error(['message' => 'List name required']);
			}
			if (count($legislator_ids) > 20) {
				wp_send_json_error(['message' => 'Maximum 20 legislators per list']);
			}
			$result = fi_list_create($name, $legislator_ids);

			if (isset($result['list_id'])) {
				wp_send_json_success($result);
			}
			wp_send_json_error($result);
		}

		/**
		 * Handle saving list (modal, inline-js, my-legislators: nonce fi_list_nonce; legislators JSON or legislator_ids array).
		 */

		public function handle_save_list() {
			if (!is_user_logged_in()) {
				wp_send_json_error(['message' => 'Must be logged in']);
			}
			check_ajax_referer('fi_list_nonce', 'nonce');
			$name = sanitize_text_field($_POST['name'] ?? '');
			//$legislator_ids = $this->normalize_create_list_legislator_ids();
			$legislator_ids = $_POST['legislators'] ?? [];

			if (!is_array($legislator_ids)) {
				$legislator_ids = [];
			}
			$legislator_ids = array_values(array_map('intval', array_filter($legislator_ids)));
			if (count($legislator_ids) > 20) {
				wp_send_json_error(['message' => 'Maximum 20 legislators per list']);
			}
			$result = fi_list_create($name, $legislator_ids);
fi_log('handle_save_list: NAME: '.$name.' | LEGISLATORS: '.json_encode($legislator_ids).' | RESULT: '.json_encode($result),__FILE__,__LINE__);

			if (isset($result['list_id'])) {
				wp_send_json_success($result);
			}
			wp_send_json_error($result);
		}



		/**
		* Handle updating list (add/remove legislator)
		*/
		public function handle_update_list() {
			if (!is_user_logged_in()) {
				wp_send_json_error('Must be logged in');
			}
			
			check_ajax_referer('fi_list_nonce', 'nonce');
			
			$list_id = intval($_POST['list_id'] ?? 0);
			$legislator_id = intval($_POST['legislator_id'] ?? 0);
			$add = isset($_POST['add']) && $_POST['add'] === '1';
			$user_id = get_current_user_id();

			if (!$list_id || !$legislator_id) {
				wp_send_json_error('Invalid list ID or legislator ID');
			}
			
			global $wpdb;
			
			// Get current list
			$list = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fi_user_lists WHERE id = %d AND user_id = %d",
				$list_id, $user_id
			));
			
			if (!$list) {
				wp_send_json_error('List not found');
			}
			
			$legislator_ids = json_decode($list->legislators, true);
			if (!is_array($legislator_ids)) {
				$legislator_ids = array();
			}
			
			// Add or remove legislator
			if ($add) {
				if (!in_array($legislator_id, $legislator_ids)) {
					if (count($legislator_ids) >= 20) {
						wp_send_json_error('Maximum 20 legislators per list');
					}
					$legislator_ids[] = $legislator_id;
				}
			} else {
				$legislator_ids = array_values(array_filter($legislator_ids, function($id) use ($legislator_id) {
					return $id != $legislator_id;
				}));
			}
			
			// Update list
			$result = $wpdb->update(
				$wpdb->prefix . 'fi_user_lists',
				array('legislators' => json_encode($legislator_ids)),
				array('id' => $list_id, 'user_id' => $user_id),
				array('%s'),
				array('%d', '%d')
			);
fi_log('handle_update_list: LIST_ID: '.$list_id.' | LEGISLATOR_ID: '.$legislator_id.' | ADD: '.$add.' | RESULT: '.json_encode($result),__FILE__,__LINE__);
			
			if ($result !== false) {
				wp_send_json_success(array(
					'count' => count($legislator_ids)
				));
			} else {
				wp_send_json_error('Failed to update list');
			}
		}

		/**
		 * Handle update list name
		 */
		public function handle_update_list_name() {
			if (!is_user_logged_in()) {
				wp_send_json_error(['message' => 'Must be logged in']);
			}
			
			check_ajax_referer('fi_list_manage', 'nonce');
			
			$list_id = intval($_POST['list_id'] ?? 0);
			$name = sanitize_text_field($_POST['name'] ?? '');
			$user_id = get_current_user_id();
			
			if (!$list_id || empty($name)) {
				wp_send_json_error(['message' => 'Invalid list ID or name']);
			}
			global $wpdb;
			$result = $wpdb->update(
				$wpdb->prefix . 'fi_user_lists',
				['name' => $name],
				['id' => $list_id, 'user_id' => $user_id],
				['%s'],
				['%d', '%d']
			);
fi_log('handle_update_list_name: USER_ID: '.$user_id.' | LIST_ID: '.$list_id.' | NAME: '.$name.' | RESULT: '.json_encode($result),__FILE__,__LINE__);			


			if ($result !== false) {
				wp_send_json_success(['message' => 'List name updated successfully']);
			} else {
				wp_send_json_error(['message' => 'Failed to update list name']);
			}
		}

		/**
		 * Handle update list contact selection
		 */
		public function handle_update_list_contact() {
			if (!is_user_logged_in()) {
				wp_send_json_error(['message' => 'Must be logged in']);
			}
			
			check_ajax_referer('fi_list_manage', 'nonce');
			
			$list_id = intval($_POST['list_id'] ?? 0);
			$contact_index_raw = $_POST['contact_index'] ?? '';
			$contact_index = ($contact_index_raw !== '' && $contact_index_raw !== null) ? intval($contact_index_raw) : null;
			$user_id = get_current_user_id();

			if (!$list_id) {
				wp_send_json_error(['message' => 'Invalid list ID']);
			}
			
			// Verify user owns the list
			$list_obj = fi_list_get_by_id($list_id);
			if (!$list_obj || $list_obj->user_id != $user_id) {
				wp_send_json_error(['message' => 'You do not have permission to update this list']);
			}
			
			// Verify contact exists if one is selected
			if ($contact_index !== null) {
				$pdf_contacts = fi_pdf_contacts_get($user_id);
				if (!isset($pdf_contacts[$contact_index])) {
					wp_send_json_error(['message' => 'Invalid contact selection']);
				}
			}
			
			// Get existing meta (if column exists, otherwise use empty array)
			$meta = [];
			if (isset($list_obj->meta) && !empty($list_obj->meta)) {
				$decoded = json_decode($list_obj->meta, true);
				if (is_array($decoded)) {
					$meta = $decoded;
				}
			}
			
			// Update contact_index
			if ($contact_index !== null) {
				$meta['contact_index'] = $contact_index;
			} else {
				unset($meta['contact_index']);
			}
			
			// Use existing fi_list_save method which handles meta properly
			// Always include meta (even if empty array) so it's not filtered out by save method
			$result = fi_list_save([
				'user_id' => $user_id,
				'name' => $list_obj->name,
				'legislators' => json_decode($list_obj->legislators, true),
				'meta' => $meta // Always include, even if empty (will be '{}' when encoded)
			], $list_id);

fi_log('handle_update_list_contact: LIST_ID: '.$list_id.' | CONTACT_INDEX: '.$contact_index.' | RESULT: '.json_encode($result),__FILE__,__LINE__);


			if ($result !== false) {
				wp_send_json_success(['message' => 'Contact selection updated successfully']);
			} else {
				wp_send_json_error(['message' => 'Failed to update contact selection']);
			}
		}

		public function handle_delete_list() {
			if (!is_user_logged_in()) {
				wp_send_json_error('Must be logged in');
			}
			
			check_ajax_referer('fi_delete_list', 'nonce');
			
			$list_id = intval($_POST['list_id'] ?? 0);
			$user_id = get_current_user_id();
			
			if (!$list_id) {
				wp_send_json_error('Invalid list ID');
			}
			
			global $wpdb;
			
			$result = $wpdb->delete(
				$wpdb->prefix . 'fi_user_lists',
				array(
					'id' => $list_id,
					'user_id' => $user_id
				),
				array('%d', '%d')
			);
			
			if ($result) {
				wp_send_json_success();
			} else {
				wp_send_json_error('Failed to delete list');
			}
		}
	}
}