# JavaScript migration guide: from jQuery to vanilla JS

This is a plan, not a record of finished work. It covers what Kunena's JavaScript consists of, which parts depend on
jQuery or on third-party widgets, and how to rewrite it in plain JavaScript in safe steps. Read [AGENTS.md](AGENTS.md)
first, especially section 4.7 (JavaScript rules) and section 5 (testing).

Inventory as of 5.3.0 (2026-11-10). Line counts exclude minified copies.

## 1. Where things stand

- **MooTools: none left.** Joomla 3.x UTD doesn't ship MooTools, and every MooTools call in Kunena was rewritten for
  5.3.0 (CHANGELOG section 15). Don't add any back: `window.addEvent`, `$$`, `document.id`, `.addEvent`, `.getElements`,
  `JSON.decode`.
- **jQuery: almost everywhere, and it works.** Joomla 3.x UTD still ships jQuery 1.12.4 (`jquery.framework`), Bootstrap
  2.3.2 (`bootstrap.framework`) and jQuery UI 1.14.2, so this migration is modernisation, not a fix. Pages must request
  jQuery with `HTMLHelper::_('jquery.framework')`; Kunena's administrator entry point does it for every page.
- **Bootstrap behaviours** (tabs, modals, collapse, dropdowns, tooltips, popovers) are written as Bootstrap 2 `data-`
  attributes in most places. Joomla 3.x UTD's `media/system/js/global.js` implements them in vanilla JS, and Isis loads
  Bootstrap's own script. Don't load `bootstrap.framework` globally in the administrator: both would handle the same
  clicks (see AGENTS.md 4.7).
- **Already vanilla:** `plugins/kunena/baldrick/assets/baldrick.js` (uses `CMS.chosen` on Joomla 3.x UTD, jQuery Chosen
  elsewhere: a pattern to copy), `crypsis*/assets/js/rating.js`, the administrator lists' inline ordering and "Clear"
  handlers.

## 2. Inventory

**Removed in 5.3.0 (dead code):** WysiBB 1.5.1 (`jquery.wysibb.js`, `wysibb.lang.js`, `wbbtheme.css`, the
`widget/editor/wysibb*.php` layouts of the three templates, their settings and language strings: the option was commented
out, and the quick reply loaded it only for an `editor` setting of `1`, which these versions never save; such a site now
gets the plain textarea like every other), `jquery.fileupload-ui.js` (never loaded), `build-config.js` (CKEditor's
builder record, never loaded), the copy of `jquery.caret.js` in `crypsisb4/assets/js` (the template reads its scripts from
`media/kunena/core/js`), and the status-text handler `$("btn_statustext")` of the three templates' logout widget (its
selector matched nothing; the button submits the form natively). The installer deletes these files on upgraded sites.


### 2.1 Third-party libraries (about 1.6 MB)

