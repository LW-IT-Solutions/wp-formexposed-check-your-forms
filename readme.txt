=== formexposed – check your forms ===
Contributors: lwitsolutions
Tags: mainwp, contact form, spam, agency, monitoring
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The contact form protection of all your MainWP child sites, from formexposed.com, in your MainWP dashboard.

== Description ==

For agencies with a formexposed Business plan. The extension lists every child site of your MainWP dashboard with its formexposed rating (Exposed / Partially protected / Protected / No contact form found), risk score, date of the last check, number of problems and bots blocked in the last 30 days - and a link to the full report.

* A summary on top: how many sites are exposed, partially protected, protected, and how many child sites are not in formexposed yet.
* Sites that are not in formexposed yet are marked. Add them under Agency → Import on formexposed.com, or connect them with the rollout code of the formexposed plugin: `wp formexposed connect fxc_...`.
* Sites that formexposed checks but that are not child sites of this dashboard are listed too ("not in MainWP").
* The API key is checked before it is saved; errors (revoked key, no Business plan, rate limit) are explained in plain words.

Without MainWP the extension works as a plain overview under Settings → formexposed portfolio.

The checks themselves run on formexposed.com. To protect the forms of a site, install the plugin "formexposed – Contact form spam check" on that site.

== Installation ==

1. Install and activate the extension on your MainWP dashboard site.
2. Create an API key on formexposed.com under Agency → API (Business plan).
3. Open MainWP → Extensions → formexposed (or Settings → formexposed portfolio without MainWP), enter the key and click "Check and save the key".

== Frequently Asked Questions ==

= Does the extension change anything on my child sites? =

No. It only reads the results from formexposed.com and shows them in your dashboard. It does not talk to your child sites.

= How are child sites matched with formexposed? =

By host name, without "www.". The list of your child sites never leaves your dashboard; the matching happens here.

= How fresh is the list? =

The answer of formexposed.com is kept for 10 minutes. "Refresh now" fetches it again at once.

= Which data does the extension store? =

The API key (an option that is not autoloaded) and the list from formexposed.com for 10 minutes (a transient). Uninstalling removes both.

== Screenshots ==

1. The portfolio in the MainWP dashboard: summary, child sites matched with formexposed, sites that are only in one of the two, and the API key form.

== External services ==

The extension uses the formexposed REST API of LW IT Solutions (formexposed.com):

* GET https://formexposed.com/api/v1/domains, with your API key in the Authorization header - when you save a key (to check it), when you open the page and the list is older than 10 minutes, and when you click "Refresh now". Sent: your API key. Received: the domains of your account with rating, score, date of the last check, number of problems, blocked bots and a report link. The list of your child sites is not sent anywhere.
* The "Report" links and the link to "Agency → API" open formexposed.com in your browser.

Privacy policy: https://formexposed.com/privacy · Terms of service: https://formexposed.com/terms · Legal notice: https://formexposed.com/legal

== Changelog ==

= 1.1.0 =
* The API key is checked before it is saved; plain-words errors for a revoked key, a missing Business plan and the rate limit.
* Summary of the portfolio on top; "No contact form found" as a rating.
* Report links only to formexposed.com; better screen reader texts; "Remove the key".
* "Settings" link in the plugin list; uninstall cleans every site of a multisite network.
* Tested with WordPress 7.1 and MainWP 6.4.

= 1.0.0 =
* First release: portfolio of all child sites with rating, score, last check, problems, blocked bots and report link.

== Upgrade Notice ==

= 1.1.0 =
Checks the API key before saving it and explains errors in plain words. Adds a summary of the portfolio.
