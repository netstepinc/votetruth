<?php
/**
* Template Name: FI Compare Table
* Template Post Type: page
* FI Compare Table Full Width without Wrapper
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ─── DATA ────────────────────────────────────────────────────────────────────

// Column 0 = Metric label (always fixed)
// Column 1 = Our scorecard (always fixed, highlighted)
// Columns 2–10 = Comparison scorecards (carousel, 2 per page desktop / 1 mobile)

$sc_our = [
    'name'     => 'Freedom Index', //The New American<br>
    'sub'      => 'State Legislative Scorecards',
    'logo_url' => STYLE_IMG.'compare/freedomindex.png', // ← paste our logo URL here
];

$sc_compare = [
    ['name' => 'Liberty Score',			'sub' => 'Conservative Review',  'logo_url' => STYLE_IMG.'compare/cr.png', 'url' => 'https://libertyscore.conservativereview.com/'],
    ['name' => 'Heritage Action', 		'sub' => 'Scorecard',            'logo_url' => STYLE_IMG.'compare/heritage-action.png', 'url' => 'https://heritageaction.com/scorecard'],
    ['name' => 'Club for Growth', 		'sub' => 'Scorecard',            'logo_url' => STYLE_IMG.'compare/club-for-growth.png','url' => 'https://www.clubforgrowth.org/scorecards/'],
    ['name' => 'Turning Point Action',	'sub' => 'Scorecard',            'logo_url' => STYLE_IMG.'compare/tpa.png','url' => 'https://www.tpaction.com/scorecard'],
    ['name' => 'CPAC Ratings',                                           'logo_url' => STYLE_IMG.'compare/cpac.jpg','url' => 'http://ratings.conservative.org/'],
    ['name' => 'Gun Owners of America',	'sub' => 'Scorecard',            'logo_url' => STYLE_IMG.'compare/goa.png','url' => 'https://www.gunowners.org/scorecard/'],
    ['name' => 'Limited Government Index',                               'logo_url' => STYLE_IMG.'compare/legislative-analysis.png','url' => 'https://analysis.limitedgov.org/'],
    ['name' => 'Susan B. Anthony',		'sub' => 'Pro-Life America',     'logo_url' => STYLE_IMG.'compare/susan-b-anthony.png','url' => 'https://sbaprolife.org/scorecard'],
];

// Each row: [ 'label', our_value, comp0=Liberty, comp1=Heritage, comp2=Club, comp3=TPUSA, comp4=CPAC, comp5=GOA, comp6=LimitedGov, comp7=SBA ]
$sc_rows = [

    [ 'Core standard',
        'U.S. Constitution / pro-freedom standard',
        'Conservative/liberty alignment',
        'Conservative policy alignment',
        'Economic-growth / free-market alignment',
        'Conservative/populist issue alignment — not Constitutional',
        'Conservative / Republican platform rating standard',
        'Second Amendment / gun-rights alignment',
        'Limited government principles / U.S. Constitution',
        'Pro-life/anti-abortion policy alignment',
    ],

    [ 'Primary purpose',
        'Measure whether lawmakers vote constitutionally and explain why',
        'Grade lawmakers on top liberty votes over a rolling window',
        'Track support for Heritage-backed priorities',
        'Track support for pro-growth policies',
        'Score lawmakers across key issue categories / TPUSA priorities',
        'Rate lawmakers on conservative (Republican) voting records',
        'Rate lawmakers based on gun-rights record',
        'Rate lawmakers based on the proper role, scope, and duty of government',
        'Track where members of Congress stand on key pro-life votes and activities',
    ],

    [ 'Congress covered',
        'Yes', 'Yes', 'Yes', 'Yes', 'Yes', 'Yes', 'Yes', 'Yes', 'Yes',
    ],

    [ 'All 50 state legislatures covered',
        'Yes',
        'No',
        'No',
        'No',
        'Yes',
        'Yes',
        'Limited / not primary public frame',
        'Yes (but incomplete)',
        'No',
    ],

    [ 'Federal + state in one system',
        'Yes',
        'No',
        'No',
        'No',
        'Yes',
        'Yes',
        'Not clearly presented as one unified system',
        '',
        'No',
    ],

    [ 'Session / current scoring',
        'Yes',
        'No — rolling window emphasized',
        'Yes',
        'Yes',
        'Yes',
        'Yes',
        'Yes',
        '',
        'Yes',
    ],

    [ 'Lifetime / long-term scoring',
        'Yes',
        'Yes — rolling six-year score',
        'Not emphasized',
        'Yes',
        'No — former legislator data is non-existent',
        'Historical archives available',
        'Candidate/incumbent grading exists, but lifetime framing not primary',
        '',
        'Not emphasized',
    ],

    [ 'Per-vote explanations',
        'Strong',
        'Limited',
        'Limited public explanation via key-vote pages',
        'Moderate',
        'Limited public detail visible',
        'Limited public detail visible',
        'Some vote and grade context',
        '',
        'Moderate — tracked votes and activity pages provide issue context',
    ],

    [ 'Explains why a vote is constitutional / unconstitutional',
        'Yes', 'No', 'No', 'No', 'No', 'No', 'No', '', 'No',
    ],

    [ 'Taxpayer-cost / fiscal-impact framing',
        'Yes',
        'Not core feature',
        'Not core feature',
        'Yes — often integral to pro-growth framing',
        'Not core feature',
        'Not core feature',
        'No',
        '',
        'Sometimes, especially on taxpayer-funded abortion questions, but not a core universal metric',
    ],

    [ 'Broad standard (not single-issue silo)',
        'Yes',
        'Broad conservative/liberty',
        'Broad but organization-defined',
        'Mostly economic/fiscal',
        'Broad but issue-bucket based',
        'Broad conservative',
        'No — single issue',
        '',
        'No — single issue',
    ],

    [ 'Same vote set for all lawmakers in a chamber',
        'Yes',
        'Uses top 50 votes in rolling window',
        'Generally yes',
        'Yes',
        'Not fully clear from public scoring explainer',
        'Yes in ratings framework',
        'Not always — endorsements/sponsorships and other factors can affect grades',
        '',
        'Largely yes for chamber scorecards, though activities as well as votes are included',
    ],

    [ 'Find legislator by address / ZIP',
        'Yes',
        'No',
        'No',
        'No',
        'Yes',
        'No',
        'No',
        '',
        'No',
    ],

    [ 'Printable / shareable personalized scorecards',
        'Yes', 'No', 'No', 'No', 'No', 'No', 'No', '', 'No',
    ],

    [ 'Mobile / easy public lookup tool',
        'Yes', 'Yes', 'Yes', 'Yes', 'Yes', 'Yes', 'Yes', '', 'Yes',
    ],

    [ 'Citizen-action / activist utility',
        'Very high',
        'Moderate',
        'High',
        'Moderate',
        'High',
        'Moderate',
        'High for gun-rights activists',
        '',
        'High for pro-life activists',
    ],

    [ 'Educational value beyond the score',
        'Very high',
        'Moderate',
        'Moderate',
        'Moderate',
        'Moderate',
        'Moderate',
        'Moderate within gun-rights lane',
        '',
        'Moderate within pro-life lane',
    ],

    [ 'Best positioning',
        'Constitutional accountability platform',
        'Conservative/liberty vote scorecard',
        'Conservative advocacy scorecard',
        'Economic-liberty advocacy scorecard',
        'Conservative and organization activist scorecard',
        'Conservative coalition scorecard',
        'Gun-rights advocacy scorecard',
        '',
        'Pro-life advocacy scorecard',
    ],

    [ 'Ease of printing / print format',
        'Strong and easy — built for printable, shareable, personalized scorecards and handouts',
        'Limited / not emphasized',
        'Limited / not emphasized',
        'Limited / not emphasized',
        'Limited / not emphasized',
        'Limited / not emphasized',
        'Limited / not emphasized',
        '',
        'Limited / not emphasized',
    ],

]; // end $sc_rows

// ─── HELPERS ─────────────────────────────────────────────────────────────────

// Freedom Index vote graphics (already in the plugin)
$_fs_yes = STYLE_IMG.'compare/fs_vote-good.png';
$_fs_no  = STYLE_IMG.'compare/fs_vote-bad.png';

// Closure avoids fatal "Cannot redeclare" if block renders more than once per request.
$sc_cell = function( $val, $is_our = false ) use ( $_fs_yes, $_fs_no ) {

    if ( $val === '' ) return '<span class="text-muted">—</span>';

    $lower = strtolower( trim( $val ) );

    $is_yes  = ( $lower === 'yes' );
    $is_no   = ( $lower === 'no' );
    $is_high = in_array( $lower, [ 'very high', 'high', 'strong', 'strong and easy — built for printable, shareable, personalized scorecards and handouts' ] );

    if ( $is_yes ) {
        return '<span class="sc-yes"><img src="' . esc_url( $_fs_yes ) . '" alt="Yes" class="sc-vote-icon"> Yes</span>';
    }
    if ( $is_no ) {
        return '<span class="sc-no"><img src="' . esc_url( $_fs_no ) . '" alt="No" class="sc-vote-icon"> No</span>';
    }
    if ( $is_high && $is_our ) {
        return '<span class="sc-highlight fw-semibold">' . esc_html( $val ) . '</span>';
    }

    return esc_html( $val );
};

$uid = 'sc-compare-' . substr( md5( uniqid() ), 0, 8 );

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="profile" href="http://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>
<body>
<style>
/* ── Comparison Table ──────────────────────────────────────── */
#<?php echo $uid; ?> {
    font-size: .75rem;
}
/* Table wrapper */
.sc-compare-wrap {
    overflow-x: auto;
}
table.sc-compare-tbl {
    table-layout: fixed;
    border-collapse: collapse;
    width: 100%;
    min-width: 0;
}
table.sc-compare-tbl th,
table.sc-compare-tbl td {
    border: 1px solid var(--bs-border-color, #dee2e6);
    padding: .45rem .6rem;
    vertical-align: top;
}
/* Metric column (col 0) */
table.sc-compare-tbl .sc-col-label {
    width: 28%;
    min-width: 140px;
    font-weight: 600;
    background: #f8f9fa;
}
/* Our scorecard column (col 1) */
table.sc-compare-tbl .sc-col-our {
    width: 26%;
    min-width: 140px;
    background: #eaf4ff;
}
table.sc-compare-tbl thead .sc-col-our {
    background: #fff;
    color: #000;
    text-align: center;
}
table.sc-compare-tbl thead .sc-col-label {
    background: #fff;
    color: #000;
}
/* Comparison columns */
table.sc-compare-tbl .sc-col-comp {
    width: 23%;
    min-width: 120px;
}
table.sc-compare-tbl thead .sc-col-comp {
    background: #fff;
    color: #000;
    text-align: center;
    font-size: .75rem;
    font-weight: 600;
}
/* Row striping */
table.sc-compare-tbl tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}
table.sc-compare-tbl tbody tr:nth-child(even) .sc-col-our {
    background-color: #daeeff;
}

