<?php
if (!defined('ABSPATH')) exit;

/**
 * Vote Card Partial - Context Agnostic
 *
 * Accepts pre-processed vote data and configuration options.
 * All data processing MUST happen in the parent template.
 *
 * ## modal_mode
 *
 * Controls how the "Read More" button and vote detail modal behave.
 * Default: 'bootstrap'
 *
 *   'bootstrap'  Per-card modal rendered in the DOM. Bootstrap auto-opens it via
 *                data-bs-toggle/target on the Read More button. Use this on standard
 *                vote-list pages (votes, tags, reports, etc.) where each card stands alone.
 *
 *   'page'       No modal HTML is rendered per card — avoids DOM bloat on pages with
 *                many votes (e.g. a legislator with 400+ votes). Instead, the Read More
 *                button receives class="fi-vote-readmore" plus data-vote-title and
 *                data-vote-body attributes containing the pre-rendered content.
 *                The page is responsible for providing ONE shared modal and a JS listener
 *                on .fi-vote-readmore that populates and opens it. See legislator-api.php
 *                for the reference implementation.
 *
 *   'none'       Read More button is not rendered at all. Use when the calling context
 *                has no modal support or the detail text is surfaced another way.
 *
 * Backward compat: show_modal => true maps to 'bootstrap', false maps to 'page'.
 */

$args = $args ?? [];

//All Defaults then merge with args: 'chamber' not set by default. used as switch
$config = [
	'id' => 0,
	'title' => '',
	'text' => '',
	'text_more' => '',
	'tags' => [],
	'date_voted' => '',
	'date_formatted' => '',
	'constitutional' => '',
	'vote_format' => [],
	'bill_number' => '',
	'bill_url' => '',
	'cost_html' => '',
	'url_vote' => '',
	'chamber' => '',
	'chamber_label' => '',
	'chamber_title' => false,
	'search_text' => '',
	'show_cast' => true,
	'show_link' => true,
	'modal_mode' => 'bootstrap', // 'bootstrap' = per-card modal | 'page' = delegated to page JS | 'none' = no Read More button
	'show_link_title' => false,
	'card_class' => 'h-100 rounded rounded-4 shadow',
	'header_class' => 'bg-white rounded-top-4',
	'header_title_class' => 'fs-6',
	'body_class' => 'pb-0',
	'body_text_class' => 'small',
	'footer_class_col' => 'col-4 text-center',
	'footer_class' => 'bg-light p-0 rounded-bottom-4',
	'collapse' => false,
	'collapse_text' => 'Show More',
	'collapse_class' => '',
];

$config = array_merge($config, $args);
$vote_id = $config['id'] ?? 0;

// Backward-compat: accept legacy show_modal bool and resolve modal_mode
if (isset($args['show_modal'])) {
	$config['modal_mode'] = $args['show_modal'] ? 'bootstrap' : 'page';
}
// Normalize true/false passed directly to modal_mode
if ($config['modal_mode'] === true)  $config['modal_mode'] = 'bootstrap';
if ($config['modal_mode'] === false) $config['modal_mode'] = 'page';

$modal_mode = $config['modal_mode'];


if($config['collapse']){
	$config['body_class'] = $config['body_class'] . ' collapse';
}

//if(get_current_user_id() == 1){	echo "<textarea style='width: 100%; height: 200px;'>"; print_r($config); echo "</textarea>"; }


//CONSTRUCT FOOTER COLUMNS
ob_start();
?>
<div class="col-4 text-center py-2 fi-vote-card-date">
	<div class="fs-7 ff-h lh-1 fw-bold"><?php echo esc_html($config['date_formatted']); ?></div>
	<small class="text-muted ff-h">Vote Date</small>
</div>
<?php 
$col_date = ob_get_clean();


ob_start();
if ($config['show_link']): ?>
<div class="col-4 text-center py-2 fi-vote-card-link">
	<!-- Modal Trigger Button (md-and-up only) -->
	<?php if (empty($config['text_more'])): ?>
	<div class="fs-7 ff-h lh-1 d-block">&nbsp;</div>
	<?php else: ?>
	<?php if ($modal_mode === 'bootstrap'): ?>
	<a href="#" class="fs-7 ff-h lh-1 fw-bold text-decoration-none d-block mb-1"
		data-bs-toggle="modal"
		data-bs-target="#voteCardDetailsModal<?php echo $config['id']; ?>"
	>Read More</a>
	<?php elseif ($modal_mode === 'page'):
		// No per-vote modal rendered — carry content as data attrs for the page's shared modal.
		$_modal_body = '';
		if (!empty($config['text_more'])) {
			$_modal_body .= '<div class="mb-4"><div class="entry-content post-content">' . wp_kses_post(wpautop($config['text_more'])) . '</div></div>';
		}
		if (!empty($config['tags'])) {
			global $fi_gov;
			$_gov = $fi_gov ?? 'US';
			$_modal_body .= '<div><h3>Related Votes</h3><div class="d-flex flex-wrap gap-2">';
			foreach ($config['tags'] as $_tag) {
				$_tag_url = fi_tag_url($_tag->slug ?? '', strtolower($_gov));
				$_modal_body .= '<a href="' . esc_url($_tag_url) . '" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">' . esc_html($_tag->name ?? '') . '</a>';
			}
			$_modal_body .= '</div></div>';
		}
	?>
	<a href="#" class="fs-7 ff-h lh-1 fw-bold text-decoration-none d-block mb-1 fi-vote-readmore"
		data-vote-title="<?php echo esc_attr($config['title']); ?>"
		data-vote-body="<?php echo esc_attr($_modal_body); ?>"
	>Read More</a>
	<?php endif; // modal_mode === 'page' ?>
	<?php endif; // modal_mode !== 'none' ?>
