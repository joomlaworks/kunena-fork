# AGENTS.md

Guide for AI coding agents and developers working on this repository: what it is, how it is laid out, the rules the
code must follow, and how to verify changes. User-facing summary: [README.md](README.md). Detailed history of every
change: [CHANGELOG.md](CHANGELOG.md).

## 1. What this is

**Kunena Fork**: a maintained fork of **Kunena 5.2.14** (forum component for Joomla), released as **5.3.0** (2026-11-12).

| Target | Supported |
|---|---|
| Joomla | 3.x, **3.9 to 3.18**; primary target [Joomla 3.x UTD](https://github.com/joomlaworks/joomla-3.x) (3.18). Joomla 4+ is refused by the installer. |
| PHP | **7.1 to 8.5** - code must parse and run on all of them |
| Databases | MySQL 5.5.3+ / MariaDB, PostgreSQL 9.5+ (drivers `postgresql`, `pgsql`), SQLite 3.37+ (UTD driver `mysqlonsqlite`). **Not** SQL Server / Azure SQL. |
| Coding standard | PSR-12 via PHP CS Fixer (`.php-cs-fixer.dist.php`) |

The fork will be renamed later: don't add new "Kunena" branding to frontend output (credits were removed on purpose,
see section 6).

## 2. Repository layout

The repository root **is** the installable package (K2-style): it mirrors a Joomla site so the ZIP that GitHub exports
can be uploaded to Extensions > Install as-is.

```
kunena.xml                              component manifest (root manifest, type="component")
script.kunena.php                       installer script (Com_KunenaInstallerScript)
administrator/components/com_kunena/    backend: controllers/, models/, views/, template/j3/ (admin layouts),
                                        language/, media/, install/ (Kunena's own install wizard, see 3.2)
components/com_kunena/                  frontend: controllers/ (task controllers), controller/ (HMVC display
                                        controllers), models/, views/, layout/, router.php, attachment.php,
                                        template/blackadder/ (the default template, see 4.9),
                                        template/{crypsis,crypsisb3,crypsisb4,system}/ (layouts, pages, assets)
libraries/kunena/                       the Kunena framework (KunenaForum*, KunenaUser*, KunenaBbcode, ...);
                                        has its own manifest libraries/kunena/kunena.xml
                                        (forum/trash.php: Empty Trash; sampledata/: Sample Data generator + data set;
                                        baldrick/: Baldrick AI, see 4.6; seo/: structured data and sharing tags, 4.10)
media/kunena/                           CSS/JS/images, plus upload folders (attachments, avatars, ...) with .htaccess
plugins/system/kunena/                  System - Kunena plugin (own manifest)
plugins/quickicon/kunena/               Quick Icon - Kunena plugin (own manifest)
plugins/console/kunena/                 Console - Kunena plugin: Joomla 3.x UTD command line commands (own manifest)
plugins/content/kunenadiscuss/          Content - Kunena Discuss (article discussions in the forum; own manifest)
plugins/search/kunena/                  Search - Kunena (com_search plugin, function-based; own manifest)
plugins/kunena/baldrick/                Kunena - Baldrick AI (AI first responder: events, settings form; own manifest)
modules/mod_kunena{latest,login,search,stats}/  site modules (own manifests)
docs/                                   Joomla update server files (update.xml, collection.xml), published with GitHub Pages
                                        at https://joomlaworks.github.io/kunena-fork/ (not part of the package; export-ignored)
tests/                                  test tooling (not part of the package; export-ignored)
AGENTS.md README.md CHANGELOG.md CLI-GUIDE.md LICENSE.txt .php-cs-fixer.dist.php .gitattributes .gitignore
JS-MIGRATION.md                         plan for rewriting Kunena's JavaScript without jQuery (inventory, order, rules)
```

- Class autoloading: `libraries/kunena/bootstrap.php` registers the prefix `Kunena` on `libraries/kunena`, and Joomla
  maps the camel-case parts to folders (`KunenaForumTopicHelper` -> `forum/topic/helper.php`,
  `KunenaForumTrash` -> `forum/trash.php`). Only a single-word name may repeat itself as a folder
  (`KunenaCaptcha` -> `captcha/captcha.php`); other classes living in `<name>/<name>.php` (e.g. `KunenaForumTopic`)
  must be registered with `JLoader::register()` in `bootstrap.php`. Frontend HMVC controllers: `ComponentKunenaController<Path>Display`
  -> `components/com_kunena/controller/<path>/display.php`.
