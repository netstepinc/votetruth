<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Votes List Template
 * Displays a list of votes for a specific government with search and tag filtering.
 * 
 * Available global variables:
 * - $fi_gov: Government code (e.g., 'US', 'TX', 'WI')
 * - $fi_session: Current session ID
 * - $fi_tag_slug: Tag slug for filtering (if present)
 * - $fi_chamber: Chamber filter (S/H) (if present)
 */

// Get global variables set by rewrite handler
global $fi_gov, $fi_gov_name, $fi_session, $fi_tag_slug, $fi_chamber;

$gov = strtoupper($fi_gov ?? 'US');
$gov_slug = strtolower($gov);
$gov_name = $fi_gov_name ?? fi_gov_name($gov);
$gov_name_adj = ($gov === 'US') ? 'Congressional' : $gov_name;

$tag_slug = $fi_tag_slug ?? '';

$chambers = fi_chamber_info($gov);
$chamber = strtoupper((string) ($fi_chamber ?? ''));
if (!in_array($chamber, ['H', 'S'], true)) {
	$chamber = '';
}

// SEO Meta Tags
$gov_slug = strtolower($gov);
$current_url = home_url('/' . $gov_slug . '/votes/');
$title_text = $gov_name_adj . ' Votes';
$description = 'Browse all votes for ' . $gov_name . '. Search by title, bill name, or filter by tags.';
$header_description = 'All votes for ' . $gov_name;
$chamber_label = '';
if (!empty($chamber)) {
	$current_url = home_url('/' . $gov_slug . '/votes/chamber/' . $chamber . '/');
	$chamber_label = $chambers[$chamber]['chamber'] ?? '';
	$description = $chamber_label . ' votes';
	$header_description = $chamber_label . ' votes';
}
if (!empty($tag_slug)) {
    $tag = fi_taxonomy_get_by_slug($tag_slug, 'tag', $gov);
    if ($tag) {
        $current_url = fi_tag_url($tag_slug, $gov_slug);
        $description = 'Votes tagged as "' . $tag->name . '" for ' . $gov_name . '.';
		$header_description = 'Votes tagged as "' . $tag->name;
    }
}

fi_seo_tags([
    'title' => $title_text . ' | Freedom Index',
    'description' => $description,
    'canonical' => $current_url,
    'robots' => 'index, follow',
    'og' => [
        'og:title' => $title_text . ' | Freedom Index',
        'og:description' => $description,
        'og:url' => $current_url,
        'og:type' => 'website',
    ],
    'twitter' => [
        'twitter:card' => 'summary',
        'twitter:title' => $title_text,
        'twitter:description' => $description,
    ],
]);

get_header();

// Get all votes for this gov
$votes_args = [
	'gov' => $gov,
	'status' => 'publish',
	'orderby' => 'date_voted',
	'order' => 'DESC',
	'per_page' => -1, // Get all votes for filtering/pagination
];
if (!empty($chamber)) {
	$votes_args['chamber'] = $chamber;
}

// If tag filter is present, get votes by tag
$votes = [];
$active_tag = null;
$has_filter = false; // Track if we should skip pagination
if (!empty($tag_slug)) {
	// Get tag by slug
	$tag = fi_taxonomy_get_by_slug($tag_slug, 'tag', $gov);
	if ($tag) {
		$active_tag = $tag;
		$votes = fi_votes_get_by_tag($tag->id, $votes_args);
		$has_filter = true; // Tag filter active - show all results
	} else {
		// Tag not found, show all votes
		$votes = fi_votes_get($votes_args);
	}
} else {
	// Get all votes (only call once)
	$votes = fi_votes_get($votes_args);
}

// Pagination settings
$per_page = 50;
$total_votes = count($votes);
$show_pagination = !$has_filter && $total_votes > $per_page;

// Always render all votes, but hide those beyond first page if paginated
// This allows search to work across all votes and Load More to just show/hide
$votes_to_display = $votes; // Display all votes
$remaining_count = $show_pagination ? ($total_votes - $per_page) : 0;