</div>
<?php
endif;
$col_link = ob_get_clean();


//Modal - only rendered for 'bootstrap' mode; 'page' carries content via data attrs, 'none' skips entirely
if ($modal_mode === 'bootstrap'):
ob_start();
?>
<div class="modal fade" id="voteCardDetailsModal<?php echo $config['id']; ?>" tabindex="-1" aria-labelledby="voteCardDetailsModalLabel<?php echo $config['id']; ?>" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered">
	<div class="modal-content text-start">
		<div class="modal-header">
		<h3 class="modal-title fs-5 fw-bold" id="voteCardDetailsModalLabel<?php echo $config['id']; ?>"><?php echo esc_html($config['title']); ?> Details</h3>
		<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
		</div>
		<div class="modal-body">

		<!-- Vote Content -->
		<?php if (!empty($config['text_more'])): ?>
			<div class="mb-4">
				<div class="entry-content post-content">
					<?php echo wp_kses_post(wpautop($config['text_more'])); ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- Vote Tags -->
		<?php if (!empty($config['tags'])): ?>
			<div>
				<h3>Related Votes</h3>
				<div class="d-flex flex-wrap gap-2">
					<?php 
					global $fi_gov;
					$gov = $fi_gov ?? 'US';
					foreach ($config['tags'] as $tag): 
						$tag_url = fi_tag_url($tag->slug ?? '', strtolower($gov));
					?>
						<a href="<?php echo esc_url($tag_url); ?>" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
							<?php echo esc_html($tag->name ?? ''); ?>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		</div>
		<div class="modal-footer">
		<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
		</div>
	</div>
	</div>
</div>
<?php
$more_modal = ob_get_clean();
else:
	$more_modal = '';
endif;

ob_start();
?>
<div class="col-4 text-center py-2 fi-vote-card-bill-url">
<?php if (!empty($config['bill_url'])): ?>
	<a href="<?php echo esc_url($config['bill_url']); ?>" target="_blank" rel="noopener noreferrer" class="d-block fs-7 ff-h lh-1 fw-bold text-decoration-none">View Bill</a>
	<small class="text-muted fw-bold ff-h">Vote Text</small>
<?php else: ?>
<!-- No bill URL -->
<?php endif; ?>
</div>
<?php 
$col_bill = ob_get_clean();


//SECOND ROW OF FOOTER COLUMNS
ob_start();
?>
<div class="<?php echo $config['footer_class_col']; ?> py-2 border-md-top border-lg-0 fi-vote-card-cost">
	<?php if (!empty($config['cost_html'])): ?>
	<div class="fs-7 ff-h lh-1 fw-bold"><?php echo wp_kses_post($config['cost_html']); ?></div>
	<small class="text-muted fw-bold ff-h">Your Cost</small>
	<?php endif; ?>
</div>
<?php
$col_cost = ob_get_clean();


//Constitutional
ob_start();
?>
<div class="<?php echo $config['footer_class_col']; ?> py-2 border-md-top border-lg-0 fi-vote-card-good">
	<div class="fs-7 ff-h lh-1 fw-bold <?php echo esc_attr($config['vote_format']['vote_class'] ?? ''); ?>">
		<i class="<?php echo esc_attr($config['vote_format']['vote_class_icon'] ?? ''); ?> me-1"></i>
		<?php echo esc_html($config['vote_format']['vote_text'] ?? ''); ?>
	</div>
	<small class="text-muted fw-bold ff-h">Constitutional</small>
</div>
<?php
$col_constitutional = ob_get_clean();


//Chamber
ob_start();
if(isset($config['chamber']) && $config['chamber'] != ''): //Show chamber on Votes list else show cast ?>
<div class="<?php echo $config['footer_class_col']; ?> py-2 border-md-top border-lg-0 fi-vote-card-chamber">
	<div class="fs-7 ff-h lh-1 fw-bold">
		<?php echo esc_html($config['chamber_label'] ?? ''); ?>
	</div>
	<small class="text-muted fw-bold ff-h">Chamber</small>
