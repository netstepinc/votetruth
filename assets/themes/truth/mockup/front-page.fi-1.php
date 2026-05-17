<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
 * Front Page Template — Freedom Index Home v2
 *
 * Hero-led civic accountability landing page. Replaces the legacy
 * dashboard-style two-column front page.
 *
Sections:
1. Hero (h1 + sub + search + scope chips)
Votes Tell the Truth.
See how your legislators vote on the issues that affect your freedom.
Know the facts. Hold them accountable.
[Find Your Legislator]

→ Emotional Problem

→ Search / Action Reinforcement

→ Stats / Credibility

→ “What Is a Freedom Score?”

→ Real Example

→ CTA


 *   3. Stats bar
 *   4. Score explainer + Freedom Score Scale
 *   5. Cost section (debt widget + copy)
 *   6. Actions (3 cards)
 *
 * Header: global-templates/header-2604.php
 * Footer: global-templates/footer-2604.php
 *
 * @package bootnews
 */

get_header();

/**
 * Initialize the legislator-search infrastructure (filters, results target,
 * AJAX handlers). Hero search input below uses the same `fs_search`
 * parameter it expects.
 */
if ( function_exists( 'fs_legislators_find_mine' ) ) {
	fs_legislators_find_mine();
}

// Stat values — pull from helper if available, else fall back to design copy.
$fs_stats = function_exists( 'fs_get_home_stats' )
	? fs_get_home_stats()
	: array(
		'legislators' => '12,909',
		'votes'       => '3,622',
		'roll_calls'  => '503,557',
	);
?>

