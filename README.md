# Kunena 5.3 for Joomla 3.x

Kunena is a native forum component for Joomla. This repository is a maintained fork of **Kunena 5.2.14**, the last
release of Kunena for Joomla 3.x, modernised for current PHP versions, hardened, and packaged as a single installable
extension.

- **Version:** 5.3.0 (2026-11-10)
- **Joomla:** 3.x, from 3.9 to 3.18 - built and tested for [Joomla 3.x UTD](https://github.com/joomlaworks/joomla-3.x)
- **PHP:** 7.1 to 8.5 (7.4 or newer recommended)
- **Databases:** MySQL 5.5.3+ / MariaDB, PostgreSQL 9.5+, SQLite 3.37+ (Joomla 3.x UTD's SQLite driver)
- **License:** GNU GPL v3 or later (see [LICENSE.txt](LICENSE.txt))

## Installation

1. On GitHub, use **Code > Download ZIP** (or download a release archive).
2. In Joomla's administrator, go to **Extensions > Install > Upload Package File** and upload the ZIP as it is.
3. You are taken to Kunena's installation wizard, which creates (or upgrades) the forum's database tables.

One package installs everything: the Kunena component, its media files, the Kunena library, the System - Kunena,
Quick Icon - Kunena and Console - Kunena plugins, and the extensions kunena.org used to distribute separately: the
Kunena Latest, Login, Search and Stats modules and the Content - Kunena Discuss and Search - Kunena plugins, and the new
Kunena - Baldrick AI plugin. New plugins
are enabled; plugins you already had keep their state (e.g. a disabled Kunena Discuss stays disabled). Modules are
installed but not published - add them to your template's positions in Extensions > Modules. The command line works too: `php cli/joomla.php extension:install --path=kunena.zip`
(the wizard then runs the first time you open Kunena in the administrator).

### Upgrading from Kunena 5.2.x

Install the package over your existing Kunena 5.2 site, as above. The old "Kunena Forum Package" and "Kunena Media
Files" entries are replaced by the single "Kunena" component; your forum, attachments, avatars and settings are kept.
Take a backup first, as for any upgrade. Upgrades are supported on MySQL/MariaDB (Kunena 5.2 only ran on MySQL).

## What's new in 5.3.0

- **One package** that installs straight from GitHub's ZIP, now including the Kunena modules and plugins that used to
  be separate downloads.
- **PHP 8.x**: runs cleanly on PHP 7.1 to 8.5, with no deprecation warnings from Kunena's code.
- **PostgreSQL and SQLite**, in addition to MySQL/MariaDB.
- **CAPTCHA from Joomla's Global Configuration**: Kunena now uses the CAPTCHA selected as "Default Captcha" in
  System > Global Configuration (reCAPTCHA, hCaptcha, Cloudflare Turnstile, IconCaptcha, Akismet, ...). If a CAPTCHA is
  required but none is configured, posting is refused instead of silently allowed.
- **Security**: about 90 vulnerabilities fixed, among them the hole bots used to post spam into any forum category
  without solving a CAPTCHA, SQL injections, stored XSS in posts and profiles, arbitrary file upload/deletion and
  account takeover by moderators. Details in [CHANGELOG.md](CHANGELOG.md).
- **No "Powered by Kunena" credits** in the frontend.
- **Faster banning**: the ban form preselects "block the account" and the removal of the spammer's posts, signature,
  avatar and profile.
- **Empty Trash** in one click (Kunena > Tools > Empty Trash): permanently deletes every trashed thread and post with
  their attachments, in batches with a progress bar. Also from the command line, e.g. in a cron job:
  `php cli/joomla.php kunena:trash:purge --no-interaction` (Joomla 3.x UTD).
- **Sample data** in one click (Kunena > Tools > Sample Data): a complete fictional forum with categories, users,
  avatars, about 60 threads and 400 posts, polls, attachments and every post state, to style and test a new site.
  "Remove" deletes exactly what was installed. Command line: `kunena:sampledata:install` / `kunena:sampledata:remove`.
- **Baldrick AI**, the forum's AI first responder: answers new questions (and questions revived after a week of
  silence) that no administrator or moderator has answered, and follows up when the poster replies to it (up to 3
  replies per thread, 120 per day), with Anthropic or any OpenAI-compatible API. A cheap model
  sorts each question first; clear questions get an answer from a more capable model, based on related public threads
  of the forum; broad ones get a canned "have you searched the forum?" reply; spam is unpublished. Replies are published
  at once, in the poster's language with an English translation. Staff are notified by e-mail. Costs and activity:
  Kunena > Baldrick AI. See below.
- **Blackadder**, the new default frontend template: clean and monochrome, one stylesheet and one plain-JavaScript
  file, no Bootstrap. It takes the site template's fonts and colours, follows its light/dark scheme (tested on Joomla 3.x
  UTD's Hammond, Finch and Rookwood) and can be themed with CSS variables. Discourse-like category and thread lists,
  threads with one reply box, a lightbox for images, and every page redone (posting, profiles, search, moderation...).
  Upgraded sites keep their template; Crypsis remains available.
- The "Time to create page" line is gone from the frontend footer.
- **Moderation queue:** moderators see how many posts await approval (account menu) and list them all in one place,
  across categories, with bulk approve, delete and move.
- Code formatted to the PSR-12 coding standard.

## Configuring the CAPTCHA

1. Choose and configure a CAPTCHA plugin in **Extensions > Plugins** (Joomla 3.x UTD ships IconCaptcha ready to use,
   with no keys).
2. Select it as **Default Captcha** in **System > Global Configuration > Site**.
3. In **Kunena > Configuration > Security**, choose who must solve it ("Display Captcha for") and, for registered
   users, below how many posts ("CAPTCHA Challenge for Users"). Moderators are never challenged.

## Configuring Baldrick AI

The plugin **Kunena - Baldrick AI** is enabled on installation but does nothing until it has an API key (Kunena's
administrator pages remind you). In **Extensions > Plugins > Kunena - Baldrick AI**:

1. **Step 1 (triage)**: choose the API type, leave the base URL empty for Anthropic's or OpenAI's own API (or enter a
   compatible endpoint, e.g. Qwen: `https://dashscope-intl.aliyuncs.com/compatible-mode/v1`, or its Anthropic-compatible
   `.../apps/anthropic`), enter the API key, then **Fetch models** and pick a small, cheap model (e.g. `claude-haiku-5-5`).
   **Test** checks the connection.
2. **Step 2 (answers)**: pick a more capable model (e.g. `claude-sonnet-5-5`), on the same connection or another one.
3. Describe your forum in **Site context** (what it is about, versions supported, documentation links, tone), review
   the canned replies, and choose who gets the e-mail notifications.
4. Turn on **Dry run** first: Baldrick then only answers new threads started by administrators and moderators, so you
   can try it safely. Turn it off when you are happy.

Processing happens in the background right after a post is saved. Slow or briefly unavailable providers are retried;
if the provider refuses the key (expired key, no credits or quota left), Baldrick pauses, keeps the waiting threads,
shows the error in Kunena's administrator and e-mails your notification list once, then resumes by itself when the
provider answers again. Limits and timeouts are under **Advanced** at the bottom of the settings.

On large forums (tens of thousands of posts or more) on MySQL/MariaDB or PostgreSQL, create the **search index** in
Kunena > Baldrick AI (or `php cli/joomla.php kunena:baldrick:index`) at a quiet time: Baldrick then finds related
threads in a fraction of a second instead of reading the latest 50,000 posts for each question. On hosts that block requests from the site to
itself, set **Processing** to "Command line only" and run `php cli/joomla.php kunena:baldrick:process --no-interaction`
from cron every minute (Joomla 3.x UTD). The posts Baldrick handles (and related public threads) are sent to the AI
provider you configure: mention it in your privacy policy.

## Command line

On Joomla 3.x UTD, Kunena's tools also run from the command line (`php cli/joomla.php list kunena`): emptying the
trash, installing and removing the sample data, Baldrick AI's processing and search index. See
[CLI-GUIDE.md](CLI-GUIDE.md).

## Notes

- Kunena's PHP support is limited by the Joomla core it runs on: Joomla 3.9 does not support PHP 8 and Joomla 3.10 only
  up to PHP 8.0. For PHP 8.1 to 8.5 use Joomla 3.x UTD.
- Of the third-party integration plugins Kunena used to bundle, only Community Builder's is kept (updated for security,
  only tested on MySQL); the plugins for AltaUserPoints, JomSocial, EasyProfile, EasySocial, EasyBlog and UddeIM are
  removed from existing sites when upgrading.
- The official Kunena update server is no longer registered (it only offers Kunena 5.2.x). Install newer versions of
  this fork from this repository, the same way as the first installation.

## For developers

See [AGENTS.md](AGENTS.md) for the repository layout, coding rules, how the installer works and how to test, and
[CHANGELOG.md](CHANGELOG.md) for every change in detail.

Kunena is (C) 2008 - 2026 Kunena Team and contributors.