- **A new top-level folder in `libraries/kunena/` must be added to `libraries/kunena/kunena.xml`**, or it won't be
  installed. Likewise new top-level files/folders of the component must be listed in `kunena.xml`.
- No XML manifest may be placed directly in a first-level folder of the repository (Joomla's installer searches the
  root and one level below for manifests; the root `kunena.xml` must be the one it finds).
- Language strings: `administrator/components/com_kunena/language/en-GB/` (admin and the shared
  `en-GB.com_kunena.libraries.ini`, which is also loaded in the frontend) and `components/com_kunena/language/en-GB/`.
  Other languages fall back to the constant name, so add new strings to en-GB.
- Several files (mostly templates) are ISO-8859-1 / contain non-UTF-8 bytes: when editing them with scripts, read and
  write them as latin-1, and note that some `grep` builds treat them as binary (use `grep -a`).

## 3. How installation works

### 3.1 Joomla installer (`kunena.xml` + `script.kunena.php`)

1. `preflight()`: refuses Joomla 4+, checks requirements (`$versions`: PHP 7.1+, Joomla 3.9+, MySQL 5.5.3+ /
   PostgreSQL 9.5+ / SQLite 3.37+, database driver, PHP extensions, no downgrade), runs the legacy file clean-ups, and
   calls `KunenaModelInstall::install()` of the installed version (if any).
2. Joomla copies the site/admin files and `media/kunena` (`<media>`).
3. `postflight()`:
   - installs the library, plugins and modules listed in `$bundled` from the extracted package, each with its own
     `Installer`. Each entry has an enable mode: `always` (system, quickicon, console: Kunena needs them), `new` (Kunena
     Discuss, Search - Kunena: enabled only on first installation, an existing plugin keeps its enabled state) or
     `never` (library, modules: module instances are placed by the site owner); removes the old `search/kunenasearch`;
   - `removeLegacyPackage()`: drops the 5.2.x `pkg_kunena` (package) and `kunena_media` (file) extension records,
     their manifests and script folders in `administrator/manifests/`, detaches children (`package_id = 0`) and
     removes the `update.kunena.org` update sites - without deleting any file;
   - utf8mb4 index conversion (MySQL only), cache clean-up;
   - in the administrator only, sets the installer's `redirect_url` to `index.php?option=com_kunena&view=install`.
4. `uninstall()`: `KunenaModelInstall::uninstall()` removes the library, plugins, modules and menu items (tables are kept;
   Kunena > Tools > Uninstall drops them).

### 3.2 Kunena's install wizard (`administrator/components/com_kunena/install/`)

`view=install` (also forced while `administrator/components/com_kunena/install.php` exists) runs
`KunenaModelInstall` steps over AJAX: Prepare -> Plugins (installs the bundled `install/plugins/plg_*` Kunena
integration plugins) -> Database (creates the schema from `install/install.xml` through `install/schema.php`, or
upgrades it with `install/kunena.install.upgrade.xml`) -> Finish. Access: `core.admin` on Kunena or `core.manage` on
the Installer.

### 3.3 Releasing a new version

1. Replace the version and date in: `kunena.xml`, `libraries/kunena/kunena.xml`, every manifest under `plugins/` and
   `modules/`,
   `administrator/components/com_kunena/install/plugins/*/*.xml`, the templates' `config/*.xml`,
   `media/kunena/labels/labels.xml`, `media/kunena/topic_icons/default/*.xml`, and the three strings in
   `KunenaForum::buildVersion()` / `isDev()` (`libraries/kunena/forum/forum.php`). A quick way:
   `grep -rl "5\.3\.0\|2026-11-12" --include=*.xml --include=*.php .`
2. Add a `<version>` entry at the end of `install/kunena.install.upgrade.xml` (with any upgrade queries/PHP files).
3. Set the new version in `docs/update.xml` and `docs/collection.xml` (Joomla's updater compares it with the installed
   one; the download is the ZIP of `main`, so update them when `main` holds the release). `targetplatform` stays
   `3\.(9|[1-9][0-9])\.`: Joomla 3.9 and every later 3.x (Joomla 3.x UTD) release; the manifest's `<updateservers>`
   points to `https://joomlaworks.github.io/kunena-fork/update.xml`.
4. Update CHANGELOG.md and README.md.

## 4. Rules for code changes

### 4.1 PHP 7.1 - 8.5

