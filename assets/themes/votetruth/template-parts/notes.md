/* Canonical URL
Our legislator pages generate dozens of different URLs based on session, report, and issue parameters, but that's dilluting the SEO value of the page.
The canonical URL is the original legislator page URL without any session, report, or issue parameters, and is used by search engines to determine the "true" URL of the page.
Social media shares must be able to share the exact page vairant being viewed, not just the base legislator page.
home / legislator / legislator-id
*/
$canonical_url = home_url('/legislator/' . $legislator_id);