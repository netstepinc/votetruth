<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
The placement question
Bottom of page, dark section, clearly separated from the main message — that's exactly right. By the time a visitor reaches this section they've already encountered the hero, the problem, the answer, the how-it-works, and the closing CTA. They've either searched or they haven't. This section serves the visitor who searched, found something useful, and is now asking "what else can I do?" That's a legitimate user and this section serves them well in that position.
The section header
"More ways to hold them accountable" is good. It's action-oriented, it echoes the site's core message, and it frames all six cards as extensions of the same purpose rather than a feature list. Keep it. "Go Further" as an eyebrow is fine — low cognitive load, implies progression.

Card-by-card honest assessment
Free Download and Scorecards are your strongest cards. Both connect directly to the "share what you find" beat from the Change section. A visitor who just checked their legislator's score and wants to do something with it will click both of these immediately.
Alerts is strong. "Get notified before key votes happen — not after" is the best copy on this entire section. It's specific, it implies urgency, and it creates a reason to come back. Keep it exactly as written.
Mobile Apps is fine but "No app store required" as a selling point lands flat for mass market visitors. Most people don't know what a PWA is and don't care why there's no app store. Reframe around the benefit: "Check any legislator's score from your phone — anytime, anywhere."
Freedom Toolbox is vague. "Learn how to hold legislators accountable" tells me nothing specific. If this links to concrete resources — how to contact legislators, how to share scorecards, how to show up at town halls — say that. If it's genuinely valuable content, give it a specific description. If it's thin, cut the card.
Reprints is the one I'd cut or move. "Buy high quality reprints of the Freedom Index by The New American magazine" introduces a brand name — The New American — that a first-time visitor has no relationship with. It also asks someone to buy something before they've fully engaged with the free tool. It feels like a product placement on a page that's been carefully non-commercial. If reprints matter to your existing audience, put them in the footer or the Help section rather than the homepage.
Revised six or clean five
If you keep all six, rewrite Freedom Toolbox and Mobile Apps copy. If you cut one, cut Reprints. If you cut two, cut Reprints and Freedom Toolbox. Five clean cards beats six cards where two are doing weak work.
Dark section at the bottom is the right call. It visually communicates "this is supplementary" without saying so explicitly.
*/
$actions = [];

$actions[] = [
    'title' => 'Scorecards',
    'desc' => 'Learn how to customize, print and share at meetings, events, and community gatherings.',
    'icon' => 'bi bi-card-checklist',
    'link' => home_url('/help/printing/'),
    'button_text' => 'Get scorecards'
];

$actions[] = [
    'title' => 'Mobile Apps',
    'desc' => 'Check any legislator\'s score from your phone — anytime, anywhere.',
    'icon' => 'bi bi-phone-fill',
    'link' => home_url('/app/'),
    'button_text' => 'Get the App'
];

$actions[] = [
    'title' => 'Resources',
    'desc' => 'Learn more about the Constitution and how to hold legislators accountable.',
    'icon' => 'bi bi-tools',
    'link' => home_url('/tools/'),
    'button_text' => 'Learn More'
];

/*These are related to TheNewAmerican.com...we probably should not confuse people by sending them to another site.
Homepage converts a frustrated voter into someone who checks their legislator's score. That's the only job. Once they've engaged — once they've searched, seen a score, maybe shared it — then you introduce the deeper ecosystem. That's what email onboarding, the app experience, and interior pages are for.
VotesTellTheTruth.com as a feeder for TNA subscriptions and JBS support is a legitimate long-term strategy. But the funnel has to work in order. You can't ask someone to subscribe to a magazine before they trust the tool.
$actions[] = [
    'title' => 'Free Download',
    'desc' => 'Download the latest Congressional Freedom Index PDF.',
    'icon' => 'download',
    'link' => '#'
	'button_text' => 'Download'
];
$actions[] = [
    'title' => 'Reprints',
    'desc' => 'Buy high quality reprints of the Freedom Index by The New American magazine.',
	'icon' => 'reprints',
	'link' => '#',
    'button_text' => 'Buy Reprints'
];
$actions[] = [
    'title' => 'Alerts',
    'desc' => 'Get notified before key votes happen — not after.',
    'icon' => 'alerts',
    'link' => '#',
    'button_text' => 'Sign up'
];
*/
?>

<section class="container-fluid bg-primary text-light py-5">
	<div class="container">
		<div class="row">
			<?php foreach ($actions as $action): ?>
			<div class="col-12 col-md-4 py-3">
				<div class="card bg-primary border-white h-100">
					<div class="card-body">
						<div class="card-title text-white fw-bold fs-7"><?php echo $action['title']; ?></div>
						<p class="card-text text-white"><?php echo $action['desc']; ?></p>
					</div>
					<div class="card-footer p-0">
						<a href="<?php echo $action['link']; ?>" class="btn btn-primary rounded-0 rounded-bottom w-100"><?php echo $action['button_text']; ?> →</a>
					</div>
				</div>
            </div>
			<?php endforeach; ?>
		</div>
	</div>
</section>