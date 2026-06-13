<?php if ( ! defined( 'ABSPATH' ) ) exit;
/*
White Label Login/Logout
by Sam Mittelstaedt <smittelstaedt@jbs.org>

What it does
	Redirects all wp-login.php user-facing actions to branded /account/ flows.
	Login: handled by [sam_login_form]
	Registration: handled by [sam_registration_form]
	Lost password: handled by [sam_lost_password_form]
	Password reset: handled by [sam_password_reset_form]
	Reset email links: now point to /account/?action=rp&key=...&login=...
	Logout: uses WP nonce/logout internally, then redirects to /account/?loggedout=true
	Removed multisite logic
	Removed WooCommerce logic
	No default WP auth screens should be user-facing
*/
function sam_auth_url( $args = array() ) {
	return add_query_arg( $args, home_url( '/account/' ) );
}

function sam_auth_redirect_url() {
	return home_url( '/account/dashboard/' );
}

add_filter( 'auth_cookie_expiration', function( $expiration, $user_id, $remember ) {
	return $remember ? ( 60 * DAY_IN_SECONDS ) : ( 14 * DAY_IN_SECONDS );
}, 10, 3 );

add_filter( 'login_url', function( $login_url, $redirect, $force_reauth ) {
	$args = array();
	if ( $redirect ) {
		$args['redirect_to'] = rawurlencode( $redirect );
	}
	if ( $force_reauth ) {
		$args['reauth'] = '1';
	}
	return sam_auth_url( $args );
}, 10, 3 );

add_filter( 'lostpassword_url', function( $lost_url, $redirect ) {
	$args = array( 'auth' => 'lost-password' );
	if ( $redirect ) {
		$args['redirect_to'] = rawurlencode( $redirect );
	}
	return sam_auth_url( $args );
}, 10, 2 );

add_filter( 'register_url', function() {
	return sam_auth_url( array( 'auth' => 'register' ) );
} );

add_filter( 'logout_url', function( $logout_url, $redirect ) {
	$redirect = $redirect ? $redirect : sam_auth_url( array( 'loggedout' => 'true' ) );
	return wp_nonce_url( add_query_arg( array( 'action' => 'logout', 'redirect_to' => rawurlencode( $redirect ) ), site_url( 'wp-login.php', 'login' ) ), 'log-out' );
}, 10, 2 );

add_action( 'login_init', function() {
	$action = sanitize_key( $_REQUEST['action'] ?? 'login' );

	if ( $action === 'logout' ) {
		check_admin_referer( 'log-out' );
		wp_logout();
		$redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : sam_auth_url( array( 'loggedout' => 'true' ) );
		wp_safe_redirect( wp_validate_redirect( $redirect_to, sam_auth_url( array( 'loggedout' => 'true' ) ) ) );
		exit;
	}

	if ( in_array( $action, array( 'rp', 'resetpass' ), true ) ) {
		wp_safe_redirect( sam_auth_url( array(
			'action' => 'rp',
			'key'    => sanitize_text_field( wp_unslash( $_REQUEST['key'] ?? '' ) ),
			'login'  => sanitize_text_field( wp_unslash( $_REQUEST['login'] ?? '' ) ),
		) ) );
		exit;
	}

	if ( $action === 'lostpassword' ) {
		wp_safe_redirect( sam_auth_url( array( 'auth' => 'lost-password' ) ) );
		exit;
	}

	if ( $action === 'register' ) {
		wp_safe_redirect( sam_auth_url( array( 'auth' => 'register' ) ) );
		exit;
	}

	wp_safe_redirect( sam_auth_url() );
	exit;
} );

add_action( 'send_headers', function() {
	$path = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	if ( strpos( (string) $path, '/account' ) === false && strpos( (string) $path, 'wp-login.php' ) === false ) {
		return;
	}
	header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
	header( 'Pragma: no-cache' );
	header( 'Expires: Thu, 01 Jan 1970 00:00:00 GMT' );
	header( 'X-Robots-Tag: noindex, noarchive' );
} );

