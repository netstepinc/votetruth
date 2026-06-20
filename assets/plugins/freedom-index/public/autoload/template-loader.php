<?php

if (!defined('ABSPATH')) exit;

/**
 * Normalize a public template name to a safe relative template path without extension.
 *
 * Allows nested template names such as partials/my-partial while preventing traversal.
 *
 * @param string $template_name Template name without .php extension.
 * @return string Safe relative template name.
 */
function fi_template_normalize_name(string $template_name): string {
	$template_name = trim($template_name);
	$template_name = str_replace('\\', '/', $template_name);
	$template_name = preg_replace('#/+#', '/', $template_name);
	$template_name = ltrim($template_name, '/');
	$template_name = preg_replace('#\.php$#i', '', $template_name);

	$parts = array_filter(explode('/', $template_name), static function($part) {
		return $part !== '' && $part !== '.' && $part !== '..';
	});

	$clean = [];
	foreach ($parts as $part) {
		$part = sanitize_file_name($part);
		if ($part !== '') {
			$clean[] = $part;
		}
	}

	return implode('/', $clean);
}

/**
 * Get absolute file path for a public template.
 *
 * Theme override support is intentionally omitted because this plugin/theme pair is purpose-built.
 *
 * @param string $template_name Template name without .php extension.
 * @return string|false Template path or false if invalid/missing.
 */
function fi_template_path(string $template_name): string|false {
	$template_name = fi_template_normalize_name($template_name);

	if ($template_name === '' || !defined('FI_PUBLIC_DIR')) {
		return false;
	}

	$template_file = trailingslashit(FI_PUBLIC_DIR) . 'templates/' . $template_name . '.php';

	return file_exists($template_file) ? $template_file : false;
}

/**
 * Get template part from FI public templates directory.
 *
 * @param string $template_name Template name without .php extension.
 * @param array $args Arguments to extract for template scope.
 * @return void
 */
function fi_get_public_template($template_name, $args = []): void {
	$template_name = (string) $template_name;
	$args = is_array($args) ? $args : [];
	$template_file = fi_template_path($template_name);

	if ($template_file) {
		if (!empty($args)) {
			extract($args, EXTR_SKIP);
		}

		include $template_file;
		return;
	}

}

/**
 * Render a template part and return it as a string.
 *
 * @param string $template_name Template name without .php extension.
 * @param array $args Arguments to extract for template scope.
 * @return string Rendered HTML.
 */
function fi_get_template_html(string $template_name, array $args = []): string {
	ob_start();
	fi_get_public_template($template_name, $args);
	return ob_get_clean();
}

/**
 * Generate reports navigation HTML.
 *
 * Single source of truth for reports nav markup.
 *
 * @param array $report_links Array of report link arrays with url and title keys.
 * @return string HTML string for reports navigation, or empty string if no links.
 */
