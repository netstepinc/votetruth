<?php
namespace FI\Public {

	if (!defined('ABSPATH')) exit;

	/**
	* Template Loader Class
	* Handles loading of Freedom Index templates with theme override support
	*/
	class TemplateLoader {
		/**
		* Get template part (public static method)
		* 
		* @param string $template_name Template name (without .php extension)
		* @param array $args Arguments to pass to template
		*/
		public static function get_template_part($template_name, $args = array()) {
			// Check for theme override first
			/*SKIP: Theme and template are purpose built to work together.
			$theme_template = get_template_directory() . "/freedom-index/{$template_name}.php";
			if (file_exists($theme_template)) {
				$template_file = $theme_template;
			} else {
				// Use plugin template
				$template_file = FI_PUBLIC_DIR . "templates/{$template_name}.php";
			}
			*/
			$template_file = FI_PUBLIC_DIR . "templates/{$template_name}.php";
			if (file_exists($template_file)) {
				// Extract args for template
				if (!empty($args)) {
					extract($args);
				}
				include $template_file;
			} else {
				self::log("Freedom Index template not found: {$template_name}", __FILE__, __LINE__, 'warning');
			}
		}
		
		/**
		* Get template path (private method)
		* 
		* @param string $template_name Template name (without .php extension)
		* @return string|false Template file path or false if not found
		*/
		/* SKIP: Theme and template are purpose built to work together.
		private static function get_template_path($template_name) {
			// Check for theme override first
			$theme_template = get_template_directory() . "/freedom-index/{$template_name}.php";
			
			if (file_exists($theme_template)) {
				return $theme_template;
			}
			
			// Use plugin template
			$template_file = FI_PUBLIC_DIR . "templates/{$template_name}.php";
			
			if (file_exists($template_file)) {
				return $template_file;
			}
			
			return false;
		}
		*/
		
		/**
		* Generate reports navigation HTML
		* Single source of truth for reports nav markup
		* 
		* @param array $report_links Array of report link arrays with 'url' and 'title' keys
		* @return string HTML string for reports navigation, or empty string if no links
		*/
		public static function get_reports_nav_html(array $report_links): string {
			if (empty($report_links)) {
				return '';
			}
			
			ob_start();
			?>
<div class="row border-bottom border-top mb-3">
	<div class="col-12 py-1" id="fi-reports-nav">
		<!-- SM-MD: Mobile/Tablet with toggle menu -->
		<nav class="navbar navbar-light py-0 d-md-none">
			<div class="d-flex justify-content-between align-items-center w-100">
				<span class="navbar-brand fs-5 text-muted flex-shrink-0 py-0 mb-0">VOTE REPORTS:</span>
				<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#fi-reports-nav-collapse" aria-controls="fi-reports-nav-collapse" aria-expanded="false" aria-label="Toggle reports navigation">
					<span class="navbar-toggler-icon"></span>
				</button>
			</div>
			<div class="collapse navbar-collapse" id="fi-reports-nav-collapse">
				<ul class="navbar-nav py-0">
					<?php foreach ($report_links as $report_link) : ?>
						<li class="nav-item">
							<a class="nav-link fw-bold fs-4 py-0" href="<?= esc_url($report_link['url']); ?>"><?= esc_html($report_link['title']); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</nav>
		
		<!-- LG+: Desktop horizontal scroll -->
		<div class="d-none d-md-block">
			<div class="d-flex align-items-center">
				<span class="fs-5 text-muted flex-shrink-0 py-0 pb-1 me-3">VOTE REPORTS:</span>
				<div class="fi-reports-nav-scroll flex-grow-1 mt-1" style="min-width: 0;">
					<?php foreach ($report_links as $report_link) : ?>
						<a class="btn btn-sm btn-primary fw-bold fs-6 rounded-4 px-3 me-3" href="<?= esc_url($report_link['url']); ?>"><?= esc_html($report_link['title']); ?></a>
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
		* Wrap fi_log in function so we can log this class only if necessary.
		*/
		public static function log(string $message, string $file = '', int $line = 0, string $level = 'debug'): void {
			//fi_log($message, $file, $line, $level);
		}

	}
}

// Public namespace functions
namespace {
    /**
     * Get template part (wrapper for TemplateLoader)
     * 
     * @param string $template_name Template name (without .php extension)
     * @param array $args Arguments to pass to template
     */
    function fi_get_template($template_name, $args = array()) {
        \FI\Public\TemplateLoader::get_template_part($template_name, $args);
    }
    
