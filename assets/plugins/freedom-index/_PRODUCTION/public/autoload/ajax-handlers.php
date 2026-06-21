<?php
namespace FI\Public{

	if (!defined('ABSPATH')) exit;

	fi_autoload_module(FI_DIR . 'public/ajax/');

	class AjaxHandlers {
		use AjaxHandlersSearchTrait;
		use AjaxHandlersPrefsTrait;
		use AjaxHandlersListsTrait;
		use AjaxHandlersAccountTrait;
		use AjaxHandlersPdfContactsTrait;
		use AjaxHandlersVoteDetailTrait;
		use AjaxHandlersLegislatorFilterTrait;
		use AjaxHandlersVoteHistoryTrait;

		use AjaxHandlersApiLegislatorFilterTrait;
		use AjaxHandlersApiVoteHistoryTrait;
		
		public function __construct() {
			//API-backed Legislator filtering (Medoo)
			add_action('wp_ajax_fi_api_legislator_filter', array($this, 'handle_api_legislator_filter'));
			add_action('wp_ajax_nopriv_fi_api_legislator_filter', array($this, 'handle_api_legislator_filter'));

			// API:Legislator vote history
			add_action('wp_ajax_fi_api_legislator_vote_history', array($this, 'handle_api_legislator_vote_history'));
			add_action('wp_ajax_nopriv_fi_api_legislator_vote_history', array($this, 'handle_api_legislator_vote_history'));

			// API:Search autocomplete (suggestions only)
			add_action('wp_ajax_fi_api_search_autocomplete', array($this, 'handle_api_search_autocomplete'));
			add_action('wp_ajax_nopriv_fi_api_search_autocomplete', array($this, 'handle_api_search_autocomplete'));
			
			// API:Legislator name search (full results)
			add_action('wp_ajax_fi_api_legislator_search', array($this, 'handle_api_legislator_search'));
			add_action('wp_ajax_nopriv_fi_api_legislator_search', array($this, 'handle_api_legislator_search'));


			// STANDARD WORDPRESS AJAX HANDLERS

			// WP:Legislator filtering
			add_action('wp_ajax_fi_legislator_filter', array($this, 'handle_legislator_filter'));
			add_action('wp_ajax_nopriv_fi_legislator_filter', array($this, 'handle_legislator_filter'));

			// WP:Legislator vote history
			add_action('wp_ajax_fi_legislator_vote_history', array($this, 'handle_legislator_vote_history'));
			add_action('wp_ajax_nopriv_fi_legislator_vote_history', array($this, 'handle_legislator_vote_history'));

			// WP:Search autocomplete (suggestions only)
			add_action('wp_ajax_fi_search_autocomplete', array($this, 'handle_search_autocomplete'));
			add_action('wp_ajax_nopriv_fi_search_autocomplete', array($this, 'handle_search_autocomplete'));
			
			// WP:Legislator name search (full results)
			add_action('wp_ajax_fi_legislator_search', array($this, 'handle_legislator_search'));
			add_action('wp_ajax_nopriv_fi_legislator_search', array($this, 'handle_legislator_search'));

			// User signup
			add_action('admin_post_fi_user_signup', array($this, 'handle_user_signup'));
			add_action('admin_post_nopriv_fi_user_signup', array($this, 'handle_user_signup'));
			
			// Profile and address (AJAX; logged-in only)
			add_action('wp_ajax_fi_update_profile', array($this, 'handle_update_profile_ajax'));
			add_action('wp_ajax_fi_update_address', array($this, 'handle_update_address_ajax'));
					
			// User preferences
			add_action('wp_ajax_fi_save_prefs', array($this, 'handle_save_prefs'));
			add_action('wp_ajax_fi_sync_prefs', array($this, 'handle_sync_prefs'));

			// PDF contact management
			add_action('admin_post_fi_save_pdf_contact', array($this, 'handle_save_pdf_contact'));
			add_action('admin_post_fi_set_default_pdf_contact', array($this, 'handle_set_default_pdf_contact'));

			// PDF contact management
			add_action('wp_ajax_fi_save_pdf_contact', array($this, 'handle_save_pdf_contact_ajax'));
			add_action('wp_ajax_fi_get_pdf_contact', array($this, 'handle_get_pdf_contact'));
			add_action('wp_ajax_fi_delete_pdf_contact', array($this, 'handle_delete_pdf_contact'));
			add_action('wp_ajax_fi_set_default_pdf_contact', array($this, 'handle_set_default_pdf_contact_ajax'));
		
			// Lists (account: fi_create_list; modal: fi_modal_create_list; other: fi_save_list)
			add_action('wp_ajax_fi_modal_create_list', array($this, 'handle_modal_create_list'));
			add_action('wp_ajax_fi_create_list', array($this, 'handle_create_list')); // Dashboard
			add_action('wp_ajax_fi_save_list', array($this, 'handle_save_list'));
			add_action('wp_ajax_fi_update_list', array($this, 'handle_update_list'));
			add_action('wp_ajax_fi_delete_list', array($this, 'handle_delete_list'));
			add_action('wp_ajax_fi_update_list_name', array($this, 'handle_update_list_name'));
			add_action('wp_ajax_fi_update_list_contact', array($this, 'handle_update_list_contact'));
			
			// PDF generation
			add_action('wp_ajax_fi_generate_pdf', array($this, 'handle_generate_pdf'));
			add_action('wp_ajax_nopriv_fi_generate_pdf', array($this, 'handle_generate_pdf'));
					
			// Vote detail modal
			add_action('wp_ajax_fi_vote_detail', array($this, 'handle_vote_detail'));
			add_action('wp_ajax_nopriv_fi_vote_detail', array($this, 'handle_vote_detail'));
			
			// Find my representatives
			add_action('wp_ajax_fi_find_representatives', array($this, 'handle_find_representatives'));
			add_action('wp_ajax_nopriv_fi_find_representatives', array($this, 'handle_find_representatives'));
		}
		
