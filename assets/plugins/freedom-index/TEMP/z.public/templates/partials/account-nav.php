<?php
if (!defined('ABSPATH')) exit;
/**
 * Account Navigation
 * Left navigation for account pages (like WooCommerce)
 * 
 * @var string $current_page Current page identifier
 */
$current_page = $args['current_page'] ?? 'dashboard';
$user = wp_get_current_user();

$menu_items = [
	'dashboard' => [
		'url' => home_url('/account/dashboard/'),
		'slug' => 'dashboard',
		'icon' => 'bi-speedometer2',
		'label' => 'Dashboard',
	],
	'profile' => [
		'url' => home_url('/account/profile/'),
		'slug' => 'profile',
		'icon' => 'bi-person',
		'label' => 'My Profile',
	],
	'personalize' => [
		'url' => home_url('/account/personalize/'),
		'slug' => 'personalize',
		'icon' => 'bi-file-pdf',
		'label' => 'PDF Contact Info',
	],
	'lists' => [
		'url' => home_url('/account/lists/'),
		'slug' => 'lists',
		'icon' => 'bi-bookmark',
		'label' => 'Legislator Lists',
	],
	'notifications' => [
		'url' => home_url('/account/notifications/'),
		'slug' => 'notifications',
		'icon' => 'bi-bell',
		'label' => 'Notifications',
	],
	'logout' => [
		'url' => wp_logout_url(home_url('/account/?loggedout=true')),
		'slug' => 'logout',
		'icon' => 'bi-box-arrow-right',
		'label' => 'Logout',
	],
];
?>
<nav class="fi-account-nav d-none d-md-block col-md-3 mb-4">
	<ul class="list-group rounded-4 shadow">
		<?php 
		foreach ($menu_items as $item) {
			echo '<li class="list-group-item ' . ($current_page === $item['slug'] ? 'bg-primary' : '') . '">';
			echo '<a href="' . esc_url($item['url']) . '" class="text-decoration-none ' . ($current_page === $item['slug'] ? 'fw-bold text-white' : '') . '">';
			echo '<i class="bi ' . esc_attr($item['icon']) . ' me-2"></i> ' . esc_html($item['label']);
			echo '</a>';
			echo '</li>';
		}
		?>
	</ul>
</nav>

<!-- Mobile Navigation -->
<nav class="fi-account-nav-mobile d-md-none mb-4">
	<div class="dropdown">
		<button class="btn btn-outline-primary w-100 dropdown-toggle fw-bold p-2 fs-6" type="button" id="accountNavDropdown" data-bs-toggle="dropdown" aria-expanded="false">
			<?php //echo 'MENU: ' . esc_html($menu_items[$current_page]['label'] ?? 'Account');?>
			Account Menu
		</button>
		<ul class="dropdown-menu w-100" aria-labelledby="accountNavDropdown">
			<?php 
			foreach ($menu_items as $item) {
				echo '<li class="dropdown-item' . ($current_page === $item['slug'] ? ' active bg-primary' : '') . '">';
				echo '<a href="' . esc_url($item['url']) . '" class="text-decoration-none' . ($current_page === $item['slug'] ? ' active fw-bold text-white' : '') . '">';
				echo '<i class="bi ' . esc_attr($item['icon']) . ' me-2"></i> ' . esc_html($item['label']);
				echo '</a>';
				echo '</li>';
			}
			?>
		</ul>
	</div>
</nav>