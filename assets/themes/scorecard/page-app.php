<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
Strategic Notes
	You are positioning this correctly:
	Don’t lead with “cancel culture.”
	Lead with independence.
	Reinforce security and privacy.
	Make install feel normal and reversible.
	Remove fear around “download.”
Next Step (Strategic)
If you want this to convert well:
	The hero image should show: An iPhone with the app icon visible on the home screen.
	A push notification example.
	A clean in-app screen.

Add a subtle badge near the CTA: “No App Store Required”

Consider adding a small line under the CTA: “Takes less than 30 seconds.”

https://freedomindex.us/app/?ostest=android – show Android instructions
https://freedomindex.us/app/?ostest=ios – show iOS instructions
https://freedomindex.us/app/?ostest=desktop – show desktop instructions
https://freedomindex.us/app/?ostest=unsupported – show unsupported message
*/
get_header();
echo '<div class="container-xl p-0 m-0 mx-auto"><div id="legislator-search-results"></div></div>';
get_template_part('global-templates/page','top',['title' => get_the_title()]);
/*
while ( have_posts() ) : the_post();
?>
<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
	<div class="entry-content post-content post-page">
		<?php the_content(); ?>
	</div>
</article>
<?php 
endwhile; // end of the loop.
*/
?>
<section class="pwa-hero text-center pb-4">
	<div class="container-xl p-0">
		<div class="card rounded-4 shadow h-100">
			<div class="card-body">
				<h1 class="display-5 fw-bold"><span class="text-nowrap">Stay Informed.</span> <span class="text-nowrap">Stay FREE!</span></h1>
				<p class="lead mt-3">Install our app directly to your phone or tablet — no app store required. Fast. Secure. Private. And impossible for big tech to delete.</p>
				<div class="mt-4"><a href="#install" class="btn btn-primary px-4 fs-5">Install the Freedom Index App</a></div>
				<p class="mt-3 text-danger">Uses the same secure technology as online banking. No large download.</p>
				<p class="mt-2 text-danger fw-bold">No big tech tracking or censorship.</p>
			</div>
		</div>
	</div>
</section>

<section class="py-4 bg-light">
	<div class="container-xl p-0">
		<h2 class="fw-bold text-center mb-4 fs-2">Why We Built Our Own App</h2>
		<div class="card rounded-4 shadow h-100">
			<div class="card-body lead">
				<p>In recent years, many organizations have seen content restricted, removed, or silenced by powerful tech platforms.</p>
				<p>That’s why we built our app using open web technology. It installs directly from our secure website —
				meaning no corporation can remove it, hide it, or shut it off.</p>

				<p class="fw-semibold">
				When you install this app, you’re choosing a direct connection — independent, reliable, and uncensorable.
				</p>
				<div class="row">
<?php
$screenshots = [];
$screenshots[] = [
	'src' => 'https://freedomindex.us/assets/sites/5/2026/help-app-home-1.jpg',
	'alt' => 'Freedom Index App home screen',
];
$screenshots[] = [
	'src' => 'https://freedomindex.us/assets/sites/5/2026/help-app-gov.jpg',
	'alt' => 'Freedom Index App government screen',
];
$screenshots[] = [
	'src' => 'https://freedomindex.us/assets/sites/5/2026/help-app-legislators.jpg',
	'alt' => 'Freedom Index App legislators screen',
];
$screenshots[] = [
	'src' => 'https://freedomindex.us/assets/sites/5/2026/help-app-legislator.jpg',
	'alt' => 'Freedom Index App legislator screen',
];
$screenshots[] = [
	'src' => 'https://freedomindex.us/assets/sites/5/2026/help-app-leg-contact.jpg',
	'alt' => 'Freedom Index App legislator contact screen',
];
$screenshots[] = [
	'src' => 'https://freedomindex.us/assets/sites/5/2026/help-app-leg-share.jpg',
	'alt' => 'Freedom Index App legislator share screen',
];
foreach ($screenshots as $screenshot){
	echo '<div class="col-2"><img src="' . $screenshot['src'] . '" alt="' . $screenshot['alt'] . '" class="img-fluid"></div>';
}?>
				</div>
			</div>
		</div>
	</div>
</section>