// Get all tags for this gov's votes (for button group)
// Sort alphabetically for easier scanning
$tags = fi_vote_tags_get_tag_counts($gov, null, 'name');

$header_args = [
	'title' => $title_text,
	'gov' => $gov,
	'gov_name' => $gov_name,
	'description' => $header_description . ' | <span id="fi-votes-count">' . ($show_pagination ? $per_page : count($votes)) . '</span> ', //votes' . ($show_pagination ? ' <span class="text-muted">of ' . $total_votes . '</span>' : ''),
	'breadcrumbs' => [
		['text' => $gov_name, 'url' => home_url('/' . strtolower($gov) . '/')],
		['text' => 'Votes', 'url' => '', 'class' => 'fw-bold']
	],
	'id' => 'fi-votes',
	'class' => 'fi-votes-list',
	'filter_enabled' => false, // We'll add custom search/filter UI
	'breadcrumbs_args' => [
		'template_name' => 'votes',
	],
];

fi_get_public_template('partials/template-header', $header_args);

$all_votes = empty($tag_slug) && empty($chamber);
?>
<div class="row g-4 mb-4">
	<!-- Search and Tag Filters -->
	<div class="col-md-3 order-md-2">
		<div id="fi-votes-filters" class="card rounded-4 shadow-sm mb-3 p-3">
			<!-- Search Box -->
			<div class="mb-3">
				<input type="text" class="form-control shadow" style="background-color: #ffffe0;" id="fi-votes-search" placeholder="Search by title, bill name, or text" autocomplete="off">
			</div>
			
			<!-- Filter Buttons -->
			<div class="mb-3">
				<a href="<?php echo esc_url(home_url('/' . strtolower($gov) . '/votes/')); ?>" class="btn btn-sm shadow-sm w-100 mb-2 text-start <?= $all_votes ? 'btn-primary' : 'btn-outline-primary'; ?>">
					All Votes
				</a>
				<?php
				// Chamber filter buttons (s/h)
				foreach ($chambers as $chamber_code => $chamber_info) {
					$chamber_code = strtoupper((string) $chamber_code);
					if (!in_array($chamber_code, ['S', 'H'], true)) {
						continue;
					}

					// Skip "S" if unicameral and chamber info is empty
					if ($chamber_code === 'S' && empty($chamber_info)) {
						continue;
					}

					$chamber_label = $chambers[$chamber_code]['chamber'] ?? '';
					$url_vote_chamber = home_url('/' . strtolower($gov) . '/votes/chamber/' . $chamber_code . '/');
					$is_active = (!empty($chamber) && $chamber === $chamber_code);

					echo '<a href="' . esc_url($url_vote_chamber) . '" class="btn btn-sm shadow-sm w-100 text-start mb-2 ' . ($is_active ? 'btn-primary' : 'btn-outline-primary') . '">';
					echo esc_html($chamber_label . ' Votes');
					echo '</a>';
				}

				if(count($tags) > 0):
				?>
				<label class="form-label small text-muted mb-2 d-block">Filter by Issue:</label>
				<div class="d-block" role="group" aria-label="Tag filters">
					<?php foreach ($tags as $tag): 
						$tag_url = fi_tag_url($tag->slug ?? '', strtolower($gov));
						$is_active = ($tag_slug === ($tag->slug ?? ''));
					?>
						<a href="<?php echo esc_url($tag_url); ?>" class="btn btn-sm shadow-sm w-100 text-start mb-2 <?php echo $is_active ? 'btn-primary' : 'btn-outline-primary'; ?>">
							<span class="d-flex align-items-center justify-content-between">
								<span><?php echo esc_html($tag->name ?? ''); ?></span>
							<?php if (isset($tag->vote_count)): ?>
								<span class="badge bg-primary text-white ms-2"><?php echo (int)$tag->vote_count; ?></span>
							<?php endif; ?>
							</span>
						</a>
					<?php endforeach; ?>
				</div>
				<?php endif;?>
			</div>
		</div>
	</div>

	<!-- Votes List -->
	<div class="col-md-9 order-md-1">
		<!-- Votes Grid -->
		<div class="row g-4" id="fi-votes-results" data-total-votes="<?php echo esc_attr($total_votes); ?>" data-per-page="<?php echo esc_attr($per_page); ?>" data-has-filter="<?php echo $has_filter ? '1' : '0'; ?>">
			<?php if (empty($votes)): ?>
				<div class="col-12">
					<div class="text-center py-5">
						<h3 class="text-muted">No votes found</h3>
						<p class="text-muted"><?php echo !empty($tag_slug) ? 'No votes match this tag filter.' : 'No votes available for this government.'; ?></p>
					</div>
				</div>
			<?php else: ?>
				<?php 
				$vote_index = 0;
				foreach ($votes_to_display as $vote):
					$vote_index++;
					// Hide votes beyond first page if pagination is active
					$should_hide = $show_pagination && $vote_index > $per_page;
					$hidden_class = $should_hide ? ' fi-vote-hidden' : '';
					
					// Process vote data for vote-card partial
					$vote_meta = fi_vote_decode_meta($vote);
					
					// Get description
					$descriptions = fi_vote_get_description($vote_meta);
					$text = $descriptions['short'] ?? '';
					$text_more = $descriptions['long'] ?? '';
					
					// Tags (optional; used by vote-card modal)
					$vote_tags = $vote_meta['tags'] ?? [];
					
					// Format date
					$formatted_date = '';
					if (!empty($vote['date_voted'])) {
						$date = !empty($vote['date_voted']) ? strtotime($vote['date_voted']) : false;
						if ($date) {
							$formatted_date = date('m/d/Y', $date); // mm/dd/yyyy
						} else {
							$formatted_date = $vote['date_voted'];
						}
					}
					
					// Get vote format (constitutional only, no cast)
					$vote_format = fi_vote_format([
						'constitutional' => $vote['constitutional'] ?? '',
						'format' => 'full'
					]);
					
					// Format cost
					$cost = !empty($vote_meta['cost']) ? fi_vote_cost_format($vote_meta['cost']) : ['html' => ''];

					// Build URLs
					$url_vote = fi_url_vote($vote['gov'], $vote['id']);
					$bill_url = $vote_meta['url_bill'] ?? '';
					
					// Build search text (vote-specific fields only)
					$search_text = strtolower(($vote['title'] ?? '') . ' ' . ($vote['bill_number'] ?? '') . ' ' . strip_tags($text));
					
					// Prepare vote card data (ONLY keys supported by vote-card.php)
					$vote_data = [
						'id' => $vote['id'],
						'title' => $vote['title'],
						'text' => $text,
						'text_more' => $text_more,
						'tags' => $vote_tags,
						'date_formatted' => $formatted_date,
						'constitutional' => $vote['constitutional'],
						'vote_format' => $vote_format,
						'chamber' => $vote['chamber'],
						'chamber_label' => $chambers[$vote['chamber']]['chamber'] ?? '',
						'bill_number' => $vote['bill_number'],
						'bill_url' => $bill_url,
						'cost_html' => $cost['html'],
						'url_vote' => $url_vote,
						'search_text' => $search_text,
						// For votes list, we don't show legislator's vote cast
						'show_cast' => false,
						'show_link' => true,
						'show_link_title' => true,
					];

					// Wrap vote card in a container that can be hidden
					echo '<div class="fi-vote-wrapper' . esc_attr($hidden_class) . '">';
					fi_get_public_template('partials/vote-card', $vote_data);
					echo '</div>';
				endforeach;
			endif;
			?>
		</div>

		<?php if ($show_pagination && $remaining_count > 0): ?>
		<!-- Load More Button -->
		<div class="row mt-4">
			<div class="col-12 text-center">
				<button id="fi-votes-load-more" class="btn btn-primary btn-lg" data-loaded="<?php echo esc_attr(count($votes_to_display)); ?>" data-remaining="<?php echo esc_attr($remaining_count); ?>">
					Load More Votes (<span id="fi-remaining-count"><?php echo $remaining_count; ?></span> remaining)
				</button>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>