| Library | Files | Size | Needs jQuery | Used for | Plan |
|---|---|---|---|---|---|
| CKEditor 4.20.0 | `media/kunena/core/js/ckeditor.js`, `lang/`, `skins/moonocolor/`, `styles.js`, `plugins/` (88 files; 11 are Kunena's own) | 1.17 MB | No | Default editor (`editor` = `ckeditor`) | Keep for now. Free CKEditor 4 support ended in 2023 and 4.20 has published XSS advisories. CKEditor 5 can't edit BBCode; **SCEditor** (vanilla, MIT, BBCode mode) is the candidate. Treat as its own project (section 4, step 7). |
| markItUp! 1.1.x | `media/kunena/core/js/markitup.js` (681 lines) and a copy in `crypsis/assets/js/` | 21 KB | Yes | BBCode editor (`editor` = `bbcode`) | Replace with a small vanilla toolbar inserting BBCode into the textarea (300-500 lines). |
| At.js 1.5.4 + Caret.js | `jquery.atwho.js` (1,240), `jquery.caret.js` (466) | 46 KB | Yes | @mentions (`edit.js`), user search (`search.js`) | Tribute.js (vanilla, MIT) or about 200 lines of our own. UTD's `CMS.suggest` covers field autocomplete, not mentions. |
| blueimp jQuery File Upload (archived project) | `jquery.fileupload{,-process,-image,-audio,-video,-validate}.js`, `jquery.iframe-transport.js`, `load-image.all.min.js`, `canvas-to-blob.min.js` | 133 KB | Yes, and jQuery UI's `$.widget` | Attachments (`upload.main.js`), avatars (`upload.avatar.js`) | Native `FormData` + XHR `upload.onprogress`, drag and drop, canvas `toBlob()` for resizing (browsers apply EXIF rotation themselves now). |
| fancyBox 3.5.7 | `fancybox-min.js` | 68 KB | Yes | Image lightbox (`lightbox` setting, `data-fancybox`) | GLightbox or PhotoSwipe 5 (vanilla, MIT). |
| bootstrap-datepicker 1.9.0 | `bootstrap.datepicker.js` (2,039) + Kunena language file | 62 KB | Yes | Announcements, poll end date, search dates | Native `<input type="date">`. |
| jsSocials 1.5.0 | `jssocials.js` (552) | 13 KB | Yes | Share buttons (`widget/social`) | Plain share links plus `navigator.share`. |
| Sisyphus | `sisyphus.js` (587) | 16 KB | Yes | Drafts kept in the browser (`storage` setting) | About 40 lines with `localStorage`. |
| rating | `crypsis*/assets/js/rating.js` (194, identical in the three templates) | 4 KB | No | Topic rating | Keep; dedupe into `media/kunena`. |

Remote scripts: Font Awesome from its CDN (`libraries/kunena/template/template.php`; Kunena Discuss loads 5.6.1),
Google Maps (`[map]` BBCode), Tableau (`[tableau]` BBCode). Not part of this migration, but worth a separate decision:
self-host Font Awesome, privacy of the map embeds.

### 2.2 Kunena's own scripts (about 4,900 lines)

| Area | Files | Lines | Depends on |
|---|---|---|---|
| Frontend `crypsis` | `main`, `topic`, `poll`, `profile`, `search`, `krating`, `upload.main`, `upload.avatar` | 1,184 | jQuery; `.popover()`, `.tab()`, `.collapse()` |
| Frontend `crypsisb3` | the same 8 | 1,179 | jQuery |
| Frontend `crypsisb4` | the same 8 | 1,210 | jQuery |
| Generated editor settings | `crypsis/assets/js/markitup.editor.js`, written by `KunenaBbcodeEditor::initializeHMVC()` (`libraries/kunena/bbcode/editor.php`) | 150 | data + jQuery `.modal()` |
| Shared (`media/kunena/core/js`) | `edit.js` 503, `markitup.set.js` 345, `markitup.editor.js` 363, `localstorage.js` 18, `ckeditor_config.js` 81 | 1,310 | jQuery, CKEditor API, `.modal()`/`.tab()`/`.collapse()` |
| Kunena's CKEditor plugins | `code`, `confidential`, `ebay`, `hidetext`, `instagram`, `map`, `polls`, `soundcloud`, `spoiler`, `twitter`, `video` | 574 | CKEditor API (the polls dialog also jQuery) |
| Administrator | `template/j3/categories/edit.js` 47, `tools/ajaxtask.js` 96, `tools/recount.js` 93 | 236 | jQuery (`recount.js` also `.modal()`) |
| Baldrick AI settings | `plugins/kunena/baldrick/assets/baldrick.js` | 254 | vanilla |

The three frontend templates total 3,573 lines, of which only about 1,200 are unique: the copies differ by Bootstrap
2/3/4 class names and a few fixes. Every file has a hand-made `-min.js` twin, served by `KunenaTemplate::addScript()`
whenever debug is off; the repository has no build step to regenerate them (so a fix must be made in both).

### 2.3 Inline JavaScript in PHP (about 450 lines)

| Where | Depends on | Lines |
|---|---|---|
| `administrator/components/com_kunena/install/tmpl/default.php` (installation wizard) | jQuery | 70 |
| `template/j3/user/edit.php` (signature counter) | jQuery | 15 |
| `libraries/kunena/template/template.php` (active menu class, 3 places) | jQuery | 10 |
| Administrator list filters (`onclick="jQuery('.filter')..."`), `plugins/default.php` modal buttons | jQuery | one-liners |
| `crypsis*/layouts/bbcode/map/default.php` (Google Maps) | jQuery ready | 35 x 3 |
| `crypsis*/layouts/widget/login/logout/default.php`, `widget/forumjump/default.php` | jQuery | 8 / 3, x 3 |
| `layout/widget/editor.php`, `layout/topic/edit/editor.php`: 62 `addScriptDeclaration()` calls setting `kunena_show*tag` globals | vanilla | 62 x 2 (move to `addScriptOptions()`) |
| `mod_kunenalogin`, `mod_kunenasearch` `onfocus`/`onblur` placeholder tricks | vanilla | replace with the `placeholder` attribute |

The third-party integration plugins (`administrator/components/com_kunena/install/plugins/`) have no JavaScript of
their own: they print markup for their host product's scripts (JomSocial, EasySocial). Nothing to migrate.

## 3. Rules for the rewrite

1. **Both platforms.** The fork supports Joomla 3.9 to 3.18. UTD's `CMS.*` helpers (`CMS.request`, `CMS.chosen`,
   `CMS.modal`, `CMS.tip`) and `behavior.cms` don't exist on stock 3.9/3.10: depend on the DOM only, or feature-detect
   and fall back as `baldrick.js` does.
