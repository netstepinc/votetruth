<?php
/**
 * Freedom Index Help System
 *
 * Straight function version of the former FICore\Help class file.
 * Provides help content for templates via modal windows.
 * Supports both public and admin help pages.
 *
 * @author Sam Mittelstaedt <smittelstaedt@jbs.org>
 */

if (!defined('ABSPATH')) exit;

/**
 * Get help content for a template.
 *
 * @param string $template_name Template name without .php extension.
 * @param string $context Context: public or admin. Reserved for future use.
 * @return array|string|false Help content array/string from fi_kb_by_slug(), or false if unavailable.
 */
function fi_get_help($template_name, $context = 'public') {
	if (empty($template_name)) {
		return false;
	}

	$template_name = sanitize_file_name($template_name);
	$help = fi_kb_by_slug($template_name);

	if (empty($help)) {
		return false;
	}

	return $help;
}

/**
 * Check whether help exists for a template.
 *
 * The original wrapper called a missing Help::help_exists() method.
 * This uses fi_get_help() directly so the public function now works.
 *
 * @param string $template_name Template name without .php extension.
 * @param string $context Context: public or admin. Reserved for future use.
 * @return bool True if help content exists.
 */
function fi_help_exists($template_name, $context = 'public'): bool {
	return !empty(fi_get_help($template_name, $context));
}

/**
 * Get help button and modal HTML.
 *
 * Includes both the button and modal if help content exists.
 *
 * @param string $template_name Template name without .php extension.
 * @param string $context Context: public or admin. Reserved for future use.
 * @return string HTML for help button and modal, or empty string/comment if no help exists.
 */
function fi_get_help_button($template_name, $context = 'public'): string {
	if (empty($template_name)) {
		return '';
	}

	$template_name = sanitize_file_name($template_name);
	$html = '<!-- No help available for ' . esc_html($template_name) . ' -->';

	$kb = fi_get_help($template_name, $context);
	if (!empty($kb)) {
		$html = '';

		$html .= '<button type="button" id="fi-breadcrumb-help" class="btn btn-sm btn-outline-success fw-bold py-1 px-2 ms-auto" ';
		$html .= 'data-bs-toggle="modal" data-bs-target="#fi-help-modal" ';
		$html .= 'data-template="' . esc_attr($template_name) . '" ';
		$html .= 'title="Get help with this page">';
		$html .= 'Help<i class="bi bi-question-circle ms-1"></i>';
		$html .= '</button>';

		$html .= fi_get_help_modal_from_kb($kb);
	}

	return $html;
}

/**
 * Get help modal HTML from a template name.
 *
 * This preserves the original public function signature while fixing the old wrapper mismatch.
 *
 * @param string $template_name Template name without .php extension.
 * @param string $context Context: public or admin. Reserved for future use.
 * @return string Modal HTML or empty string.
 */
function fi_get_help_modal($template_name, $context = 'public'): string {
	if (empty($template_name)) {
		return '';
	}

	$kb = fi_get_help($template_name, $context);
	if (empty($kb)) {
		return '';
	}

	return fi_get_help_modal_from_kb($kb);
}

/**
 * Render help modal HTML from KB data.
 *
 * Shared helper used by fi_get_help_button() and fi_get_help_modal().
 *
 * @param array|string $kb Knowledge-base record. Expected array keys: title, content.
 * @return string Modal HTML or empty string.
 */
function fi_get_help_modal_from_kb($kb): string {
	if (empty($kb)) {
		return '';
	}

	$modal_id = 'fi-help-modal';
	$modal_title = 'Help';
	$modal_body = '';

	if (is_array($kb)) {
		$modal_title = !empty($kb['title']) ? (string) $kb['title'] : 'Help';
		$modal_body = !empty($kb['content']) ? (string) $kb['content'] : '';
	} elseif (is_object($kb)) {
		$modal_title = !empty($kb->title) ? (string) $kb->title : 'Help';
		$modal_body = !empty($kb->content) ? (string) $kb->content : '';
	} elseif (is_string($kb)) {
		$modal_body = $kb;
	}

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
 * Get current template name from query vars or global.
 *
 * @return string|false Template name or false if not found.
 */
function fi_get_current_template_name() {
	$entity = get_query_var('fi_entity');

	if (!empty($entity)) {
		$template_map = [
			'legislator'  => 'legislator',
			'legislators' => 'legislators',
			'report'      => 'report',
			'reports'     => 'reports',
			'vote'        => 'vote',
			'votes'       => 'votes',
			'account'     => 'account',
			'government'  => 'government',
		];

		if ($entity === 'account') {
			$account_page = get_query_var('fi_account_page');
			if (!empty($account_page)) {
				return 'account-' . sanitize_file_name($account_page);
			}
			return 'account';
		}

		if (isset($template_map[$entity])) {
			return $template_map[$entity];
		}

		return sanitize_file_name($entity);
	}

	global $fi_entity;
	if (!empty($fi_entity)) {
		return sanitize_file_name($fi_entity);
	}

	return false;
}