- **Syntax: PHP 7.1.** Not allowed: arrow functions, typed properties, `match`, `??=`, nullsafe `?->`, named
  arguments, union/`mixed`/`static`/`never` types, `readonly`, enums, constructor promotion, trailing commas in
  calls or parameter lists, `str_contains()`-style PHP 8 functions without a fallback. Allowed: nullable types,
  `void`, `iterable`, class constant visibility, `[]` arrays, `??`.
- Attributes only as a single line of their own (`#[\Foo]`), which PHP 7 reads as a comment.
- Every class gets `#[\AllowDynamicProperties]` (Kunena assigns properties dynamically everywhere; PHP 8.2+).
  Not on interfaces or traits (fatal).
- Methods implementing internal interfaces (`ArrayAccess`, `Iterator`, `Countable`, `IteratorAggregate`,
  `JsonSerializable`) without PHP 8 return types need `#[\ReturnTypeWillChange]`.
- Parameters defaulting to `null` must be explicitly nullable: `?Foo $x = null` (PHP 8.4).
- Always pass flags to `htmlspecialchars()`/`htmlentities()`/`html_entity_decode()`
  (`ENT_QUOTES | ENT_SUBSTITUTE`, plus `'UTF-8'` where the charset matters).
- Don't pass `null` to internal string functions (cast: `(string) $x`), don't use `strftime()`, `utf8_encode()`,
  `E_STRICT`, `${var}` interpolation, `case x;`, `(boolean)`/`(integer)`/`(double)` casts, or `Serializable` without
  `__serialize()`/`__unserialize()`.

### 4.2 Databases (MySQL/MariaDB, PostgreSQL, SQLite)

- Write MySQL-flavoured SQL with backtick-quoted names (UTD's PostgreSQL drivers translate backticks; SQLite runs
  MySQL SQL through an emulation layer). Prefer the query builder.
- Anything that differs goes through **`KunenaDatabaseHelper`** (`libraries/kunena/database/helper.php`):
  `isMysql()`/`isPostgresql()`/`isSqlite()`, `choose(['mysql' => ..., 'postgresql' => ..., 'sqlite' => ...])`,
  `insertIgnore()`, `upsert()`, `fromUnixtime()`, `like()` (case-insensitive LIKE), `quoteValue()`,
  `getColumns()`, `isAutoIncrement()`. Keep the MySQL text byte-identical when porting.
- Avoid on PostgreSQL: `INSERT IGNORE`, `REPLACE`, `ON DUPLICATE KEY`, `IF()`, `UNIX_TIMESTAMP()`, `SHOW ...`,
  `LIMIT x,y` in raw SQL (use `setQuery($q, $offset, $limit)`), multi-table `UPDATE`/`DELETE`, user variables
  (`@x`), double-quoted string literals, `GROUP BY` that doesn't list every selected non-aggregated column, comparing
  integer columns with `''`, zero dates (`0000-00-00`).
- Schema changes: edit `install/install.xml` (schema.php generates MySQL and PostgreSQL DDL from it); upgrade steps for
  existing MySQL sites go into `install/kunena.install.upgrade.xml`.

### 4.3 Security conventions

- **Authorisation:** `$object->isAuthorised($action)` returns a boolean and **never throws**; use
  `tryAuthorise($action)` (throws `KunenaExceptionAuthorise`) or check the return value. Wrapping `isAuthorised()` in
  `try/catch` was the root cause of several critical holes (posting/editing anything).
- Every state-changing task checks `Session::checkToken()` (`'post'` for forms, `'get'`/`'request'` for links and
  AJAX), and forms/links include the token.
- SQL: cast ids (`(int)`, `ArrayHelper::toInteger()`, and **assign the result**), `$db->quote()` strings,
  `$db->quoteName()` identifiers, whitelist ordering columns and `ASC`/`DESC`, escape `LIKE` patterns with
  `$db->escape($value, true)`.
- Output: escape everything in layouts (`$this->escape()`); BBCode output (`KunenaHtmlParser::parseBBCode()`) is HTML
  by design - make sure new BBCode tags encode their attributes and only allow safe URL schemes; values in
  `<script>` use `json_encode()` with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`.
- Files: uploads go through `KunenaUpload` (fixed deny-list `hasForbiddenExtension()` on every dot-separated part,
  allow-list from the configuration); never use client-supplied paths for reading/deleting; validate file and
  folder names against a strict pattern; serve attachments through the attachment controller (inline only for raster
  images and verified PDF files).
- No server-side fetching of user-supplied URLs without `KunenaBbcode::isSafeRemoteUrl()`-style checks.
- `unserialize()` only with `allowed_classes`.

### 4.4 CAPTCHA

Use `KunenaCaptcha` (`libraries/kunena/captcha/captcha.php`) - never talk to a specific CAPTCHA plugin:

```php
// Form (controller or layout)
$this->captchaEnabled = KunenaCaptcha::isRequired($this->me);
$this->captchaDisplay = $this->captchaEnabled ? KunenaCaptcha::display('kunena_captcha_' . $id) : '';