<main id="primary" class="fi-home" role="main">

	<!-- ============================================================
	     1. Hero
	     ============================================================ -->
	<section class="fi-hero" aria-labelledby="fi-hero-heading">
		<h1 id="fi-hero-heading">Talk Is Cheap.<br>Votes Aren&rsquo;t.</h1>
		<p class="fi-hero-sub">
			Look up your members of Congress or state legislature.
			See who&rsquo;s defending your freedom &mdash; and who isn&rsquo;t.
		</p>

		<div class="fi-search-outer">
			<form id="fi-hero-search-form" class="fi-search-row" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search" novalidate>
				<input
					type="search"
					id="fi-hero-search"
					name="fs_search"
					class="form-control"
					placeholder="Enter legislator name or ZIP code"
					aria-label="Search for a legislator by name or ZIP code"
					autocomplete="off"
					value="<?php echo esc_attr( isset( $_GET['fs_search'] ) ? $_GET['fs_search'] : '' ); ?>" />
				<button type="submit" class="fi-search-btn">See Their Score</button>
			</form>
		</div>

		<div class="fi-chips" role="group" aria-label="Filter by level of government">
			<button type="button" class="fi-chip active" data-fi-scope="us">US Congress</button>
			<button type="button" class="fi-chip" data-fi-scope="state">Select a State</button>
			<button type="button" class="fi-chip saved-chip" id="fi-saved-chip" style="display:none" data-fi-scope="saved">
				<span class="fi-chip-dot"></span>
				<span id="fi-saved-chip-label">Wisconsin</span>
			</button>
		</div>

		<!-- Hidden target for AJAX legislator search results (populated by fs_legislators_find_mine()) -->
		<div id="legislator-search-results" class="container-xl"></div>
	</section>

	<!-- ============================================================
	     2. Credibility Band
	     ============================================================ -->
	<div class="fi-cred">
		A nonpartisan, 55-year record of holding lawmakers accountable to the Constitution.
	</div>

	<!-- ============================================================
	     3. Stats
	     ============================================================ -->
	<div class="fi-stats">
		<div class="container-xl">
			<div class="row g-0">
				<div class="col-12 col-sm-4">
					<div class="fi-stat">
						<div class="fi-stat-num"><?php echo esc_html( $fs_stats['legislators'] ); ?></div>
						<div class="fi-stat-lbl">Legislators Scored</div>
					</div>
				</div>
				<div class="col-12 col-sm-4">
					<div class="fi-stat">
						<div class="fi-stat-num"><?php echo esc_html( $fs_stats['votes'] ); ?></div>
						<div class="fi-stat-lbl">Votes Analyzed</div>
					</div>
				</div>
				<div class="col-12 col-sm-4">
					<div class="fi-stat">
						<div class="fi-stat-num"><?php echo esc_html( $fs_stats['roll_calls'] ); ?></div>
						<div class="fi-stat-lbl">Roll Calls Counted</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ============================================================
	     4. Score Explainer
	     ============================================================ -->
	<section class="fi-explainer" aria-labelledby="fi-explainer-heading">
		<div class="container-xl">
			<div class="row g-5 align-items-start">
				<div class="col-lg-8">
					<p class="fi-eyebrow">One number tells a story</p>
					<h2 id="fi-explainer-heading" class="fi-section-h">What Is a Freedom Score?</h2>
					<p class="fi-section-p fi-section-lede">
						The Constitution was designed to keep the government small and your life big.
					</p>
					<p class="fi-section-p">
						A Freedom Score tells you how often your lawmaker voted to protect that &mdash;
						based on actions, not speeches or promises.
					</p>
					<ul class="fi-grade-list">
						<li><strong>Your rights and your life.</strong><span class="fi-list-q"> Did they keep the government out of decisions that belong to you?</span></li>
						<li><strong>Your wallet.</strong><span class="fi-list-q"> Did they vote to stop overspending the country can&rsquo;t afford?</span></li>
						<li><strong>Your country.</strong><span class="fi-list-q"> Did they put America&rsquo;s interests ahead of foreign or globalist agendas?</span></li>
						<li><strong>Your independence.</strong><span class="fi-list-q"> Did they vote to keep America out of foreign wars, treaties, and entanglements that let outsiders control us?</span></li>
					</ul>
					<p class="fi-section-p mt-3">
						Every lawmaker swore an oath to uphold the Constitution. The score shows whether they kept it.
					</p>
					<a href="<?php echo esc_url( home_url( '/about/' ) ); ?>" class="fi-method-link">Read the full methodology &rarr;</a>
				</div>

				<div class="col-lg-4">
					<aside class="fi-scale" aria-label="Freedom Score grading scale">
						<div class="fi-scale-header">Freedom Score Scale</div>
						<div class="fi-scale-bar" aria-hidden="true">
							<span style="background:var(--fi-g-a);flex:10"></span>
							<span style="background:var(--fi-g-b);flex:9"></span>
							<span style="background:var(--fi-g-c);flex:9"></span>
							<span style="background:var(--fi-g-d);flex:9"></span>
							<span style="background:var(--fi-g-f);flex:59"></span>
						</div>
						<div class="fi-scale-rows">
							<div class="fi-scale-row">
								<div class="fi-grade-pill" style="background:var(--fi-g-a)">A</div>
								<div class="fi-scale-range">90&ndash;100%</div>
								<div class="fi-scale-desc">Constitutional Champion</div>
							</div>
							<div class="fi-scale-row">
								<div class="fi-grade-pill" style="background:var(--fi-g-b)">B</div>
								<div class="fi-scale-range">80&ndash;89%</div>
								<div class="fi-scale-desc">Generally Reliable</div>
							</div>
							<div class="fi-scale-row">
								<div class="fi-grade-pill" style="background:var(--fi-g-c)">C</div>
								<div class="fi-scale-range">70&ndash;79%</div>
								<div class="fi-scale-desc">Unreliable / Mixed Record</div>
							</div>
							<div class="fi-scale-row">
								<div class="fi-grade-pill" style="background:var(--fi-g-d)">D</div>
								<div class="fi-scale-range">60&ndash;69%</div>
								<div class="fi-scale-desc">Often Opposes Freedom</div>
							</div>
							<div class="fi-scale-row">
								<div class="fi-grade-pill" style="background:var(--fi-g-f)">F</div>
								<div class="fi-scale-range">0&ndash;59%</div>
								<div class="fi-scale-desc">Failing the Constitution</div>
							</div>
						</div>
					</aside>
				</div>
			</div>
		</div>
	</section>

	<!-- ============================================================
	     5. Cost Section
	     The legacy template part `template-parts/us-debt-clock` produced
	     a different visual; the design's lighter card is implemented
	     inline below. Wire the values to live data via the data-* hooks.
	     ============================================================ -->
	<section class="fi-cost" id="fi-cost-section" aria-labelledby="fi-cost-heading">
		<div class="container-xl" style="max-width: 860px;">
			<div class="row g-4 align-items-start">
				<div class="col-md-5">
					<div class="fi-debt-display" id="fi-debt-widget">
						<div class="fi-debt-header">National Debt</div>
						<div class="fi-debt-body">
							<div class="fi-debt-row">
								<div class="fi-debt-lbl">U.S. National Debt</div>
								<div class="fi-debt-val fi-ticker" data-debt-trillions>$36.9 Trillion</div>
								<div class="fi-debt-sub" data-debt-full>$36,900,000,000,000</div>
							</div>
							<div class="fi-debt-divider"></div>
							<div class="fi-debt-row">
								<div class="fi-debt-lbl">Your Household&rsquo;s Share</div>
								<div class="fi-debt-val household fi-ticker" data-debt-household>$278,400</div>
							</div>
						</div>
						<div class="fi-debt-source">Source: U.S. Treasury &middot; Updated daily</div>
					</div>
				</div>
				<div class="col-md-7 fi-cost-copy">
					<p class="fi-eyebrow">Every vote has a price</p>
					<h2 id="fi-cost-heading" class="fi-section-h" style="color:var(--fi-navy)">What every vote costs you.</h2>
					<p>
						Most scorecards tell you how a lawmaker voted. We also tell you what it cost.
						When a vote moves real money, we show the impact on your household &mdash;
						what it cost, or what it saved. Not every vote has a dollar figure, but when
						there&rsquo;s one to show, we show it.
					</p>
				</div>
			</div>
		</div>
	</section>

	<!-- ============================================================
	     6. Actions
	     ============================================================ -->
	<section class="fi-actions" aria-labelledby="fi-actions-heading">
		<div class="container-xl">
			<p class="fi-eyebrow">Go further</p>
			<h2 id="fi-actions-heading" class="fi-section-h">More ways to hold them accountable</h2>
			<div class="row g-3 mt-2">
				<div class="col-12 col-md-6 col-lg-4">
					<div class="fi-action-card">
						<div class="fi-action-title">Scorecards</div>
						<p class="fi-action-desc">Customize and print to share at meetings, events, and community gatherings.</p>
						<a href="<?php echo esc_url( home_url( '/scorecards/' ) ); ?>" class="fi-action-link">Get scorecards &rarr;</a>
					</div>
				</div>
				<div class="col-12 col-md-6 col-lg-4">
					<div class="fi-action-card">
						<div class="fi-action-title">Alerts</div>
						<p class="fi-action-desc">Get notified before key votes happen &mdash; not after.</p>
						<a href="<?php echo esc_url( home_url( '/alerts/' ) ); ?>" class="fi-action-link">Sign up &rarr;</a>
					</div>
				</div>
				<div class="col-12 col-md-6 col-lg-4">
					<div class="fi-action-card">
						<div class="fi-action-title">The App</div>
						<p class="fi-action-desc">Free, fast access on any phone or tablet. No app store required.</p>
						<a href="<?php echo esc_url( home_url( '/app/' ) ); ?>" class="fi-action-link">Install free &rarr;</a>
					</div>
				</div>
			</div>
		</div>
	</section>