function sam_auth_notice_html() {
	$messages = array();
	$login = sanitize_text_field( wp_unslash( $_GET['login'] ?? '' ) );
	$err = sanitize_text_field( wp_unslash( $_GET['err'] ?? '' ) );

	if ( isset( $_GET['loggedout'] ) && $_GET['loggedout'] === 'true' ) {
		$messages[] = '<div class="alert alert-success text-center mb-3">You have been logged out.</div>';
	}

	if ( $login === 'failed' ) {
		if ( $err === 'security' ) {
			$messages[] = '<div class="alert alert-danger text-center mb-3">Security check failed. Please refresh the page and try again.</div>';
		} elseif ( $err === 'empty' ) {
			$messages[] = '<div class="alert alert-danger text-center mb-3">Please enter your username/email and password.</div>';
		} else {
			$messages[] = '<div class="alert alert-danger text-center mb-3">Your username and/or password were not found. Please try again or use Password Reset.</div>';
		}
	}

	return implode( "\n", $messages );
}

add_action( 'template_redirect', function() {
	if ( empty( $_POST['sam_auth_action'] ) || $_POST['sam_auth_action'] !== 'login' ) {
		return;
	}

	if ( ! isset( $_POST['sam_login_nonce'] ) || ! wp_verify_nonce( $_POST['sam_login_nonce'], 'sam_login_form' ) ) {
		wp_safe_redirect( sam_auth_url( array( 'login' => 'failed', 'err' => 'security' ) ) );
		exit;
	}

	$username = sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) );
	$password = (string) wp_unslash( $_POST['pwd'] ?? '' );

	if ( $username === '' || $password === '' ) {
		wp_safe_redirect( sam_auth_url( array( 'login' => 'failed', 'err' => 'empty' ) ) );
		exit;
	}

	$user = wp_signon( array(
		'user_login'    => $username,
		'user_password' => $password,
		'remember'      => ! empty( $_POST['rememberme'] ),
	), is_ssl() );

	if ( is_wp_error( $user ) ) {
		wp_safe_redirect( sam_auth_url( array( 'login' => 'failed', 'err' => $user->get_error_code() ) ) );
		exit;
	}

	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, ! empty( $_POST['rememberme'] ), is_ssl() );

	$redirect_to = ! empty( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : sam_auth_redirect_url();
	wp_safe_redirect( wp_validate_redirect( $redirect_to, sam_auth_redirect_url() ) );
	exit;
} );

add_shortcode( 'sam_login_form', function() {
	ob_start();
	echo sam_auth_notice_html();

	if ( is_user_logged_in() ) {
		$current_user = wp_get_current_user();
		echo '<div class="text-center">';
		echo '<p>You are signed in as ' . esc_html( $current_user->display_name ) . '.</p>';
		echo '<p><a class="btn btn-primary text-white" href="' . esc_url( sam_auth_redirect_url() ) . '">My Account</a></p>';
		echo '<p><a class="btn btn-light" href="' . esc_url( wp_logout_url( sam_auth_url( array( 'loggedout' => 'true' ) ) ) ) . '">Log Out</a></p>';
		echo '</div>';
		return ob_get_clean();
	}

	$redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : sam_auth_redirect_url();
	?>
	<form class="scorecard-login-form" method="post" novalidate>
		<input type="hidden" name="sam_auth_action" value="login">
		<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
		<div class="form-group mb-2"><label for="sam_username" class="form-label">Username or Email</label><input type="text" name="log" id="sam_username" class="form-control" required autocomplete="username"></div>
		<div class="form-group mb-2"><label for="sam_password" class="form-label">Password</label><input type="password" name="pwd" id="sam_password" class="form-control" required autocomplete="current-password"></div>
		<div class="form-check mb-2"><input type="checkbox" class="form-check-input" id="rememberme" name="rememberme" value="1" checked><label class="form-check-label" for="rememberme">Remember Me</label></div>
		<?php wp_nonce_field( 'sam_login_form', 'sam_login_nonce' ); ?>
		<button type="submit" class="btn btn-sm btn-primary text-white h4 w-100 mb-2">Sign In</button>
		<a href="<?php echo esc_url( sam_auth_url( array( 'auth' => 'lost-password' ) ) ); ?>" class="btn btn-sm btn-light btn-block mb-0 mx-2 col-6 h4">Lost password?</a>
	</form>
	<?php
	return ob_get_clean();
} );