<section class="py-4">
	<div class="container-xl p-0">
		<h2 class="fw-bold text-center mb-4 fs-2">What to Expect</h2>

		<div class="row g-4">
<?php
$features = [];
$features[] = [
	'title' => 'Fast Loading',
	'description' => 'Pages open quickly because key parts of the app are saved securely on your device. Even with weak signal, it performs smoothly.',
];
$features[] = [
	'title' => 'Push Notifications',
	'description' => 'Choose to receive important alerts and updates. You can turn notifications on or off anytime.',
];
$features[] = [
	'title' => 'Keeps You Signed In',
	'description' => 'As long as you use the app at least once every 30 days, you’ll typically remain signed in automatically.',
];
$features[] = [
	'title' => 'Secure by Design',
	'description' => 'Protected by HTTPS encryption — the same security used by banks and financial institutions.',
];
$features[] = [
	'title' => 'Private',
	'description' => 'We do not use big tech tracking tools, and we will never sell or share your personal data.',
];
$features[] = [
	'title' => 'Works Like an App',
	'description' => 'Opens from your home screen, runs full-screen, and feels just like any other app — without needing an app store account.',
];

foreach ($features as $feature):
?>
				<div class="col-md-4">
					<div class="card rounded-4 shadow h-100">
						<div class="card-body">
							<h3 class="fw-bold"><?= $feature['title'];?></h3>
							<p><?= $feature['description'];?></p>
						</div>
					</div>
				</div>
<?php endforeach; ?>
		</div>
	</div>
</section>


<section id="install" class="py-4 bg-light">
	<div class="container-xl p-0">
		<h2 class="fw-bold text-center mb-3 fs-2">How to Install</h2>
		<div class="card rounded-4 shadow h-100 col-12 col-md-10 col-lg-8 mx-auto">
			<div class="card-body">
				<p class="lead text-center">Visit this page using the browser on your phone, tablet, or computer. Click the install button to show instructions for your device.</p>
				<div class="text-center mt-3">
					<button id="fi-app-install-btn" class="btn btn-success fw-bold fs-3 col-12 col-md-6 col-lg-4">Install Now</button>
					<p id="fi-app-install-note" class="small text-muted mt-2 mb-0"></p>
				</div>
<div id="install-ios" class="d-none">
	<ul class="list-group list-group-flush fs-4">
		<li class="list-group-item">Open this page in Safari.</li>
		<li class="list-group-item">Tap the three-dot menu at the bottom right</li>
		<li class="list-group-item">Tap the Share icon (square with arrow).</li>
		<li class="list-group-item">Scroll down and select <strong>“Add to Home Screen.”</strong></li>
		<li class="list-group-item">Tap <strong>Add</strong>. Make sure "Open as Web App" is checked.</li>
	</ul>
	<div class="text-center fs-3">Screenshots:</div>
	<div class="row">
		<div class="col-10 offset-1 col-md-4 offset-md-0">
<img src="https://freedomindex.us/assets/sites/5/2026/kb-pwa-install-ios-1.jpg" alt="Screenshot of the iOS install process" class="img-fluid mb-3">
		</div>
		<div class="col-10 offset-1 col-md-4 offset-md-0">
<img src="https://freedomindex.us/assets/sites/5/2026/kb-pwa-install-ios-2.jpg" alt="Screenshot of the iOS install process" class="img-fluid mb-3">
		</div>
		<div class="col-10 offset-1 col-md-4 offset-md-0">
<img src="https://freedomindex.us/assets/sites/5/2026/kb-pwa-install-ios-3.jpg" alt="Screenshot of the iOS install process" class="img-fluid mb-3">
		</div>
	</div>
</div>
<div id="install-android" class="d-none">
	<ul class="list-group list-group-flush fs-4">
		<li class="list-group-item">Open this page in Chrome.</li>
		<li class="list-group-item">Tap the three-dot menu.</li>
		<li class="list-group-item">Select <strong>“Install App”</strong> or <strong>“Add to Home Screen.”</strong></li>
		<li class="list-group-item">Tap <strong>Install</strong>.</li>
	</ul>
	<div class="text-center fs-3">Screenshots:</div>
	<div class="row">
		<div class="col-10 offset-1 col-md-4 offset-md-0">
