<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
 * Compact Legislator Card Partial Template
 * Displays a compact legislator card for search results and find-my-legislator
 * 
 * @var object|array $args Array with legislator data
*/

$column_class = ( isset($args['class_col']) && $args['class_col'] != '' ) ? $args['class_col'] : 'col-12 col-md-6 col-lg-4 p-3';
?>
<div class="<?php echo esc_attr($column_class); ?>">
	<?php 
	if(isset($args['legislator']['url']) && $args['legislator']['url'] != ''){
		echo '<a href="' . esc_url($args['legislator']['url']) . '" class="text-decoration-none" title="View ' . esc_attr($args['name']) . ' Freedom Index">';
		$close_link = '</a>';
	}else{
		$close_link = '';
	}
	?>
	<div class="fi-legislator-sm card p-3 ps-4 rounded-3 shadow h-100">
		<div class="row">
			<div class="col-2 p-0">
				<?php 
				if (!empty($args['image_id'])){
					echo fi_legislator_image($args['image_id'], '',['size' => [160, 200], 'crop' => true, 'alt' => esc_attr($args['name']), 'class' => 'img-fluid rounded']);
				}elseif (!empty($args['photo_url'])){
					echo '<img src="' . esc_url($args['photo_url']) . '" alt="' . esc_attr($args['name']) . '" class="img-fluid rounded">';
				}else{
					echo '<div class="bg-light rounded d-flex align-items-center justify-content-center h-100" style="min-height: 60px;"><small class="text-muted text-center">No<br>Photo</small></div>';
				} ?>

		<?php if (!empty($args['list_id']) && !empty($args['legislator']['id'])): ?>
		<button type="button" 
			class="btn btn-sm btn-outline-danger p-1 bg-white" style="font-size:.75rem; margin-top:-54px;" 
			onclick="if(confirm('Remove <?php echo esc_js($args['name']); ?> from this list?')) { FI.removeLegislatorFromList(<?php echo (int) $args['list_id']; ?>, <?php echo (int) $args['legislator']['id']; ?>); }"
			title="Remove from list">
			<i class="bi bi-x-lg"></i>
		</button>
		<?php endif; ?>

			</div>
			<div class="col-10 px-2">
				<h4 class="card-title fs-6 mb-0 lh-1"><?php echo esc_html($args['name']); ?></h4>
				<?php if (!empty($args['gov_name'])): ?>
					<p class="mb-0 small"><?php echo esc_html($args['gov_name']); ?></p>
				<?php endif; ?>
				<?php if (!empty($args['chamber']) || !empty($args['party'])): ?>
					<p class="mb-0 text-muted small"><?php echo (!empty($args['party']) ? esc_html($args['party']) . ' ' : '') . (!empty($args['chamber']) ? esc_html($args['chamber']) : ''); ?></p>
				<?php endif; ?>
				<?php
				$score_label = '';
				if (isset($args['score_label']) && $args['score_label'] !== null) {
					$score_label = $args['score_label'];
				} else {
					// fallback: label based on context or defaults
					$score_label = 'Freedom Score';
				}
				?>
				<?php if (isset($args['score']) && $args['score'] !== null): ?>
					<?php
					echo "\n<!--".$args['legislator']['id']."-->";
					echo fi_score_bar($args['score'], $score_label, 24);
					?>
				<?php endif; ?>
			</div>
		</div>
	</div>
<?php echo $close_link; ?>
</div>
<?php //if(get_current_user_id() == 1){ echo "\n<!--"; print_r($args); echo "-->\n"; } ?>

<?php
/*
<!--Array
(
    [name] => John Cornyn
    [party] => Republican
    [chamber] => Senate
    [photo_url] => https://freedomindex.us/assets/sites/5/img/3084/995-C001056-80x100.jpg
    [score] => 63
    [score_label] => Freedom Score
    [legislator] => Array
        (
            [id] => 995
            [url] => https://freedomindex.us/legislator/995/
        )

)
-->


<!--Array
(
    [name] => John Cornyn
    [party] => R
    [party_name] => Republican
    [chamber] => Senator - Congressional District 5
    [division] => Congressional District 5
    [contact] => Array
        (
            [url] => https://www.cornyn.senate.gov
            [address] => 517 Hart Senate Office Building Washington DC 20510
            [phone] => 202-224-2934
            [contact_form] => https://www.cornyn.senate.gov/contact
        )

    [social] => Array
        (
            [rss_url] => http://www.cornyn.senate.gov/public/?a=rss.feed
            [twitter] => JohnCornyn
            [facebook] => sen.johncornyn
            [youtube] => senjohncornyn
            [youtube_id] => UCyQwLQavlOaJ64YuY_VMtiQ
        )

    [bio] => Array
        (
            [last_name] => Cornyn
            [first_name] => John
            [birthday] => 1952-02-02
            [gender] => M
            [party] => Republican
            [photo_url] => https://www.congress.gov/img/member/c001056_200.jpg
            [photo_attribution] => Courtesy U.S. Senate Historical Office (http://www.senate.gov/artandhistory/history/common/generic/Photo_Collection_of_the_Senate_Historical_Office.htm)
        )

    [photo_url] => https://www.congress.gov/img/member/c001056_200.jpg
    [birthday] => 1952-02-02
    [gender] => M
    [seniority] => senior
    [references] => Array
        (
            [bioguide_id] => C001056
            [thomas_id] => 01692
            [opensecrets_id] => N00024852
            [lis_id] => S287
            [cspan_id] => 93131
            [govtrack_id] => 300027
            [votesmart_id] => 15375
            [ballotpedia_id] => John Cornyn
            [washington_post_id] => 
            [icpsr_id] => 40305
            [wikipedia_id] => John Cornyn
        )

    [id] => 995
    [url] => https://freedomindex.us/legislator/995/
    [first_name] => John
    [last_name] => Cornyn
    [freedom_score] => 63
    [image_id] => 3084
    [gov] => US
    [state] => TX
    [state_name] => Texas
    [score] => 63
    [score_label] => Freedom Score
)
-->

*/