<?php if (!defined('ABSPATH')) exit;

/**
 * Report legislators table. Expects $args: legislator_data, votes, gov, parties.
 * Data built by fi_report_legislators_build_data() in report.php; filter JS targets .scorecard-row.
 */
$legislator_data = $args['legislator_data'] ?? [];
$votes = $args['votes'] ?? [];
$gov = $args['gov'] ?? 'US';
$parties = $args['parties'] ?? [];

if (empty($legislator_data)) {
    echo '<p class="text-muted">No legislators found for this session and chamber.</p>';
    return;
}

// Average score by party (footer table)
$party_count = [];
$party_sum = [];
foreach ($legislator_data as $data) {
    $leg = $data['legislator'];
    $score = $data['report_score'];
    $party_name = isset($leg->party) ? ($parties[$leg->party] ?? fi_party_name(strtolower((string) $leg->party))) : '';
    if ($party_name !== '') {
        $party_count[$party_name] = ($party_count[$party_name] ?? 0) + 1;
        $party_sum[$party_name] = ($party_sum[$party_name] ?? 0) + ($score !== null ? (int) $score : 0);
    }
}

$gov_slug = strtolower($gov);
?>
<div class="row">
    <div class="col-12 pb-1 text-end">
        <span class="text-body-secondary">Legend:</span>
        <span class="text-success ms-3">[ + ] Constitutional vote</span>
        <span class="text-danger ms-3">[ − ] Unconstitutional vote</span>
        <span class="text-muted ms-3">[ · ] Did not vote</span>
    </div>
</div>
<div class="table-responsive">
    <table class="table table-bordered table-sm table-hover">
        <thead class="table-dark">
            <tr>
                <th scope="col" style="min-width: 10rem;">Name</th>
                <th scope="col" class="text-center" style="width: 4rem;">Party</th>
                <th scope="col" class="text-center" style="width: 4rem;">State</th>
                <th scope="col" class="text-center" style="width: 4rem;">Score</th>
                <?php
				$v=0;
				foreach ($votes as $vote){
					$v++;
					$vote_number = $v;
					if(isset($vote_meta['vote_start'])){
						$vote_number = (int) $vote_meta['vote_start'] + $v;
					}
                    echo '<th scope="col" class="text-center">'.$vote_number.'</th>';
                } ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($legislator_data as $data):
                $leg = $data['legislator'];
                $name = $leg->display_name ?? (trim(($leg->first_name ?? '') . ' ' . ($leg->last_name ?? '')) ?: '—');
                $leg_url = fi_get_legislator_url($leg->id ?? 0, $gov);
                $party_slug = isset($leg->party) ? strtolower((string) $leg->party) : '';
                $party_label = $party_slug ? strtoupper($party_slug) : '—';
                $party_css = $party_slug ? 'bg-party-' . $party_slug . ' fw-bold' : '';
                $state_abbr = isset($leg->state) ? strtoupper((string) $leg->state) : '—';
                $score_val = $data['report_score'];
                $score_text = $score_val !== null ? (int) $score_val . '%' : 'N/A';
            ?>
                <tr class="scorecard-row"
                    data-name="<?php echo esc_attr(strtolower($name)); ?>"
                    data-state="<?php echo esc_attr($state_abbr); ?>"
                    data-party="<?php echo esc_attr($party_slug); ?>">
                    <th scope="row" class="fw-normal text-nowrap">
                        <a href="<?php echo esc_url($leg_url); ?>" class="text-decoration-none"><i class="bi bi-link-45deg"></i> <?php echo esc_html($name); ?></a>
                    </th>
                    <td class="text-center <?php echo esc_attr($party_css); ?>"><?php echo esc_html($party_label); ?></td>
                    <td class="text-center fw-bold"><?php echo esc_html($state_abbr); ?></td>
                    <th scope="row" class="text-center"><?php echo esc_html($score_text); ?></th>
                    <?php foreach ($data['votes'] as $v):
                        $vf = $v['vote_format'] ?? [];
                        $cell_class = 'text-center fi-vote ' . esc_attr($vf['table_class'] ?? 'text-muted');
                        $symbol = $vf['table_symbol'] ?? '·';
                    ?>
                        <td class="<?php echo $cell_class; ?>"><?php echo $symbol; ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="row mt-4">
    <div class="col-12">
        <h2 class="h5">Average Freedom Score by Party</h2>
    </div>
</div>
<div class="table-responsive col-12 col-md-6 ps-0">
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th scope="col">Party</th>
                <th scope="col">Score</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (!empty($party_count)) {
                ksort($party_count);
                foreach ($party_count as $pname => $count):
                    $avg = $count > 0 ? round(($party_sum[$pname] ?? 0) / $count, 1) : 0;
            ?>
                <tr>
                    <td><?php echo esc_html($pname); ?></td>
                    <td><?php echo esc_html($avg); ?>%</td>
                </tr>
            <?php
                endforeach;
            } else {
                echo '<tr><td colspan="2" class="text-muted">—</td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>
