<?php if(!defined('ABSPATH')){ exit; }

// Household count is a stable hardcoded value (~134.79M as of 2025).
// JS fetches live debt data directly from Treasury API.
$households = fs_api_census_households() ?: 134790000;
?>
<div class="card rounded-4 shadow bg-white gsap-duration-1 mb-4 gsap-slide-right">
	<div class="card-body p-3 p-sm-4">

    <!-- National Debt Total — SECONDARY (smaller) -->
		<div class="text-center mb-3">
			<div class="text-muted mb-1">U.S. National Debt</div>
			<div id="dc-national" class="dc-secondary fw-bold text-dark">—</div>
		</div>

		<!-- Debt Per Household — PRIMARY (large) -->
		<div class="text-center mb-3">
			<div class="text-muted fw-bold mb-1">Your Household's Share</div>
			<div id="dc-per-hh" class="dc-primary text-danger" style="font-weight:900;">—</div>
		</div>

		<p class="text-center mb-0">
			<small class="text-muted" id="dc-source"></small>
		</p>

	</div>
</div>

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

<script>
(function () {
	var HOUSEHOLDS    = <?php echo $households; ?>;
	// Fallback per-second rate (~$2T/year deficit) used when two consecutive
	// API records show a decrease (intragovernmental fluctuation).
	var FALLBACK_PER_SEC = 63419;

	var elPH  = document.getElementById('dc-per-hh');
	var elND  = document.getElementById('dc-national');
	var elSrc = document.getElementById('dc-source');

	function fmt(n) {
		return '$' + Math.round(n).toLocaleString('en-US');
	}

	function fmt2(n) {
		var cents = Math.round(n * 100) / 100;
		return '$' + cents.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function fmtDate(s) {
		// Noon avoids DST off-by-one issues
		return new Date(s + 'T12:00:00').toLocaleDateString('en-US', {
			month: 'long', day: 'numeric', year: 'numeric'
		});
	}

	var API = 'https://api.fiscaldata.treasury.gov/services/api/fiscal_service/v2/accounting/od/debt_to_penny'
	        + '?fields=record_date,tot_pub_debt_out_amt&sort=-record_date&format=json&page[number]=1&page[size]=2';

	fetch(API)
		.then(function (r) { return r.json(); })
		.then(function (json) {
			var data = json.data;
			if (!data || data.length < 2) return;

			var latest    = parseFloat(data[0].tot_pub_debt_out_amt);
			var prev      = parseFloat(data[1].tot_pub_debt_out_amt);
			var latestDate = data[0].record_date;
			var prevDate   = data[1].record_date;

			// Normalize for weekends/holidays between records
			var days = Math.max(1,
				(new Date(latestDate + 'T12:00:00') - new Date(prevDate + 'T12:00:00')) / 86400000
			);

			var dailyChange = (latest - prev) / days;
			// Use fallback if records show a decrease (normal short-term fluctuation)
			var perSec     = dailyChange > 0 ? dailyChange / 86400 : FALLBACK_PER_SEC;
			var perHHperSec = perSec / HOUSEHOLDS;

			// Seed current value: latest official figure + elapsed time since that midnight
			var elapsed = Math.max(0, (Date.now() - new Date(latestDate + 'T00:00:00').getTime()) / 1000);
			var curDebt = latest + perSec * elapsed;
			var curPH   = curDebt / HOUSEHOLDS;

			if (elSrc) elSrc.textContent = 'Source: U.S. Treasury Data'; /* as of ' + fmtDate(latestDate);*/

			var lastTime = Date.now();
			function tick() {
				var now = Date.now();
				var dt  = (now - lastTime) / 1000;
				lastTime = now;

				curDebt += perSec * dt;
				curPH   += perHHperSec * dt;

				if (elPH) elPH.textContent = fmt2(curPH);
				if (elND) elND.textContent = fmt(curDebt);

				requestAnimationFrame(tick);
			}
			requestAnimationFrame(tick);
		})
		.catch(function () {
			if (elPH) elPH.textContent = 'Unavailable';
			if (elND) elND.textContent = 'Unavailable';
		});
})();
</script>
