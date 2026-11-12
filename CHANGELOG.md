# Changelog

All notable changes to Kunena Fork, this fork of Kunena, are documented here. For a short overview see [README.md](README.md); for
how the code base is organised and how to work on it, see [AGENTS.md](AGENTS.md).

## [5.3.0] - 2026-11-12

Based on Kunena 5.2.14 (2022-12-14), the last release of Kunena for Joomla 3.x.

Severity labels in the security sections: **Critical** (remote code execution, SQL injection, account takeover, or
anything exploitable by guests at scale), **High**, **Medium**, **Low** (hardening). "Guest" means an anonymous visitor,
"registered" a logged-in user without special rights.

---

### 1. Packaging: one installable package (K2-style)

Kunena 5.2 was a Joomla "package" (`pkg_kunena`) wrapping five archives (component, library, media files, System and
Quick Icon plugins). The repository is now a single installable package whose layout mirrors a Joomla site:

| Path in the repository | Installed to | Installed by |
|---|---|---|
| `kunena.xml` | `administrator/components/com_kunena/kunena.xml` | Joomla (component manifest) |
| `script.kunena.php` | `administrator/components/com_kunena/script.kunena.php` | Joomla (installer script) |
| `administrator/components/com_kunena/` | same | Joomla (`<administration><files>`) |
| `components/com_kunena/` | same | Joomla (`<files>`) |
| `media/kunena/` | same | Joomla (`<media>`; was the `kunena_media` "file" extension) |
| `libraries/kunena/` (with its own `kunena.xml`) | `libraries/kunena/` | `script.kunena.php` postflight |
| `plugins/system/kunena/`, `plugins/quickicon/kunena/`, `plugins/console/kunena/` | same | `script.kunena.php` postflight (and enabled) |
| `plugins/content/kunenadiscuss/`, `plugins/search/kunena/` | same | `script.kunena.php` postflight (enabled when new; an existing one keeps its state) |
| `modules/mod_kunenalatest/`, `mod_kunenalogin/`, `mod_kunenasearch/`, `mod_kunenastats/` | same | `script.kunena.php` postflight (not published: place them yourself) |

