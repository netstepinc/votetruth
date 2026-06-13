<?php
namespace FI\Core {

	if (!defined('ABSPATH')) exit;

	/**
	* Help System for Freedom Index
	* 
	* Provides help content for templates via modal windows
	* Supports both public and admin help pages
	* 
	* @author Sam Mittelstaedt <smittelstaedt@jbs.org>
	*/

	final class Help {

		/**
		* Get help content for a template
		* 
		* @param string $template_name Template name (without .php extension)
		* @param string $context 'public' or 'admin' (default: 'public')
		* @return string|false Help content HTML or false if help file doesn't exist
		*/
		public static function get_help($template_name, $context = 'public') {
			if (empty($template_name)) {
				return false;
			}
			// Sanitize template name
			$template_name = sanitize_file_name($template_name);
			$help = fi_kb_by_slug($template_name);
			if(empty($help)) {
				return false;
			}else{
				return $help;
			}
		}

		/**
		* Get help button and modal HTML
		* Includes both the button and modal if help file exists
		* 
		* @param string $template_name Template name (without .php extension)
		* @param string $context 'public' or 'admin' (default: 'public')
		* @return string HTML for help button and modal, or empty string if no help file
		*/
		public static function get_help_button($template_name, $context = 'public') {
			if (empty($template_name)) {
				return '';
			}
			$template_name = sanitize_file_name($template_name);
			$html = '<!-- No help available for ' . $template_name . ' -->';			
			// Check if help file exists
			$kb = self::get_help($template_name, $context);
			if(!empty($kb)){
				$html = '';
				
				// Add help button (without li wrapper - breadcrumbs function will handle it)
				$html .= '<button type="button" id="fi-breadcrumb-help" class="btn btn-sm btn-outline-success fw-bold py-1 px-2 ms-auto" ';
				$html .= 'data-bs-toggle="modal" data-bs-target="#fi-help-modal" ';
				$html .= 'data-template="' . esc_attr($template_name) . '" ';
				$html .= 'title="Get help with this page">';
				$html .= 'Help<i class="bi bi-question-circle ms-1"></i>';
				$html .= '</button>';
				
				// Add help modal
				$html .= self::get_help_modal($kb);
			}	
			return $html;
		}

		/**
		* Get help modal HTML
		* 
		* @param string $template_name Template name
		* @param string $context 'public' or 'admin' (default: 'public')
		* @return string Modal HTML
		*/
		public static function get_help_modal($kb) {
			if(empty($kb)) {
				return '';
			}
			$modal_id = 'fi-help-modal';
			$modal_title = $kb['title'] ? $kb['title'] : 'Help';
			$modal_body = $kb['content'] ? $kb['content'] : '';
			
			ob_start();
			?>
		<div class="modal fade text-start" id="<?php echo esc_attr($modal_id); ?>" tabindex="-1" aria-labelledby="<?php echo esc_attr($modal_id); ?>-label" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
				<div class="modal-content rounded-4">
					<div class="modal-header bg-light rounded-top-4">
						<h5 class="modal-title lh-1 mb-0 fs-5" id="<?php echo esc_attr($modal_id); ?>-label"><?php echo esc_html($modal_title); ?></h5>
						<button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<?php echo wp_kses_post($modal_body); ?>
					</div>
					<div class="modal-footer p-0 rounded-bottom-4">
						<button type="button" class="btn btn-sm btn-light text-dark fw-bold text-primary m-0 w-100 rounded-0" data-bs-dismiss="modal">Close</button>
					</div>
				</div>
			</div>
		</div>
			<?php
			return ob_get_clean();
		}

		/**
		* Get current template name from query vars or global
		* 
		* @return string|false Template name or false if not found
		*/
		public static function get_current_template_name() {
			// Try to get from query var first
			$entity = get_query_var('fi_entity');
			
			if (!empty($entity)) {
				// Map entity to template name
				$template_map = [
					'legislator' => 'legislator',
					'legislators' => 'legislators',
					'report' => 'report',
					'reports' => 'reports',
					'vote' => 'vote',
					'votes' => 'votes',
					'account' => 'account',
					'government' => 'government',
				];
				
				if (isset($template_map[$entity])) {
					return $template_map[$entity];
				}
				
				// For account pages, check account_page query var
				if ($entity === 'account') {
					$account_page = get_query_var('fi_account_page');
					if (!empty($account_page)) {
						return 'account-' . $account_page;
					}
					return 'account';
				}
				
				return $entity;
			}
			
			// Fallback: try to get from global
			global $fi_entity;
			if (!empty($fi_entity)) {
				return $fi_entity;
			}
			
			return false;
		}
	}
}

// Global namespace functions for backward compatibility
namespace {
	function fi_get_help($template_name, $context = 'public') {
		return \FI\Core\Help::get_help($template_name, $context);
	}
	
	function fi_help_exists($template_name, $context = 'public') {
		return \FI\Core\Help::help_exists($template_name, $context);
	}
	
	function fi_get_help_button($template_name, $context = 'public') {
		return \FI\Core\Help::get_help_button($template_name, $context);
	}
	
	function fi_get_help_modal($template_name, $context = 'public') {
		return \FI\Core\Help::get_help_modal($template_name, $context);
	}
	
	function fi_get_current_template_name() {
		return \FI\Core\Help::get_current_template_name();
	}
}