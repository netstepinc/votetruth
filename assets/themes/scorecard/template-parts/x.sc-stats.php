<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
Home page widget display showing our system totals from the database.
- Legislators tracked
- Votes scored
- Rollcalls counted
*/ 
global $wpdb;
//Get total publish votes from jbsw_5_fs_votes table
$votes_scored = $wpdb->get_var("SELECT COUNT(*) FROM jbsw_5_fs_votes WHERE status = 'publish'");

//Get total published rollcalls from jbsw_5_fs_voterc table
$rollcalls_counted = $wpdb->get_var("SELECT COUNT(*) FROM jbsw_5_fs_voterc");

//Get total published votes from jbsw_5_fs_legislators table
$legislators_tracked = $wpdb->get_var("SELECT COUNT(*) FROM jbsw_5_fs_legislators");
?>
<style>
.counter{
	font-family: var(--bs-font-headings);
	color: #0055a4;
	font-weight: 700;
	font-size: 3rem;
	line-height: 1;
	text-align: center;
}
.counter-label{
	font-weight: 600;
	font-size: 1rem;
	line-height: 1.4;
	color: #333;
	text-align: center;
}
@media (max-width: 1400px) {
	.counter{font-size: 2rem;}
}
@media (max-width: 1200px) {
	.counter{font-size: 1.75rem;}
}
@media (max-width: 992px) {
	.counter{font-size: 1.5rem;}
}
@media (max-width: 768px) {
	.counter{font-size: 2rem;}
}
@media (max-width: 576px) {
	.counter{font-size: 2.5rem;}
}
</style>
<div class="row">

	<!-- Home search: unique IDs to avoid duplicate mobile selectors -->
	<div id="homeSearch" class="col-12 d-lg-none pb-4">
		<form id="home-legislator-search-form" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search" novalidate>
			<div class="input-group shadow position-relative">
				<input id="home-legislator-search-input" class="form-control rounded-start border-success" name="fs_search" type="search" placeholder="Find a Legislator&hellip;" value="<?php echo esc_attr( isset( $_GET['fs_search'] ) ? $_GET['fs_search'] : '' ); ?>" aria-label="Search" autocomplete="off" minlength="3">
				<div id="home-search-suggestions" class="position-absolute top-100 start-0 w-100 bg-white border rounded shadow-lg d-none" style="z-index: 1050; max-height: 300px; overflow-y: auto;"></div>
				<button id="home-search-clear-btn" class="btn btn-outline-secondary rounded-0 d-none" type="button" aria-label="Clear search" title="Clear search">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
				<button class="btn btn-success border-success rounded-0 rounded-end" type="submit" aria-label="Search">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="11" cy="11" r="8"></circle>
						<path d="m21 21-4.35-4.35"></path>
					</svg>
				</button>
			</div>
		</form>
	</div>

	<div class="col-6 col-lg-4">
		<div class="card rounded-4 shadow bg-white mb-4 gsap-duration-1 gsap-zoom-in">
			<div class="card-body p-2">
				<div class="counter" data-count="<?php echo $legislators_tracked; ?>">0</div>
				<div class="counter-label">Legislators<br>Scored</div>
			</div>
		</div>
	</div>
	<div class="col-6 col-lg-4">
		<div class="card rounded-4 shadow bg-white mb-4 gsap-duration-1 gsap-zoom-in">
			<div class="card-body p-2">
				<div class="counter" data-count="<?php echo $votes_scored; ?>">0</div>
				<div class="counter-label">Votes<br>Analyzed</div>
			</div>
		</div>
	</div>
	<div class="col-12 col-lg-4 d-none d-md-block">
		<div class="card rounded-4 shadow bg-white mb-4 gsap-duration-1 gsap-zoom-in">
			<div class="card-body p-2">
				<div class="counter counter-rc" data-count="<?php echo $rollcalls_counted; ?>">0</div>
				<div class="counter-label d-md-none">Rollcalls Counted</div>
				<div class="counter-label d-none d-md-block">Rollcalls<br>Counted</div>
			</div>
		</div>
	</div>

</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-count]').forEach(el => {
        const target = +el.dataset.count.replace(/,/g, '');
        let current = 0;
        const increment = target / 120; // ~2 seconds at 60fps
        const timer = setInterval(() => {
            current += increment;
            el.textContent = Math.min(Math.round(current), target).toLocaleString('en-US');
            if (current >= target) {
                clearInterval(timer);
                el.textContent = target.toLocaleString('en-US');
            }
        }, 1000 / 60);
    });
});
</script>