add_shortcode( 'sam_registration_form', function() {
	if ( is_user_logged_in() ) {
		return '<div class="alert alert-info">You are already signed in.</div>';
	}

	$errors = array();
	$success = '';

	if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['sam_auth_action'] ) && $_POST['sam_auth_action'] === 'register' ) {
		if ( ! isset( $_POST['sam_registration_nonce'] ) || ! wp_verify_nonce( $_POST['sam_registration_nonce'], 'sam_registration_form' ) ) {
			$errors[] = 'Security check failed. Please refresh the page and try again.';
		} else {
			$username = sanitize_user( wp_unslash( $_POST['user_login'] ?? '' ) );
			$email = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) );
			$password = (string) wp_unslash( $_POST['user_pass'] ?? '' );

			if ( $username === '' || $email === '' || $password === '' ) $errors[] = 'All fields are required.';
			if ( $email && ! is_email( $email ) ) $errors[] = 'Please enter a valid email address.';
			if ( $password && strlen( $password ) < 8 ) $errors[] = 'Password must be at least 8 characters.';
			if ( $username && username_exists( $username ) ) $errors[] = 'Username already exists.';
			if ( $email && email_exists( $email ) ) $errors[] = 'Email already registered.';

			if ( empty( $errors ) ) {
				$user_id = wp_create_user( $username, $password, $email );
				if ( is_wp_error( $user_id ) ) {
					$errors[] = $user_id->get_error_message();
				} else {
					wp_set_current_user( $user_id );
					wp_set_auth_cookie( $user_id, true, is_ssl() );
					wp_safe_redirect( sam_auth_redirect_url() );
					exit;
				}
			}
		}
	}

	ob_start();
	foreach ( $errors as $error ) echo '<div class="alert alert-danger">' . esc_html( $error ) . '</div>';
	if ( $success ) echo '<div class="alert alert-success">' . esc_html( $success ) . '</div>';
	?>
	<form method="post" id="registrationform">
		<input type="hidden" name="sam_auth_action" value="register">
		<?php wp_nonce_field( 'sam_registration_form', 'sam_registration_nonce' ); ?>
		<div class="form-group mb-2"><label for="user_login_reg">Username</label><input type="text" class="form-control" id="user_login_reg" name="user_login" required></div>
		<div class="form-group mb-2"><label for="user_email_reg">Email</label><input type="email" class="form-control" id="user_email_reg" name="user_email" required></div>
		<div class="form-group mb-2"><label for="user_pass_reg">Password</label><input type="password" class="form-control" id="user_pass_reg" name="user_pass" required></div>
		<input type="submit" class="btn btn-sm btn-primary text-white btn-block h3 m-0 mb-3 col-12" value="Register">
	</form>
	<?php
	return ob_get_clean();
} );

add_shortcode( 'sam_lost_password_form', function() {
	if ( is_user_logged_in() ) {
		return '<div class="alert alert-info">You are already signed in.</div>';
	}

	$errors = array();
	$success = '';

	if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['sam_auth_action'] ) && $_POST['sam_auth_action'] === 'lost-password' ) {
		if ( ! isset( $_POST['sam_lost_password_nonce'] ) || ! wp_verify_nonce( $_POST['sam_lost_password_nonce'], 'sam_lost_password_form' ) ) {
			$errors[] = 'Security check failed. Please refresh the page and try again.';
		} else {
			$user_login = sanitize_text_field( wp_unslash( $_POST['user_login'] ?? '' ) );
			if ( $user_login === '' ) {
				$errors[] = 'Enter a username or email address.';
			} else {
				$result = retrieve_password( $user_login );
				if ( is_wp_error( $result ) ) {
					$errors[] = 'If an account exists for that username or email, a password reset link will be sent.';
				} else {
					$success = 'Check your email for the password reset link.';
				}
			}
		}
	}

	ob_start();
	foreach ( $errors as $error ) echo '<div class="alert alert-danger">' . esc_html( $error ) . '</div>';
	if ( $success ) echo '<div class="alert alert-success">' . esc_html( $success ) . '</div>';
	?>
	<form method="post">
		<input type="hidden" name="sam_auth_action" value="lost-password">
		<?php wp_nonce_field( 'sam_lost_password_form', 'sam_lost_password_nonce' ); ?>
		<div class="form-group mb-2"><label for="user_login_lost">Username or Email address</label><input type="text" class="form-control" id="user_login_lost" name="user_login" required></div>
		<button type="submit" class="btn btn-sm btn-primary text-white btn-block h4 w-100 m-0">Get Password Reset Link</button>
	</form>
	<?php
	return ob_get_clean();
} );

add_filter( 'retrieve_password_title', function() {
	return get_bloginfo( 'name' ) . ' Password Reset';
} );

