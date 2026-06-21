<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$height = $args['height'] ?? FI_GOV_CARD_HEIGHT;

?>
<div class="card rounded-4 shadow h-100">
	<div class="card-header bg-warning rounded-top-4">
		<h2 class="card-title fs-4 mb-0 text-muted text-center"><i class="bi bi-exclamation-diamond"></i> Legislative Alerts</h2>	
	</div>
	<div class="card-body">
		<div class="list-group list-group-flush" style="max-height:<?php echo esc_attr($height); ?>; overflow-y: auto; overscroll-behavior: contain; scrollbar-width: thin;" data-scrollbar>
		<?php
		// Get RSS feed URL based on gov
		$rss_url = '';
		if ($gov === 'US') {
			$rss_url = 'https://jbs.org/alerts/federal/feed/';
			fi_get_template('partials/gov-alerts-feed', ['url' => $rss_url]);
		} else {
			$state_slug = str_replace(' ', '-', strtolower(FI_GOVERNMENTS[$gov]));
			$rss_url = 'https://jbs.org/alerts/' . $state_slug . '/feed/';
			fi_get_template('partials/gov-alerts-feed', ['url' => $rss_url]);

			$rss_url_all_states = 'https://jbs.org/alerts/state/feed/';
			fi_get_template('partials/gov-alerts-feed', ['url' => $rss_url_all_states]);
		}
		fi_scrollbar_css();
		?>
		</div>
	</div>
	<div class="card-footer p-0">
		<a href="<?php echo esc_url(str_replace('/feed/', '/', $rss_url)); ?>" target="_blank" rel="noopener" class="btn btn-warning w-100 fs-7 rounded-0 rounded-bottom-4">
			View All <?php echo fi_gov_name($gov); ?> Alerts
		</a>
	</div>
</div>