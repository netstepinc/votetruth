<?php
// Debug output (visible on page - remove after testing)
if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('administrator')) {
	global $fi_debug_query;
	echo '<!-- FI Debug Info -->';
	echo '<div class="alert alert-warning mb-3">';
	echo '<strong>Debug Info:</strong><br>';
	echo 'Gov: ' . esc_html($fi_gov ?? 'not set') . '<br>';
	echo 'Gov Name: ' . esc_html($fi_gov_name ?? 'not set') . '<br>';
	echo 'Session ID: ' . esc_html($fi_session ?? 'not set') . '<br>';
	echo 'Legislators Count: ' . count($fi_legislators ?? []) . '<br>';
	echo 'Filter Session: ' . esc_html($filter_session ?? 'null') . '<br>';
	echo 'Filter Party: ' . esc_html($filter_party ?: 'empty') . '<br>';
	echo 'Filter Chamber: ' . esc_html($filter_chamber ?: 'empty') . '<br>';
	echo 'Filter Search: ' . esc_html($filter_search ?: 'empty') . '<br>';
	echo '<br><strong>Query Vars:</strong><br>';
	echo 'fi_session_slug: ' . esc_html(get_query_var('fi_session_slug') ?: 'empty') . '<br>';
	echo 'fi_party_slug: ' . esc_html(get_query_var('fi_party_slug') ?: 'empty') . '<br>';
	echo 'fi_chamber: ' . esc_html(get_query_var('fi_chamber') ?: 'empty') . '<br>';
	echo 'fi_search: ' . esc_html(get_query_var('fi_search') ?: 'empty') . '<br>';
	if (!empty($fi_debug_query)) {
		echo '<br><strong>SQL Query:</strong><br>';
		echo '<pre style="font-size: 11px; max-height: 300px; overflow: auto;">' . esc_html($fi_debug_query['sql']) . '</pre>';
		echo '<br><strong>Query Args:</strong><br>';
		echo '<pre style="font-size: 11px;">' . esc_html(print_r($fi_debug_query['args'], true)) . '</pre>';
		echo '<br><strong>Where Values:</strong><br>';
		echo '<pre style="font-size: 11px;">' . esc_html(print_r($fi_debug_query['where_values'], true)) . '</pre>';
	}
	echo '</div>';
}
?>