<img src="https://freedomindex.us/assets/sites/5/2026/kb-pwa-install-android-1.jpg" alt="Screenshot of the Android install process" class="img-fluid mb-3">
		</div>
		<div class="col-10 offset-1 col-md-4 offset-md-0">
<img src="https://freedomindex.us/assets/sites/5/2026/kb-pwa-install-android-2.jpg" alt="Screenshot of the Android install process" class="img-fluid mb-3">
		</div>
		<div class="col-10 offset-1 col-md-4 offset-md-0">
<img src="https://freedomindex.us/assets/sites/5/2026/kb-pwa-install-android-3.jpg" alt="Screenshot of the Android install process" class="img-fluid mb-3">
		</div>
	</div>

</div>
<div id="install-desktop" class="d-none">
	<ul class="list-group list-group-flush fs-4">
		<li class="list-group-item">Open this page in Chrome, Edge, or Safari on your Mac/PC.</li>
		<li class="list-group-item">Click <strong>Install Now</strong> first. If the install window appears, click <strong>Install</strong>.</li>
		<li class="list-group-item">If no window appears in Chrome: open <strong>⋮ menu</strong> and choose <strong>Install page as app</strong>.</li>
		<li class="list-group-item">If no window appears in Edge: open <strong>... menu</strong> → <strong>Apps</strong> → <strong>Install this site as an app</strong>.</li>
		<li class="list-group-item">If no window appears in Safari (Mac): open <strong>File</strong> → <strong>Add to Dock...</strong>.</li>
	</ul>
</div>
<div id="install-unsupported" class="d-none">
	<ul class="list-group list-group-flush fs-4">
		<li class="list-group-item">Install is unavailable in this browser right now. Use Chrome/Edge or your mobile device.</li>
	</ul>
</div>
			</div>
		</div>
	</div>
</section>


<section class="py-5">
  <div class="container-xl p-0">
    <h2 class="fw-bold text-center mb-5 fs-2">Frequently Asked Questions</h2>

    <div class="accordion shadow rounded-4" id="pwaFAQ">
<?php
$faqs = [];
$faqs[] = [
  'question' => 'Why isn’t the Freedom Index App in the app store?',
  'answer' => 'App stores can remove or restrict apps at any time.
            By installing directly from our website, the connection remains independent and cannot be deleted by outside companies.',
];
$faqs[] = [
  'question' => 'Is this safe?',
  'answer' => 'Yes. The app runs through secure HTTPS encryption — the same technology used by online banking.',
];
$faqs[] = [
  'question' => 'Does this take up a lot of space?',
  'answer' => 'No. It’s much smaller than most traditional apps because it uses secure web technology.',
];
$faqs[] = [
  'question' => 'Will I stay signed in?',
  'answer' => 'As long as you use the app at least once every 30 days,
            you will typically remain signed in automatically.
            If you’re inactive for a longer period, you may be asked to log in again for security.',
];
$faqs[] = [
  'question' => 'Do you track or sell my data?',
  'answer' => 'No. We do not use big tech tracking systems, and we will never sell or share your personal information with any other organization or the government.',
];
$faqs[] = [
	'question' => 'Will this app be updated?',
	'answer' => 'Yes. We will update the app regularly to ensure it is secure and functional.',
];
$faqs[] = [
	'question' => 'How do I uninstall the app?',
	'answer' => 'You can remove it exactly like any other app — simply hold the icon and tap “Remove.”',
];
$faqs[] = [
	'question' => 'How does this app work differently than a traditional app?',
	'answer' => '<p>Most apps depend entirely on Apple or Google for approval and continued access. If an app store decides to remove an app, users lose it.</p>
<p>Our app works differently. It connects directly from our secure website to your device using modern web technology. It behaves like a normal app — but without a gatekeeper.</p>
<p>That means continued access, even if app store policies change in the future.</p>',
];



foreach ($faqs as $index =>$faq):
	echo '<div class="accordion-item">';
	echo '	<h2 class="accordion-header">';
	echo '		<button class="accordion-button collapsed fw-bold fs-3" data-bs-toggle="collapse" data-bs-target="#faq' . $index . '">';
	echo $faq['question'];
	echo '		</button>';
	echo '	</h2>';
	echo '	<div id="faq' . $index . '" class="accordion-collapse collapse" data-bs-parent="#pwaFAQ">';
	echo '		<div class="accordion-body lead">';
	echo '			' . $faq['answer'] . '';
	echo '		</div>';
	echo '	</div>';
	echo '</div>';
