Kunena Fork 5.3 for Joomla 3.x & Joomla 3.x UTD
===============================================

Kunena Fork is a native forum component for Joomla. This repository is a maintained fork of **Kunena 5.2.14**, the last release of Kunena for Joomla 3.x & [Joomla 3.x UTD](https://j3xutd.joomlaworks.net), modernised for current PHP versions, hardened and packaged as a single installable extension.

---

**This is an intermediate project until it's renamed to something else.**

---

- **Version:** 5.3.0 - released November 12th, 2026
- **Joomla:** 3.x, from 3.9 to 3.18 - built and tested for [Joomla 3.x UTD](https://j3xutd.joomlaworks.net)
- **PHP:** 7.1 to 8.5 (7.4 or newer recommended)
- **Databases:** MySQL 5.5.3+ / MariaDB, PostgreSQL 9.5+, SQLite 3.37+ (Joomla 3.x UTD's SQLite driver)
- **License:** GNU GPL v3 or later (see [LICENSE.txt](LICENSE.txt))

---

## CONTENTS
- [Changelog](#changelog)
  - [Version 5.3.0 - released November 12th, 2026](#version-530---released-november-12th-2026)
- [How to Install](#how-to-install)
- [How to Upgrade from Kunena 5.2.x](#how-to-upgrade-from-kunena-52x)
- [Configuring the CAPTCHA](#configuring-the-captcha)
- [Configuring Baldrick AI](#configuring-baldrick-ai)
- [Search Engines & Social Networks](#search-engines--social-networks)
- [Command Line](#command-line)
- [Notes](#notes)
- [For Developers](#for-developers)

## CHANGELOG

A short overview of each release. Every change is described in detail in [CHANGELOG.md](CHANGELOG.md).

## Version 5.3.0 - released November 12th, 2026
Summary of changes:
- **One package** that installs straight from GitHub's ZIP, now including the Kunena modules and plugins that used to be separate downloads
- **PHP 7.1 to 8.5**, with no deprecation warnings from Kunena Fork's code, and **PostgreSQL and SQLite** in addition to MySQL/MariaDB
- **Security:** about 90 vulnerabilities fixed, among them the hole bots used to post spam into any forum category without solving a CAPTCHA, SQL injections, stored XSS in posts and profiles, arbitrary file upload/deletion and account takeover by moderators
- **CAPTCHA from Joomla's Global Configuration** (reCAPTCHA, hCaptcha, Cloudflare Turnstile, IconCaptcha, Akismet...), refusing posts when a CAPTCHA is required but none is configured
- **Blackadder**, the new default frontend template: clean and monochrome, no Bootstrap, adapting to the site template's fonts, colours and light/dark scheme
- **Baldrick AI**, the forum's AI first responder: answers questions nobody from the staff has answered, with Anthropic or any OpenAI-compatible API, and unpublishes spam
- **Structured data, Open Graph and X cards** for threads, profiles and the forum's pages, so search engines understand discussions and shared links look right
- **Tools that save hours:** a moderation queue across categories, Empty Trash in one click, Sample Data in one click, and a much faster Recount on large forums
- **Command line** (Joomla 3.x UTD): empty the trash, install the sample data and run Baldrick AI from a terminal or a cron job
- **No "Powered by Kunena" credits** in the frontend

Every change is described in detail in [CHANGELOG.md](CHANGELOG.md).

**New features:**
- **Forum (frontend):**
  - Blackadder, the new default template: one stylesheet and one plain-JavaScript file, Bootstrap Icons as SVG, Discourse-like category and thread lists, threads with one reply box, a lightbox for attached images, and every page redone (posting, profiles, search, moderation...); it takes the site template's fonts and colours, follows its light/dark scheme (tested on Joomla 3.x UTD's Hammond, Finch and Rookwood) and can be themed with CSS variables. Upgraded sites keep their template; Crypsis remains available
  - Moderation queue: moderators see how many posts await approval (account menu) and list them all in one place, across categories, with bulk approve, delete and move
- **Baldrick AI**, the forum's AI first responder (plugin "Kunena - Baldrick AI"):
  - Answers new questions, and old questions revived after a while (configurable), that no administrator or moderator has answered, and follows up when the poster replies to it (also configurable)
  - A cheap model sorts each question first; clear questions get an answer from a more capable model, based on related public threads of the forum; broad ones get a canned "have you searched the forum?" reply; spam is unpublished
  - Replies are published at once, in the poster's language with an English translation, signed with the Baldrick account's forum signature
  - E-mail notifications for every reply and spam, or for spam only; errors (a thread Baldrick had to give up on, a provider refusing the key) are always notified
  - Costs, activity and the queue in Kunena > Baldrick AI; a full-text search index for large forums
- **Administrator:**
  - Tools > Empty Trash: permanently deletes every trashed thread and post with their attachments, in batches with a progress bar
  - Tools > Sample Data: a complete fictional forum with categories, users, avatars, about 60 threads and 400 posts, polls, attachments and every post state, to style and test a new site; "Remove" deletes exactly what was installed
  - Configuration > SEO: the search engine settings in a tab of their own, with switches for structured data and the sharing tags, a default share image and the site's X account
  - Configuration > Development (the last tab): Kunena Fork's debug mode, and a switch that stops all of Kunena Fork's e-mails (subscriptions, reports, mentions, Baldrick AI's notifications) on development and test sites, writing each one to Kunena Fork's log instead
- **Search engines and social networks:**
  - Google structured data (JSON-LD) for discussions: each thread as a `DiscussionForumPosting` with its author, full text, images, reply and view counts, its category, and the replies shown on the page as `Comment`; replies by Baldrick AI are marked as AI-generated (`digitalSourceType`), as Google recommends
  - Member profiles as a `ProfilePage` (name, avatar, join date, number of posts)
  - Open Graph and X card tags for threads, profiles, categories and the forum's other pages: title, description, the thread's first attached image (or a default share image) and a large image card
  - Only categories guests can read are described, and only what the page shows
  - On Joomla 3.x UTD, Kunena Fork hands all of this to the core "System - Structured Data & Social Tags" plugin: one graph per page, joined with the site's own (publisher, website, breadcrumbs), and no duplicate tags. On Joomla 3.9/3.10 Kunena Fork writes the tags itself
- **Command line** (Joomla 3.x UTD, `php cli/joomla.php list kunena`): `kunena:trash:purge`, `kunena:sampledata:install`, `kunena:sampledata:remove`, `kunena:baldrick:process`, `kunena:baldrick:index` and `kunena:baldrick:check` (tells why Baldrick would or wouldn't answer a post), all with `--dry-run`, also offered to AI assistants through Joomla 3.x UTD's MCP server. See [CLI-GUIDE.md](CLI-GUIDE.md)
- **Databases:** PostgreSQL 9.5+ and SQLite 3.37+ (Joomla 3.x UTD's driver), in addition to MySQL/MariaDB

**Improvements:**
- Updates through Joomla's updater (System > Update > Extensions), from Kunena Fork's own update server, for Joomla 3.9 and every later Joomla 3.x release
- One package installs everything: the component, its media, the library, the System, Quick Icon and Console plugins, the Latest, Login, Search and Stats modules, the Kunena Discuss and Search plugins, and Baldrick AI
- Runs cleanly on PHP 7.1 to 8.5
- CAPTCHA comes from the "Default Captcha" of Joomla's Global Configuration, and fails closed: if a CAPTCHA is required but none is configured, posting is refused instead of silently allowed
- Faster banning: the ban form preselects "block the account" and the removal of the spammer's posts, signature, avatar and profile
- Tools > Recount is fast on large forums: 3 to 4 seconds for 200,000 posts on MariaDB, MySQL and PostgreSQL, where a single batch could take over 20 minutes before
- Crypsis works with any site template: Bootstrap 2.3's CSS is loaded scoped to the forum, so it no longer looks unstyled on templates not built on Bootstrap 2
- Kunena's administrator works with Joomla 3.x UTD's Prothero template (no jQuery or MooTools assumed)
- The "Time to create page" line is gone from the frontend footer
- Code formatted to the PSR-12 coding standard

**Security fixes:**
- About 90 vulnerabilities fixed: the CAPTCHA bypass bots used to post spam into any category, SQL injections, access control holes (e.g. posting or editing anything), stored XSS in posts and profiles, remote code execution, arbitrary file upload and deletion, SSRF, CSRF and account takeover by moderators. Details in [CHANGELOG.md](CHANGELOG.md)

**Removed:**
- "Powered by Kunena" credits and the credits page
- The third-party integration plugins for AltaUserPoints, JomSocial, EasyProfile, EasySocial, EasyBlog and UddeIM (removed from existing sites when upgrading); Community Builder's is kept
- The WysiBB editor and other unused JavaScript
- The kunena.org update server (it only offers Kunena 5.2.x), replaced by Kunena Fork's own: updates now come through Joomla's updater

## HOW TO INSTALL

1. On GitHub, use **Code > Download ZIP** (or download a release archive).
2. In Joomla's administrator, go to **Extensions > Install > Upload Package File** and upload the ZIP as it is.
3. You are taken to Kunena Fork's installation wizard, which creates (or upgrades) the forum's database tables.

One package installs everything: the Kunena Fork component, its media files, the Kunena Fork library, the System - Kunena, Quick Icon - Kunena and Console - Kunena plugins, and the extensions kunena.org used to distribute separately: the Kunena Latest, Login, Search and Stats modules and the Content - Kunena Discuss and Search - Kunena plugins, and the new Kunena - Baldrick AI plugin. New plugins are enabled; plugins you already had keep their state (e.g. a disabled Kunena Discuss stays disabled). Modules are installed but not published - add them to your template's positions in Extensions > Modules.

The command line works too: `php cli/joomla.php extension:install --path=kunena.zip` (the wizard then runs the first time you open Kunena Fork in the administrator).

## HOW TO UPGRADE FROM KUNENA 5.2.X

Install the package over your existing Kunena 5.2 site, as above. The old "Kunena Forum Package" and "Kunena Media Files" entries are replaced by the single "Kunena" component; your forum, attachments, avatars and settings are kept. Take a backup first, as for any upgrade. Upgrades are supported on MySQL/MariaDB (Kunena 5.2 only ran on MySQL).

## CONFIGURING THE CAPTCHA

1. Choose and configure a CAPTCHA plugin in **Extensions > Plugins** (Joomla 3.x UTD ships IconCaptcha ready to use, with no keys).
2. Select it as **Default Captcha** in **System > Global Configuration > Site**.
3. In **Kunena > Configuration > Security**, choose who must solve it ("Display Captcha for") and, for registered users, below how many posts ("CAPTCHA Challenge for Users"). Moderators are never challenged.

## CONFIGURING BALDRICK AI

The plugin **Kunena - Baldrick AI** is enabled on installation but does nothing until it has an API key (Kunena Fork's administrator pages remind you). In **Extensions > Plugins > Kunena - Baldrick AI**:

1. **Step 1 (triage)**: choose the API type, leave the base URL empty for Anthropic's or OpenAI's own API (or enter a compatible endpoint, e.g. Qwen: `https://dashscope-intl.aliyuncs.com/compatible-mode/v1`, or its Anthropic-compatible `.../apps/anthropic`), enter the API key, then **Fetch models** and pick a small, cheap model (e.g. `claude-haiku-5-5`). **Test** checks the connection.
2. **Step 2 (answers)**: pick a more capable model (e.g. `claude-sonnet-5-5`), on the same connection or another one.
3. Describe your forum in **Site context** (what it is about, versions supported, documentation links, tone), review the canned replies, and choose who gets the e-mail notifications and what about (every reply and spam, or spam only; errors are always notified).
4. Turn on **Dry run** first: Baldrick then only answers new threads started by administrators and moderators, so you can try it safely. Turn it off when you are happy.

Baldrick posts as the user selected in its settings, or as a "Baldrick" user it creates (a plain Registered user is all it needs). Its replies are signed with that account's forum signature: edit it in the account's forum profile.

Processing happens in the background right after a post is saved. Slow or briefly unavailable providers are retried; if the provider refuses the key (expired key, no credits or quota left), Baldrick pauses, keeps the waiting threads, shows the error in Kunena Fork's administrator and e-mails your notification list once, then resumes by itself when the provider answers again. Limits and timeouts are under **Advanced** at the bottom of the settings.

On large forums (tens of thousands of posts or more) on MySQL/MariaDB or PostgreSQL, create the **search index** in Kunena > Baldrick AI (or `php cli/joomla.php kunena:baldrick:index`) at a quiet time: Baldrick then finds related threads in a fraction of a second instead of reading the latest 50,000 posts for each question.

On hosts that block requests from the site to itself, or end the processing that runs after a page is sent (the activity log then shows "The previous attempt stopped before finishing"), set **Processing** to "Command line only" and run `php cli/joomla.php kunena:baldrick:process --no-interaction` from cron every minute (Joomla 3.x UTD). `php cli/joomla.php kunena:baldrick:check <post id>` tells why Baldrick would or wouldn't answer a post.

The posts Baldrick handles (and related public threads) are sent to the AI provider you configure: mention it in your privacy policy.

## SEARCH ENGINES & SOCIAL NETWORKS

Structured data and the Open Graph / X card tags are on by default. In **Kunena > Configuration > SEO** you can turn each of them off (e.g. when an SEO extension already writes them for the forum), choose a **default share image** for pages without an image of their own (1200 x 630 pixels recommended) and enter the site's **X account**.

On Joomla 3.x UTD, the core plugin **System - Structured Data & Social Tags** writes the tags of every page; Kunena Fork gives it the forum's own (threads, profiles, categories), and the plugin's settings (site name, publisher, default image, X account) apply to everything else. Test a thread with Google's [Rich Results Test](https://search.google.com/test/rich-results).

## COMMAND LINE

On Joomla 3.x UTD, Kunena Fork's tools also run from the command line (`php cli/joomla.php list kunena`): emptying the trash, installing and removing the sample data, and Baldrick AI's processing, search index and checks. See [CLI-GUIDE.md](CLI-GUIDE.md).

## NOTES

- Kunena's PHP support is limited by the Joomla core it runs on: Joomla 3.9 does not support PHP 8 and Joomla 3.10 supports only up to PHP 8.0. For proper support for PHP 7.1 to 8.5 just do yourself a favour and use [Joomla 3.x UTD](https://j3xutd.joomlaworks.net) - it's better, faster, more secure & current.
- Of the third-party integration plugins Kunena used to bundle, only Community Builder's is kept (updated for security, only tested on MySQL); the plugins for AltaUserPoints, JomSocial, EasyProfile, EasySocial, EasyBlog and UddeIM are removed from existing sites when upgrading.
- Updates come through Joomla's own updater (**System > Update > Extensions**): Kunena Fork registers its update server (`https://joomlaworks.github.io/kunena-fork/update.xml`) for every Joomla 3.x from 3.9 on, Joomla 3.x UTD's future releases included. The official Kunena update server is no longer registered (it only offers Kunena 5.2.x).

## FOR DEVELOPERS

See [AGENTS.md](AGENTS.md) for the repository layout, coding rules, how the installer works and how to test, and [CHANGELOG.md](CHANGELOG.md) for every change in detail.

For development and test sites, **Kunena > Configuration > Development** stops all of Kunena Fork's e-mails (each one is written to Kunena Fork's log instead) and turns on Kunena Fork's debug mode.

Copyright (C) 2008 - 2026 Kunena Team, JoomlaWorks Ltd. and contributors.