    /**
     * Generate personalize form HTML
     * 
     * @param array $args {
     *     @type string $name_value Name field value
     *     @type string $phone_value Phone field value
     *     @type string $email_value Email field value
     *     @type bool $show_edit_index Show hidden edit index field
     *     @type bool $show_cancel Show cancel button
     *     @type string $submit_text Submit button text
     *     @type string $submit_text_id ID for submit text span (empty to use static text)
     *     @type string $label_class CSS class for labels
     *     @type string $submit_class CSS class for submit button
     *     @type string $form_id Form ID (default: 'fiPersonalizeForm')
     *     @type string $name_id Name field ID (default: 'fiPersonalizeName')
     *     @type string $name_name Name field name attribute (default: 'name')
     *     @type string $phone_id Phone field ID (default: 'fiPersonalizePhone')
     *     @type string $phone_name Phone field name attribute (default: 'phone')
     *     @type string $email_id Email field ID (default: 'fiPersonalizeEmail')
     *     @type string $email_name Email field name attribute (default: 'email')
     *     @type bool $wrap_form Wrap in form tag (default: true)
     *     @type string $form_action Form action URL (default: empty)
     *     @type string $form_method Form method (default: 'post')
     *     @type string $privacy_notice Privacy notice HTML to insert before submit button
     *     @type string $cancel_url Cancel button URL (when show_cancel is true)
     * }
     * @return string Form HTML
     */
    function fi_get_personalize_form_html(array $args = []): string {
        $defaults = [
            'name_value' => '',
            'phone_value' => '',
            'email_value' => '',
            'show_edit_index' => false,
            'show_cancel' => false,
            'submit_text' => 'Save',
            'submit_text_id' => '',
            'label_class' => 'form-label',
            'submit_class' => 'btn btn-primary w-100',
            'form_id' => 'fiPersonalizeForm',
            'name_id' => 'fiPersonalizeName',
            'name_name' => 'name',
            'phone_id' => 'fiPersonalizePhone',
            'phone_name' => 'phone',
            'email_id' => 'fiPersonalizeEmail',
            'email_name' => 'email',
            'wrap_form' => true,
            'form_action' => '',
            'form_method' => 'post',
            'privacy_notice' => '',
            'cancel_url' => '',
        ];
        $args = wp_parse_args($args, $defaults);
        
        ob_start();
        if ($args['wrap_form']):
        ?>
        <form id="<?php echo esc_attr($args['form_id']); ?>" class="mb-0 needs-validation" novalidate<?php echo !empty($args['form_action']) ? ' action="' . esc_attr($args['form_action']) . '"' : ''; ?> method="<?php echo esc_attr($args['form_method']); ?>">
        <?php endif; ?>
            <?php if ($args['show_edit_index']): ?>
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
                <?php echo $args['privacy_notice']; ?>
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
                <?php if ($args['show_cancel'] && !empty($args['cancel_url'])): ?>
                    <a href="<?php echo esc_url($args['cancel_url']); ?>" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                <?php elseif ($args['show_cancel']): ?>
                    <button type="button" class="btn btn-outline-secondary w-100 mt-2" id="fiCancelEdit" style="display: none;">
                        Cancel
                    </button>
                <?php endif; ?>
            </div>
        <?php if ($args['wrap_form']): ?>
        </form>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate reports navigation HTML
     * Single source of truth for reports nav markup
     * 
     * @param array $report_links Array of report link arrays with 'url' and 'title' keys
     * @return string HTML string for reports navigation, or empty string if no links
     */
    function fi_reports_nav_html(array $report_links): string {
        return \FI\Public\TemplateLoader::get_reports_nav_html($report_links);
    }

	function fi_legislators_find_mine(array $args = array()) {
		fi_get_template('partials/legislators-find-mine', $args);
	}

	/**
	 * Public helper: get legislator ID from query vars.
	 */
	function fi_public_get_legislator_id(): string {
		$legislator_id = (int)get_query_var('fi_legislator_id');
		return $legislator_id ? (string)$legislator_id : '';
	}

	/**
	 * Public helper: get requested session ID from query string or rewrite var.
	 */
	function fi_public_get_legislator_session_id(): ?int {
		// Check rewrite query var first (from pretty URL)
		$session_id = get_query_var('fi_session');
		if ($session_id) {
			$session_id = (int) $session_id;
			return $session_id > 0 ? $session_id : null;
		}
		
		// Fallback to query string
		if (isset($_GET['session'])) {
			$session_id = (int) $_GET['session'];
			return $session_id > 0 ? $session_id : null;
		}
		
		return null;
	}

	/**
	 * Public helper: get requested report slug from query string or rewrite var.
	 */
	function fi_public_get_report_id(): ?string {
		// Check rewrite query var first (from pretty URL)
		$report_slug = get_query_var('fi_report_slug');
		if ($report_slug) {
			return sanitize_title($report_slug);
		}
		
		// Fallback to query string
		if (isset($_GET['report']) && $_GET['report'] !== '') {
			return sanitize_title($_GET['report']);
		}
		
		return null;
	}

	/**
	 * Public helper: get requested report ID from rewrite (legislator URLs use ID).
	 * e.g. /legislator/123/session/14/report/5/ sets fi_report_id=5.
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
	 * Public helper: get requested tag ID from rewrite (ID-based URL only).
	 * e.g. /legislator/123/issue/456/ sets fi_tag_id=456.
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
	* Render custom scrollbar CSS */
	function fi_scrollbar_css() {
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
	 * Include PDF part file and return as string
	 * Simple include function for PDF generation (doesn't use template parts to avoid conflicts)
	 * 
	 * @param string $part_name Part name (without .php extension)
	 * @param array $args Variables to extract for the part
	 * @return string The rendered part content
	 */
	function fi_pdf_part(string $part_name, array $args = []): string {
		$part_file = FI_PUBLIC_DIR . "templates/pdf/{$part_name}.php";
		
		if (file_exists($part_file)) {
			// Extract args for part
			if (!empty($args)) {
				extract($args);
			}
			
			// Capture output and return as string
			ob_start();
			include $part_file;
			return ob_get_clean();
		} else {
			fi_log("Freedom Index PDF part not found: {$part_name}", __FILE__, __LINE__, 'warning');
			return '';
		}
	}

	//Alert signup form: https://superiorcampaignsolutionsllc.createsend.com/subscribers/signupformbuilder/F12EF5A4C9E6352E
	function jbs_alert_signup_form(){
		$conf = [
			'data-id' => '191722FC90141D02184CB1B62AB3DC26D8C96BC8F6B9AF099B2357DDA08DCFE6D3751AD6A32F1BB8A28C210AA67EA0414C83EEFB7CE47ACAECAD0D9306BC2503',
			'name' => ['id' => 'fieldName', 'name' => 'cm-name'],
			'email' => ['id' => 'fieldEmail', 'name' => 'cm-tyqkly-tyqkly'],
			'state' => ['id' => 'fieldtltdsl', 'name' => 'cm-f-tltdsl'],
			'zip' => ['id' => 'fieldtliikky', 'name' => 'cm-f-tliikky'],
		];

		ob_start();
		?>
	<form class="js-cm-form" id="subForm" action="https://www.createsend.com/t/subscribeerror?description=" method="post" data-id="<?= $conf['data-id']; ?>">
	<div class="row">
		<div class="col-sm-12 col-md-12 mb-3">
			<input aria-label="Name" class="form-control" placeholder="Name" id="<?= $conf['name']['id']; ?>" maxlength="200" name="<?= $conf['name']['name']; ?>" required="" data-kpxc-id="<?= $conf['name']['id']; ?>">
		</div>
		<div class="col-sm-12 col-md-12 mb-3">
		<input placeholder="Email" autocomplete="Email" aria-label="Email" class="js-cm-email-input form-control" id="<?= $conf['email']['id']; ?>" maxlength="200" name="<?= $conf['email']['name']; ?>" required="" type="email" data-kpxc-id="<?= $conf['email']['id']; ?>">
		</div>
	</div>
	<div class="row">
		<div class="col-sm-12 col-md-12 mb-3">
			<input placeholder="State" class="form-control" aria-label="State" id="<?= $conf['state']['id']; ?>" maxlength="200" name="<?= $conf['state']['name']; ?>" data-kpxc-id="<?= $conf['state']['id']; ?>">
		</div>
		<div class="col-sm-12 col-md-12 mb-3">
			<input placeholder="Zip Code" class="form-control" aria-label="Postal Code" id="<?= $conf['zip']['id']; ?>" maxlength="200" name="<?= $conf['zip']['name']; ?>" required="" data-kpxc-id="<?= $conf['zip']['id']; ?>">
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


}