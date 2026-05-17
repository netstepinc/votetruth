<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Fetch live debt data directly from Treasury API. :: Source: U.S. Treasury Data
https://api.fiscaldata.treasury.gov/services/api/fiscal_service/v2/accounting/od/debt_to_penny?fields=record_date,tot_pub_debt_out_amt&sort=-record_date&format=json&page[number]=1&page[size]=2
{"data":[{"record_date":"2026-05-12",
"tot_pub_debt_out_amt":"38968295059805.35"},
{"record_date":"2026-05-11","tot_pub_debt_out_amt":"38946800561409.14"}],
"meta":{"count":2,"labels":{"record_date":"Record Date","tot_pub_debt_out_amt":"Total Public Debt Outstanding"},
"dataTypes":{"record_date":"DATE","tot_pub_debt_out_amt":"CURRENCY"},
"dataFormats":{"record_date":"YYYY-MM-DD","tot_pub_debt_out_amt":"$10.20"},
"total-count":8306,"total-pages":4153},
"links":{"self":"&page%5Bnumber%5D=1&page%5Bsize%5D=2","first":"&page%5Bnumber%5D=1&page%5Bsize%5D=2","prev":null,"next":"&page%5Bnumber%5D=2&page%5Bsize%5D=2","last":"&page%5Bnumber%5D=4153&page%5Bsize%5D=2"}}

Returns: $data = ['amount' => $debt_amount,'date' => $debt_date,];
*/

$debt_data = fs_api_treasurygov_debt_now(); 
//Format into text like: $38.97 Trillion
$debt_national = '$'.number_format(round( ($debt_data['amount'] / 1000000000000),2),2).' Trillion';


// Household count is a stable hardcoded value (~134.79M as of 2025).
$households = fs_api_census_households() ?: 134790000;
$debt_household = '$'.number_format(round(($debt_data['amount'] / $households),2),2);



//INLINE STYLE FOR DEVELOPMENT: Consolidate when perfected.
?>
<style>
.dc-primary {
	font-size: clamp(2rem, 8vw, 3rem);
	line-height: 1.1;
	font-variant-numeric: tabular-nums;
	letter-spacing: -0.02em;
}
.dc-secondary {
	font-size: clamp(1rem, 4vw, 1.5rem);
	line-height: 1.2;
	font-variant-numeric: tabular-nums;
	letter-spacing: -0.01em;
}
</style>
<div class="card rounded-4 shadow bg-white gsap-duration-1 mb-4 gsap-slide-right">
	<div class="card-body p-3 p-sm-4">

    <!-- National Debt Total — SECONDARY (smaller) -->
		<div class="text-center mb-3">
			<div class="text-muted mb-1">U.S. National Debt</div>
			<div id="dc-national" class="dc-secondary fw-bold text-dark"><?php echo $debt_national; ?></div>
		</div>

		<!-- Debt Per Household — PRIMARY (large) -->
		<div class="text-center mb-3">
			<div class="text-muted fw-bold mb-1">Your Household's Share</div>
			<div id="dc-per-hh" class="dc-primary text-danger" style="font-weight:900;"><?php echo $debt_household; ?></div>
		</div>

		<p class="text-center mb-0">
			<small class="text-muted" id="dc-source">Source: U.S. Treasury Data</small>
		</p>

	</div>
</div>