</main>

<!-- ================================================================
     Front-page styles
     Co-located here for clarity during the redesign rollout.
     Move into the theme stylesheet (or a dedicated front-page.css)
     once the design is locked.
     ================================================================ -->
<style>
:root {
	/* Brand */
	--fi-blue:      #0055a4;
	--fi-blue-mid:  #00408a;
	--fi-navy:      #002b62;
	--fi-navy-deep: #001840;
	--fi-red:       #c52029;
	--fi-red-dark:  #9e1520;

	/* Neutrals */
	--fi-white:     #ffffff;
	--fi-gray-1:    #f4f5f7;
	--fi-gray-2:    #e2e5ea;
	--fi-ink:       #1a1c1e;
	--fi-ink-mid:   #444b54;
	--fi-ink-light: #6b7280;

	/* Grade scale */
	--fi-g-a: #1d7a45;
	--fi-g-b: #5aab6b;
	--fi-g-c: #d4891a;
	--fi-g-d: #c95e1a;
	--fi-g-f: #bf2b2b;

	/* Radii */
	--fi-r-sm: 6px;
	--fi-r-md: 10px;
	--fi-r-lg: 14px;
}

/* ── Hero ── */
.fi-home .fi-hero {
	background:
		linear-gradient(160deg,
			rgba(0, 24, 58, 0.96) 0%,
			rgba(0, 38, 80, 0.93) 50%,
			rgba(0, 30, 68, 0.97) 100%),
		repeating-linear-gradient(
			45deg,
			transparent 0px,
			transparent 18px,
			rgba(255, 255, 255, 0.012) 18px,
			rgba(255, 255, 255, 0.012) 19px
		);
	padding: 72px 24px 64px;
	text-align: center;
	position: relative;
	overflow: hidden;
}
.fi-home .fi-hero::after {
	content: '';
	position: absolute;
	inset: 0;
	background-image: radial-gradient(circle, rgba(255, 255, 255, 0.04) 1px, transparent 1px);
	background-size: 32px 32px;
	pointer-events: none;
}
.fi-home .fi-hero > * { position: relative; z-index: 1; }

