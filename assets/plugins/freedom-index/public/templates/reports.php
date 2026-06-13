<?php
if (!defined('ABSPATH')) exit;

/* Reports List Page Template
 * by Sam Mittelstaedt <smittelstaedt@jbs.org>
 */

global $fi_gov;

$gov = strtoupper($fi_gov ?? 'US');
$gov_slug = strtolower($gov);
$gov_name = $fi_gov_name ?? fi_gov_name($gov);
$gov_name_adj = ($gov === 'US') ? 'Congressional' : $gov_name;

// SEO Meta Tags
$current_url = home_url('/' . $gov_slug . '/reports/');

fi_seo_tags([
    'title' => $gov_name_adj . ' Vote Reports | Freedom Index',
    'description' => 'Browse all vote reports for ' . $gov_name . '. View legislator scores and voting records by session.',
    'canonical' => $current_url,
    'robots' => 'index, follow',
    'og' => [
        'og:title' => $gov_name_adj . ' Vote Reports | Freedom Index',
        'og:description' => 'Browse all vote reports for ' . $gov_name . '.',
        'og:url' => $current_url,
        'og:type' => 'website',
    ],
    'twitter' => [
        'twitter:card' => 'summary',
        'twitter:title' => $gov_name_adj . ' Vote Reports',
        'twitter:description' => 'Browse all vote reports for ' . $gov_name . '.',
    ],
]);

get_header();

// Get all published reports for this government
$reports = fi_reports_get([
    'gov' => $gov,
    'status' => 'publish',
    'orderby' => 'date_publish',
    'order' => 'DESC'
]);

$header_args = [
    'title' => $gov_name_adj . ' Vote Reports',
	//'pretext' => $gov_name . ' Vote Reports',
    //'description' => 'Browse all vote reports for ' . $gov_name . '.',
    'id' => 'fi-reports',
    'class' => 'fi-reports-list',
	'gov' => $gov,
	'gov_name' => $gov_name_adj,
    'breadcrumbs_args' => [
        'template_name' => 'reports',
    ],
	'filter_enabled' => false,
];

fi_get_public_template('partials/template-header', $header_args);
?>
<div class="row g-4">
	<?php if (empty($reports)): ?>
		<div class="col-12">
			<div class="alert alert-info">
				<h4>No Reports Found</h4>
				<p>No reports are available for <?php echo esc_html($gov_name); ?> at this time.</p>
			</div>
		</div>
	<?php else: ?>
		<?php foreach ($reports as $report): 
			// Decode payload to get report content and votes
			$payload = fi_report_decode_payload($report->payload_json ?? null);
			$description = $payload['content'] ?? '';
			$pdf_url = $payload['report_pdf_url'] ?? '';

			$votes_s = $payload['votes_s'] ?? [];
			$votes_h = $payload['votes_h'] ?? [];
			$vote_chambers = [
				's' => [
					'title' => 'Senate Votes',
					'votes' => $votes_s,
				],
				'h' => [
					'title' => 'House Votes',
					'votes' => $votes_h,
				],
			];
			$report->title = fi_report_title_reformat($gov, $report->title);
		?>
			<!-- Report Title + Description -->
			<div class="col-12 mb-5">
				<div class="card h-100 shadow rounded-4">
					<div class="card-header rounded-top-4 bg-white">
						<h3 class="card-title fs-3 mb-0 d-flex align-items-center justify-content-between w-100 flex-wrap gap-2">
							<a href="<?php echo esc_url(fi_url_report($report->id, strtolower($gov))); ?>" class="text-decoration-none">
								<?php echo esc_html($report->title); ?>
							</a>
<?php
if (!empty($pdf_url)) {
	echo '<a href="' . esc_url($pdf_url) . '" class="btn btn-danger d-none d-lg-inline-block fw-bold py-2 ms-2" target="_blank"><i class="fas fa-file-pdf me-2"></i>Download PDF</a>';
}
?>
						</h3>
						<?php if (!empty($description)): ?>
							<div class="card-body px-0"><?php echo wp_kses_post(wpautop($description)); ?></div>
						<?php endif; ?>
					</div>
					<!-- List of Votes in Report -->
					<div class="card-body">
						<div class="row g-5">
						<?php foreach ($vote_chambers as $key => $chamber): ?>
							<?php if (!empty($chamber['votes'])): ?>
							<div class="col-12 col-md-6">
								<h4 class="card-title fs-5 mb-3"><?php echo esc_html($chamber['title']); ?></h4>
								<ul class="list-group list-group-flush">
									<?php foreach ($chamber['votes'] as $vote_id): 
										$vote = fi_vote_get($vote_id);
										if ($vote):
											$meta = json_decode($vote->meta,true) ?? [];
											$descriptions = fi_vote_get_description($meta);
											$description = $descriptions['short'] ?? '';
											$vote_url = fi_url_vote($vote->gov, $vote->id);
										?>
										<li class="list-group-item px-0 py-1 border-bottom">
											<a href="<?php echo esc_url($vote_url); ?>" class="text-decoration-none fw-semibold">
												<?php echo esc_html($vote->title); ?>
											</a>
											<div class="small text-muted pmb-0">
												<?php echo wp_kses_post(wpautop($description)); ?>
											</div>
										</li>
										<?php endif; ?>
									<?php endforeach; ?>
								</ul>
							</div>
							<?php endif; ?>
						<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
<?php
fi_get_public_template('partials/template-footer');
get_footer();
?>
