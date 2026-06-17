<?php
if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
	wp_safe_redirect(home_url('/account/'));
	exit;
}
?>
<div class="row">
	<?php fi_get_public_template('account-nav', ['current_page' => 'notifications']); ?>
	<div class="col-12 col-md-9">

		<div class="row g-3">
			<div class="col-12 col-md-6 col-lg-4">
				<div class="card mb-4 rounded-4 shadow">
					<div class="card-header rounded-4 rounded-bottom-0">
						<h3 class="card-title mb-0">Sign Up for Legislative Alerts</h3>
					</div>
					<div class="card-body">
						<?php echo jbs_alert_signup_form(); ?>
					</div>
					<div class="card-footer p-0 border-0 rounded-4 rounded-top-0">
						<a href="https://jbs.org/alerts/" target="_blank" class="btn btn-sm btn-secondary w-100 rounded-0 m-0 rounded-4 rounded-top-0">
							Learn more about Alerts
						</a>
					</div>
				</div>
			</div>

			<div class="col-12 col-md-6 col-lg-8">
				<div class="card mb-4 rounded-4 shadow">
					<div class="card-header rounded-4 rounded-bottom-0">
						<h3 class="card-title mb-0">Recent Legislative Alerts</h3>
					</div>
					<div class="card-body">

						<div class="list-group list-group-flush" style="max-height:480px; overflow-y: auto; overscroll-behavior: contain; scrollbar-width: thin;" data-scrollbar>
						<?php
						// User State Alerts
						$address = fi_user_meta_get(get_current_user_id(), 'address');
						if (is_array($address)) {
							$state = !empty($address['state']) ? $address['state'] : '';
							if($state) {
								$state_name = fi_gov_name($state);
								echo '<div class="list-group-item p-0 border-bottom border-primary"><span class="text-primary fs-5">' . $state_name . ' Alerts</span></div>';
								$state_slug = str_replace(' ', '-', strtolower(FI_GOVERNMENTS[$gov]));
								$rss_url = 'https://jbs.org/alerts/' .$state_slug . '/feed/';
								fi_get_public_template('gov-alerts-feed', ['url' => $rss_url]);
							}
						}	

						//All States
						$rss_url = 'https://jbs.org/alerts/state/feed/';
						fi_get_public_template('gov-alerts-feed', ['url' => $rss_url]);

						//Congressional Alerts
						echo '<div class="list-group-item p-0 border-bottom border-primary"><span class="text-primary fs-5">Congressional Alerts</span></div>';
						$rss_url = 'https://jbs.org/alerts/federal/feed/';
						fi_get_public_template('gov-alerts-feed', ['url' => $rss_url]);

						fi_scrollbar_css();
						?>
						</div>

					</div>
					<div class="card-footer p-0">
						<a href="<?php echo esc_url('https://jbs.org/alerts/'); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-warning w-100 fs-7 rounded-0 rounded-bottom-4">
							View All Alerts at JBS.org
						</a>
					</div>
				</div>
			</div>

		</div>
	</div>
</div>