// Task
$error = null;
if (!KunenaCaptcha::verify($this->me, $error)) { /* enqueue $error, redirect back */ }
```

It uses the plugin selected as "Default Captcha" in Joomla's Global Configuration through Joomla's `Captcha` API,
posts the control as `kunena_captcha`, and **fails closed** when a CAPTCHA is required but none is configured. Who must
solve it is decided by `KunenaUser::canDoCaptcha()` (Kunena configuration `captcha` / `captcha_post_limit`). Any new
endpoint that creates content for guests or new users must call `KunenaCaptcha::verify()`.

### 4.5 Command line (Joomla 3.x UTD)

User documentation of every command: [CLI-GUIDE.md](CLI-GUIDE.md); update it with each new command or option.

Commands live in `plugins/console/kunena/commands.php` (classes extending `Joomla\CMS\Console\AbstractCommand`, returned
by `PlgConsoleKunena::onGetConsoleCommands()`, loaded only inside that handler so the plugin is harmless on stock
Joomla). Follow UTD's CLI-GUIDE.md: `kunena:<noun>:<verb>` names, output only through `$io`, `--dry-run` support
(`$dryRun = true`, `$io->plan()`), confirmation only when `$io->isInteractive()`. Keep the logic in library classes
shared with the administrator (e.g. `kunena:trash:purge` and Tools > Empty Trash both use `KunenaForumTrash`;
`kunena:sampledata:install|remove` and Tools > Sample Data use `KunenaSampledata`; `kunena:baldrick:process` and
Kunena > Baldrick AI > "Process the queue now" use `KunenaBaldrickEngine`). On the command line Kunena's
"current user" is not the Super User the command runs as, so library code which checks the current user's rights
(e.g. `KunenaUser::save()` for other users) refuses: write through the tables where that is intended.

Long administrator tasks run in steps over AJAX: the controller task answers JSON (`success`, `done`, `status`,
`message`, `errors`) and the page uses `administrator/components/com_kunena/template/j3/tools/ajaxtask.js` (a button
with the class `kunena-ajax-task` and `data-url`/`data-confirm`/`data-progress`/`data-reload`).

Kunena's object caches live for one request: code which creates posts and then trashes/deletes them in the same
request can see stale threads (wrong counters). Tests do such steps in separate requests, as a real site does.

### 4.6 Baldrick AI (`libraries/kunena/baldrick/`, `plugins/kunena/baldrick/`)

The AI first responder. The plugin only wires events (`onKunenaGetActivity` -> `KunenaBaldrickActivity`, which Kunena
calls when a post becomes visible; `onAfterRespond`; `onAjaxBaldrick` for the background request); its settings are
read with `KunenaBaldrick::getParams()`. Classes: `KunenaBaldrick` (settings, trigger rules, staff/category checks,
public categories, background kick), `KunenaBaldrickQueue`, `KunenaBaldrickEngine` (triage -> spam / canned / request
for details / retrieval + answer -> post, log, e-mail), `KunenaBaldrickClient` (raw HTTP, Anthropic Messages and
OpenAI-compatible Chat Completions, model listing, retries, cost), `KunenaBaldrickPrompt`, `KunenaBaldrickRetriever`,
`KunenaBaldrickMailer`, `KunenaBaldrickUsage` (log + monthly totals), `KunenaBaldrickMessage` (post class without the
form-only flood/duplicate checks). Administrator page: `view=baldrick` (also the JSON tasks `models` and `test` used by
the settings form's buttons). Tables `#__kunena_baldrick_{queue,log,usage}` are in `install/install.xml`.

Rules:
- Posting must never wait for an AI provider and must never fail because of Baldrick (`onPost()` swallows errors).
- Forum posts are untrusted: they go to the models as data inside tags (`<thread>`, `<post>`, `<related_thread>`, with
  `<`/`>` neutralised), never as instructions; AI output is sanitised (`KunenaBaldrickEngine::sanitise()`: allowed
  BBCode only, no HTML/media, links to the site made relative) before it is posted.
- Only guest-readable categories feed the retrieval (`KunenaBaldrick::getPublicCategories()`); Baldrick's own posts
  never do.
