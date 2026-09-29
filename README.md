# Chamber Nation Member Directory

A WordPress member directory for chambers of commerce on **ChamberOrganizer** or **ECTownUSA**. It's built on the server so search engines and AI assistants can read it. Each member gets its own page, with **schema.org** markup, an XML sitemap and **llms.txt**.

## Install

1. Download **[chamber-nation-member-directory.zip](https://github.com/iamseventhpro/chamber-nation-member-directory/releases/latest/download/chamber-nation-member-directory.zip)** from the [latest release](https://github.com/iamseventhpro/chamber-nation-member-directory/releases/latest).
   Use the release zip, not GitHub's green **Code → Download ZIP** button. That button creates a folder with the wrong name, and automatic updates won't work.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the zip, then click **Install Now** and **Activate**.
3. Go to **CN Directory** in the dashboard and enter your chamber's **Organization ID**.
4. Add the shortcode to a page, e.g. a page called "Directory":

   ```
   [cn_directory]
   ```

   The plugin detects this page, and member pages appear under it (`/directory/business-name/`).

Requires WordPress 6.2+ and PHP 7.4+. Updates appear on the **Plugins** screen once they're released here.

## What you get

| | |
|---|---|
| **Directory** | Keyword, category and City/ZIP radius search, an A–Z bar, and pagination. Every view is a real URL, with no AJAX. |
| **Member pages** | A short overview of each member, with a **View Full Profile** button to their external page, which remains the source of truth. |
| **schema.org** | `LocalBusiness` / `Organization` JSON-LD on list and member pages, plus breadcrumbs. Hidden fields stay hidden in the structured data too. |
| **Sitemap** | `/cn-directory-sitemap.xml`, listed in robots.txt and in the core, Yoast and Rank Math sitemap indexes. |
| **llms.txt** | `/llms.txt` (overview and categories) and `/llms-full.txt` (every member, in Markdown). Shared with the [Events Calendar](https://github.com/iamseventhpro/chamber-nation-events-calendar) plugin. |
| **SEO hygiene** | Canonicals, titles and descriptions. Searches are `noindex`, removed members return `410 Gone`, and page numbers past the end redirect. |
| **Bootstrap** | Loaded only if your theme doesn't already include it (Auto / Always / Never). |

## How data stays fresh

The chamber API can't return one page at a time; it only returns the whole list, about 5 MB for a 2,000-member chamber. So:

- **The full list is re-downloaded in the background** every 15 min, 1 hour or 6 hours (your choice). Browsing, A–Z, page numbers and member pages use this copy, and visitors never wait for the download.
- **Keyword, category and location searches always hit the live API**, cached for 15 minutes.
- **CN Directory → Refresh now** updates immediately. If a refresh fails, the previous copy stays in place.

Refreshes use WP-Cron. On low-traffic sites, add a server cron job that loads `wp-cron.php` every 15 minutes.

## Shortcode options

Any setting can be overridden per page:

```
[cn_directory initial_view="categories" sorting="alphabetically" per_page="24"]
```

| Attribute | Values |
|---|---|
| `org_id`, `server` | Organization code; `org` (ChamberOrganizer) or `ectown` (ECTownUSA) |
| `initial_view` | `list` or `categories` |
| `sorting` | `random` (reshuffled daily) or `alphabetically` |
| `per_page` | 3–60 |
| `directory_type`, `single_cat_id` | `all` / `single` + a category ID |
| `about_field` | `yes` (About Us), `no` (Profile), `none` |
| `default_image` | Logo URL for members without one |
| `bootstrap` | `auto`, `always`, `never` |
| `hide_email`, `hide_phone_h`, `hide_address`, `hide_website`, `hide_primary_category`, `hide_name_position`, `hide_branches`, `hide_designations`, `hide_contact_form` | `yes` / `no` |
| `disable_radius_filter`, `geolocate`, `hide_alpha_until_filtered` | `yes` / `no` |
| `things_to_do`, `exclude` | Things-to-do filter; comma-separated member types to hide |
| `landing_url`, `docuteam_cname` | `mlp` or `docuteam`; DocuTeam domain |

Member pages, the sitemap and llms.txt use the saved settings of the main directory page.

## Customizing

Copy any file from `templates/` (`directory.php`, `member-card.php`, `categories.php`, `member-page.php`) into `your-theme/cn-directory/`. Developers can use these filters: `cnmd_schema_business`, `cnmd_schema_item_list`, `cnmd_http_args`, `cnmd_servers`, `cnmd_template`, `cnmd_bootstrap_present`, `cn_llms_txt_sections`.

## External services

Member data comes from the Chamber Nation API (`auth.chamberwidgets.com`). Logos, badges and full profiles are on Chamber Nation / DocuTeam servers. "Use my location" uses OpenStreetMap Nominatim, and update checks go to GitHub. See [readme.txt](readme.txt) for details and the [Chamber Nation privacy policy and terms](https://www.chambernation.com/website---app-privacy-policy).

## Releasing a new version (maintainers)

1. Update the version in `chamber-nation-member-directory.php` (the `Version:` header and `CNMD_VERSION`) and in `readme.txt` (`Stable tag`, plus a changelog entry).
2. Commit, then run `git tag v1.0.1 && git push origin main v1.0.1`.
3. The **Release** workflow checks the versions, lints the PHP on 7.4, builds the zip, and publishes the release.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
