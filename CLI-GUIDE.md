# Kunena Fork on the command line

Kunena Fork adds commands to Joomla 3.x UTD's command line, `php cli/joomla.php`. They do from a terminal or a cron job
what the matching administrator tools do, and they are also offered to AI assistants through UTD's MCP server.

## Requirements

- **Joomla 3.x UTD.** Stock Joomla 3 has no `cli/joomla.php` command line; there the plugin does nothing.
- **The plugin "Console - Kunena"**, installed and enabled with Kunena Fork.
- **A completed installation:** Kunena Fork's installation wizard must have run. Otherwise every command stops with
  "Kunena is not installed, or its installation has not been completed."

Run the commands from the site's root folder, as the user that owns the site's files (so that files they create or
delete, such as attachments and avatars, keep the right owner):

```bash
cd /path/to/site
php cli/joomla.php list kunena                      # the Kunena commands
php cli/joomla.php help kunena:trash:purge          # a command's options and help text
```

## The commands

| Command | Does | Same as |
|---|---|---|
| [`kunena:baldrick:check`](#kunenabaldrickcheck) | Tells whether Baldrick AI would answer a post, and why not | - |
| [`kunena:baldrick:index`](#kunenabaldrickindex) | Creates Baldrick AI's full-text search index | Kunena > Baldrick AI > Create the search index |
| [`kunena:baldrick:process`](#kunenabaldrickprocess) | Processes the threads waiting for Baldrick AI | Kunena > Baldrick AI > Process the queue now |
| [`kunena:database:utf8mb4`](#kunenadatabaseutf8mb4) | Converts Kunena's MySQL tables to utf8mb4 | Kunena > Maintenance > Convert Kunena's tables to utf8mb4 |
| [`kunena:sampledata:install`](#kunenasampledatainstall) | Installs the sample forum | Kunena > Tools > Sample Data |
| [`kunena:sampledata:remove`](#kunenasampledataremove) | Removes the sample forum | Kunena > Tools > Sample Data |
| [`kunena:sitemap:generate`](#kunenasitemapgenerate) | Writes the forum's XML sitemaps | - |
| [`kunena:trash:purge`](#kunenatrashpurge) | Permanently deletes every trashed thread and post | Kunena > Maintenance > Empty Trash |
| [`kunena:users:normalize`](#kunenausersnormalize) | Rewrites usernames and names that break the rules | Kunena > Maintenance > Normalise Usernames & Names |

All of them support `--dry-run` and run with Super User rights (whoever can run the site's command line can change it
anyway). All change the site, except `kunena:baldrick:check` without `--queue`; `kunena:sitemap:generate` only writes
files into `media/kunena/sitemaps/`.

### `kunena:baldrick:check`

Applies Baldrick's rules to one post, as things are now, and says which rule stops it: plugin not configured, post or
thread unpublished, category not allowed (including "Public categories only"), poster is staff (Super User, Kunena
administrator, or Kunena moderator of the category: Kunena > Users, independent of Joomla's user groups), dry-run mode,
previous post too recent (the inactivity days), text too short, or the reply limit per thread reached.

Baldrick decides **only when a post becomes visible** (posted, or approved). A post stopped by a rule that no longer
applies (for example a poster's rights corrected afterwards) is never looked at again by itself, and
`kunena:baldrick:process` only works through what is already queued. `--queue` queues such a post's thread when it
passes the rules now.

- `<post>`: the post's id (the number after `#msg` in its link).
- `--queue`: queue the thread if the post passes the rules (with `--dry-run`: only show it).

```bash
php cli/joomla.php kunena:baldrick:check 12345
php cli/joomla.php kunena:baldrick:check 12345 --queue
php cli/joomla.php kunena:baldrick:process
```

JSON data: `check` (post, author, staff, answer, reason), `queued` (thread, post, reason).

### `kunena:baldrick:index`

Creates the full-text index of the forum's posts (MySQL/MariaDB `FULLTEXT`, PostgreSQL GIN) so that Baldrick finds
related threads among all posts quickly, instead of scanning only the latest posts. Takes a few seconds per 100,000
posts; on MySQL/MariaDB, new posts wait until it is done, so run it at a quiet time. Not available on SQLite (Baldrick
uses its plain search there). Succeeds without changes when the index already exists.

- `--dry-run`: shows whether the index exists; creates nothing.

```bash
php cli/joomla.php kunena:baldrick:index --dry-run
php cli/joomla.php kunena:baldrick:index
```

JSON data: `index` (the state; `created` and `seconds` after creating it).

### `kunena:baldrick:process`

Processes the threads queued for Baldrick, the forum's AI first responder (plugin "Kunena - Baldrick AI"): triage,
related threads, answer, replies, spam actions and notification e-mails, exactly like the automatic processing that
runs after a page is sent. Use it when the plugin's **Processing** setting is "Command line only", from cron, e.g. every
minute.

- `--limit=N`: threads to process at most in this run (default 10).
- `--unlock`: takes over threads still shown as "being processed" by a run which stopped without finishing, with a
  fresh count of attempts, so that you see in the terminal what happens. Such a thread is otherwise kept for 10 minutes
  and then tried again, and fails after three attempts. Use it only when no other run is working on the thread.
- `--dry-run`: shows how many threads are due; calls no AI provider.
- Does nothing (successfully) when the plugin is disabled or has no API key and models yet.

```bash
php cli/joomla.php kunena:baldrick:process --dry-run
php cli/joomla.php kunena:baldrick:process --limit=20 --no-interaction
```

If the activity log keeps showing "The previous attempt stopped before finishing" while `--unlock` works, the web
server ends the background processing (PHP-FPM's `request_terminate_timeout` is the usual cause): set the plugin's
**Processing** to "Command line only" and run the command from cron.

Cron (every minute, quiet unless something goes wrong):

```cron
* * * * * cd /path/to/site && php cli/joomla.php kunena:baldrick:process --no-interaction --quiet
```

JSON data: `queue` (threads due, being processed), `processed` (each job and its result). Exits with code 1 when a thread failed.

### `kunena:database:utf8mb4`

MySQL and MariaDB only. Kunena's installer only ever changed its tables' *default* character set to utf8mb4, so forums
installed before utf8mb4 kept their columns in utf8 (utf8mb3): posts can't hold emoji and other 4-byte characters, and
searching for them fails with `Illegal mix of collations (utf8mb3_general_ci,IMPLICIT) and (utf8mb4_..., COERCIBLE) for
operation 'like'`. This converts every Kunena table that still has such columns to `utf8mb4_unicode_ci`, one table at
a time, and reports each table's time.

Large tables (the posts) take a while and are locked meanwhile: make a backup and run it at a quiet time. On MySQL 5.5
or 5.6 without large index prefixes, a table with an indexed 255-character column can be refused ("key too long"): the
other tables are still converted and the command reports the failure.

```bash
php cli/joomla.php kunena:database:utf8mb4 --dry-run
php cli/joomla.php kunena:database:utf8mb4
```

JSON data: `tables` (table, estimated rows, columns not utf8mb4, collation), then `converted` and `failed`.

### `kunena:sampledata:install`

Installs a complete fictional forum for styling and testing: sections and categories, sample users with generated
avatars (active but unusable for signing in: random passwords, `example.invalid` addresses), moderators, an
announcement and about 60 threads with 400 posts (polls, BBCode, image attachments, thank-yous, sticky, locked, unapproved
and trashed posts). Refused when the sample data is already installed. For development and test sites.

- `--dry-run`: shows what would be created.

```bash
php cli/joomla.php kunena:sampledata:install --dry-run
php cli/joomla.php kunena:sampledata:install
```

JSON data: `installed` (what was created, per kind).

### `kunena:sampledata:remove`

Removes exactly what `kunena:sampledata:install` (or Kunena > Tools > Sample Data) created: sample categories, users,
threads, posts and attachments. Posts that other users added to sample threads are kept with their thread, and sample
categories that contain other threads are kept too.

- Interactive: asks for confirmation.
- `--dry-run`: shows what would be removed.
- `--no-interaction`: no question.

```bash
php cli/joomla.php kunena:sampledata:remove --dry-run
php cli/joomla.php kunena:sampledata:remove --no-interaction
```

JSON data: `removed` (counts per kind), `kept` (what was kept and why).

### `kunena:sitemap:generate`

Writes the forum's XML sitemaps as static files into `media/kunena/sitemaps/`, replacing the previous ones:

- `sitemap_index.xml`: the sitemap index, listing the files below with the time of their newest post;
- `categories.xml`: every published category guests can read;
- `threads-YYYY.xml`: the threads started in that year, newest year first (`threads-YYYY-2.xml`, `-3`... when a year
  has more than 50,000 threads, the protocol's limit per file);
- `profiles.xml`: the profiles of members with at least one post, an enabled account and no ban in force, only when
  "Allow Guests to see User Profiles" is on (Kunena > Configuration > Security & Moderation); `profiles-2.xml`... past
  50,000 members. Profiles have no modification time.

Only what a guest can see is listed: published threads of categories guests can read, each by its first page, without
the placeholders left by moved threads. The URLs are the site's own SEF URLs, built by Kunena's router with the site's
menu items, exactly as on the pages. Files with nothing left to list (a year without threads, or the profiles once
guests may no longer see them) are removed. Each file is written to a
temporary file first and then renamed, so search engines never read half a file. Memory stays low on large forums
(threads are read 500 at a time).

The site's address isn't known on the command line: set `$live_site` in `configuration.php`, or pass Joomla 3.x UTD's
global option `--live-site=https://www.example.com`.

```bash
php cli/joomla.php kunena:sitemap:generate --dry-run --live-site=https://www.example.com
php cli/joomla.php kunena:sitemap:generate --live-site=https://www.example.com
```

From cron, e.g. every 30 minutes:

```
*/30 * * * * cd /path/to/site && php cli/joomla.php kunena:sitemap:generate --live-site=https://www.example.com --no-interaction --quiet
```

Then add the index to the site's `robots.txt` and submit it in Google Search Console (a sitemap stored in
`media/kunena/sitemaps/` only covers URLs outside that folder when it is submitted one of these two ways):

```
Sitemap: https://www.example.com/media/kunena/sitemaps/sitemap_index.xml
```

JSON data: `files` (file, URLs), `sitemap` (the index's URL and the `robots.txt` line), `removed` (files removed, when
any); with `--dry-run`, `files` and the plan.

### `kunena:trash:purge`

Empties Kunena's trash: permanently deletes every trashed thread (with all its posts) and every trashed post of live
threads, with their attachments (the files too), polls, thank-yous and ratings, then updates the forum's counters.
**This cannot be undone.**

- Interactive: shows the counts and asks for confirmation.
- `--dry-run`: shows the counts and what would be deleted; deletes nothing.
- `--no-interaction`: no question, for cron jobs.

```bash
php cli/joomla.php kunena:trash:purge --dry-run
php cli/joomla.php kunena:trash:purge --no-interaction      # e.g. weekly from cron
```

JSON data (`--format=json`): `trash` (the counts), `plan` (dry run), `deleted` (`threads`, `posts`).

### `kunena:users:normalize`

Rewrites the usernames and names of existing accounts that break the rules in Kunena > Configuration > Members >
Usernames & Names (usernames: letters A to Z, digits and dashes; names: letters of any alphabet, digits, spaces and
`. , ' - & ( )`; both starting with a letter or a digit). Characters that aren't allowed become dashes in usernames
(accented and Greek letters are written in Latin letters: "Φώτης" becomes "Fotis") and spaces in names, and leading
symbols are removed. A username another account already has gets the account's ID added (`maria-lopez-1234`).

Only usernames and names change. Members whose username changes must log in with the new one (Joomla's "Forgot your
username?" sends it to them). Make a backup first and look at `--dry-run`'s list.

```bash
php cli/joomla.php kunena:users:normalize --dry-run
php cli/joomla.php kunena:users:normalize
```

JSON data: `counts` (accounts, usernames, names); `changes` (with `--dry-run`) or `changed`: id, username and name, as
`old -> new` where they change.

## Options of every command

These come with Joomla 3.x UTD's command line:

| Option | Effect |
|---|---|
| `--dry-run` | Show what would change; change nothing |
| `--no-interaction`, `-n` | Never ask; confirmations take their default (for cron jobs and scripts) |
| `--format=json` | Structured output: the data listed above, messages, and `error` on failures |
| `--quiet`, `-q` | No output except errors |
| `--verbose`, `-v` | More detail |
| `--live-site=URL` | The site's address, for links in e-mails sent from the command line (e.g. by Baldrick's notifications) when Joomla's `live_site` is not set |
| `--help`, `-h` | The command's help |

## Exit codes

| Code | Meaning |
|---|---|
| 0 | Done (also when there was nothing to do) |
| 1 | Failed (e.g. Kunena not installed, an item that could not be deleted, a thread Baldrick failed to process) |
| 4 | Refused (e.g. a confirmation answered "no") |

## AI assistants (MCP)

`php cli/joomla.php mcp:serve` offers every command as a tool, named with underscores (`kunena_trash_purge`,
`kunena_sampledata_install`, `kunena_sampledata_remove`, `kunena_baldrick_process`, `kunena_baldrick_index`,
`kunena_baldrick_check`). The default server runs `kunena_baldrick_check` (without `--queue`) normally and the
others only as dry runs; they change the site only with `--allow-write`. See
Joomla 3.x UTD's CLI guide for the server's options.

## For developers

The commands live in `plugins/console/kunena/commands.php` (registered by `PlgConsoleKunena::onGetConsoleCommands()`),
and their logic in library classes shared with the administrator (`KunenaForumTrash`, `KunenaSampledata`,
`KunenaBaldrickEngine`, `KunenaBaldrickRetriever`). Conventions: [AGENTS.md](AGENTS.md), section 4.5, and Joomla 3.x
UTD's `CLI-GUIDE.md`.