- Never reply when the last post is by staff (`KunenaBaldrick::isStaff()`); re-check right before posting.
- Temporary provider errors are retried (`KunenaBaldrickQueue::retry()`, honouring `retry-after`); account and
  configuration errors (`KunenaBaldrickClient::isAccountError()`) pause Baldrick (`KunenaBaldrick::pause()`, state in
  the plugin's parameters `pause_*`, so saving the settings ends it) and defer the queue without counting attempts.
- Optional API features only for the official APIs, and dropped on HTTP 400 (`KunenaBaldrickClient::featureToDrop()`):
  third-party "compatible" endpoints differ. No SDKs (PHP 7.1, no Composer).
- Code which creates posts in bulk (imports, sample data) sets `KunenaBaldrick::$suspended` meanwhile.
- Retrieval (`KunenaBaldrickRetriever`) must stay cheap on large forums: one query per question; the full-text index
  (`createIndex()`, never created automatically) or a `LIKE` scan bounded to the latest posts by primary key
  (`retrieval_max_posts`). No `ts_rank()` on PostgreSQL (it rebuilds every matching post's vector).
- After `fastcgi_finish_request()` headers are out: never close Joomla's session object before it (Joomla would try to
  start the session again); `KunenaBaldrick::releaseSession()` unlocks it. `tests/e2e/serve.sh` with `FCGI_SHIM=1`
  imitates PHP-FPM on PHP's built-in server.
- New provider behaviour goes into `tests/e2e/baldrick-mock.php` and the `baldrick` scenario, never a real API.

### 4.7 JavaScript

- Load jQuery with `HTMLHelper::_('jquery.framework')` before any script using it: Joomla 3.x UTD's administrator
  template (Prothero) doesn't load it by itself. Kunena's administrator entry point does it for every page.
- No MooTools (`window.addEvent`, `$$`, `document.id`, `.addEvent`): Joomla 3.x UTD doesn't ship it. New code is plain
  JavaScript. The plan for moving the existing jQuery code to vanilla JS is in [JS-MIGRATION.md](JS-MIGRATION.md).
- Frontend CSS: Crypsis is built on Bootstrap 2.3 and loads `media/kunena/core/css/bootstrap.kunena.css`, Bootstrap
  scoped to `#kunena`, so that the forum works with any site template without restyling it. Never load Joomla's global
  `bootstrap.min.css` from Kunena. Regenerate the scoped file with `php tests/tools/scope-bootstrap.php <media/system/css
  of a Joomla 3.x UTD site>` (don't edit it by hand).
- Don't load `bootstrap.framework` globally in the administrator: Joomla 3.x UTD's `global.js` implements Bootstrap 2's
  data attributes (tabs, modals...) and both would handle the same clicks. Joomla's helpers which need Bootstrap's
  script (`dropdown.init`, `bootstrap.*`) load it themselves.

### 4.8 Style

- Run `php-cs-fixer fix` (config in the repository; PHP CS Fixer 3.x) before committing; `php-cs-fixer fix --dry-run`
  must report nothing. Don't enable PER-CS or PHP 8 migration rule sets (they add PHP 8 syntax).
- XML (manifests, forms, `install.xml`, layouts' metadata): each element's start tag, with all its attributes, on one
  line; never split attributes over several lines.
- Template HTML indentation and PSR-1 naming (no namespaces, legacy method names) are intentionally left as they are:
  renaming public classes/methods breaks third-party integrations.

### 4.9 Blackadder (frontend template)

- `components/com_kunena/template/blackadder/` is the default template (`KunenaConfig::$template`, the fallback in
  `KunenaTemplate::getInstance()`, `KUNENA_TEMPLATE_DEFAULT`). Every frontend layout a page renders has a Blackadder
  version; anything missing falls back to Crypsis (`$default`), whose layouts assume Bootstrap 2: add the Blackadder
  layout instead. Pages are Crypsis's (`isHmvc()` returns true: keep it, see CHANGELOG section 17). The page frames
  `page/default.php`, `page/unauthorized.php` and `page/offline.php` all carry `class="kb layout"`.
- Colours: the forum takes the site template's colours (transparent background, the page's text colour, greys mixed
  from them; `blackadder.js` sets `--kb-auto-fg` / `--kb-auto-bg` and `kb--dark`). Use the `--kb-*` tokens; never
  hard-code a colour or give the forum an opaque background (opaque panels use `--kb-surface`). Check a change on
  Hammond, Finch and Rookwood in both schemes.
- **One stylesheet, one script:** `assets/css/blackadder.css` and `assets/js/blackadder.js`. Don't add other CSS/JS files
  or load Crypsis's (`rating.css`, `jquery.atwho.css`, `topic.js`...): put the rules and code there. Kunena's shared
  jQuery plugins (`jquery.caret.js`, `jquery.atwho.js`) are the exception.
- CSS in Joomla 3.x UTD's style: one rule per line (`sel {prop:value;}`), one selector per line, 4-space indentation inside
  at-rules, aligned variable lists, `/* --- Section --- */` headings. Check with UTD's formatter
  (`python3 <joomla-3.x>/docs/format/css.py blackadder.css` lists nothing) and `css_prefixes.py` / `css_ie_hacks.py`.
- Everything scoped to `.kb` and prefixed `kb-`; colours only through the `--kb-*` tokens (from `--kunena-*`); dark mode
  through `[data-theme="dark"]` / `[data-scheme="dark"]` (and "auto" with `prefers-color-scheme`).
- Icons: `$template->icon('bootstrap-icon-name', $label)`; a new icon is a `<symbol>` added to `assets/images/icons.svg`
  (copied from Bootstrap Icons, MIT). No icon fonts.
- Some Kunena controllers echo HTML directly (e.g. Message/Item/Actions' login line): capture subrequests with output
  buffering when the output belongs elsewhere.
- Markup: no `header`, `footer`, `article`, `section`, `nav`, `aside`, `figure`, `figcaption` or `cite` (site templates
  style them globally): use `div`/`span` with ARIA roles. `details`/`summary`, `blockquote` and `dl` are reset in the
  base styles; style them through Blackadder's classes.
- Plain JavaScript, working without it where HTML allows (`<details>` menus, normal form posts).
- `tests/e2e` checks the default template on the index (`blackadder-default`); a page-by-page audit (assets, Bootstrap
  markup, untranslated strings, overflow at 390 px, JS errors) is easy to repeat with Playwright when layouts change.
- Crypsis layouts that load their own assets (`Widget/Lightbox`, `poll.js`, `upload.main.js`, the editors) must be
  overridden in Blackadder before a page uses them. Check a page with the browser's network list: only
  `blackadder.css`, `blackadder.js` and Kunena's `jquery.caret.js` / `jquery.atwho.js` should come from Kunena.
- The posting form talks to Kunena's existing endpoints (`task=upload`, `task=setinline`, `layout=edit&format=raw`
  for the preview, `layout=categorytemplatetext`); attachments travel as `attachments[id]` (known) and
  `attachment[id]` (kept), new poll options as `polloptionsID[newNNN]` (saved in key order).

### 4.10 Structured data, Open Graph and X (`libraries/kunena/seo/seo.php`)

- All search engine and sharing metadata goes through `KunenaSeo`: display controllers call `KunenaSeo::topic()`,
  `::profile()` or `::page()` at the end of `prepareDocument()`. Don't write `og:`/`twitter:` meta or JSON-LD anywhere
  else.
- Describe only what the page shows to a guest: published posts of categories guests can read
  (`KunenaForumCategoryHelper::getGuestReadable()`), the posts on the current page; follow Google's documentation for
  `DiscussionForumPosting` / `ProfilePage` (no unsupported types such as `aggregateRating`).
- On Joomla 3.x UTD with its "System - Structured Data" plugin enabled (`KunenaSeo::viaPlatform()`), values are handed
  to it through `onStructuredDataBeforeRender` (System - Kunena plugin -> `KunenaSeo::render()`): Kunena's tags replace
  the generic ones and its nodes join the plugin's `@graph`. Otherwise Kunena writes them itself; JSON-LD always as a
  custom tag of its own (`addCustomTag()`), never `addScriptDeclaration()` (Joomla merges same-type declarations).
- Settings: Kunena > Configuration > SEO (`seo_structured_data`, `seo_social_tags`, `seo_share_image`, `seo_x_site`).
- Development and test sites: Configuration > Development > "Don't send e-mails" (`dev_disable_email`); new code that
  sends mail checks `KunenaEmail::isDisabled()` (or goes through `KunenaEmail::send()`, which does).

### 4.11 Threads: locks, resolved state, images

- A thread is locked by a moderator (`$topic->locked`) or automatically after days without replies (Configuration >
  Users and the category's `autolock_days`): check `$topic->isLocked()` for permissions and display, never `locked`
  alone (only the moderator's lock/unlock action works on `locked` itself).
- Resolved threads: `$topic->isResolved()` / `canResolve()` / `setResolved()` (the topic's `label_id` 8); don't use
  `label_id` 8 for anything else.
- Images: `KunenaImage` (not Joomla's `Image`) reads, resizes and writes WebP and AVIF; check `KunenaImage::canProcess()`
  before accepting a format.

## 5. Verifying changes

Run the checks that match the change; all of them before a release. Paths are relative to the repository root.

### 5.1 Lint on every PHP version (Docker)

```bash
for v in 7.1 7.4 8.0 8.1 8.2 8.3 8.4 8.5; do
  docker run --rm -v "$PWD":/src:ro php:$v-cli sh -c \
    'find /src -name "*.php" -not -path "*/tests/*" -print0 | xargs -0 -n1 -P8 php -l | grep -v "^No syntax errors"'
done
```

(Any image with the matching PHP CLI works; the original work used locally built `j3qa-php:<version>` images.)

### 5.2 Static PHP compatibility (PHPCompatibility)

```bash
composer global require squizlabs/php_codesniffer "phpcompatibility/php-compatibility:dev-develop" \
    dealerdirect/phpcodesniffer-composer-installer
phpcs --standard=PHPCompatibility --runtime-set testVersion 7.1-8.5 --extensions=php --ignore=*/tests/* .
```

Expected: only known false positives: `finfo_close()` guarded by `PHP_VERSION_ID < 80100`; the `@throws` tag of
`KunenaControllerDisplay::__toString()`, which catches everything; `imagedestroy()` and the `num_points` argument of
`imagefilledpolygon()` in `KunenaSampledata`, both guarded by `PHP_VERSION_ID` checks.

### 5.3 Class-load check against Joomla 3.x UTD (`tests/tools/`)

Loads every Kunena class file, one process per file, under Joomla 3.x UTD's class loader and reports PHP
deprecations/warnings/fatal errors raised by Kunena code (signature incompatibilities, dynamic properties, return types):

```bash
git clone --depth 1 https://github.com/joomlaworks/joomla-3.x.git /tmp/joomla-3.x
tests/tools/classload.sh /tmp/joomla-3.x
```

Expected output: nothing but the summary line. It never writes into the Joomla copy or the repository.

### 5.4 End-to-end tests on all databases (`tests/e2e/`)

Real installations on Joomla 3.x UTD, driven over HTTP with curl (PHP built-in web server, databases in Docker).
Requirements: PHP CLI with `curl`, `pdo_sqlite`, `pgsql`, `pdo_pgsql`, `mysqli`; Docker; `zip`; a Joomla 3.x UTD
checkout. Configuration and credentials: `tests/e2e/env.sh` (override with environment variables, e.g.
`J3SRC=/path/to/joomla-3.x WORKDIR=/tmp/kunena-tests`).

```bash
tests/e2e/containers.sh up          # postgres:16, mariadb:11.4, mysql:8.4 test containers (kunena-test-*)
tests/e2e/matrix.sh                 # sqlite pg pgpdo mariadb mysql: install from a GitHub-style ZIP, wizard, ~75 scenarios
tests/e2e/matrix.sh sqlite pg       # a subset
tests/e2e/captcha.sh                # CAPTCHA (IconCaptcha, fail-closed, stub plugin right/wrong answer) + no credits
tests/e2e/upgrade.sh                # Kunena 5.2.14 package -> this repository, on MariaDB (needs LEGACY_ZIP; with
                                    # LEGACY_EXTRAS = folder of the unpacked 5.2.2 modules/plugins it also checks that a
                                    # disabled Kunena Discuss stays disabled and a missing search plugin gets installed)
tests/e2e/final.sh                  # everything above
tests/e2e/containers.sh down
```

- Scenarios (`tests/e2e/run.php`): install, wizard, admin pages, configuration save, frontend index/categories,
  topic + reply, guest post, search, profile/user list, recent/unread lists, moderation (delete, undelete, move,
  merge, trash purge), Empty Trash (administrator AJAX tool: trashed thread and post with attachment files removed,
  live posts and counters intact; `kunena:trash:purge` with and without `--dry-run`), Sample Data (install over AJAX,
  every count and counter checked, 17 frontend pages rendered, staff-only category hidden from visitors, removal
  keeping a thread another user replied to, no orphans or files left, CLI with and without `--dry-run`), the
  integrated extensions (all seven installed at the package version with the plugins enabled, the four modules
  rendered in a template position, forum posts found by com_search, a Kunena Discuss reply creating the forum thread,
  a guest's reply refused without the Global Configuration CAPTCHA), Baldrick AI (against the mock provider
  `baldrick-mock.php`, e-mails captured by a fake sendmail: notice without a key, settings page, fetch models / test
  connection, dry run, answer with related threads and relative links, request for details with translation, canned
  reply, not-a-question, spam thread and spam reply unpublished, revived threads, staff replies, retry on overload,
  refusal fallback, automatic background processing, administrator page, CLI, client features against the official
  APIs), every Tools action (recount,
  diagnostics fixes, sync users, prune, IP cleanup), uninstall, reinstall over existing tables. Single steps: `php tests/e2e/run.php <db> install,wizard,post`.
- A run fails on any failed scenario, failed SQL query (logged by an instrumented copy of the database drivers) or
  PHP notice/warning/deprecation coming from Kunena files.
- The scenarios use curl and never run the pages' JavaScript: `tests/e2e/jscheck.js` does, in headless Chrome
  (`PLAYWRIGHT_CORE=/path/to/node_modules/playwright-core node tests/e2e/jscheck.js http://127.0.0.1:8101/ admin
  Admin12345678`, on a site where the package is installed and the wizard not run yet, e.g. after
  `run.php <db> install`): the wizard, every administrator page, tabs, a modal, frontend pages; exit code 1 on any
  JavaScript error.
- Sites are built in `$WORKDIR/sites/<db>` and served on ports 8101 (sqlite), 8102 (pg), 8103 (mariadb), 8104
  (pgpdo), 8105 (mysql); administrator login `admin` / `Admin12345678`. Pages and logs go to `$WORKDIR/out`.
- Test sites never use the system's mailer: `mksite.sh` points Joomla to a fake sendmail that appends every e-mail to
  `$WORKDIR/out/<db>-mail.log` (a local MTA that can't queue mail would block a request for ever).
- `tests/e2e/sync.sh <db>` copies the repository over an installed test site for quick iteration.
- Test sites receive a helper (`kexec.php`) that evaluates posted PHP: test sites only, never on a real site. The
  scripts refuse to work on a site containing symbolic links, so that an installation can never write through links
  into the repository.

### 5.5 Results for 5.3.0

- Lint: 0 errors on PHP 7.1, 7.4, 8.0, 8.1, 8.2, 8.3, 8.4, 8.5. PHP CS Fixer @PSR12: 0 files to fix.
- Class-load check (Joomla 3.x UTD 3.18, PHP 8.5): no messages from Kunena code.
- End-to-end: see "Test results" in CHANGELOG.md.

## 6. Decisions to keep

- One package, root manifest at the repository root; no `pkg_` wrapper, no ZIPs inside the repository.
- CAPTCHA comes from Joomla's Global Configuration only; it fails closed.
- No "Powered by Kunena" output, no credits page (redirects to the forum index), no kunena.org links in frontend error
  messages. `poweredBy()` stays (returns `''`) for third-party code.
- The ban form preselects the Joomla-level ban and the removal of signature, avatar, profile and posts.
- Sample data is English-only static data; sample accounts are active but unusable (random passwords,
  example.invalid addresses), and removal never deletes content other users added.
- MySQL SQL output is unchanged by the PostgreSQL/SQLite work; upgrades from Kunena < 5.3 and FireBoard/JoomlaBoard
  migrations are MySQL-only.
- Baldrick AI: installed enabled but inert without an API key (notice in the administrator); replies only when the
  last post isn't by staff, published immediately, in the poster's language plus an English translation (option, on),
  signed through its account's forum signature (set to "Replied by Baldrick AI, my lord, using a cunning model (...and
  do remember that AI can make mistakes, so double check responses)" when Baldrick creates the account), never in the
  post text; spam is unpublished (whole thread for a first post) and
  e-mailed; spam and errors are always e-mailed, replies unless "Notify about" is "spam and errors only";
  notification e-mails hold the first post and the last 5 posts, newest first; links to the forum are
  relative; no CAPTCHA for Baldrick's own posts; all settings in one tab with spacer headers.
- Blackadder is the default frontend template for new installations; upgrades keep theirs (`KunenaModelInstall::migrateConfig()`
  records Crypsis for sites upgrading from < 5.3 that never saved their configuration; `tests/e2e/upgrade.sh` checks it).
  One stylesheet and one script; no "Time to create page" in the footer of any template.
- `LICENSE.txt` (GPL v3) stays at the root.
