<?php
if (!defined('ABSPATH')) exit;

// If user is already logged in, redirect to dashboard (before any output). Skip in admin so edit screen/preview don't redirect.
if (!is_admin() && is_user_logged_in()) {
	wp_safe_redirect(home_url('/account/dashboard/'));
	exit;
}
?>
<div class="col-12 mb-5 mx-auto">
	<div class="row">
		<div class="col-12 col-md-6">
			<div class="card rounded-4 shadow mb-4">
				<div class="card-header h3">Welcome Back</div>
				<div class="card-body">
					<p class="card-text text-danger text-center small">You may login with your <b>ShopJBS.org</b> or <b>TheNewAmerican.com</b> credentials.</p>
					<?php
					if (is_user_logged_in()) {
						echo '<h4 class="card-title mt-4">You are already signed in.</h4>';
						echo '<p class="mb-4"><a href="/account/">My Account</a>.</p>';
						echo '<p class="mb-4"><a href="' . wp_logout_url() . '">Log Out</a></p>';
					} else {
						echo do_shortcode('[sam_login_form]');
					}
					?>
				</div>
			</div>

			<div class="card border-primary rounded-4 shadow mb-4">
				<div class="card-header h3">Password Reset</div>
				<div class="card-body">
					<?php echo do_shortcode('[sam_lost_password_form]'); ?>
					<p class="text-center text-danger mt-2 mb-0"><small>Please <b>check your SPAM folder</b> if you do not receive the email after 5 minutes.</small></p>
				</div>
			</div>
		</div>

		<div class="col-12 col-md-6">
			<div class="card rounded-4 shadow mb-4">
				<div class="card-header h3">Account Benefits</div>
				<div class="card-body">
					<ul class="list-unstyled mb-0">
						<li class="mb-2"><i class="bi bi-check-circle text-success"></i> Save PDF contact information for faster printing</li>
						<li class="mb-2"><i class="bi bi-check-circle text-success"></i> Save legislators to custom lists</li>
						<li class="mb-2"><i class="bi bi-check-circle text-success"></i> Track your representatives</li>
						<li class="mb-0"><i class="bi bi-check-circle text-success"></i> Receive score updates and notifications</li>
						<li class="mb-2 text-muted"><i class="bi bi-circle"></i> Create and compare custom scorecards (coming soon)</li>
					</ul>
				</div>
			</div>

			<div class="card rounded-4 shadow mb-4">
				<div class="card-header h3">Create Your Free Account</div>
				<div class="card-body">
					<?php echo do_shortcode('[sam_registration_form]'); ?>
				</div>
			</div>
		</div>
	</div>
</div>