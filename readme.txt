=== Chamber Nation Member Directory ===
Contributors: chambernation
Tags: member directory, business directory, chamber of commerce, schema, seo
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An SEO- and AI-friendly member directory for chambers of commerce on ChamberOrganizer or ECTownUSA, with a page for each member, schema.org markup and llms.txt.

== Description ==

Chamber Nation Member Directory shows your chamber's members from ChamberOrganizer or ECTownUSA on your WordPress site.

Embeddable directories usually build the member list in the visitor's browser, which search engines and AI assistants often can't read. This plugin builds the directory on your server. Every member, category, letter and page number is a real URL with the content in plain HTML.

**Features**

* Search by business name or keyword, category, and city/ZIP within a radius. An A–Z bar, and server-side pagination.
* Two layouts: a list with a search sidebar, or a search bar with a category grid. There's also a single-category mode.
* **A page for each member** (e.g. `/directory/business-name/`): logo, categories, description, address with a map link, phone, website, texting keyword, representative, social links and badges. It includes a prominent **View Full Profile** button to the member's external page, which remains the source of truth.
* schema.org `LocalBusiness` / `Organization` JSON-LD on directory pages (`ItemList`) and member pages, plus breadcrumbs. Fields you hide are also left out of the structured data.
* An XML sitemap of member pages, listed in robots.txt and in the WordPress core, Yoast and Rank Math sitemap indexes.
* `/llms.txt` and `/llms-full.txt` for AI assistants: a site overview, the categories, and the full member list in Markdown.
* Canonical URLs, titles and descriptions for each member and category page. Search results are `noindex`, removed members return 410, and unknown members return 404. Works with Yoast SEO, Rank Math and All in One SEO.
* Show or hide each field: category, representative, email, phone, address, website, contact button, branches and designations.
* Bootstrap 5 styling is loaded only if your theme doesn't already include Bootstrap. This can be set to Auto, Always or Never.
* The member list is downloaded in the background on a schedule, so visitors never wait for it. Searches use the live API and are cached for 15 minutes.

**How data stays fresh**

The chamber API can't return one page of members at a time, so the full member list is stored and refreshed in the background every 15 minutes, hour or 6 hours (your choice). Browsing, A–Z, page numbers and member pages use that stored copy. Keyword, category and location searches always query the API live. **CN Directory → Refresh now** updates immediately.

**Usage**

1. Go to **CN Directory** in the dashboard and enter your Organization ID.
2. Add the `[cn_directory]` shortcode to a page. The plugin detects it as the directory page, and member pages appear under it.

You can override any setting on a single page, for example:

`[cn_directory directory_type="single" single_cat_id="59879" per_page="24"]`

**Template overrides**

Copy any file from `templates/` into `your-theme/cn-directory/` to customize it.

== Installation ==

1. Download `chamber-nation-member-directory.zip` from the latest release: https://github.com/iamseventhpro/chamber-nation-member-directory/releases/latest
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the zip, then **Install Now** and **Activate**.
3. Enter your Organization ID under **CN Directory**.

Updates appear on the Plugins screen like any other plugin.

== External services ==

This plugin uses the following services. Each is needed for the feature listed.

**Chamber Nation directory API** (`https://auth.chamberwidgets.com/cn-api/{platform}/mms/mms_members` and `/mms/mms_categories`)
Supplies member and category data. Your web server sends your Organization ID and any search terms (keyword, category, city, ZIP, distance). It does this on a schedule (every 15 minutes to 6 hours), when a visitor searches (cached 15 minutes), and when the settings page is opened. No visitor personal data is sent.
Provider: Chamber Nation. Privacy policy and terms and conditions: https://www.chambernation.com/website---app-privacy-policy

**Chamber platform and DocuTeam content** (chamberorganizer.com, ectownusa.net, docu.team and their CDNs)
The visitor's browser loads member logos from these servers. "View Full Profile", "View Information" and "Contact" link to member pages on them. When Bootstrap is needed, the stylesheet `https://docu.team/theme/assets/css/theme.min.css` is loaded. Badge images come from `https://stage.docu.team/images/`; your server checks whether a badge image exists once per badge name (cached 30 days). These are Chamber Nation services, covered by the policy above.

**OpenStreetMap Nominatim** (`https://nominatim.openstreetmap.org/reverse`)
Used only when a visitor clicks "Use my location", or when "Pre-fill City and ZIP" is enabled and the visitor allows location access. The visitor's browser sends its approximate coordinates to look up a city and ZIP code. Privacy policy: https://osmfoundation.org/wiki/Privacy_Policy. Usage policy: https://operations.osmfoundation.org/policies/nominatim/

**Google Maps** (`https://www.google.com/maps/search/`)
Addresses link to Google Maps. Nothing is sent unless the visitor clicks. Terms: https://policies.google.com/terms. Privacy: https://policies.google.com/privacy

**GitHub** (`https://api.github.com/repos/iamseventhpro/chamber-nation-member-directory/releases/latest`)
Checked about twice a day in the dashboard, to offer plugin updates. Terms: https://docs.github.com/site-policy/github-terms/github-terms-of-service. Privacy: https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement

== Frequently Asked Questions ==

= Why does a member not have its own page? =

Members without an external profile link (no landing page or catalog URL) are listed in the directory but don't get a page. This avoids many near-empty pages.

= A member changed their details. When will it show? =

In searches, within 15 minutes. In the browse list and on member pages, after the next background refresh. Use **Refresh now** for immediate updates.

= My site gets very little traffic. Will refreshes still run? =

Refreshes use WP-Cron, which runs when the site gets visits. On quiet sites, set up a server cron job that requests `wp-cron.php` every 15 minutes.

= Does it conflict with an existing llms.txt? =

No. A real `llms.txt` file in your site root is served by the web server before WordPress runs, so it always takes priority.

== Changelog ==

= 1.0.0 =
* Initial release.