</div>
<?php else: ?>
	<?php if ($config['show_cast']): ?>
	<div class="<?php echo $config['footer_class_col']; ?> py-0 border-md-top border-lg-0">
		<div class="<?php echo esc_attr($config['vote_format']['cast_bg-class'] ?? ''); ?> py-2 h-100 fi-vote-card-cast">
			<div class="fs-7 ff-h lh-1 fw-bold">
				<i class="<?php echo esc_attr($config['vote_format']['cast_class_icon'] ?? ''); ?> me-1"></i>
				<?php echo esc_html($config['vote_format']['cast_text'] ?? ''); ?>
			</div>
			<small class="text-muted fw-bold ff-h">Vote Cast</small>
		</div>
	</div>
<?php 
	endif;
endif; //Show chamber on Votes list else show cast
$col_chamber_cast = ob_get_clean();

?>
<div id="vcard-<?= $config['id']; ?>" class="col-12 fi-vote-card" data-vote-id="<?php echo esc_attr($vote_id); ?>" data-search-text="<?php echo esc_attr($config['search_text']); ?>">
	<div class="card <?php echo esc_attr($config['card_class']); ?>">
		<!-- Card header -->
		<div class="card-header <?php echo esc_attr($config['header_class']); ?>">
			<h6 class="card-title <?php echo esc_attr($config['header_title_class']); ?> mb-0 d-flex justify-content-between align-items-center">
				<span>
					<?php 
					if($config['show_link_title']){
						echo '<a href="' . esc_url($config['url_vote']) . '" rel="noopener noreferrer" title="Learn why this vote matters">'.esc_html($config['title']).'</a>';
					} else {
						echo esc_html($config['title']);
					}
					?>
				</span>
				<?php if($config['chamber_title']): ?>
					<span class="text-muted fw-normal small ms-2 text-end d-none d-md-block"><?php echo esc_html($config['chamber_label'] ?? ''); ?></span>
				<?php endif; ?>
				<?php if($config['collapse']): ?>
					<span class="ms-2 text-end d-none d-lg-block">
						<button class="btn btn-sm btn-primary collapsed" 
							type="button" 
							id="vote-body-toggle-<?php echo $config['id']; ?>"
							data-bs-toggle="collapse" 
							data-bs-target="#vote-body-collapse-<?php echo $config['id']; ?>" 
							aria-expanded="false" 
							aria-controls="vote-body-collapse-<?php echo $config['id']; ?>">
						<?= esc_html($config['collapse_text'] ?? ''); ?>
					</button>
					</span>
				<?php endif; ?>
			</h6>
		</div>
		
		<!-- Card body -->
		<?php if(!empty($config['text'])): ?>
		<div class="d-md-none">
			<div class="card-body p-0">
				<button class="btn btn-sm btn-link col-12 col-md-6 collapsed" 
				        type="button" 
				        id="vote-toggle-<?php echo $config['id']; ?>"
				        data-bs-toggle="collapse" 
				        data-bs-target="#collapse<?php echo $config['id']; ?>" 
				        aria-expanded="false" 
				        aria-controls="collapse<?php echo $config['id']; ?>">
					Read why this vote matters
				</button>
				<div class="collapse" id="collapse<?php echo $config['id']; ?>" data-vote-id="<?php echo $config['id']; ?>">
					<div class="card-text small p-2"><?php echo wp_kses_post(wpautop($config['text'])); ?></div>
					<div class="row bg-light border-top mx-0">
						<?php echo $col_date . $col_link . $col_bill;?>
					</div>
				</div>
			</div>
		</div>
		<div class="d-none d-md-block">
			<div class="card-body <?php echo esc_attr($config['body_class']); ?>" id="vote-body-collapse-<?php echo $config['id']; ?>" data-vote-id="<?php echo $config['id']; ?>">
				<div class="card-text <?php echo esc_attr($config['body_text_class']); ?>"><?php echo wp_kses_post(wpautop($config['text'])); ?></div>
			</div>
		</div>
		<?php endif; ?>
		
		<!-- Card footer -->
		<div class="card-footer <?php echo esc_attr($config['footer_class']); ?>">
			<div class="row">
				<div class="col-12 col-lg-6 d-none d-lg-block">
					<div class="row">
					<?php echo $col_date . $col_link . $col_bill;?>
					</div>
				</div>
				<div class="col-12 col-lg-6">
					<div class="row">
					<?php echo $col_cost . $col_constitutional . $col_chamber_cast;?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php echo $more_modal; ?>

<?php if(!empty($config['text'])): ?>
<script>
(function() {
	var collapseId = 'collapse<?php echo $config['id']; ?>';
	var buttonId = 'vote-toggle-<?php echo $config['id']; ?>';
	var collapseEl = document.getElementById(collapseId);
	var buttonEl = document.getElementById(buttonId);
	
	if (collapseEl && buttonEl) {
		collapseEl.addEventListener('show.bs.collapse', function() {
			buttonEl.textContent = 'Close Details';
			buttonEl.classList.remove('collapsed');
		});
		
		collapseEl.addEventListener('hide.bs.collapse', function() {
			buttonEl.textContent = 'Read why this vote matters';
			buttonEl.classList.add('collapsed');
		});
	}
})();
</script>
<?php endif; ?>