/* Yes / No vote icons */
.sc-vote-icon { width: 20px; height: 20px; vertical-align: middle; }
.sc-yes  { color: #198754; white-space: nowrap; }
.sc-no   { color: #dc3545; white-space: nowrap; }
/*.sc-highlight { color: #1a6bbf; }*/

/* Logo in column headers */
.sc-th-logo { height: 40px; }

/* Column sub-labels in header */
.sc-th-sub {
    font-weight: 400;
    font-size: .78rem;
    opacity: .85;
    display: block;
}
</style>

<div id="<?php echo $uid; ?>">

    <!-- Table -->
    <div class="sc-compare-wrap">
    <table class="sc-compare-tbl">
        <thead>
            <tr>
                <th class="bg-white">&nbsp;</th>
                <th class="bg-white align-top">
                    <?php if ( ! empty( $sc_our['logo_url'] ) ) : ?>
                        <img src="<?php echo esc_url( $sc_our['logo_url'] ); ?>" alt="<?php echo esc_attr( $sc_our['name'] ); ?>" class="img-fluid">
                    <?php endif; ?>
                </th>
                <?php foreach ( $sc_compare as $col ) : ?>
                <th class="bg-white align-bottom sc-th-logo">
                    <?php if ( ! empty( $col['logo_url'] ) ) : ?>
                        <img src="<?php echo esc_url( $col['logo_url'] ); ?>" alt="<?php echo esc_attr( $col['name'] ); ?>" class="img-fluid">
                    <?php endif; ?>
                </th>
                <?php endforeach; ?>
            </tr>

			<tr>
                <th class="sc-col-label">Metric</th>
                <th class="sc-col-our">
                    <?php echo $sc_our['name']; //echo esc_html( $sc_our['name'] ); ?>
                    <?php if ( ! empty( $sc_our['sub'] ) ) : ?>
                        <span class="sc-th-sub"><?php echo esc_html( $sc_our['sub'] ); ?></span>
                    <?php endif; ?>
                </th>
                <?php foreach ( $sc_compare as $col ) : ?>
                <th class="sc-col-comp">
                    <?php if ( ! empty( $col['url'] ) ) : ?>
                        <a href="<?php echo esc_url( $col['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html( $col['name'] ); ?>
                        </a>
                    <?php else : ?>
                        <?php echo esc_html( $col['name'] ); ?>
                    <?php endif; ?>
                    <?php if ( ! empty( $col['sub'] ) ) : ?>
                        <span class="sc-th-sub"><?php echo esc_html( $col['sub'] ); ?></span>
                    <?php endif; ?>
                </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $sc_rows as $row ) :
                $label = array_shift( $row ); // first element is the row label
                $our   = array_shift( $row ); // second element is our value
                // $row now contains the 9 comparison values
            ?>
            <tr>
                <td class="sc-col-label"><?php echo esc_html( $label ); ?></td>
                <td class="sc-col-our"><?php echo $sc_cell( $our, true ); ?></td>
                <?php foreach ( $row as $cval ) : ?>
                <td class="sc-col-comp"><?php echo $sc_cell( $cval ); ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div><!-- .sc-compare-wrap -->

</div><!-- #<?php echo $uid; ?> -->
</body>
</html>