2. **Let Joomla load the behaviours.** Request them through `HTMLHelper` (`bootstrap.modal`, `bootstrap.tooltip`,
   `formbehavior.chosen`...): UTD answers with its vanilla implementation, stock Joomla with jQuery, and Kunena stays
   neutral. Keep Bootstrap 2 `data-` attributes for tabs and modals where possible.
3. **One script per feature, shared by the three templates.** Move frontend scripts to `media/kunena/core/js/` and pass
   per-template class names (Bootstrap 2/3/4) as options, instead of three copies.
4. **No inline scripts for data.** Pass settings with `Factory::getDocument()->addScriptOptions()` and read them with
   `Joomla.getOptions()`; values in `<script>` blocks must use `json_encode()` with the `JSON_HEX_*` flags (AGENTS.md 4.3).
5. **Target ES2017.** UTD's own code uses ES2015+; no transpiler. Keep a build step (or drop the `-min.js` copies and
   serve the plain files: they are small) so minified copies can't drift from their sources.
6. **Events from Joomla widgets:** Chosen-style lists announce changes through jQuery when it is present; listen with
   jQuery when available, natively otherwise (see `baldrick.js` `onChange()`).
7. **Don't break third-party overrides.** Template overrides may call Kunena's global functions; keep the public ones
   (e.g. `kunenaSelectUsernameView`, editor insert helpers) as thin wrappers until a major release.

## 4. Recommended order and effort

| Step | Scope | Effort | Risk |
|---|---|---|---|
| 1 | ~~Delete dead code~~ (done in 5.3.0, see below); still to decide: the `-min.js` copies | ½ day | Low |
| 2 | Administrator: `categories/edit.js`, `ajaxtask.js`, `recount.js`, the wizard, `user/edit.php`, list one-liners | 1-2 days | Low |
| 3 | Small frontend scripts, written once for the three templates: `main`, `topic`, `poll`, `profile`, `search`, `krating`, `localstorage`, the inline snippets | 3-4 days | Medium (Bootstrap 3/4 templates) |
| 4 | Native replacements: date inputs, drafts (`localStorage`), share links, lightbox (GLightbox) | 2-3 days | Low |
| 5 | Mentions (Tribute.js or own code) | 1-2 days | Medium |
| 6 | Uploads: rewrite `upload.main.js` (about 600 lines of File Upload callbacks) and `upload.avatar.js` on XHR | 3-5 days | High: progress, resizing, insert into editor, delete |
| 7 | Editors: port `edit.js` and the markItUp toolbar; keep the 11 custom CKEditor plugins | 4-7 days | High |
| 7b | Optional: replace CKEditor 4 with SCEditor | +2-3 weeks | High: BBCode round-trips through `KunenaBbcode`, custom tags |

Total: about **3-5 developer-weeks** with CKEditor 4 kept, plus 2-3 weeks to replace it. When jQuery is gone from a
page, stop requesting `jquery.framework` there; jQuery stays on the platform for other extensions.

## 5. Templates crypsisb3 and crypsisb4

They rely on the site template's Bootstrap 3/4 JavaScript. UTD imitates Bootstrap 2 (`.in`, not `.show`), and
`localstorage.js` listens to the Bootstrap 2 events `hidden`/`shown`. Either add a small Kunena helper for modal and
collapse with per-template class names, or consider retiring these two templates: decide before step 3.

## 6. Known bugs to fix along the way

- `crypsis*/assets/js/main.js` trigger the old Chosen event `liszt:updated` only; also trigger `chosen:updated`.
- `[map]` BBCode: post content goes into the HTML of a Google Maps info window; check the escaping when porting.
- Fixed in 5.3.0: `crypsisb4/assets/js/topic.js` quick reply (`$('#' . boxToOpen)`), all MooTools calls.

## 7. Testing

- `tests/e2e/jscheck.js` (headless Chrome via Playwright; see AGENTS.md 5.4) opens the installation wizard, every
  administrator page, the tabbed pages, a modal, the lists' "Clear" buttons and the main frontend pages, and fails on
  any JavaScript error. Extend it with every feature you port: the posting form (both editors, mentions, preview,
  drafts), attachments and avatar upload, polls, rating, the lightbox, the date fields, the search form.
- Run it on Joomla 3.x UTD **and** on a stock Joomla 3.10 site with Isis (jQuery and Bootstrap loaded by the template,
  no `CMS.*`), with each of the three frontend templates.
- The curl-based scenarios (`tests/e2e/run.php`) must keep passing: they cover the server side of every form.