add_filter( 'retrieve_password_message', function( $message, $key, $user_login, $user_data ) {
	$reset_url = sam_auth_url( array(
		'action' => 'rp',
		'key'    => $key,
		'login'  => rawurlencode( $user_login ),
	) );

	$message  = 'Someone requested a password reset for ' . home_url( '/' ) . "\n\n";
	$message .= 'Username: ' . $user_login . "\n\n";
	$message .= "If this was a mistake, ignore this email and nothing will happen.\n\n";
	$message .= "To reset your password, visit this address:\n\n";
	$message .= '<' . $reset_url . ">\n";
	return $message;
}, 10, 4 );

add_shortcode( 'sam_password_reset_form', function() {
	if ( is_user_logged_in() ) {
		return '<div class="alert alert-info my-5">You are already signed in.</div>';
	}

	$error = '';
	$success = '';
	$rp_key = sanitize_text_field( wp_unslash( $_REQUEST['key'] ?? '' ) );
	$rp_login = sanitize_text_field( wp_unslash( $_REQUEST['login'] ?? '' ) );

	if ( isset( $_POST['sam_auth_action'] ) && $_POST['sam_auth_action'] === 'reset-password' ) {
		if ( ! isset( $_POST['sam_password_reset_nonce'] ) || ! wp_verify_nonce( $_POST['sam_password_reset_nonce'], 'sam_password_reset_form' ) ) {
			$error = 'Security check failed. Please refresh the page and try again.';
		} else {
			$rp_key = sanitize_text_field( wp_unslash( $_POST['rp_key'] ?? '' ) );
			$rp_login = sanitize_text_field( wp_unslash( $_POST['rp_login'] ?? '' ) );
			$password = (string) wp_unslash( $_POST['password'] ?? '' );
			$user = check_password_reset_key( $rp_key, $rp_login );

			if ( is_wp_error( $user ) ) $error = 'This password reset link is invalid or has expired.';
			elseif ( strlen( $password ) < 8 ) $error = 'Password must be at least 8 characters.';
			else {
				reset_password( $user, $password );
				$success = 'Your password has been reset successfully.';
			}
		}
	}

	ob_start();
	if ( $error ) echo '<div class="alert alert-danger my-3">' . esc_html( $error ) . '</div>';
	if ( $success ) {
		echo '<div class="alert alert-success my-3">' . esc_html( $success ) . '</div>';
		echo '<a href="' . esc_url( sam_auth_url() ) . '" class="btn btn-primary text-white btn-block h4 mt-3">Go to Login</a>';
		return ob_get_clean();
	}
	if ( ! $rp_key || ! $rp_login ) {
		echo '<div class="alert alert-danger my-3">Invalid password reset link. Please request a new one.</div>';
		echo do_shortcode( '[sam_lost_password_form]' );
		return ob_get_clean();
	}
	?>
	<form method="post" class="password-reset-form">
		<input type="hidden" name="sam_auth_action" value="reset-password">
		<input type="hidden" name="rp_key" value="<?php echo esc_attr( $rp_key ); ?>">
		<input type="hidden" name="rp_login" value="<?php echo esc_attr( $rp_login ); ?>">
		<?php wp_nonce_field( 'sam_password_reset_form', 'sam_password_reset_nonce' ); ?>
		<div class="form-group mb-2"><label for="sam_new_password">New Password</label><input type="password" id="sam_new_password" class="form-control" name="password" required autocomplete="new-password"></div>
		<input type="submit" class="btn btn-primary text-white btn-block h3 m-0" value="Reset Password">
	</form>
	<?php
	return ob_get_clean();
} );

add_filter( 'the_content', function( $content ) {
	if ( ! is_page( 'account' ) ) {
		return $content;
	}
	$action = sanitize_key( $_GET['action'] ?? '' );
	$auth = sanitize_key( $_GET['auth'] ?? '' );
	if ( in_array( $action, array( 'rp', 'resetpass' ), true ) ) {
		return '<div class="container my-5"><div class="card rounded-4 shadow"><div class="card-header h3">Reset Password</div><div class="card-body">' . do_shortcode( '[sam_password_reset_form]' ) . '</div></div></div>';
	}
	if ( $auth === 'lost-password' ) {
		return '<div class="container my-5"><div class="card rounded-4 shadow"><div class="card-header h3">Password Reset</div><div class="card-body">' . do_shortcode( '[sam_lost_password_form]' ) . '</div></div></div>';
	}
	return $content;
}, 20 );