- The ZIP GitHub exports for the repository ("Code" > "Download ZIP") installs as-is through Extensions > Install
  (Joomla uses the archive's single top folder). Development files are excluded from that ZIP with `.gitattributes`
  `export-ignore`.
- `script.kunena.php` merges the old package script (`pkg_kunena` `install.php`) and the component script
  (`administrator/components/com_kunena/install/install.script.php`, removed):
  - requirement checks (PHP, Joomla, database type/version, PHP extensions, downgrade protection);
  - the legacy file clean-ups of the component script;
  - installs the bundled library and plugins from the package (postflight), enables the plugins;
  - **upgrade from 5.2.x**: removes the legacy `pkg_kunena` and `kunena_media` (`file`) extension records and their
    manifests from `administrator/manifests/`, detaches the former children (`package_id = 0`), and removes the
    update-site records of `update.kunena.org` (they only describe the old package) - no files are deleted;
  - redirects to Kunena's installation wizard (`view=install`, database installation/migration) through the
    installer's `redirect_url` (administrator only; command line installs skip the redirect).
- The update server of the official Kunena 5.2 releases is no longer registered.
- Removed from the repository: the original package folder, `COPYRIGHT.php`. `LICENSE.txt` (GNU GPL v3) is kept at the
  root.
- New `.php-cs-fixer.dist.php`, `.gitattributes`, `.gitignore`.
- **Updates through Joomla's updater:** the manifest registers Kunena Fork's update server,
  `https://joomlaworks.github.io/kunena-fork/update.xml` (`docs/update.xml` and `docs/collection.xml`, published with
  GitHub Pages and left out of the installable ZIP). It offers the ZIP of `main` to Joomla 3.9 and every later Joomla 3.x
  release (`targetplatform` `3\.(9|[1-9][0-9])\.`, so Joomla 3.x UTD's future versions are covered), PHP 7.1 or newer.


### 2. Version and requirements

- Version **5.3.0**, release date **2026-11-12** in every manifest (component, library, plugins, bundled Kunena
  plugins, templates, labels/topic icon sets), in `KunenaForum::version()` and as a new entry of
  `install/kunena.install.upgrade.xml`. Copyright years updated to 2008 - 2026.
- **Joomla 3.x: 3.9 to 3.18** (Joomla 3.x UTD); the installer accepts Joomla 3.9.0 or newer (3.18.0 recommended) and
  still refuses Joomla 4 and later. The manifest declares `version="3.9"`.
- **PHP 7.1 to 8.5** (was 7.0.4 - 8.0). PHP 7.4 or newer is recommended.
- **Databases**: MySQL 5.5.3+ / MariaDB, **PostgreSQL 9.5+** and **SQLite 3.37+** (Joomla 3.x UTD's SQLite driver).
  SQL Server / Azure SQL are not supported.

### 3. PHP 7.1 - 8.5 compatibility

The code keeps PHP 7.1 syntax (no arrow functions, typed properties, `match`, `??=`, nullsafe operator, named
arguments, union/`mixed`/`static` types, trailing commas in calls). Every PHP file lints on PHP 7.1, 7.4, 8.0, 8.1,
8.2, 8.3, 8.4 and 8.5, and every class loads without deprecation notices against Joomla 3.x UTD on PHP 8.5.

- **PHP 8.2 dynamic properties**: `#[\AllowDynamicProperties]` on every class (372 classes; the attribute is a
  comment for PHP 7).
- **PHP 8.1 internal interfaces**: `#[\ReturnTypeWillChange]` on `KunenaCollection` (`ArrayAccess`, `Countable`,
  `IteratorAggregate`) and `KunenaTree` (`Iterator`).
- **PHP 8.1 `Serializable`**: `KunenaControllerBase` implements `__serialize()`/`__unserialize()`;
  `unserialize()` restricted to the expected classes.
- **PHP 8.1 `htmlspecialchars()`/`htmlentities()`/`html_entity_decode()` default flags**: 94 calls now pass
  `ENT_QUOTES | ENT_SUBSTITUTE` explicitly, so output is the same on every PHP version (and single quotes are always
  encoded).
- **PHP 8.1 `strftime()`** (admin trash lists) replaced with `date()`.
- **PHP 8.4 implicitly nullable parameters**: `Type $x = null` became `?Type $x = null` (96 parameters).
- **PHP 8.4 `E_STRICT`** no longer referenced (`KunenaError`).
- **PHP 8.5**: `case ...;` switch labels (`KunenaError`, lessc), `(boolean)` cast, `finfo_close()` (only called
  before PHP 8.1), `${var}` string interpolation (lessc, deprecated since 8.2).
- **PHP 8.0**: removed `track_errors` handling (`KunenaFactory`).
- Further PHP 8.x warnings found during the end-to-end tests fixed in route, BBCode, NBBC, layouts, category
  channels, menu widget, user, tables, message and controller code; `post()`'s "full reply" redirect read
  `$fields->catid` from an array.

### 4. Coding standard

- PHP CS Fixer (`@PSR12` + `nullable_type_declaration_for_default_null_value`) applied to all PHP files; configuration
  in `.php-cs-fixer.dist.php`. `php-cs-fixer fix --dry-run` reports no violations.
- Not changed on purpose: the indentation of the HTML parts of template files (outside PHP CS Fixer's scope) and PSR-1
  naming (Kunena's classes have no namespaces and some methods aren't camelCase; renaming them would break the public
  API used by third-party extensions).
- XML files: every element's start tag, with all its attributes, on a single line (858 tags in 38 files were split
  over several lines); the parsed content is unchanged.


### 5. Databases: PostgreSQL and SQLite

Kunena installs and runs on every database Joomla 3.x UTD supports. MySQL/MariaDB SQL is unchanged.

- New `KunenaDatabaseHelper` (`libraries/kunena/database/helper.php`): `isMysql()`, `isPostgresql()`, `isSqlite()`,
  `choose()` (per-database SQL), `insertIgnore()`, `upsert()` (`ON DUPLICATE KEY UPDATE` / `ON CONFLICT`),
  `fromUnixtime()`, `like()` (`ILIKE` on PostgreSQL), `getColumns()`, `isAutoIncrement()`, `quoteValue()` (MySQL's
  non-strict conversions of `''`, booleans and zero dates on PostgreSQL).
- Installer (`script.kunena.php`): accepts the `mysqlonsqlite`, `postgresql` and `pgsql` drivers, checks PostgreSQL
  9.5+ / SQLite 3.37+, finds the Kunena tables with `getTableList()`, skips the utf8mb4 conversion outside MySQL.
- Schema (`install/schema.php`): generates PostgreSQL DDL from `install/install.xml` (serial/bigserial, mapped types,
  indexes, MySQL's implicit defaults, ALTER support) and reads the schema back from PostgreSQL; the MySQL output is
  byte-identical to 5.2.14.
- Installation model (`install/model.php`): no `SHOW`, `INSERT ... SET` or `UPDATE ... LIMIT`; MySQL-only ALTERs
  skipped on PostgreSQL; sequences reset after the sample data; menu creation fixed for empty tables.
- Runtime SQL ported throughout the library, site and administrator models: multi-table `UPDATE`/`DELETE`,
  `ON DUPLICATE KEY`/`REPLACE`/`INSERT IGNORE`, `IF()`, `SUM(boolean)`, the `@ktime` session variable (now computed in
  PHP), `UNIX_TIMESTAMP()`, loose `GROUP BY`/`HAVING`, double-quoted literals, unquoted camelCase columns, comparisons
  of text with numbers; case-insensitive search and user filters on PostgreSQL; `KunenaTable` uses `RETURNING` for
  serial keys; Tools > Diagnostics fix/delete queries use `ctid` (PostgreSQL) / `rowid` (SQLite).
- Limitations: upgrades from old Kunena releases and FireBoard/JoomlaBoard migrations stay MySQL-only (new PostgreSQL
  and SQLite installations start at 5.3.0); the Community Builder integration plugin
  (`install/plugins`) was not ported; some administrator list filters stay case-sensitive on PostgreSQL (trash, attachments, smilies, ranks, logs).

### 6. CAPTCHA: Joomla's Global Configuration, and the bot bypass

**CAPTCHA-1 - Critical - CAPTCHA bypass: bots could post without solving any CAPTCHA.** Kunena's server-side check
(`components/com_kunena/controllers/topic.php` `post()`) took the *first enabled* captcha plugin and only validated
the answer when that plugin had reCAPTCHA-style `public_key`/`private_key` parameters; otherwise it silently skipped
validation. With IconCaptcha (Joomla 3.x UTD's default, keyless), Akismet (`api_key`), a disabled-but-selected plugin
or any non-reCAPTCHA plugin, every post was accepted without a CAPTCHA. The forms had the same reCAPTCHA-only logic
(with a `$catcha_privkey` typo), and the CrypsisB4 quick reply called a non-existent `$template->recaptcha()` (fatal
error whenever a CAPTCHA was due).

- New `KunenaCaptcha` (`libraries/kunena/captcha/captcha.php`) uses Joomla's `Captcha` API with **the plugin selected
  as "Default Captcha" in Joomla's Global Configuration**, so all CAPTCHA plugins work: reCAPTCHA (checkbox,
  invisible, v3), hCaptcha, Cloudflare Turnstile, IconCaptcha, Akismet, or third-party plugins.
- **Fails closed**: when Kunena's configuration requires a CAPTCHA for the user but no usable plugin is selected, the
  post is refused (with a message, and a warning in Kunena's log) instead of accepted.
- Used by the new topic, reply and quick reply forms of all templates (Crypsis, CrypsisB3, CrypsisB4) and by
  `post()`. The "Go to full reply" button no longer requires solving the CAPTCHA (nothing is saved).
- `KunenaUser::canDoCaptcha()` always returns a boolean. Who is challenged is unchanged: Kunena > Configuration >
  Security > "Display Captcha for" (nobody / registered users below "CAPTCHA Challenge for Users" posts / guests and
  registered users); moderators are never challenged.
- New language strings `COM_KUNENA_CAPTCHA_NOT_SELECTED`, `COM_KUNENA_RECAPTCHA` (heading used by CrypsisB4).

The CAPTCHA bypass was only half of the bot problem: **AUTH-S1** below (posting into any category regardless of
permissions) let bots inject content into categories where guests could not even post.

### 7. Security fixes

#### SQL injection

- **SQL-1 - Critical**: the topic, message, attachment, thank-you, topic-user, topic-read and category-user loaders
  called `intval($id)` without using the result, so raw ids reached `IN (...)`. Reachable by guests (with a session
  token) through the AJAX tasks `topic.setinline`/`topic.removeattachments` (JSON ids) and the bulk actions of
  `controllers/topics.php` (array keys): blind SQL injection. Ids are now cast and assigned.
- **SQL-2 - High**: second-order SQL injection through stored IP addresses (attacker-controlled through
  `X-Forwarded-For` when Joomla's "behind load balancer" option is on), executed when an administrator opened the
  user's IP list (`administrator/.../models/user.php`). IPs are quoted, ids cast.
- **SQL-3 - Low/Medium** (hardening): integer casts and `ASC`/`DESC` whitelists for `hold`, ordering, user ids,
  limits, `cid[]` and list sorting across the forum helpers, tables (`kunenausers`' `intval() < 1` check let
  `"5 OR 1=1"` through), administrator list models and the bundled Joomla/JomSocial access plugins; the rate helper's
  unquoted `escape()`.
- **SQL-4 - Low**: `LIKE` wildcards escaped in search, user finder and emoji lookup.

#### Access control and information disclosure

- **AUTH-S1 - Critical**: `post()` and `edit()` wrapped `isAuthorised()` (which returns a boolean and never throws) in
  `try/catch`, so **no permission check ran**: guests and banned users could post into any category (locked,
  read-protected, or not open to guests) and anyone could edit anyone's posts. Fixed with real checks.
- **AUTH-S2 - Critical**: the same pattern in `controllers/announcement.php`: anyone could create, edit, publish or
  delete announcements (and save/delete ran twice).
- **ACL-1 - Critical**: `user.save` let any global moderator change the name, username, e-mail, password and
  parameters of any Joomla account (e.g. managers): account takeover. Now requires the profile edit authorisation,
  `core.edit` on `com_users` for other users' account data, and Super User rights to change a Super User.
- **AUTH-A3 - Critical**: Kunena > Tools > Uninstall's "super user" re-authentication treated a failed login as
  success; any Kunena manager could uninstall Kunena and drop its tables. Now requires `core.admin` and checks the
  current user's own password.
- **ACL-2 - High**: the `Topic/Form/History` layout was callable through the AJAX controller without a read check:
  guests could read the last posts of any topic, including private categories.
- **AUTH-S3 - High**: guests could replace or delete any user's avatar.
- **AUTH-S4 - High**: `setinline`/`removeattachments` acted on any attachment id (inverted permission logic).
- **AUTH-S5 - High**: any reader could remove other users' thank-yous.
- **AUTH-A4 - High**: `view=install&task=uninstall` ran a full uninstall with only a token check; now requires the
  one-time flag set by Tools > Uninstall.
- **AUTH-A6 - High**: the administrator Plugins manager could publish/unpublish/trash any Joomla extension by id
  (e.g. disable two-factor authentication plugins); limited to Kunena plugins.
- **ACL-3 - Medium**: another user's uploaded-but-unposted attachment could be attached to one's own post.
- **ACL-4 - Medium**: other users' favorites, subscriptions and "involved" topic lists were visible to anyone.
- **ACL-5 - Medium**: the banned users list was visible to anyone (now moderators only).
- **ACL-6 - Medium**: the `[article]` BBCode disclosed unpublished, trashed or scheduled Joomla articles.
- **ACL-7 - Medium**: unapproved/deleted topics and posts of other categories leaked through category "channels"
  (allowed states were computed from the category in the URL); now evaluated per category.
- **ACL-8 - Medium**: the "exclude categories" mode matched categories the user cannot read.
- **AUTH-S6/S7/S8/S9/S10 - Medium**: anyone could reset poll votes; unsubscribe other users; the ban form's "delete all
  posts" ignored `moderator_permdelete`; the offline / registered-only checks for tasks were commented out; the edit
  form opened for messages the user may not edit.
- **AUTH-A1/A5/A7 - Medium**: Kunena template install/uninstall and the installation wizard need installer rights
  (`core.admin` on Kunena or `core.manage` on the Installer); deleting Joomla accounts from Kunena > Users needs
  `core.delete` on `com_users`.
- **ACL-9 - Low/Medium**: the user list could be sorted by hidden columns (IP, e-mail) to infer values, and showed
  e-mail addresses of users who hide them.
- **ACL-10/11 - Low**: rating (guests, unreadable topics, unbounded stars), topic templates of unreadable categories,
  scheduled/expired announcements readable by id, empty category lists matching every category, authorisation
  exceptions returned instead of thrown.
- **AUTH-S11/S12 - Low**: AJAX uploads while editing checked an undefined action (always failed) and classic uploads
  used the category from the request; guests could set a status, banned users could give karma.
- **Protected attachments**: with attachment protection on, inline PDF files and all attachments in feeds still got
  direct links into `media/kunena/attachments` (feeds even linked the folder). They are now always served through
  Kunena's access check; PDF files are shown inline only when the file really is a PDF.

#### Remote code execution, files, SSRF, CSRF

- **RCE-1 - Critical**: `KunenaUpload::upload()` checked the allowed extensions against PHP's temporary file name
  (`/tmp/phpXXXX`), so the check never rejected anything and the client's extension was used.
- **RCE-A1 - Critical**: the Kunena template manager (`applyless`/`saveless`/`applycss`/`savecss`, edit/choose)
  accepted any file name and template: a Kunena manager could write PHP anywhere in the site.
- **FILE-S1 - Critical**: the avatar gallery value was not validated: a registered user could set
  `users/../../../../configuration.php` and have it deleted by removing their avatar (site takeover through the
  installer). Only `gallery/<folder>/<image>` is accepted, and only files under `avatars/users/` are deleted.
- **RCE-2/RCE-3 - High**: fixed deny-list of dangerous extensions (PHP variants, `phar`, `phtml`, CGI/Perl/Python/ASP/
  JSP, `shtml`, `htaccess`, `ini`, HTML, XML, SVG, JS, SWF, executables, ...) checked on every part of the file name
  (double extensions like `x.php.jpg`) for attachments, avatars, smilies and ranks; dangerous types are dropped from
  Kunena's configured file types even if an administrator adds them (**RCE-A4**).
- **RCE-A2 - High**: template package installs used the client's file name in `tmp/` and left files behind.
- **RCE-A3 - High**: smilies and ranks uploads only accept single-extension GIF/PNG/JPEG/WebP images.
- **FILE-1 - High**: on any upload error, `KunenaUpload::cleanup()` deleted the file named in the request: arbitrary
  file deletion (e.g. `images/logo.png`) by anyone allowed to upload.
- **FILE-2 - High**: attachments were sent inline with their stored MIME type: an SVG could run scripts on the site's
  origin (stored XSS). Only raster images (and verified PDF files) are shown inline; everything is sent with
  `X-Content-Type-Options: nosniff` and a sandboxing `Content-Security-Policy`; `Content-Disposition` file names are
  sanitised.
- **SSRF-1 - High**: with "smart linking", every auto-linked URL in a post was fetched by the server
  (`file_get_contents()`, no host filter, no timeout, redirects followed): requests to localhost, the internal network
  or cloud metadata endpoints, and DoS. Now http(s) on ports 80/443 only, public IP addresses only, 3 s timeout, no
  redirects, 64 KB.
- **CSRF-S1 - High**: the AJAX avatar upload had no token check.
- **RCE-4/RCE-5 - Medium**: attachment file names reduced to a safe base name (dots in the name become `_`, e.g.
  `my.photo.jpg` is stored as `my_photo.jpg`); new `.htaccess` files in `media/kunena/{attachments,avatars,emoticons,
  ranks,category_images}` deny script execution (modelled on Joomla 3.x UTD's `images/.htaccess`).
- **FILE-3/FILE-4/FILE-S2/FILE-A2 - Low/Medium**: path validation for avatar versions, the `kunena_template` cookie
  (was used in `require_once`), the template switcher and template restore (now with a token).
- **SSRF-2/SSRF-3, CSRF-A2 - Low**: local `getimagesize()` for attachments; StopForumSpam requests built with
  `http_build_query()`; token checks for plugin resync and menu trashing.
- Caches unserialized with `allowed_classes => false` (access and template caches).

#### Cross-site scripting and redirects

- **XSS-1 - Critical**: the BBCode URL autolinker (`$index && 1` instead of `$index & 1`, plus capturing groups) left
  all text after the first automatically linked URL HTML-unencoded: `http://a.com <script>...</script>` was stored
  XSS by any poster, guests included.
- **XSS-2 - Critical**: SoundCloud auto-embeds put the raw URL into an iframe `src` (attribute injection).
- **XSS-3/XSS-4/XSS-5 - High**: Instagram embeds (unencoded, unanchored URL check), smart-link page titles inserted as
  HTML, NBBC's plain-text mode (`[url=javascript:...]`, used in the administrator log viewer).
- **XSS-11/XSS-13/XSS-14/XSS-15 - High**: profile website name in a `title` attribute (fired on every post of the
  user), poll title/options/end date in the topic edit form, the administrator log viewer (`</pre>` breakout) and
  signatures in administrator tooltips.
- **XSS-20 - High (DOM)**: topic subjects inserted as HTML in the move/merge form (moderators).
- **XSS-6..XSS-10, XSS-12, XSS-16..XSS-19, XSS-21..XSS-26 - Low/Medium**: parser state leaking between parses,
  `[tableau]`/`[map]`, `[spoiler]`/`[file]`/`[attachment]` short forms, tweet ids, BBCode layouts (URL class, image
  alt/title, Twitter/eBay data), user links, notification and report e-mails, search messages and highlighting,
  attachment names in the upload widget, moderator IP links, JSON-LD, and several layouts with trusted data.
- **REDIR-1 - Low**: `KunenaRoute::getReferrer()` accepted protocol-relative paths (`//evil.example`): open redirect.

#### Spam and abuse

- **SPAM-S1 - Medium**: guests could post with a registered user's name (or a case variant of the username).
- **SPAM-S2 - Medium**: flood protection skipped replies and compared `REMOTE_ADDR` with a differently stored IP, so
  it never matched; it now covers replies, uses Joomla's `IpHelper`, and also applies to registered users.

#### Known residual issues (not changed)

- `isSafeRemoteUrl()` resolves the host before PHP fetches the URL (DNS rebinding window).
- The AJAX upload chooses the image/file size limit from the client-declared MIME type (content is still verified).
- Guests uploading (when allowed) can fill the disk; `report()` has no rate limit.
- Kunena managers can still log out or ban Super Users from Kunena > Users.
- Tools > "Delete blocked users" deletes every blocked Joomla account, Super Users included.
- Bootstrap tooltips render their titles as HTML (user names are filtered by Joomla).
- Some finders count items before per-item access filtering (totals may include hidden items).

### 8. Frontend credits removed

- The "Powered by Kunena" footer (and the template credit line) is no longer added to frontend pages;
  `KunenaControllerApplicationDisplay::poweredBy()` is kept for compatibility and returns an empty string.
- The credits page (`view=credits`) redirects to the forum index.
- The System - Kunena plugin no longer injects CSS forcing the credits visible nor shows the "buy the powered by
  remover" notice.
- Frontend error messages no longer link to kunena.org (new string `COM_KUNENA_INTERNAL_ERROR_GENERIC`).
- No "Time to create page: X seconds" line in the frontend footer (every template): `KunenaLayoutWidgetFooter::getTime()`
  returns `null`, and the "Display Page Creation Time" option is gone from Kunena > Configuration.

### 9. Other changes

- **Ban form** (all frontend templates): the ban level defaults to the Joomla-level ban and "delete signature",
  "delete avatar", "delete profile information", "delete all posts" and "delete all posts permanently" are preselected,
  so banning a spammer is a single click. The "delete all posts permanently" checkbox had the same HTML id as "delete
  all posts" (its label toggled the wrong checkbox).
- Administrator trash lists escape IP addresses.
- Kunena > Configuration: the "e-mail recipient count" list passed a `null` id to Joomla's `select.integerlist`
  (PHP 8.1+ deprecation in Joomla's `select.php`); it now has a proper id.
- Installing from Joomla 3.x UTD's command line (`php cli/joomla.php extension:install`) works (the installer script
  no longer calls the router outside the administrator).
- **Links without a menu item lost their page:** Kunena redirects a page requested without an `Itemid` (Configuration >
  "SEF Redirect") to one, but rebuilt the address with only a few parameters, so a link to page 2 of a thread, to a
  post (`mesid`) or with its own page size landed on page 1. `KunenaRoute::withItemid()` now keeps the request's Kunena
  parameters (view, layout, mode, ids, `limitstart`, `limit`...) for the thread, category, profile and list pages.
- **Third-party integration plugins removed**: the Kunena plugins for AltaUserPoints, JomSocial ("Community"),
  EasyProfile, EasySocial, EasyBlog and UddeIM are no longer shipped. The installation wizard uninstalls them from
  existing sites (with their language files and JomSocial's `jomsocial_rule.xml`); Community Builder, Gravatar, Joomla,
  Kunena and Smart Search integrations stay.
- **Tools > Recount is fast on large forums.** Measured on 200,000 posts in 21,000 threads, with every counter reset
  first: MariaDB 2.8 s (the topic stage did not finish within 10 minutes before), MySQL 4.1 s (over 20 minutes for a
  single batch before), PostgreSQL 2.9 s; the results are the same as before.
  - `KunenaForumTopicHelper::recount()`: each AJAX batch of thread ids now limits the posts it reads to that batch
    (before, the derived tables aggregated the posts of the whole forum on every batch). The single statement which
    joined every post of a thread to every other one to find the first and last posts is now three: the counts, the
    first posts and the last posts. The first (last) post is found in one aggregation without a self-join
    (`MIN(time * 2^32 + id)`: the earliest time, then the lowest id), which no database can turn into a per-row plan.
  - `KunenaForumCategoryHelper::recount()`: one grouped query for the counts, one for the newest topic of each
    category, and an `UPDATE` only for the categories whose values changed (509 s on MariaDB before, 0.03 s now).
  - The orphaned-attachment clean-up runs once per recount instead of once per batch.

### 10. Tools > Empty Trash (JoomlaWorks Ltd.)

- New **Kunena > Tools > Empty Trash** page (also reachable from the Trash manager's toolbar): one button permanently
  deletes every trashed thread (with all its posts) and every trashed post of live threads, with their attachments
  (files included), polls, thank-yous, ratings and read/subscription records, then updates the thread, category, user,
  thank-you and poll statistics and removes attachments left without a post.
  - Runs in batches of about 10 seconds over AJAX with a progress bar, so large trashes don't time out.
  - Asks for a confirmation; only users allowed to configure Kunena (`core.admin` on Kunena; Super Users by default)
    can use it (checked on the page and on every request, with a form token).
- New command `php cli/joomla.php kunena:trash:purge` (Joomla 3.x UTD command line): the same purge, with `--dry-run`
  (counts what would be deleted), `--format=json`, and a confirmation when interactive; runs unattended with
  `--no-interaction` (cron). Provided by the new bundled **Console - Kunena** plugin (`plugins/console/kunena`,
  installed and enabled with the package, harmless on Joomla versions without this command line).
- Shared implementation: `KunenaForumTrash` (`libraries/kunena/forum/trash.php`).
- Fixes found along the way:
  - The Trash manager's "Purge" of threads left the attachment records and files of the purged posts behind, and
    purged posts kept attachments the administrator couldn't read; both now go through `KunenaForumTrash`.
  - `KunenaForumMessage::save()` *returned* an exception object (truthy) when a guest wasn't allowed to post, so
    callers treated the refused save as a success; it now fails properly.
  - `KunenaForumMessage::delete()` executed each of its queries twice; it also accepts `$recount = false` for bulk
    deletions.

### 11. Tools > Sample Data (JoomlaWorks Ltd.)

- New **Kunena > Tools > Sample Data** page: installs (and later removes) a complete fictional forum to style and test
  Kunena with - the "Greenfield Community", in English:
  - 3 sections and 10 categories: a sub-category, a locked "Announcements" category, a moderated "Bug Reports"
    category (new threads need approval) and a "Staff Room" visible to Managers and above only;
  - 20 users with generated identicon avatars, signatures, locations, websites, karma and rank titles from their post
    counts; 2 of them moderate categories. They are active (so the forum shows them like normal members), but nobody
    can sign in with them: their passwords are random and never stored or shown, and their `@example.invalid`
    e-mail addresses can't receive password reset e-mails;
  - a Kunena announcement;
  - 60 threads with 389 posts spread over the last six months: BBCode (bold, italics, colours, lists, quotes, code,
    spoilers, links), 4 polls with votes, sticky and locked threads, about 200 thank-yous, 16 generated image
    attachments, unapproved and trashed threads and posts, and a 46-post photo thread spanning several pages.
  - The data set is plain PHP data (`libraries/kunena/sampledata/data.php`), the generator is `KunenaSampledata`
    (`libraries/kunena/sampledata/sampledata.php`). Everything is created through Kunena's own API (all databases).
  - Runs in AJAX steps with a progress bar; only users allowed to configure Kunena can use it.
- **Removal deletes exactly what was installed**: every created item is recorded in a `#__kunena_sampledata` table
  (created on demand, dropped when everything is removed). Posts which other users added to sample threads are kept
  with their thread, and sample categories which still contain other threads are kept (both reported).
  Generated avatars (and their resized copies) and attachment files are deleted.
- Command line (Joomla 3.x UTD): `kunena:sampledata:install` and `kunena:sampledata:remove`, both with `--dry-run`
  and `--format=json`.
- Fixes found along the way:
  - **Category statistics could be multiplied**: `KunenaForumCategoryHelper::recount()` joined every topic sharing a
    category's newest `last_post_time` (unapproved, trashed and moved ones included), so two topics with the same
    last post time doubled the category's thread and post counts. It now picks exactly one live topic.
  - `KunenaForumTopicUserHelper::recount()` failed on SQLite when called for all topics (MySQL-only `DELETE alias
    FROM` syntax); it now uses a plain `DELETE` valid on every database.
  - `KunenaRoute` failed on the command line (no menus); it now routes there like in the administrator.
  - PHP 8.1/8.5 deprecations: announcement cache initialised as `false` and used as an array; `null` used as an array
    key in `KunenaAccess::isModerator()` and `KunenaUser::getType()`.

### 12. The separately distributed modules and plugins are part of the package

kunena.org distributed six extensions separately (version 5.2.2): the **Kunena Latest**, **Kunena Login**, **Kunena
Search** and **Kunena Stats** modules, the **Content - Kunena Discuss** plugin (discussions of Joomla articles in the
forum) and the **Search - Kunena** plugin (forum posts in Joomla's search). They are now included, at version 5.3.0:

- Installed (or updated) with the package by `script.kunena.php`. The plugins are enabled when they are installed for
  the first time; on sites which already have them, their state is kept (a disabled Kunena Discuss stays disabled).
  Modules are installed, not published: their instances are placed by the site owner. The outdated
  `search/kunenasearch` plugin is removed. Kunena's uninstaller removes them too.
- Their own installer scripts (Joomla 2.5 clean-ups) were dropped, manifests updated (version 5.3.0, Joomla 3.9+),
  language entries for files which don't exist removed (they made the installation fail on sites with those
  languages), unused files removed.
- Same treatment as the rest of the code base: PHP 7.1 - 8.5 (`#[\AllowDynamicProperties]`, explicit flags, nullable
  types), PSR-12, security review and PostgreSQL/SQLite portability - see "Security fixes in the integrated
  extensions" below.
- **Kunena Discuss** used its own copy of the old reCAPTCHA-only check (fail-open with any other CAPTCHA); it now uses
  `KunenaCaptcha` (Joomla's Global Configuration CAPTCHA, fails closed).

#### Security fixes in the integrated extensions

- **EXT-1 - Critical - Kunena Discuss**: the quick reply on article pages created the forum thread *before* checking
  the CAPTCHA and the message, so bots left new threads behind for any article even when the CAPTCHA failed. Nothing
  is written now until every check has passed (form token, permission to post and to view the article, guest e-mail,
  non-empty message, link limit, CAPTCHA, flood control).
- **EXT-2 - High - Kunena Discuss**: the quick reply skipped the forum's anti-spam rules (flood control, holding guest
  and new users' posts for approval, IP tracking setting, links/images of new users, maximum number of links); it now
  applies them.
- **EXT-3 - High - Kunena Discuss**: the posted name and e-mail were reflected unescaped into the form (cross-site POST
  to reflected XSS).
- **EXT-4/EXT-5 - High/Medium - Kunena Discuss**: discussions were shown, created and replied to for articles the
  visitor may not see (unauthorised links in blogs, scheduled or expired articles), and snippets of restricted articles
  were copied into public forum threads. The article and its category must now be published and viewable; snippets
  only come from articles guests can read.
- **EXT-6/EXT-7/EXT-9/EXT-14/EXT-15 - Medium/Low - Kunena Discuss**: threads could be created in sections or
  unpublished categories, or owned by the visiting user; topic subjects printed unescaped (stored XSS); redirects built
  from the Host header (open redirect); debug output unescaped; a race deleted existing article/thread references.
- **EXT-8 - Medium - Kunena Latest**: guest names printed unescaped (stored XSS).
- **EXT-10 - Medium - Kunena Login**: the module's cache served one guest's form token to every guest (broken login
  and logout); caching is off for it, return URLs must be internal and are escaped.
- **EXT-11/EXT-12 - Medium/Low - Search - Kunena**: confidential text in results relied on a wrong argument (PHP 8
  warnings, snippets not shortened); `LIKE` wildcards not escaped, unlimited number of words, unescaped titles.
- **EXT-13 - Low - Kunena Stats**: statistics shown to guests when Kunena hides them from guests.
- **EXT-16/EXT-17 - Low - Kunena Search/Latest/Stats**: settings printed into JavaScript/HTML unescaped; title
  shortening could cut HTML entities.
- Kunena Discuss creates its table on MySQL/MariaDB, PostgreSQL and SQLite; all its queries and the search plugin's
  are portable (case-insensitive search on PostgreSQL).
- `KunenaBbcode::DoConfidential()` read the parent post's author when there was no parent (PHP 8 warning).

### 13. PostgreSQL fixes found by the new end-to-end scenarios

- **New users couldn't be created on PostgreSQL** while Kunena was installed: Joomla's PostgreSQL driver reports
  column defaults as SQL expressions (`'0001-01-01'::date`, `NULL::text`, ...), which `KunenaTable::reset()` stored
  verbatim, so creating the Kunena profile of a new user failed (and with it the user's registration). Defaults are
  now normalised (`KunenaTable::normaliseDefault()`); MySQL and SQLite are unaffected.
- **"Thank you" didn't work on PostgreSQL**, nor creating smilies and ranks in the administrator: they used MySQL's
  `INSERT ... SET`; now standard inserts.
- `TableKunenaUsers::check()` compared the status as a string on PHP 8 (an empty status was "below 0").
- PHP 8.1+ deprecations: `KunenaForum::display()` and `KunenaForum::isCompatible()` passed `null` to `preg_replace()`
  (e.g. when Kunena Discuss renders a thread); the System Report passed `null` to `version_compare()`.

### 14. Baldrick AI, the forum's AI first responder (JoomlaWorks Ltd.)

New plugin **Kunena - Baldrick AI** (`plugins/kunena/baldrick`, logic in `libraries/kunena/baldrick`): replies to
forum questions nobody has answered, using Anthropic's Messages API or any OpenAI-compatible Chat Completions API
(OpenAI, Qwen/DashScope, OpenRouter, DeepSeek, Ollama, vLLM...; Anthropic-compatible endpoints such as Qwen's work too).

- **When it replies**: new threads, the thread starter's replies to Baldrick (a conversation: the details it asked
  for, a further question) and replies to threads that were silent for more than N days (default 7), as soon as the
  post becomes visible (posted in an unmoderated category, or approved). Never when the last post comes from a Super User
  or a Kunena administrator/moderator (checked when the thread is queued, when it is processed and again just before
  posting), never to its own posts; at most 3 replies per thread and 120 per 24 hours (settings), category
  include/exclude lists, public categories only by default, minimum post length.
- **Two steps**:
  1. *Triage* with a small, cheap model: rates the latest post (answerable, too broad, needs details, not a question,
     spam), restates the question, writes search queries and detects the language (JSON; structured output on
     Anthropic's API, JSON mode on OpenAI-compatible APIs, tolerant parsing everywhere).
  2. Then: *answerable* -> the related threads are searched in Kunena's own tables (published posts of guest-readable
     categories only, never Baldrick's own posts, never the thread itself; see "Large forums" below) and a more
     capable model writes the answer
     from the thread and up to N related threads (default 5), citing them with relative links; *too broad* -> a reply
     from a configurable pool of canned replies (placeholders `{name}`, `{search_url}`, `{forum_url}`, `{topic_url}`;
     not repeated in a thread), no second request; *needs details* -> a short request for the missing details, written
     by the triage model; *not a question* -> nothing; *spam* -> see below. A refusal or an unusable answer falls back
     to a canned reply.
- **Replies** are published at once (also in moderated categories) as a dedicated user (chosen in the settings, or a
  "Baldrick" user created automatically: random password, `example.invalid` address), in the poster's language plus
  an English translation (option, on by default), in forum BBCode only (other tags, HTML, images and embeds removed;
  links to the site made relative), signed through the forum signature of its account ("Replied by Baldrick AI, my
  lord, using a cunning model (...and do remember that AI can make mistakes, so double check responses)" for an
  account Baldrick creates; edited in its forum profile).
  Subscribers are notified as for any reply. Kunena's flood and duplicate-post checks (meant for forms) and the CAPTCHA
  don't apply to Baldrick's posts (`KunenaBaldrickMessage`).
- **Spam**: when the triage model is confident (>= 0.8), the post is unpublished (awaiting moderation; the whole thread
  when it is the first post). Posts of the staff and of users with at least N published posts (default 10) are only
  reported; also configurable: report only, ignore.
- **Notifications**: plain-text e-mails through Joomla's mail settings to chosen Super Users/administrators/moderators
  and free-text addresses, for every reply and spam action: thread, link, verdict, models, tokens and estimated cost,
  then the first post and the last 5 posts, newest first.
- **Connections**: one per step (the answer step can share the triage connection): API type, base URL (empty: the
  official API), key, model, prices. *Fetch models* lists the provider's models from the values in the form (saved or
  not) in Joomla's searchable list (Chosen; Joomla 3.x UTD's vanilla JS version, jQuery Chosen on older Joomla 3), for
  lists of hundreds of models (OpenRouter): kept on the server for an hour per endpoint and key (*Refresh* asks again),
  models which can't chat (embeddings, speech, images...) hidden unless *Show all models* is ticked, context size and
  prices shown, and the prices filled in when the provider publishes them (OpenRouter). Anthropic-compatible endpoints
  without a model list of their own (Xiaomi MiMo, Qwen: HTTP 404) are listed from the provider's OpenAI-compatible list
  next to them (`.../v1/models`, `.../compatible-mode/v1/models`). *Test* sends a tiny request; models can always be
  typed. Optional request features (structured
  output, effort, prompt caching of the system prompt, server-side fallbacks on refusals for Opus/Sonnet 5.x) are only
  sent to Anthropic's own API, and any feature an endpoint rejects with HTTP 400 is dropped and the request retried
  (also `max_tokens`/`max_completion_tokens` and Qwen's `enable_thinking`).
- **Slow and failing providers**: a short connection timeout (10 s) and response timeouts per step (triage 30 s,
  answer 90 s: answers aren't streamed, so this covers writing the whole reply). Network errors, timeouts, rate limits
  (429), overload (529) and server errors are retried twice at once, then the thread is retried later (after 2 and 6
  minutes, or when the provider's `retry-after` says; 3 attempts). **Errors of the account or the configuration** (HTTP
  401/402/403/404, exhausted credits or quota such as OpenAI's `insufficient_quota` or Anthropic's "credit balance is
  too low") **pause Baldrick**: queued threads keep waiting (the attempt isn't counted), every Kunena administrator page
  shows the error, the notification recipients get one e-mail, and the provider is tried again every 15 minutes
  (setting); the first successful request, or saving the settings, ends the pause. Threads which waited longer than 24
  hours (setting, e.g. after an outage) are skipped instead of answered days later.
- Limits and timeouts are in an *Advanced* section at the bottom of the settings.
- **Large forums**: related threads are found with the database's full-text index when it exists - MySQL/MariaDB
  `FULLTEXT` (InnoDB: MySQL 5.6+/MariaDB 10.0.5+), PostgreSQL GIN on `to_tsvector('simple', message)` (built
  concurrently) - created from Kunena > Baldrick AI ("Create the search index") or with `php cli/joomla.php
  kunena:baldrick:index`; not automatically, as building it delays posting on MySQL (about 6 seconds per 100,000
  posts). Without it (SQLite, or before it is created) a single `LIKE` scan of the latest 50,000 posts (setting) is
  used. Measured on 100,000 posts (55 MB of text): 5.5 s per question before; 2.7 s with the plain search's window;
  0.36 s (MariaDB 11.4) and 0.11 s (PostgreSQL 16) with the index. Full-text candidates must contain at least two of
  the question's words in one post and are ranked by them (PostgreSQL's `ts_rank()` was 20 times slower). Each answer's
  log row shows the search used and its time.
- **No waiting**: posting only adds the thread to a queue (`#__kunena_baldrick_queue`); it is processed after the page
  has been sent (PHP-FPM) or by a background request to the site itself (`com_ajax`, HMAC key), or from cron with
  `php cli/joomla.php kunena:baldrick:process` (option "Command line only"). Due jobs are picked up on later forum page
  views; parallel workers can't process the same thread. With PHP-FPM the visitor's session is released (not closed)
  before the response is finished, so the processing can still read it once the headers are out. Posts created by
  code in bulk (Tools > Sample Data) are ignored. Dry run is checked again when a thread is processed (threads queued
  before it was switched on). Unexpected errors are retried like temporary ones; failed threads can be retried or
  discarded from Kunena > Baldrick AI.
- **Kunena > Baldrick AI**: status, totals of the last 36 months (cost, requests, input/output/cache tokens, answers,
  canned replies, requests for details, spam, skipped, errors), totals per month (36 months kept,
  `#__kunena_baldrick_usage`) and per model, the activity log (`#__kunena_baldrick_log`, kept 90 days by default) and
  "Process the queue now". Costs are estimates: the provider's figure where reported (OpenRouter), otherwise tokens x
  configured prices (list prices of known Claude models when empty).
- **Installation**: installed with the package and enabled on first installation; it does nothing until an API key and
  the models are set, and while it has no key every Kunena administrator page says so at the top (with a link to the
  settings). The tables are part of Kunena's schema (created by the wizard on install and upgrade, all databases).
  Uninstalling Kunena removes the plugin. *Dry run* setting: only new threads started by administrators/moderators are
  answered and nothing is unpublished.
- **Privacy**: the text of the handled threads and of related public threads is sent to the configured provider; the
  plugin's description says so.
- All settings in one tab, in sections with headers (Joomla 3.x UTD's spacer field).
- Raw HTTP through Joomla's HTTP client (proxy settings apply), no SDK: the vendors' PHP SDKs need Composer and newer PHP
  versions than Kunena supports, and one client has to talk to any compatible endpoint.
- Also fixed: `KunenaAccess::authoriseActions()`/`loadSubscribers()` used a missing category's `null` access type as
  an array key (PHP 8.5 deprecation, e.g. on a request with `catid=0`).
- A background run which stopped without finishing (a PHP fatal error, or the server's time or memory limit) left its
  thread "being processed": every 10 minutes the next run claimed it again, and nothing reached the activity log. PHP
  errors are now caught like exceptions (with their file and line), fatal errors (time and memory limits) are recorded
  by a shutdown function and the thread retried, a run which finds an abandoned thread logs it, and the thread fails
  after three such attempts. `kunena:baldrick:process --unlock` takes such a thread over at once, with a fresh count of
  attempts.
- A thread waiting in the queue is answered from its newest post: a reply made while the job waits moves the job on
  to it, and when the queued post is no longer visible (deleted, unapproved, replaced) the newest post is answered if
  it passes the rules (before, the thread was skipped with "The post is no longer visible").
- The background processing after the response (PHP-FPM, and now LiteSpeed's `litespeed_finish_request()`) ignores
  the visitor's disconnection and sets its time limit before the response ends, not after.
- Posts by deleted accounts (Kunena keeps their user ids) no longer cause Joomla's "Unable to load user with ID"
  warning while Baldrick reads a thread: `KunenaBaldrick::userExists()` checks the accounts without loading them.
- The signature is no longer written into each reply: the plugin's "Signature" setting is gone, and the account's forum
  signature (shown under every post, so changes apply to old replies too) takes its place. Once, the setting's text
  moves to the account's signature (if that is empty) and is removed from the replies already posted.
- The outcome of the notification e-mail is part of the activity log entry ("E-mail sent to 2.", "No e-mail: no
  recipients are set...", or the mailer's error, e.g. sending disabled in the Global Configuration): failures were
  only visible in Kunena's debug mode.
- Notifications: "Notify about" chooses every reply, spam and errors (default) or spam and errors only. Errors are always
  notified: a thread Baldrick gave up on (attempts used up, a run that kept stopping, a fatal error) sends "Baldrick
  could not answer" with the error, besides the existing e-mail when Baldrick pauses itself (invalid key, no credits).
- `kunena:baldrick:check <post>` tells whether Baldrick would answer a post and which rule stops it, and with
  `--queue` queues a post which passes the rules now (Baldrick decides only when a post becomes visible).


### 15. JavaScript on administrator templates without jQuery or MooTools (Joomla 3.x UTD's Prothero)

- **The installation wizard stopped with "jQuery is not defined"** on administrator templates which don't load jQuery
  by themselves (Joomla 3.x UTD's Prothero; Isis does): the wizard's page and every Kunena administrator page now
  request it the Joomla way (`HTMLHelper::_('jquery.framework')`). Bootstrap's behaviours (tabs, modals) are left to
  the template - Isis loads Bootstrap's script, Joomla 3.x UTD's `global.js` implements them; loading Bootstrap's script
  on top would make both handle every click.
- **MooTools code** (not available on Joomla 3.x UTD) rewritten in plain JavaScript: Tools > Report and the Logs
  ("copy" buttons), the access type switch of the category editor (`KunenaAccess`), `kunenagrid.behavior`, and the
  inline `document.id()` / `document.getElements()` handlers of the administrator lists ("Clear" filter buttons of
  Attachments, Categories, Logs, Plugins, Ranks, Smilies, Statistics, Users and Trash; the Trash search reset; the
  category batch and the users' moderator/subscription dialogs).
- Template crypsisb4: the quick reply box never opened (`$('#' . boxToOpen)`, PHP string concatenation in JavaScript).
- Templates manager: kunena.org's list of premium templates is no longer fetched; the page contacted kunena.org on
  every visit (no timeout; an error message when unreachable) to advertise the upstream project.
- Checked in a browser (see `tests/e2e/jscheck.js`): the wizard completes, no JavaScript errors on any administrator
  page or the main frontend pages, the tabs of every tabbed page switch, modals open.

- Dead JavaScript removed (JS-MIGRATION.md, step 1): the WysiBB editor (never selectable: its option was commented out;
  a quick reply with a leftover `editor = 1` setting now shows the plain textarea like every other), its stylesheet,
  layouts, settings and strings; `jquery.fileupload-ui.js` and CKEditor's `build-config.js` (never loaded); the unused
  copy of `jquery.caret.js` in CrypsisB4; a status-text handler whose selector matched nothing. The template manager no
  longer generates the WysiBB button list. The installer deletes these files on upgraded sites (one file at a time).

### 16. Crypsis with any site template; administrator dashboard

- **The forum looked unstyled with site templates not built on Bootstrap 2** (e.g. Joomla 3.x UTD's Hammond: the menu
  as a bulleted list, no grid, buttons or tables): Crypsis relies on Bootstrap 2.3's CSS, and its "Load Bootstrap"
  option was off by default and pointed to `media/jui/css`, which Joomla 3.x UTD doesn't have. The option now loads
  `media/kunena/core/css/bootstrap.kunena.css`: Bootstrap 2.3.2's CSS (as Joomla 3 ships it) scoped to `#kunena`, so it
  styles the forum only and never the site template (no change to the page's body, containers, fonts, forms or
  modules); tooltips, popovers and modal backdrops stay global as Bootstrap attaches them to the page. On by default
  (sites with saved Crypsis settings keep theirs). Built by `tests/tools/scope-bootstrap.php`. IcoMoon is loaded from
  `media/jui` or, on Joomla 3.x UTD, `media/system`.
- Administrator dashboard: no more update check against kunena.org, PayPal donation form (it loaded a tracking pixel
  from paypal.com on every visit) or kunena.org "More info" link; author "Kunena Team, JoomlaWorks Ltd. & Contributors",
  copyright "(c) 2008 - 2026 Kunena Team" and "(c) 2026 JoomlaWorks Ltd. & Contributors" (two lines, aligned). The footer
  of the other Kunena administrator pages shows the name and version only ("Kunena 5.3.0").

### 17. Blackadder, the new default frontend template (JoomlaWorks Ltd.)

A modern, framework-free template (`components/com_kunena/template/blackadder/`), the default for new installations.
Sites upgrading keep the template they use: Kunena < 5.3 saves its configuration only when an administrator does, so the
upgrade records Crypsis for a site that never saved it (it was running on the old default). Crypsis stays available; CrypsisB3 and CrypsisB4 are kept for backwards
compatibility and are no longer updated. Blackadder and Crypsis can't be uninstalled from Kunena > Templates.

- **Look:** monochrome, Shadcn-like (the style of Joomla 3.x UTD's Prothero). The forum takes the site template's fonts
  and colours: no background of its own, the page's text colour, and greys, borders and fills mixed from them; dark
  pages are detected (`kb--dark`) and followed when the template switches scheme (`data-theme` as in Rookwood,
  `data-scheme` as in Finch, "auto" with the device). Checked on Hammond, Finch and Rookwood in their light and dark
  schemes. CSS variables override everything (`--kunena-background`, `--kunena-foreground`, `--kunena-muted`,
  `--kunena-muted-foreground`, `--kunena-border`, `--kunena-primary`, `--kunena-primary-foreground`, `--kunena-ring`,
  `--kunena-destructive`, `--kunena-radius`). Every category row, thread row and thread carries
  `kb-cat--<category alias>`, so sites can colour categories (`.kb-cat--announcements {--kb-cat:#b45309;}`).
- **One stylesheet and one script:** `assets/css/blackadder.css` (Grid/Flex, everything scoped to `.kb` and prefixed
  `kb-`, no Bootstrap; formatted with Joomla 3.x UTD's `docs/format/css.py`; the mention and emoji popup's styles are
  included) and `assets/js/blackadder.js` (plain JavaScript). The only other Kunena files a page loads are the
  caret/atwho jQuery plugins for mentions and emojis. An optional `assets/css/custom.css` created by the site owner is
  loaded after the stylesheet. The stylesheet, the script, the icon sprite and `custom.css` carry the time of their last
  change (`?t=YYYYMMDD_HHii`, UTC, as Joomla 3.x UTD's templates do), so browsers fetch each again only when it changes. Icons are Bootstrap Icons in one SVG sprite (`assets/images/icons.svg`); `KunenaIcons`
  returns them when the template has an icon set of its own (`svgIcon()`, now also for the RSS, image and file icons).
- **Header:** the forum's name; a search field on its own line; a menu bar with the account (members) or a login panel
  that opens over the content without moving it (larger fields, remember me, the reset/remind/register links); the
  breadcrumb; the page's title. Footer: statistics, who's online and the RSS link, centred.
- **Forum pages:** the category index (sections, categories with description, subcategories, moderators, counts and
  the latest thread); a category (title, subcategories, toolbar with New Topic, Mark Read and Subscribe, the threads);
  thread lists (no icon on the left; title, state, category, starter and pages; posters, replies, views, last
  activity; moderators' bulk actions); post lists as a feed with an excerpt of each post; a thread (Reply, Subscribe,
  Favourite and one Moderate menu; posts with avatar or initials, role, time and permalink; thanks; copy link; the
  other actions in a "more" menu; one reply box at the bottom, where a post's Reply points; Baldrick AI's posts framed,
  their signature 20% fainter); pagination at the bottom, centred.
- **Posting form** (new topic, reply, edit): one card with the category (when it can be chosen), the guest's name and
  e-mail, the subject, a BBCode editor (toolbar, Write/Preview through Kunena's preview endpoint, mentions and
  emojis), attachments uploaded at once through Kunena's upload task (inserted into the text, which also marks them
  inline; existing ones with a "Keep" box when editing), an optional poll (shown only for categories with polls), the
  edit reason, anonymous posting and subscription; the topic history collapsed below. Text typed in a thread's reply box
  is carried over by "Open the full editor". It works without JavaScript too. Crypsis's editor settings (editor choice,
  one switch per toolbar button) aren't offered for Blackadder; the template manager saves templates without them.
- **Polls:** a card above the posts, the results (bars, voters) in a collapsible section; "Reset votes" asks first.
- **Images and attachments:** images (attachments and `[img]`) open in a lightbox (a `<dialog>`, Kunena's lightbox
  option, on by default) which steps through the post's images with the arrow keys or buttons, instead of fancybox;
  files show their name and size; audio and video use the browser's players; attachments placed in the text are not
  listed again under the post. Quotes, spoilers (`<details>`), hidden and confidential text have their own styles
  (quote authors escaped).
- **Members:** profiles (avatar, role, online status, actions, numbers, details, social links, about me, signature;
  posts, subscriptions, favourites, thanks, attachments and the moderators' ban history / new ban as tabs), profile
  editing (account, profile, avatar upload or gallery, settings as tabs of one form), the member list (search, sort,
  cards).
- **Other pages:** search (keywords and author up front, the other options collapsed; results as posts), statistics
  (totals as tiles, top lists with bars), announcements (list, page, add/edit with date-time fields), report to
  moderator, moderate (move, merge, split; the destination category's threads loaded as you choose), the custom page,
  the "members only" notice (its "Log in" opens the header's login panel) and the offline page. Native date fields
  replace Bootstrap's datepicker and Joomla's calendar widget.
- **Moderators:** "Awaiting approval" first in the forum's menu bar and in the account menu, with the number of posts
  waiting in the categories they may approve (also as a badge on the account button; shown only when there are some),
  leading to the list of all of them across categories, with bulk approve, delete and move.
- **Markup that site templates leave alone:** no `header`, `footer`, `article`, `section`, `nav`, `aside`, `figure` or
  `cite` elements, which site templates often style globally; `div`s with ARIA roles (`article`, `navigation`, `region`)
  instead. The elements kept for what they do (`details`/`summary`, `blockquote`, `dl`) get resets inside the forum.
- Fixed while building it:
  - the moderation queues (posts or topics awaiting approval, deleted ones) applied the reading lists' filters: the
    "Recent" category selection of the menu item or configuration and the default time window (e.g. since the last visit),
    so pending posts in other categories or older than that were missing. Without a category they now list every
    category the moderator may act on, and all of time unless the moderator picks a period;
  - a template without a `pages/` folder of its own made Kunena run the forum's home page ("Forum" menu item showing
    another menu item) through the legacy controller, which called itself until PHP ran out of memory; Blackadder
    declares itself an HMVC template;
  - announcements stored their dates exactly as typed: dates are now saved in SQL format, an empty end date as the
    database's null date (what Kunena's queries compare with) and an empty start date as now; the sample data's
    announcement had an end date (`1000-01-01`) that hid it on every database;
  - PHP 8 messages: the custom page read a configuration setting that doesn't exist (`custom_id`), the avatar upload
    and the BBCode parser passed `null` to `preg_match()`, the posting form measured a missing editor setting.

### 18. Search engines and social networks (JoomlaWorks Ltd.)

Kunena 5.2's thread page wrote a `DiscussionForumPosting` block and Open Graph / Twitter tags that Google's rules
reject or ignore: no text and no replies, the thread's *last* poster as its author, an invalid `interactionType`, an
`aggregateRating` (not supported for forum posts), a relative `discussionUrl`, a 1970 `dateModified` for unedited
posts, the same data on every page of a thread, `og:url` with whatever query string the visitor used, share images
built from the request's host name, `twitter:image` with the wrong attribute and the author's login name and gender as
`profile:` tags. All of it is replaced by one class, `KunenaSeo` (`libraries/kunena/seo/`), which the thread, profile,
category, index and user list controllers call from `prepareDocument()`:

- **Threads:** `DiscussionForumPosting` with the thread starter as author (name and profile URL), the first post's full
  text (`KunenaHtmlParser::plainText()`: no hidden, confidential or quoted parts), its first attached image, dates,
  `isPartOf` (the category), `commentCount` and `interactionStatistic` (`CommentAction` for the replies, `ViewAction`
  for the views), and the replies shown on the page as `Comment` (author, date, text, image, URL with the post's
  anchor). On the later pages of a thread, `url` stays the first page's, as Google asks. Baldrick AI's posts carry
  `digitalSourceType` = `TrainedAlgorithmicMediaDigitalSource` (content written by an AI model).
- **Profiles:** `ProfilePage` with the member (display name, profile URL, uploaded avatar, number of posts as
  `agentInteractionStatistic`, join date), only when guests may see profiles.
- **Only what guests can read:** threads of categories guests can't read get no structured data
  (`KunenaForumCategoryHelper::getGuestReadable()`, moved there from Baldrick AI, which uses it for its retrieval).
- **Open Graph and X cards** on every forum page: type, title (without the site's name), description, canonical URL
  (the page's own on later pages), site name, locale, image (the thread's first attached image, the member's avatar or
  the default share image; a large image card when there is one), `article:` dates and section for threads, and the
  site's X account.
- **Joomla 3.x UTD:** its core plugin "System - Structured Data & Social Tags" writes these tags for every page and
  skips a family another extension already wrote. Kunena no longer competes with it: through its
  `onStructuredDataBeforeRender` event (handled by the System - Kunena plugin), Kunena's values replace the plugin's
  generic ones on the forum pages and its nodes join the plugin's graph (publisher, website, breadcrumbs): one
  JSON-LD graph and one set of tags per page. Where the plugin is missing or disabled (Joomla 3.9/3.10), Kunena writes
  its own tags and a JSON-LD block of its own (a custom tag: Joomla merges script declarations of one type into a
  single element, which would join several JSON documents into invalid JSON).
- **Settings** in Kunena > Configuration > SEO (a new tab, with the existing SEO settings): structured data on/off,
  Open Graph and X tags on/off (for sites where an SEO extension writes them), default share image, X account.

### 19. Configuration: Development tab

- **Kunena > Configuration > Development**, the last tab, for development and test sites: Kunena's debug mode (moved
  from Basics) and **"Don't send e-mails"**: Kunena sends no e-mails at all (subscriptions, reports, mentions:
  `KunenaEmail::send()`; Baldrick AI's notifications), and writes a line to Kunena's log for each one instead; Baldrick
  AI's activity log says why no notification went out. Joomla's own e-mails (registration, password reset) are not
  affected.

### 20. Test tooling (`tests/`, not part of the package)

- `tests/tools/classload.sh <joomla-3.x checkout>`: loads every Kunena class under Joomla's class loader and reports
  PHP messages, exceptions and fatal errors raised by Kunena code.
- `tests/e2e/`: end-to-end tests on real Joomla 3.x UTD sites (PHP built-in server, databases in Docker): the
  database matrix (`matrix.sh`), CAPTCHA and credits scenarios (`captcha.sh`, with a test-only CAPTCHA plugin),
  the 5.2.14 upgrade (`upgrade.sh`), all of them (`final.sh`). See AGENTS.md, section 5.
- `tests/e2e/jscheck.js`: the JavaScript check in headless Chrome (Playwright): installation wizard, every
  administrator page, tabs, modals, the lists' "Clear" buttons, frontend pages; fails on any JavaScript error.
- Baldrick AI is tested against a mock provider (`tests/e2e/baldrick-mock.php`: Anthropic- and OpenAI-compatible,
  deterministic verdicts and answers, request log) with e-mails captured by a fake sendmail; no real API is contacted.

### Test results (5.3.0)

- PHP lint: 0 errors on PHP 7.1, 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 and 8.5. PHPCompatibility (7.1-8.5): only known false
  positives (version-guarded calls). PHP CS Fixer (@PSR12): 0 files to fix.
- Class-load check against Joomla 3.x UTD 3.18 on PHP 8.5: 363 class files, 0 messages.
- End-to-end on Joomla 3.x UTD 3.18, PHP 8.5, installing the GitHub-style ZIP:

| Scenario set | SQLite 3.45 | PostgreSQL 16 (`postgresql`) | PostgreSQL 16 (`pgsql`, PDO) | MariaDB 11.4 | MySQL 8.4 |
|---|---|---|---|---|---|
| Install, wizard, administrator, frontend, posting, guests, search, profiles, lists, moderation, Empty Trash and Sample Data (admin + CLI), integrated modules and plugins, structured data and sharing tags, Baldrick AI against a mock provider, Tools, uninstall; Blackadder as the default template (136) | pass | pass | pass | pass | pass |
| Reinstall over existing tables, posting, structured data, purge (20) | pass | pass | pass | pass | pass |
| Failed SQL queries / PHP messages from Kunena code | 0 / 0 | 0 / 0 | 0 / 0 | 0 / 0 | 0 / 0 |

  CAPTCHA and credits (SQLite): IconCaptcha rendered and enforced, fail-closed without a CAPTCHA plugin, wrong/right
  answers through Joomla's Captcha API, administrators not challenged, guests post when disabled, no credits: 10/10.
  Upgrade of a Kunena 5.2.14 package installation with two of the separately distributed 5.2.2 extensions (Kunena
  Discuss disabled, Latest module) (MariaDB): legacy records and manifests removed, all 13 legacy-named extensions at 5.3.0,
  Kunena Discuss still disabled, the missing Search - Kunena plugin installed and enabled, Baldrick AI installed and
  enabled with its 3 tables, existing content kept, the template kept (Crypsis: 5.2 had never saved its configuration), the
  removed dead files deleted, 39/39 scenarios, no PHP messages from Kunena code.
  Blackadder was checked page by page in Chrome (headless): 34 frontend pages as administrator and as guest, desktop and
  390 px wide (only its own CSS/JS, no Bootstrap markup, no untranslated strings, no overflow, no JavaScript errors), on
  Joomla 3.x UTD's Hammond, Finch and Rookwood in their light and dark schemes; Crypsis's quick reply checked after the
  upgrade. `tests/e2e/jscheck.js`: no JavaScript errors.
  Baldrick's model picker was also checked in Chrome (headless) against a 300-model list: searchable list, chat models
  only by default, prices filled in, no JavaScript errors.
