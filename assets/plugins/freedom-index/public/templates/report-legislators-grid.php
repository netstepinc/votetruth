<?php if (!defined('ABSPATH')) exit;

/**
 * Report legislators grid (cards). Expects $args from report: legislator_data, gov.
 * Data is built once by fi_report_legislators_build_data() in report.php.
 */
$legislator_data = $args['legislator_data'] ?? [];
$gov = $args['gov'] ?? 'US';
$vote_start = $args['vote_start'] ?? 1;

if (empty($legislator_data)) {
    echo '<p class="text-muted">No legislators found for this session and chamber.</p>';
    return;
}
?>
<div class="row g-4">
    <?php foreach ($legislator_data as $data):
        fi_get_template('report-legislator-card', [
            'legislator' => $data['legislator'],
            'report_score' => $data['report_score'],
            'votes' => $data['votes'],
            'gov' => $gov,
			'vote_start' => $vote_start,
        ]);
    endforeach; ?>
</div>
