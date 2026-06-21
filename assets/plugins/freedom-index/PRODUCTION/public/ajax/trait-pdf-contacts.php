<?php
/**
 * Freedom Index by Sam Mittelstaedt <smittelstaedt@jbs.org>
 *
 * Admin-post + AJAX handlers: PDF contacts
 */

namespace FI\Public {
	if ( ! defined( 'ABSPATH' ) ) { exit; }

	trait AjaxHandlersPdfContactsTrait {

		/**
		 * Set default PDF contact (admin-post)
		 */
		public function handle_set_default_pdf_contact() {
			if (!is_user_logged_in()) {
				wp_safe_redirect(home_url('/account/'));
				exit;
			}

			check_admin_referer('fi_set_default_pdf_contact', 'fi_default_contact_nonce');

			$user_id = get_current_user_id();
			$index_raw = isset($_POST['default_index']) ? sanitize_text_field($_POST['default_index']) : '';
			$index = ($index_raw === '') ? null : (int) $index_raw;

			$result = fi_pdf_contacts_default_index_set($user_id, $index);

			$redirect = home_url('/account/personalize/');
			if ($result) {
				wp_safe_redirect(add_query_arg('default_updated', '1', $redirect));
			} else {
				wp_safe_redirect(add_query_arg('error', 'default_failed', $redirect));
			}
			exit;
		}

		/**
		 * Handle PDF contact save (admin-post)
		 */
		public function handle_save_pdf_contact() {
			if (!is_user_logged_in()) {
				wp_die('You must be logged in to save contact information.');
			}
			
			check_admin_referer('fi_save_pdf_contact', 'fi_pdf_contact_nonce');
			
			$user_id = get_current_user_id();
			
			// Get form data
			$contact_data = [
				'name' => $_POST['contact_name'] ?? '',
				'phone' => $_POST['contact_phone'] ?? '',
				'email' => $_POST['contact_email'] ?? '',
			];
			
			// Get edit index if editing
			$edit_index = isset($_POST['edit_index']) && $_POST['edit_index'] !== '' ? (int)$_POST['edit_index'] : null;
			
			// Save contact using fi_pdf_contacts_save
			$result = fi_pdf_contacts_save($user_id, $contact_data, $edit_index);
			
			if ($result) {
				wp_redirect(add_query_arg('updated', '1', home_url('/account/personalize/')));
			} else {
				wp_redirect(add_query_arg('error', 'save_failed', home_url('/account/personalize/')));
			}
			exit;
		}

		/**
		 * Handle PDF contact save (AJAX)
		 */
		public function handle_save_pdf_contact_ajax() {
			if (!is_user_logged_in()) {
				wp_send_json_error(['message' => 'You must be logged in to save contact information.']);
			}
			
			check_ajax_referer('fi_save_pdf_contact', 'nonce');
			
			$user_id = get_current_user_id();
			
			// Get form data
			$contact_data = [
				'name' => $_POST['name'] ?? '',
				'phone' => $_POST['phone'] ?? '',
				'email' => $_POST['email'] ?? '',
			];
			
			// Get edit index if editing
			$edit_index = isset($_POST['edit_index']) && $_POST['edit_index'] !== '' ? (int)$_POST['edit_index'] : null;
			
			// Save contact using fi_pdf_contacts_save
			$result = fi_pdf_contacts_save($user_id, $contact_data, $edit_index);
			
			if ($result) {
				$contacts = fi_pdf_contacts_get($user_id);
				$default_index = fi_pdf_contacts_default_index_get($user_id);
				wp_send_json_success(['message' => 'Contact saved successfully.', 'contacts' => $contacts, 'default_index' => $default_index]);
			} else {
				wp_send_json_error(['message' => 'Failed to save contact.']);
			}
		}

		/**
		 * Handle PDF contact delete (AJAX)
		 */
		public function handle_delete_pdf_contact() {
			if (!is_user_logged_in()) {
				wp_send_json_error(['message' => 'You must be logged in to delete contact information.']);
			}
			
			check_ajax_referer('fi_delete_pdf_contact', 'nonce');
			
			$user_id = get_current_user_id();
			$index = isset($_POST['index']) ? (int)$_POST['index'] : null;
			
			if ($index === null) {
				wp_send_json_error(['message' => 'Invalid contact index.']);
			}
			
			// Delete contact using fi_pdf_contacts_delete
			$result = fi_pdf_contacts_delete($user_id, $index);
			
			if ($result) {
				$contacts = fi_pdf_contacts_get($user_id);
				$default_index = fi_pdf_contacts_default_index_get($user_id);
				wp_send_json_success(['message' => 'Contact deleted successfully.', 'contacts' => $contacts, 'default_index' => $default_index]);
			} else {
				wp_send_json_error(['message' => 'Failed to delete contact.']);
			}
		}

		/**
		 * Return one PDF contact by index (AJAX) for Edit-in-place.
		 */
		public function handle_get_pdf_contact() {
			if (!is_user_logged_in()) {
				wp_send_json_error(['message' => 'You must be logged in.']);
			}
			check_ajax_referer('fi_get_pdf_contact', 'nonce');
			$user_id = get_current_user_id();
			$index = isset($_POST['index']) ? (int) $_POST['index'] : null;
			if ($index === null) {
				wp_send_json_error(['message' => 'Invalid contact index.']);
			}
			$contact = fi_pdf_contacts_get_by_index($user_id, $index);
			if ($contact === null) {
				wp_send_json_error(['message' => 'Contact not found.']);
			}
			wp_send_json_success(['contact' => $contact, 'index' => $index]);
		}

		/**
		 * Handle set default PDF contact (AJAX)
		 */
		public function handle_set_default_pdf_contact_ajax() {
			if (!is_user_logged_in()) {
				wp_send_json_error(['message' => 'You must be logged in.']);
			}
			check_ajax_referer('fi_set_default_pdf_contact', 'nonce');
			$user_id = get_current_user_id();
			$index_raw = isset($_POST['default_index']) ? sanitize_text_field($_POST['default_index']) : '';
			$index = ($index_raw === '' || $index_raw === null) ? null : (int) $index_raw;
			$result = fi_pdf_contacts_default_index_set($user_id, $index);
			if ($result) {
				$contacts = fi_pdf_contacts_get($user_id);
				$default_index = fi_pdf_contacts_default_index_get($user_id);
				wp_send_json_success(['message' => 'Default updated.', 'contacts' => $contacts, 'default_index' => $default_index]);
			} else {
				wp_send_json_error(['message' => 'Failed to set default.']);
			}
		}
	}
}