.fi-home .fi-hero h1 {
	font-size: clamp(40px, 6vw, 64px);
	font-weight: 800;
	color: #fff;
	line-height: 1.08;
	letter-spacing: -0.02em;
	margin: 0 auto 18px;
	max-width: 720px;
	text-wrap: pretty;
}
.fi-home .fi-hero-sub {
	font-size: clamp(17px, 1.6vw, 20px);
	font-weight: 400;
	color: rgba(255, 255, 255, 0.7);
	margin: 0 auto 44px;
	max-width: 540px;
	line-height: 1.5;
}

/* ── Search ── */
.fi-home .fi-search-outer { max-width: 620px; margin: 0 auto; }
.fi-home .fi-search-row {
	display: flex;
	background: #fff;
	border-radius: 10px;
	box-shadow: 0 4px 24px rgba(0, 0, 0, 0.28), 0 1px 4px rgba(0, 0, 0, 0.1);
	overflow: hidden;
}
.fi-home .fi-search-row input.form-control {
	flex: 1; min-width: 0;
	border: none; outline: none;
	padding: 18px 24px;
	font-size: 17px;
	color: var(--fi-ink);
	background: transparent;
	border-radius: 0;
	height: auto;
}
.fi-home .fi-search-row input.form-control::placeholder { color: #aaa; }
.fi-home .fi-search-row input.form-control:focus { box-shadow: none; background: transparent; }
.fi-home .fi-search-btn {
	background: var(--fi-blue);
	color: #fff;
	border: none;
	padding: 0 28px;
	font-size: 15px;
	font-weight: 800;
	cursor: pointer;
	border-radius: 0 10px 10px 0;
	transition: background 0.12s;
	letter-spacing: 0.01em;
	flex-shrink: 0;
	white-space: nowrap;
}
.fi-home .fi-search-btn:hover,
.fi-home .fi-search-btn:focus { background: var(--fi-blue-mid); outline: none; }

/* ── Chips ── */
.fi-home .fi-chips {
	display: flex; justify-content: center; gap: 8px;
	flex-wrap: wrap; margin-top: 18px;
}
.fi-home .fi-chip {
	display: inline-flex; align-items: center; gap: 6px;
	padding: 8px 18px;
	border-radius: 6px;
	font-size: 16px;
	font-weight: 600;
	cursor: pointer;
	transition: all 0.12s;
	border: 1.5px solid rgba(255, 255, 255, 0.35);
	color: rgba(255, 255, 255, 0.85);
	background: rgba(255, 255, 255, 0.1);
}
.fi-home .fi-chip:hover {
	border-color: rgba(255, 255, 255, 0.6);
	color: #fff;
	background: rgba(255, 255, 255, 0.16);
}
.fi-home .fi-chip.active {
	background: rgba(255, 255, 255, 0.18);
	border-color: #fff; color: #fff;
}
.fi-home .fi-chip.saved-chip {
	background: var(--fi-white);
	border-color: var(--fi-white);
	color: var(--fi-navy);
	font-weight: 700;
}
.fi-home .fi-chip-dot {
	width: 5px; height: 5px;
	border-radius: 50%;
	background: currentColor;
}

/* ── Credibility Band ── */
.fi-home .fi-cred {
	background: #0a2a5e;
	padding: 16px 24px;
	text-align: center;
	font-size: 15px;
	color: rgba(220, 230, 245, 0.95);
	letter-spacing: 0.015em;
	border-top: 1px solid rgba(255, 255, 255, 0.15);
	border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

/* ── Stats ── */
.fi-home .fi-stats {
	background: var(--fi-white);
	border-bottom: 1px solid var(--fi-gray-2);
}
.fi-home .fi-stat {
	padding: 26px 16px;
	text-align: center;
	border-right: 1px solid var(--fi-gray-2);
}
.fi-home .fi-stat:last-child { border-right: none; }
.fi-home .fi-stat-num {
	font-size: 48px;
	font-weight: 800;
	color: var(--fi-blue);
	line-height: 1;
	letter-spacing: -0.02em;
	margin-bottom: 5px;
	font-variant-numeric: tabular-nums;
}
.fi-home .fi-stat-lbl {
	font-size: 14px;
	font-weight: 600;
	letter-spacing: 0.07em;
	text-transform: uppercase;
	color: var(--fi-ink-light);
}
@media (max-width: 575.98px) {
	.fi-home .fi-stat { border-right: none; border-bottom: 1px solid var(--fi-gray-2); }
	.fi-home .fi-stat:last-child { border-bottom: none; }
}

/* ── Explainer ── */
.fi-home .fi-explainer {
	background: var(--fi-gray-1);
	padding: 60px 0;
	border-bottom: 1px solid var(--fi-gray-2);
}
.fi-home .fi-eyebrow {
	font-size: 14px;
	font-weight: 700;
	letter-spacing: 0.12em;
	text-transform: uppercase;
	color: var(--fi-blue);
	margin-bottom: 10px;
}
.fi-home .fi-section-h {
	font-size: 28px;
	font-weight: 800;
	color: var(--fi-ink);
	line-height: 1.15;
	letter-spacing: -0.02em;
	margin-bottom: 14px;
}
.fi-home .fi-section-p {
	font-size: 16px;
	color: var(--fi-ink-mid);
	line-height: 1.65;
	margin-bottom: 20px;
}
.fi-home .fi-section-lede {
	color: var(--fi-navy);
	font-weight: 600;
	margin-bottom: 16px;
}
.fi-home .fi-grade-list {
	list-style: none; padding: 0;
	margin: 0 0 8px;
	display: flex; flex-direction: column; gap: 10px;
}
.fi-home .fi-grade-list li {
	font-size: 16px;
	color: var(--fi-ink-mid);
	line-height: 1.55;
	padding-left: 16px;
	border-left: 2px solid var(--fi-gray-2);
}
.fi-home .fi-list-q { color: var(--fi-ink-mid); }
.fi-home .fi-method-link {
	font-size: 14px;
	font-weight: 700;
	color: var(--fi-blue);
	text-decoration: none;
	letter-spacing: 0.01em;
}
.fi-home .fi-method-link:hover { text-decoration: underline; }

@media (max-width: 600px) {
	.fi-home .fi-grade-list li strong { display: block; margin-bottom: 2px; }
	.fi-home .fi-list-q { display: block; }
}

/* ── Score Scale Card ── */
.fi-home .fi-scale {
	background: var(--fi-white);
	border: 1px solid var(--fi-gray-2);
	border-radius: var(--fi-r-lg);
	overflow: hidden;
	box-shadow: 0 2px 16px rgba(0, 0, 0, 0.06);
}
.fi-home .fi-scale-header {
	background: var(--fi-navy);
	padding: 14px 20px;
	font-size: 14px;
	font-weight: 700;
	color: rgba(255, 255, 255, 0.8);
	letter-spacing: 0.04em;
	text-transform: uppercase;
}
.fi-home .fi-scale-bar { display: flex; height: 10px; }
.fi-home .fi-scale-rows { padding: 8px 0; }
.fi-home .fi-scale-row {
	display: flex; align-items: center; gap: 14px;
	padding: 10px 20px;
	border-bottom: 1px solid var(--fi-gray-2);
}
.fi-home .fi-scale-row:last-child { border-bottom: none; }
.fi-home .fi-grade-pill {
	width: 36px; height: 36px;
	border-radius: 8px;
	display: flex; align-items: center; justify-content: center;
	font-size: 15px; font-weight: 800;
	flex-shrink: 0; color: #fff;
}
.fi-home .fi-scale-range {
	font-size: 14px; font-weight: 600;
	color: var(--fi-ink); min-width: 60px;
}
.fi-home .fi-scale-desc {
	font-size: 14px; color: var(--fi-ink-light); flex: 1;
}

/* ── Cost Section ── */
.fi-home .fi-cost {
	background: #edf2f9;
	border-top: 1px solid #ccd9ec;
	border-bottom: 1px solid #ccd9ec;
	padding: 56px 0;
}
.fi-home .fi-cost-copy p {
	font-size: 16px;
	color: var(--fi-ink-mid);
	line-height: 1.65;
}

/* ── Debt Widget ── */
.fi-home .fi-debt-display {
	background: var(--fi-white);
	border: 1px solid #ccd9ec;
	border-radius: var(--fi-r-lg);
	overflow: hidden;
	box-shadow: 0 2px 12px rgba(0, 43, 98, 0.08);
}
.fi-home .fi-debt-header {
	background: var(--fi-navy);
	padding: 12px 20px;
	font-size: 14px;
	font-weight: 700;
	letter-spacing: 0.08em;
	text-transform: uppercase;
	color: rgba(255, 255, 255, 0.65);
}
.fi-home .fi-debt-body {
	padding: 20px;
	display: flex; flex-direction: column; gap: 16px;
}
.fi-home .fi-debt-lbl {
	font-size: 14px; font-weight: 700;
	letter-spacing: 0.08em;
	text-transform: uppercase;
	color: var(--fi-ink-light);
	margin-bottom: 4px;
}
.fi-home .fi-debt-val {
	font-size: 28px;
	font-weight: 800;
	letter-spacing: -0.02em;
	color: var(--fi-navy);
	font-variant-numeric: tabular-nums;
	line-height: 1;
}
.fi-home .fi-debt-val.household { color: #bf2b2b; font-size: 24px; }
.fi-home .fi-debt-sub {
	font-size: 12px;
	color: var(--fi-ink-light);
	font-variant-numeric: tabular-nums;
	margin-top: 2px;
}
.fi-home .fi-debt-divider {
	height: 1px;
	background: var(--fi-gray-2);
}
.fi-home .fi-debt-source {
	font-size: 14px;
	color: var(--fi-ink-light);
	padding: 10px 20px;
	border-top: 1px solid var(--fi-gray-2);
}
.fi-home .fi-ticker {
	font-variant-numeric: tabular-nums;
	font-feature-settings: "tnum";
}

/* ── Actions ── */
.fi-home .fi-actions {
	background: var(--fi-white);
	padding: 60px 0;
	border-bottom: 1px solid var(--fi-gray-2);
}
.fi-home .fi-action-card {
	border: 1px solid var(--fi-gray-2);
	border-radius: var(--fi-r-lg);
	padding: 28px 24px;
	background: var(--fi-gray-1);
	transition: box-shadow 0.15s, transform 0.15s, border-color 0.15s;
	display: flex; flex-direction: column;
	height: 100%;
}
.fi-home .fi-action-card:hover {
	box-shadow: 0 6px 24px rgba(0, 0, 0, 0.09);
	transform: translateY(-2px);
	border-color: #b8b2a8;
}
.fi-home .fi-action-title {
	font-size: 18px;
	font-weight: 800;
	color: var(--fi-ink);
	margin-bottom: 10px;
	letter-spacing: -0.01em;
	line-height: 1.2;
}
.fi-home .fi-action-desc {
	font-size: 15px;
	color: var(--fi-ink-mid);
	line-height: 1.6;
	margin-bottom: 20px;
	flex: 1;
}
.fi-home .fi-action-link {
	font-size: 14px;
	font-weight: 700;
	color: var(--fi-blue);
	text-decoration: none;
	display: inline-flex;
	align-items: center;
	gap: 4px;
	align-self: flex-start;
}
.fi-home .fi-action-link:hover { text-decoration: underline; }
</style>

<!-- ================================================================
     Front-page interactions
     ================================================================ -->
<script>
(function() {
	'use strict';

	/* ── Chips: scope filter ───────────────────────────── */
	var chips = document.querySelectorAll('.fi-home .fi-chip');
	chips.forEach(function(chip) {
		chip.addEventListener('click', function() {
			chips.forEach(function(c) { c.classList.remove('active'); });
			chip.classList.add('active');
			var scope = chip.getAttribute('data-fi-scope');
			if (scope === 'state') {
				// TODO: open state-selector modal (future)
				console.log('TODO: open state selector modal');
			}
			// Broadcast so other code (search filter) can react
			document.dispatchEvent(new CustomEvent('fi:scope-change', { detail: { scope: scope } }));
		});
	});

	/* ── Restore saved state chip from localStorage ────── */
	try {
		var savedState = localStorage.getItem('fs_last_state');
		if (savedState) {
			var chip  = document.getElementById('fi-saved-chip');
			var label = document.getElementById('fi-saved-chip-label');
			if (chip && label) {
				label.textContent = savedState;
				chip.style.display = 'inline-flex';
			}
		}
	} catch (err) { /* localStorage unavailable — ignore */ }

	/* ──────────────────────────────────────────────────────
	   Optional: live national-debt ticker
	   Toggle by setting <body data-debt-mode="ticker">.
	   ────────────────────────────────────────────────────── */
	var NATIONAL_DEBT_BASE   = 36900000000000;
	var HOUSEHOLD_SHARE_BASE = 278400;
	var DEBT_PER_SECOND      = 40000;     // ~$1.26T/yr ÷ 31.5M sec
	var HOUSEHOLDS           = 130000000;
	var HH_FACTOR            = 2.53;

	function fmtTrillions(n) { return '$' + (n / 1e12).toFixed(1) + ' Trillion'; }
	function fmtFull(n)      { return '$' + Math.round(n).toLocaleString('en-US'); }

	function startDebtTicker() {
		var trillionsEl = document.querySelector('[data-debt-trillions]');
		var fullEl      = document.querySelector('[data-debt-full]');
		var householdEl = document.querySelector('[data-debt-household]');
		var sourceEl    = document.querySelector('.fi-debt-source');
		var headerEl    = document.querySelector('.fi-debt-header');
		if (!trillionsEl || !fullEl || !householdEl) return;

		if (headerEl) headerEl.textContent = '⏱ Live National Debt';
		if (sourceEl) sourceEl.textContent = 'Source: U.S. Treasury · Updated live';

		var start = Date.now();
		setInterval(function() {
			var elapsed = (Date.now() - start) / 1000;
			var debt = NATIONAL_DEBT_BASE + elapsed * DEBT_PER_SECOND;
			var hh   = HOUSEHOLD_SHARE_BASE + (elapsed * DEBT_PER_SECOND) / HOUSEHOLDS * HH_FACTOR;
			trillionsEl.textContent = fmtTrillions(debt);
			fullEl.textContent      = fmtFull(debt);
			householdEl.textContent = fmtFull(hh);
		}, 100);
	}

	if (document.body && document.body.dataset.debtMode === 'ticker') {
		startDebtTicker();
	}
})();
</script>

<?php
get_footer();