<style>
.fi-vote-hidden {
	display: none;
}
</style>

<script>
jQuery(document).ready(function($) {
	const $searchInput = $('#fi-votes-search');
	const $results = $('#fi-votes-results');
	const $count = $('#fi-votes-count');
	const $loadMoreBtn = $('#fi-votes-load-more');
	const $remainingCount = $('#fi-remaining-count');
	
	// Get pagination settings
	const totalVotes = parseInt($results.data('total-votes')) || 0;
	const perPage = parseInt($results.data('per-page')) || 50;
	const hasFilter = $results.data('has-filter') === 1;
	
	// Get all vote wrappers (they contain the vote cards)
	const $allVoteWrappers = $results.find('.fi-vote-wrapper');
	const $allVoteCards = $results.find('.fi-vote-card');
	let currentDisplayCount = $allVoteWrappers.filter(':not(.fi-vote-hidden)').length;
	let searchActive = false;
	
	// Load more votes (show next batch)
	function loadMoreVotes() {
		const $hiddenWrappers = $allVoteWrappers.filter('.fi-vote-hidden');
		const nextBatch = $hiddenWrappers.slice(0, perPage);
		
		if (nextBatch.length === 0) {
			$loadMoreBtn.hide();
			return;
		}
		
		// Show next batch
		nextBatch.removeClass('fi-vote-hidden');
		currentDisplayCount += nextBatch.length;
		
		const remaining = $hiddenWrappers.length - nextBatch.length;
		
		if (remaining > 0) {
			$remainingCount.text(remaining);
		} else {
			$loadMoreBtn.hide();
		}
		
		// Update count
		updateCount();
	}
	
	// Update displayed count
	function updateCount() {
		const visibleCount = $allVoteCards.filter(':visible').length;
		const totalText = hasFilter || searchActive 
			? visibleCount + ' votes'
			: visibleCount + ' votes <span class="text-muted">of ' + totalVotes + '</span>';
		$count.html(totalText);
	}
	
	// Load More button handler
	if ($loadMoreBtn.length) {
		$loadMoreBtn.on('click', function() {
			loadMoreVotes();
		});
	}
	
	// Search functionality - works across all votes (even hidden ones)
	$searchInput.on('input', function() {
		const searchTerm = $(this).val().toLowerCase().trim();
		searchActive = searchTerm !== '';
		
		if (!searchActive) {
			// Show all votes that should be visible based on pagination
			$allVoteWrappers.each(function() {
				const $wrapper = $(this);
				const $card = $wrapper.find('.fi-vote-card');
				const searchText = $card.data('search-text') || '';
				
				// Show if it's in the initial batch (not hidden) or if we've loaded it
				if (!$wrapper.hasClass('fi-vote-hidden')) {
					$wrapper.show();
					$card.show();
				} else {
					$wrapper.hide();
				}
			});
			updateCount();
			return;
		}
		
		// Filter all votes (including hidden ones for search)
		let visibleCount = 0;
		$allVoteWrappers.each(function() {
			const $wrapper = $(this);
			const $card = $wrapper.find('.fi-vote-card');
			const searchText = $card.data('search-text') || '';
			
			if (searchText.toLowerCase().includes(searchTerm)) {
				$wrapper.show();
				$card.show();
				visibleCount++;
			} else {
				$wrapper.hide();
				$card.hide();
			}
		});
		
		updateCount();
	});
	
	// Initial count update
	updateCount();
});
</script>
<?php 
fi_get_public_template('partials/template-footer');
get_footer();