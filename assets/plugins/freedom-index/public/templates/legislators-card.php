<?php
/**
 * Legislator Card — unified compact format for search results and legislator lists.
 * Styles in 99.freedomindex.css (.lc-*, .fi-grade). Grade badge via fi_score_badge().
 *
 * @var array  $legislator Legislator data array
 * @var string $gov        Government code
 */
if (!defined('ABSPATH')) exit;

$name = $legislator['display_name'] ?? trim(($legislator['first_name'] ?? '') . ' ' . ($legislator['last_name'] ?? ''));

$image_html = '';
$image_id = ($legislator['session_image_id'] ?? null) ?: ($legislator['image_id'] ?? null);
if ($image_id) {
	$image_html = fi_legislator_image(
		(int) $image_id,
		null,
		[
			'size'  => [80, 100],
			'crop'  => true,
			'alt'   => $name,
			'class' => '',
		]
	);
} elseif (!empty($legislator['image_url'])) {
	$image_html = '<img src="' . esc_url($legislator['image_url']) . '" width="80" height="100" class="" alt="' . esc_attr($name) . '">';
}

$score = $legislator['score'] ?? null;

$official_gov = $legislator['gov'] ?? $gov ?? 'US';
$is_federal   = ($official_gov === 'US');

$chamber_raw = strtolower($legislator['chamber'] ?? '');
$state       = $legislator['state'] ?? '';
$state_name  = $legislator['state_name'] ?? ($state ? fi_state_name($state) : '');

if (strpos($chamber_raw, 'senator') !== false || strpos($chamber_raw, 'senate') !== false) {
    $office = $is_federal ? ($state ? "U.S. Senator · {$state}" : 'U.S. Senator') : ($state_name ? "{$state_name} State Senator" : 'State Senator');
} elseif (strpos($chamber_raw, 'assembly') !== false) {
    $office = $state_name ? "{$state_name} Assemblymember" : 'Assemblymember';
} elseif (strpos($chamber_raw, 'representative') !== false || strpos($chamber_raw, 'house') !== false) {
    $office = $is_federal
        ? ($state ? "U.S. Representative · {$state}" : 'U.S. Representative')
        : ($state_name ? "{$state_name} State Representative" : 'State Representative');
} else {
    $office = $legislator['chamber_label'] ?? ($legislator['chamber'] ?? '');
}

$party_raw  = $legislator['party'] ?? '';
$party_full = $legislator['party_name'] ?? (strlen($party_raw) <= 2 ? fi_party_name($party_raw) : $party_raw);

$is_senator     = strpos($chamber_raw, 'senator') !== false || strpos($chamber_raw, 'senate') !== false;
$district_label = $is_senator ? '' : ($legislator['district_name'] ?? $legislator['district'] ?? '');

$url = fi_legislator_get_url($legislator['id'] ?? 0);
?>
<a href="<?= esc_url($url); ?>" class="lc">
    <div class="lc-photo"><?= $image_html; ?></div>
    <div class="p-2 flex-grow-1" style="min-width:0;">
        <div class="lc-name mb-1 text-truncate"><?= esc_html($name); ?></div>
        <div class="row g-0">
            <div class="col-9">
                <?php if ($office): ?><div class="lc-office text-truncate"><?= esc_html($office); ?></div><?php endif; ?>
                <?php if ($party_full): ?><div class="lc-district text-truncate"><?= esc_html($party_full); ?></div><?php endif; ?>
                <div class="lc-district text-truncate"><?= $district_label ? esc_html($district_label) : '&nbsp;'; ?></div>
            </div>
            <div class="col-3 lc-badge ps-1">
                <?= fi_score_badge($score); ?>
            </div>
        </div>
    </div>
</a>