function fi_reports_nav_html(array $report_links): string {
	if (empty($report_links)) {
		return '';
	}

	ob_start();
	?>
	<div class="row border-bottom border-top mb-3">
		<div class="col-12 py-1" id="fi-reports-nav">
			<nav class="navbar navbar-light py-0 d-md-none">
				<div class="d-flex justify-content-between align-items-center w-100">
					<span class="navbar-brand fs-5 text-muted flex-shrink-0 py-0 mb-0">VOTE REPORTS:</span>
					<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#fi-reports-nav-collapse" aria-controls="fi-reports-nav-collapse" aria-expanded="false" aria-label="Toggle reports navigation">
						<span class="navbar-toggler-icon"></span>
					</button>
				</div>
				<div class="collapse navbar-collapse" id="fi-reports-nav-collapse">
					<ul class="navbar-nav py-0">
						<?php foreach ($report_links as $report_link): ?>
							<?php
							$url = isset($report_link['url']) ? (string) $report_link['url'] : '';
							$title = isset($report_link['title']) ? (string) $report_link['title'] : '';
							if ($url === '' || $title === '') {
								continue;
							}
							?>
							<li class="nav-item">
								<a class="nav-link fw-bold fs-4 py-0" href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</nav>

			<div class="d-none d-md-block">
				<div class="d-flex align-items-center">
					<span class="fs-5 text-muted flex-shrink-0 py-0 pb-1 me-3">VOTE REPORTS:</span>
					<div class="fi-reports-nav-scroll flex-grow-1 mt-1" style="min-width: 0;">
						<?php foreach ($report_links as $report_link): ?>
							<?php
							$url = isset($report_link['url']) ? (string) $report_link['url'] : '';
							$title = isset($report_link['title']) ? (string) $report_link['title'] : '';
							if ($url === '' || $title === '') {
								continue;
							}
							?>
							<a class="btn btn-sm btn-primary fw-bold fs-6 rounded-4 px-3 me-3" href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Generate personalize form HTML.
 *
 * @param array $args Form arguments.
 * @return string Form HTML.
 */
function fi_get_personalize_form_html(array $args = []): string {
	$defaults = [
		'name_value'      => '',
		'phone_value'     => '',
		'email_value'     => '',
		'show_edit_index' => false,
		'show_cancel'     => false,
		'submit_text'     => 'Save',
		'submit_text_id'  => '',
		'label_class'     => 'form-label',
		'submit_class'    => 'btn btn-primary w-100',
		'form_id'         => 'fiPersonalizeForm',
		'name_id'         => 'fiPersonalizeName',
		'name_name'       => 'name',
		'phone_id'        => 'fiPersonalizePhone',
		'phone_name'      => 'phone',
		'email_id'        => 'fiPersonalizeEmail',
		'email_name'      => 'email',
		'wrap_form'       => true,
		'form_action'     => '',
		'form_method'     => 'post',
		'privacy_notice'  => '',
		'cancel_url'      => '',
	];

	$args = wp_parse_args($args, $defaults);
	$form_method = strtolower((string) $args['form_method']);
	$form_method = in_array($form_method, ['post', 'get'], true) ? $form_method : 'post';

	ob_start();
	?>
	<?php if (!empty($args['wrap_form'])): ?>
		<form id="<?php echo esc_attr($args['form_id']); ?>" class="mb-0 needs-validation" novalidate<?php echo !empty($args['form_action']) ? ' action="' . esc_url($args['form_action']) . '"' : ''; ?> method="<?php echo esc_attr($form_method); ?>">
	<?php endif; ?>

		<?php if (!empty($args['show_edit_index'])): ?>
			<input type="hidden" id="fiEditContactIndex" value="">
		<?php endif; ?>

		<div class="mb-2">
			<label for="<?php echo esc_attr($args['name_id']); ?>" class="<?php echo esc_attr($args['label_class']); ?>">Name / Organization <span class="text-danger">*</span></label>
			<input
				type="text"
				class="form-control form-control-sm fs-6"
				id="<?php echo esc_attr($args['name_id']); ?>"
				name="<?php echo esc_attr($args['name_name']); ?>"
				placeholder="Your Name or Organization"
				value="<?php echo esc_attr($args['name_value']); ?>"
				required
			>
			<div class="invalid-feedback">Please enter your name or organization.</div>
		</div>

		<div class="mb-2">
			<label for="<?php echo esc_attr($args['phone_id']); ?>" class="<?php echo esc_attr($args['label_class']); ?>">Phone</label>
			<input
				type="tel"
				class="form-control form-control-sm fs-6"
				id="<?php echo esc_attr($args['phone_id']); ?>"
				name="<?php echo esc_attr($args['phone_name']); ?>"
				placeholder="(555) 123-4567"
				value="<?php echo esc_attr($args['phone_value']); ?>"
			>
		</div>

		<div class="mb-2">
			<label for="<?php echo esc_attr($args['email_id']); ?>" class="<?php echo esc_attr($args['label_class']); ?>">Email</label>
			<input
				type="email"
				class="form-control form-control-sm fs-6"
				id="<?php echo esc_attr($args['email_id']); ?>"
				name="<?php echo esc_attr($args['email_name']); ?>"
				placeholder="your@email.com"
				value="<?php echo esc_attr($args['email_value']); ?>"
			>
		</div>

		<?php if (!empty($args['privacy_notice'])): ?>
			<?php echo wp_kses_post($args['privacy_notice']); ?>
		<?php endif; ?>

		<div class="mb-0">
			<button type="submit" class="<?php echo esc_attr($args['submit_class']); ?>">
				<i class="bi bi-save"></i>
				<?php if (!empty($args['submit_text_id'])): ?>
					<span id="<?php echo esc_attr($args['submit_text_id']); ?>"><?php echo esc_html($args['submit_text']); ?></span>
				<?php else: ?>
					<?php echo esc_html($args['submit_text']); ?>
				<?php endif; ?>
			</button>

			<?php if (!empty($args['show_cancel']) && !empty($args['cancel_url'])): ?>
				<a href="<?php echo esc_url($args['cancel_url']); ?>" class="btn btn-outline-secondary">Cancel</a>
			<?php elseif (!empty($args['show_cancel'])): ?>
				<button type="button" class="btn btn-outline-secondary w-100 mt-2" id="fiCancelEdit" style="display: none;">Cancel</button>
			<?php endif; ?>
		</div>

	<?php if (!empty($args['wrap_form'])): ?>
		</form>
	<?php endif; ?>
	<?php
	return ob_get_clean();
}

/**
 * Render find-mine legislators partial.
 *
 * @param array $args Template args.
 * @return void
 */
function fi_legislators_find_mine(array $args = []): void {
	fi_get_public_template('legislators-find-mine', $args);
}

/**
 * Public helper: get legislator ID from query vars.
 *
 * @return string Legislator ID string, or empty string.
 */
function fi_public_get_legislator_id(): string {
	$legislator_id = (int) get_query_var('fi_legislator_id');
	return $legislator_id > 0 ? (string) $legislator_id : '';
}

/**
 * Public helper: get requested session ID from query string or rewrite var.
 *
 * @return int|null Session ID.
 */
function fi_public_get_legislator_session_id(): ?int {
	$session_id = get_query_var('fi_session');
	if ($session_id) {
		$session_id = (int) $session_id;
		return $session_id > 0 ? $session_id : null;
	}

	if (isset($_GET['session'])) {
		$session_id = (int) $_GET['session'];
		return $session_id > 0 ? $session_id : null;
	}

	return null;
}

/**
 * Public helper: get requested report slug from query string or rewrite var.
 *
 * Kept for legacy/non-legislator report contexts that still use report slugs.
 * Legislator report URLs should use fi_public_get_legislator_report_id().
 *
 * @return string|null Report slug.
 */
function fi_public_get_report_id(): ?string {
	$report_slug = get_query_var('fi_report_slug');
	if ($report_slug) {
		return sanitize_title((string) $report_slug);
	}

	if (isset($_GET['report']) && $_GET['report'] !== '') {
		return sanitize_title(wp_unslash($_GET['report']));
	}

	return null;
}

/**
 * Public helper: get requested report ID from rewrite.
 *
 * Example: /legislator/123/session/14/report/5/ sets fi_report_id=5.
 *
 * @return int|null Report ID.
 */
function fi_public_get_legislator_report_id(): ?int {
	$report_id = get_query_var('fi_report_id');
	if ($report_id === '' || $report_id === false) {
		return null;
	}

	$id = (int) $report_id;
	return $id > 0 ? $id : null;
}

/**
 * Public helper: get requested tag ID from rewrite.
 *
 * Example: /legislator/123/issue/456/ sets fi_tag_id=456.
 *
 * @return int|null Tag ID.
 */
function fi_public_get_legislator_tag_id(): ?int {
	$tag_id_var = get_query_var('fi_tag_id');
	if ($tag_id_var === '' || $tag_id_var === false) {
		return null;
	}

	$tag_id = (int) $tag_id_var;
	return $tag_id > 0 ? $tag_id : null;
}

/**
 * Render custom scrollbar CSS.
 *
 * @return void
 */
function fi_scrollbar_css(): void {
	?>
	<style>
	[data-scrollbar] {scrollbar-color: #6284a3 #f4f4f4;scrollbar-width: thin;}
	[data-scrollbar]::-webkit-scrollbar {width: 7px;background: #f4f4f4;border-radius: 5px;}
	[data-scrollbar]::-webkit-scrollbar-thumb {background: #c1c1c1;border-radius: 6px;}
	[data-scrollbar]::-webkit-scrollbar-thumb:hover {background: #999;}
	</style>
	<?php
}

/**
 * Include PDF part file and return it as a string.
 *
 * Uses a direct include to avoid template-loader conflicts during PDF generation.
 *
 * @param string $part_name Part name without .php extension.
 * @param array $args Variables to extract for the part.
 * @return string Rendered part content.
 */
function fi_pdf_part(string $part_name, array $args = []): string {
	$part_name = fi_template_normalize_name($part_name);

	if ($part_name === '' || !defined('FI_PUBLIC_DIR')) {
		return '';
	}

	$part_file = trailingslashit(FI_PUBLIC_DIR) . 'templates/pdf/' . $part_name . '.php';

	if (!file_exists($part_file)) {
		if (function_exists('fi_log')) {
			fi_log('Freedom Index PDF part not found: ' . $part_name, __FILE__, __LINE__, 'warning');
		}
		return '';
	}

	if (!empty($args)) {
		extract($args, EXTR_SKIP);
	}

	ob_start();
	include $part_file;
	return ob_get_clean();
}

/**
 * Alert signup form configuration.
 *
 * @return array Form field configuration.
 */
function jbs_alert_signup_form_config(): array {
	return [
		'data-id' => '191722FC90141D02184CB1B62AB3DC26D8C96BC8F6B9AF099B2357DDA08DCFE6D3751AD6A32F1BB8A28C210AA67EA0414C83EEFB7CE47ACAECAD0D9306BC2503',
		'name'    => ['id' => 'fieldName', 'name' => 'cm-name'],
		'email'   => ['id' => 'fieldEmail', 'name' => 'cm-tyqkly-tyqkly'],
		'state'   => ['id' => 'fieldtltdsl', 'name' => 'cm-f-tltdsl'],
		'zip'     => ['id' => 'fieldtliikky', 'name' => 'cm-f-tliikky'],
	];
}

/**
 * Render JBS alert signup form.
 *
 * Alert signup form: https://superiorcampaignsolutionsllc.createsend.com/subscribers/signupformbuilder/F12EF5A4C9E6352E
 *
 * @return string Form HTML.
 */
function jbs_alert_signup_form(): string {
	$conf = jbs_alert_signup_form_config();

	ob_start();
	?>
	<form class="js-cm-form" id="subForm" action="https://www.createsend.com/t/subscribeerror?description=" method="post" data-id="<?php echo esc_attr($conf['data-id']); ?>">
		<div class="row">
			<div class="col-sm-12 col-md-12 mb-3">
				<input aria-label="Name" class="form-control" placeholder="Name" id="<?php echo esc_attr($conf['name']['id']); ?>" maxlength="200" name="<?php echo esc_attr($conf['name']['name']); ?>" required data-kpxc-id="<?php echo esc_attr($conf['name']['id']); ?>">
			</div>
			<div class="col-sm-12 col-md-12 mb-3">
				<input placeholder="Email" autocomplete="email" aria-label="Email" class="js-cm-email-input form-control" id="<?php echo esc_attr($conf['email']['id']); ?>" maxlength="200" name="<?php echo esc_attr($conf['email']['name']); ?>" required type="email" data-kpxc-id="<?php echo esc_attr($conf['email']['id']); ?>">
			</div>
		</div>
		<div class="row">
			<div class="col-sm-12 col-md-12 mb-3">
				<input placeholder="State" class="form-control" aria-label="State" id="<?php echo esc_attr($conf['state']['id']); ?>" maxlength="200" name="<?php echo esc_attr($conf['state']['name']); ?>" data-kpxc-id="<?php echo esc_attr($conf['state']['id']); ?>">
			</div>
			<div class="col-sm-12 col-md-12 mb-3">
				<input placeholder="Zip Code" class="form-control" aria-label="Postal Code" id="<?php echo esc_attr($conf['zip']['id']); ?>" maxlength="200" name="<?php echo esc_attr($conf['zip']['name']); ?>" required data-kpxc-id="<?php echo esc_attr($conf['zip']['id']); ?>">
			</div>
		</div>
		<div class="row">
			<div class="col-sm-12">
				<button class="btn col-12 btn-primary bigtext-2 fw-bold" type="submit">Sign Up for Alerts</button>
			</div>
		</div>
	</form>
	<script type="text/javascript" src="https://js.createsend1.com/javascript/copypastesubscribeformlogic.js"></script>
	<?php
	return ob_get_clean();
}