endforeach;
?>
		</div>
	</div>
</section>


<script>
document.addEventListener("DOMContentLoaded", function() {

  const ua = navigator.userAgent.toLowerCase();
  const installButton = document.getElementById("fi-app-install-btn");
  const installNote = document.getElementById("fi-app-install-note");
  const iosBlock = document.getElementById("install-ios");
  const androidBlock = document.getElementById("install-android");
  const desktopBlock = document.getElementById("install-desktop");
  const unsupportedBlock = document.getElementById("install-unsupported");
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
  const isIOS = /iphone|ipad|ipod/.test(ua);
  const isAndroid = ua.includes("android");
  const isMobile = isIOS || isAndroid;
  const canUseInstallPrompt = !!(window.FS_PWA && window.FS_PWA.hasPrompt);
  const isEdge = ua.includes("edg/");
  const isChrome = ua.includes("chrome/") && !isEdge && !ua.includes("opr/");
  const isSafari = ua.includes("safari/") && !ua.includes("chrome/") && !isEdge && !ua.includes("android");
  const isDesktopInstallCapable = !isMobile && (isChrome || isEdge || isSafari || canUseInstallPrompt);

  // URL override for testing: ?ostest=ios|android|desktop|unsupported
  const ostest = new URLSearchParams(window.location.search).get("ostest");
  const ostestMap = { ios: iosBlock, android: androidBlock, desktop: desktopBlock, unsupported: unsupportedBlock };
  const ostestBlock = ostest && ostestMap[ostest.toLowerCase()] ? ostestMap[ostest.toLowerCase()] : null;

  // Show only the platform-specific install instructions block.
  function showInstallBlock(target) {
    [iosBlock, androidBlock, desktopBlock, unsupportedBlock].forEach(function(el) {
      if (!el) return;
      el.classList.add("d-none");
    });
    if (target) target.classList.remove("d-none");
  }

  if (ostestBlock) {
    showInstallBlock(ostestBlock);
  } else if (isIOS) {
    showInstallBlock(iosBlock);
  } else if (isAndroid) {
    showInstallBlock(androidBlock);
  } else if (isDesktopInstallCapable) {
    showInstallBlock(desktopBlock);
  } else {
    showInstallBlock(unsupportedBlock);
  }

  // Make CTA perform real install prompt when browser exposes it.
  if (!installButton) return;

  if (isStandalone) {
    installButton.disabled = true;
    installButton.textContent = "App Installed";
    if (installNote) installNote.textContent = "This app is already installed on this device.";
    return;
  }

  installButton.addEventListener("click", async function() {
    if (!window.FS_PWA || typeof window.FS_PWA.install !== "function") {
      if (installNote) installNote.textContent = "Install prompt not ready yet. Please refresh and try again.";
      return;
    }

    const result = await window.FS_PWA.install();
    if (result && result.ok) {
      if (installNote) installNote.textContent = "Install request accepted. Follow your browser prompts.";
      return;
    }

    // iOS and unsupported desktop/mobile browsers require manual install flow.
    if (ostestBlock) {
      showInstallBlock(ostestBlock);
      if (installNote) installNote.textContent = "Install is unavailable in this browser right now. Use Chrome/Edge/Safari or follow manual steps above.";
      return;
    }
    if (isIOS) {
      if (installNote) installNote.textContent = "On iPhone/iPad, use Safari Share -> Add to Home Screen.";
      showInstallBlock(iosBlock);
      return;
    } else if (isAndroid) {
      showInstallBlock(androidBlock);
    } else if (isDesktopInstallCapable) {
      showInstallBlock(desktopBlock);
    } else {
      showInstallBlock(unsupportedBlock);
    }
    if (isSafari && installNote) {
      installNote.textContent = "In Safari on Mac, use File -> Add to Dock if no install window appears.";
    } else if (installNote) {
      installNote.textContent = "Install is unavailable in this browser right now. Use Chrome/Edge/Safari or follow manual steps above.";
    }
  });
});
</script>
<?php get_template_part('global-templates/page','bottom');?>
<?php get_footer();