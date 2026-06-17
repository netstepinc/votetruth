<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/* Sound Bites?
The synthesis — best version of all five bites:
These pull the strongest elements across all sources including our first round, weighted toward what the Storybrand transcripts actually demand: concrete, emotional, zero mental calories, hero-in-a-hole framing.
P — Problem
Politicians make promises. Their votes tell a different story.
Pulls from ChatGPT's best line but closes with your locked tagline's language, making it feel like the page was designed as a system. The visitor is in the hole: they've been lied to.
E — Empathy
It's frustrating to feel like no one in government actually answers for what they do.
"Answers for" is doing precise work. It's not accusatory toward any party — it's about accountability as a concept. The word "frustrating" was validated by the YNAB transcript as one of the lowest-cognitive-load emotion words that 100% of the market can identify with.
A — Answer
The Freedom Index scores every legislator on how they actually vote — not what they say.
"Actually" is carrying the emotional load here without any rage. This is the rope being thrown into the hole. You are the guide. The score is the tool.
C — Change
Find your legislators. See the score. Share it.
Three words per beat. Micro-actions. This is the Storybrand rope being climbed. It echoes the verb structure of the best Nike-style CTAs without the fitness-brand energy. Grok had the right instinct here.
E — End Result
When voters know the score, politicians start answering for results.
"Answering for results" echoes the empathy bite ("answers for what they do") creating a closed loop — which is a technique the transcripts don't name explicitly but Miller demonstrates throughout. It's aspirational without being tribal. And "know the score" works on two levels simultaneously.

<h2>Government gets bigger. Your life gets smaller.</h2>

CTA: CLOSING
Promises are easy. Votes are proof.
Find your legislators. Check their record.
Hold them to the standard they swore to uphold.
[Find My Legislators]

<p class="fs-4 text-center">Promises are easy. <span class="text-nowrap">Votes are proof.</span></p>

<p class="fs-4 text-anchor text-center">Make government smaller.</p>
<h2 class="fs-3 fw-7 mb-3 text-anchor text-center">Your life gets bigger.</h2>

<p class="fs-6 text-fade fw-5 text-center mb-0">Ignore their slogans. <span class="text-nowrap">See their record.</span></p>

<p class="fs-7 text-center">Hold them accountable <span class="text-nowrap">to the Constitution.</span></p>

<p class="fs-4 text-fade-anchor text-center">Ignore their slogans.</p>
<p class="fs-4 text-anchor fw-5 text-center">Know the score.</p>
<p class="fs-4 text-anchor fw-7 text-center mb-0">Hold them accountable.</p>
<div class="mx-auto col-12 col-md-10 col-lg-8 col-xl-6 text-center py-5">
<a href="#findmy" class="btn btn-lg btn-primary fs-4 fw-7 bg-anchor w-100">Find My Legislators</a>
</div>

<div id="home-answer-score" class="container-fluid p-0">
	<div class="container py-5">
		<p class="fs-3 fw-7 text-anchor text-center">Slogans lose power when <span class="text-nowrap">you know the score.</span></p>
	</div>
</div>

<p class="fs-4 text-anchor fw-5 text-center">See their Freedom score.</p>
<p class="fs-4 text-anchor fw-7 text-center mb-0">Hold them accountable.</p>
*/
?>
<div class="container-fluid py-5 bg-amber-light-2">
	<div class="container">
		<div class="row">
			<div class="col-12">
				<p class="fs-4 text-anchor text-center mt-5">Slogans lose power when <span class="text-nowrap">you know the score.</span></p>
				<p class="fs-3 fw-7 text-black text-center">Check Your <span class="text-nowrap">Legislators' Scores.</span></p>
				<form id="footer-legislator-search-form" class="mx-auto col-12 col-md-10 col-lg-9 col-xl-8 mt-4 mb-5 pb-5" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search" novalidate>
					<div class="input-group position-relative">
						<input id="footer-legislator-search-input" class="form-control form-control-lg fs-7 bg-white" name="fi_search" type="search" placeholder="Enter ZIP code or legislator name" value="" aria-label="Enter ZIP code" autocomplete="off" minlength="3">
						<div id="footer-search-suggestions" class="position-absolute bg-white border rounded shadow d-none" style="z-index: 1050; max-height: 400px; overflow-y: auto;"></div>
						<button id="footer-search-clear-btn" class="btn btn-warning p-2 d-none" type="button" aria-label="Clear search" title="Clear search">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
								<line x1="18" y1="6" x2="6" y2="18"></line>
								<line x1="6" y1="6" x2="18" y2="18"></line>
							</svg>
						</button>
						<button class="btn btn-amber fw-4 fs-6" type="submit" aria-label="Search">
							<span class="d-none d-xl-inline">Find My Legislators</span>
							<span class="d-none d-lg-inline d-xl-none">Find Legislators</span>
							<span class="d-lg-none">Search</span>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>