		/**
		* Handle user signup
		*/
		public function handle_user_signup() {
			if (!get_option('users_can_register')) {
				wp_die('Registration is disabled.');
			}
			
			check_admin_referer('fi_signup', 'fi_signup_nonce');
			
			$username = sanitize_user($_POST['username'] ?? '');
			$email = sanitize_email($_POST['email'] ?? '');
			$password = $_POST['password'] ?? '';
			$password_confirm = $_POST['password_confirm'] ?? '';
			$redirect_to = esc_url_raw($_POST['redirect_to'] ?? home_url('/account/dashboard/'));
			
			// Validation
			if (empty($username) || empty($email) || empty($password)) {
				wp_redirect(add_query_arg('error', 'missing_fields', home_url('/account/')));
				exit;
			}
			
			if ($password !== $password_confirm) {
				wp_redirect(add_query_arg('error', 'password_mismatch', home_url('/account/')));
				exit;
			}
			
			if (strlen($password) < 8) {
				wp_redirect(add_query_arg('error', 'password_short', home_url('/account/')));
				exit;
			}
			
			if (!is_email($email)) {
				wp_redirect(add_query_arg('error', 'invalid_email', home_url('/account/')));
				exit;
			}
			
			// Check if username exists
			if (username_exists($username)) {
				wp_redirect(add_query_arg('error', 'username_exists', home_url('/account/')));
				exit;
			}
			
			// Check if email exists
			if (email_exists($email)) {
				wp_redirect(add_query_arg('error', 'email_exists', home_url('/account/')));
				exit;
			}
			
			// Create user
			$user_id = wp_create_user($username, $password, $email);
			
			if (is_wp_error($user_id)) {
				wp_redirect(add_query_arg('error', 'registration_failed', home_url('/account/')));
				exit;
			}
			
			// Auto-login user
			wp_set_current_user($user_id);
			wp_set_auth_cookie($user_id);
			
			// Redirect to dashboard
			wp_safe_redirect($redirect_to);
			exit;
		}
			
		public static function log(string $message, string $file='', int $line=0, string $level = 'info'): void {
			//fi_log_area('ajax', $message, $file, $line, $level);
		}
	}
}

namespace {
	add_action('plugins_loaded', function() {
		new \FI\Public\AjaxHandlers();
	});
}