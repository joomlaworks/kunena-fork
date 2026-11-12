/**
 * Kunena template Blackadder - plain JavaScript (no jQuery). Every feature also works without it where HTML allows
 * (menus are <details>, forms submit normally).
 *
 * @copyright  Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU/GPL
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function each(root, selector, fn) {
        Array.prototype.forEach.call(root.querySelectorAll(selector), fn);
    }

    // Mentions (@) and emojis (:) with Kunena's atwho plugin (jQuery), when the page loads it
    function mentions(form, editor) {
        var $ = window.jQuery;

        if (!editor || !$ || !$.fn || !$.fn.atwho) {
            return;
        }

        var emojis = form.querySelector('#kemojis_allowed');
        var emojiUrl = form.querySelector('#kurl_emojis');
        var usersUrl = form.querySelector('#kurl_users');

        if (emojis && emojis.value === '1' && emojiUrl) {
            $(editor).atwho({
                at: ':',
                displayTpl: '<li data-value="${key}">${name} <img src="${url}" height="20" width="20" alt=""></li>',
                insertTpl: '${name}',
                callbacks: {
                    remoteFilter: function (query, callback) {
                        if (!query.length) {
                            return;
                        }

                        fetch(emojiUrl.value + (emojiUrl.value.indexOf('?') < 0 ? '?' : '&') + 'search=' + encodeURIComponent(query), { credentials: 'same-origin' })
                            .then(function (response) { return response.json(); })
                            .then(function (data) { callback(data.emojis || []); })
                            .catch(function () { callback([]); });
                    }
                }
            });
        }

        if (usersUrl) {
            $(editor).atwho({ at: '@', data: usersUrl.value, limit: 5 });
        }
    }

    // Text inserted at the caret, or wrapped around the selection
    function insertText(editor, before, after, fallback) {
        var start = editor.selectionStart;
        var end = editor.selectionEnd;
        var selected = editor.value.slice(start, end) || fallback || '';

        editor.value = editor.value.slice(0, start) + before + selected + (after || '') + editor.value.slice(end);
        editor.focus();
        editor.setSelectionRange(start + before.length, start + before.length + selected.length);
        editor.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function post(url, data) {
        var body = new URLSearchParams();

        Object.keys(data).forEach(function (key) { body.append(key, data[key]); });

        return fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.json(); });
    }

    // The posting form: BBCode toolbar, preview, uploads, poll options, category switching
    function postForm(form) {
        var editor = form.querySelector('[data-kb-message]');
        var preview = form.querySelector('[data-kb-preview]');
        var tools = form.querySelector('[data-kb-tools]');
        var category = form.querySelector('select[name="catid"]');
        var catid = function () {
            var field = category || form.querySelector('[data-kb-catid]');

            return field ? field.value : '';
        };
        var isNewTopic = (form.querySelector('input[name="task"]') || {}).value === 'post'
            && (form.querySelector('input[name="parentid"]') || {}).value === '0';

        if (!editor) {
            return;
        }

        mentions(form, editor);

        if (tools) {
            tools.hidden = false;
        }

        // Text carried over from the quick reply ("Open the full editor")
        try {
            var carried = window.localStorage.getItem('copyKunenaeditor');

            if (carried && !editor.value.trim()) {
                editor.value = carried;
            }

            window.localStorage.removeItem('copyKunenaeditor');
        } catch (e) {
            // Storage blocked
        }

        // Toolbar
        each(form, '[data-kb-bbcode]', function (button) {
            button.addEventListener('click', function () {
                var tag = button.getAttribute('data-kb-bbcode');
                var url;

                if (tag === 'url' || tag === 'img') {
                    url = window.prompt(button.title, 'https://');

                    if (!url || url === 'https://') {
                        return;
                    }

                    if (tag === 'img') {
                        insertText(editor, '[img]' + url + '[/img]', '', ' ');
                        return;
                    }

                    insertText(editor, '[url=' + url + ']', '[/url]', url);
                    return;
                }

                if (tag === 'ul' || tag === 'ol') {
                    var lines = editor.value.slice(editor.selectionStart, editor.selectionEnd).split('\n').filter(function (line) { return line.trim(); });
                    var items = (lines.length ? lines : ['']).map(function (line) { return '[li]' + line + '[/li]'; }).join('\n');

                    insertText(editor, '[' + tag + ']\n' + items + '\n[/' + tag + ']', '', ' ');
                    return;
                }

                insertText(editor, '[' + tag + ']', '[/' + tag + ']');
            });
        });

        // Write / Preview
        each(form, '[data-kb-tab]', function (tab) {
            tab.addEventListener('click', function () {
                var show = tab.getAttribute('data-kb-tab') === 'preview';

                each(form, '[data-kb-tab]', function (other) { other.setAttribute('aria-selected', String(other === tab)); });
                editor.hidden = show;

                if (tools) {
                    tools.hidden = show;
                }

                preview.hidden = !show;

                if (!show) {
                    editor.focus();
                    return;
                }

                preview.textContent = '…';
                post(form.getAttribute('data-kb-preview-url'), { body: editor.value })
                    .then(function (data) {
                        if (data && data.preview) {
                            preview.innerHTML = data.preview;
                        } else {
                            preview.textContent = form.getAttribute('data-kb-empty') || '';
                        }
                    })
                    .catch(function () { preview.textContent = ''; });
            });
        });

        // Attachments: uploaded at once, kept with the post through attachments[id] / attachment[id]
        var input = form.querySelector('[data-kb-upload]');
        var list = form.querySelector('[data-kb-files]');
        var note = form.querySelector('[data-kb-upload-error]');
        var submit = form.querySelector('button[type="submit"]');
        var pending = 0;

        var addItem = function (id, name, image) {
            var item = document.createElement('li');
            var label = document.createElement('span');
            var insert = document.createElement('button');
            var remove = document.createElement('button');

            item.className = 'kb-file';
            item.setAttribute('data-kb-file', id);
            item.setAttribute('data-kb-name', name);
            label.className = 'kb-file-name';
            label.textContent = name;
            insert.type = remove.type = 'button';
            insert.className = remove.className = 'kb-action';
            insert.setAttribute('data-kb-insert', '');
            insert.textContent = form.getAttribute('data-kb-insert-label') || 'Insert';
            remove.setAttribute('data-kb-remove', '');
            remove.textContent = form.getAttribute('data-kb-remove-label') || 'Remove';

            [['attachments', id], ['attachment', id]].forEach(function (pair) {
                var hidden = document.createElement('input');

                hidden.type = 'hidden';
                hidden.name = pair[0] + '[' + pair[1] + ']';
                hidden.value = '1';
                item.appendChild(hidden);
            });

            if (image) {
                var img = document.createElement('img');

                img.src = image;
                img.alt = '';
                img.width = img.height = 48;
                item.appendChild(img);
            }

            item.appendChild(label);
            item.appendChild(insert);
            item.appendChild(remove);
            list.appendChild(item);
        };

        var showError = function (text) {
            if (note) {
                note.hidden = !text;
                note.textContent = text || '';
            }
        };

        if (input && list) {
            // Uploaded in the background: the field itself is not sent with the form
            input.removeAttribute('name');

            input.addEventListener('change', function () {
                var files = Array.prototype.slice.call(input.files);
                var max = parseInt(form.getAttribute('data-kb-max-files'), 10) || 0;

                showError('');

                if (max && list.children.length + files.length > max) {
                    showError((form.getAttribute('data-kb-too-many') || '%s').replace('%s', max));
                    input.value = '';
                    return;
                }

                files.forEach(function (file) {
                    var data = new FormData();
                    var mesid = form.querySelector('input[name="mesid"]');

                    data.append('file', file);
                    data.append('catid', catid());
                    data.append('filename', file.name);
                    data.append('size', file.size);
                    data.append('mime', file.type);

                    if (mesid) {
                        data.append('mesid', mesid.value);
                    }

                    pending++;
                    submit.disabled = true;

                    fetch(form.getAttribute('data-kb-upload-url'), { method: 'POST', body: data, credentials: 'same-origin' })
                        .then(function (response) { return response.json(); })
                        .then(function (result) {
                            if (result && result.success && result.data && result.data.id) {
                                addItem(result.data.id, result.data.filename || file.name, /^image\//.test(file.type) ? URL.createObjectURL(file) : '');
                            } else {
                                var message = result && (result.message || (result.messages && result.messages[0] && result.messages[0].message));

                                showError(file.name + ': ' + (message || '?'));
                            }
                        })
                        .catch(function () { showError(file.name + ': ?'); })
                        .then(function () {
                            pending--;
                            submit.disabled = pending > 0;
                        });
                });

                input.value = '';
            });

            list.addEventListener('click', function (event) {
                var item = event.target.closest('[data-kb-file]');

                if (!item) {
                    return;
                }

                if (event.target.closest('[data-kb-insert]')) {
                    insertText(editor, '[attachment=' + item.getAttribute('data-kb-file') + ']' + item.getAttribute('data-kb-name') + '[/attachment]', '', ' ');

                    // Marked as shown in the text, as Kunena's other templates expect
                    if (form.getAttribute('data-kb-inline-url')) {
                        post(form.getAttribute('data-kb-inline-url'), { files_id: JSON.stringify([item.getAttribute('data-kb-file')]) }).catch(function () {});
                    }
                } else if (event.target.closest('[data-kb-remove]')) {
                    item.parentNode.removeChild(item);
                }
            });
        }

        // Poll: more options
        var options = form.querySelector('[data-kb-poll-options]');
        var addOption = form.querySelector('[data-kb-poll-add]');
        var newOption = 100;

        if (options && addOption) {
            addOption.addEventListener('click', function () {
                var max = parseInt(options.getAttribute('data-kb-max'), 10) || 0;

                if (max && options.children.length >= max) {
                    return;
                }

                var item = document.createElement('li');
                var field = options.querySelector('input').cloneNode(false);

                field.value = '';
                field.name = 'polloptionsID[new' + (newOption++) + ']';
                item.appendChild(field);
                options.appendChild(item);
                field.focus();
                addOption.hidden = !!max && options.children.length >= max;
            });
        }

        // Category: anonymous posting, polls and the category's template text follow the selected category
        var anonymousBox = form.querySelector('[data-kb-anonymous-box]');
        var poll = form.querySelector('[data-kb-poll]');
        var anonymousMap = JSON.parse(form.getAttribute('data-kb-anonymous') || '{}');
        var pollMap = JSON.parse(form.getAttribute('data-kb-polls') || '{}');

        var loadTemplate = function () {
            if (editor.value.trim() || !catid() || !form.getAttribute('data-kb-template-url')) {
                return;
            }

            post(form.getAttribute('data-kb-template-url'), { catid: catid() })
                .then(function (text) {
                    if (typeof text === 'string' && text && !editor.value.trim()) {
                        editor.value = text;
                    }
                })
                .catch(function () {});
        };

        if (category) {
            category.addEventListener('change', function () {
                var id = catid();

                if (anonymousBox) {
                    anonymousBox.hidden = !(id in anonymousMap);
                    anonymousBox.querySelector('input').checked = anonymousMap[id] === 1 || anonymousMap[id] === '1';
                }

                if (poll && !poll.hasAttribute('data-kb-poll-fixed')) {
                    poll.hidden = !(id in pollMap);
                }

                loadTemplate();
            });

            if (poll && !poll.hasAttribute('data-kb-poll-fixed')) {
                poll.hidden = !(catid() in pollMap);
            }
        }

        if (isNewTopic) {
            loadTemplate();
        }

        form.addEventListener('submit', function (event) {
            if (pending > 0) {
                event.preventDefault();
                return;
            }

            // New poll options are saved in the order of their keys: number them in the order shown
            var number = 0;

            each(form, '[data-kb-poll-options] input[name^="polloptionsID[new"]', function (field) {
                field.name = 'polloptionsID[new' + ('000' + (++number)).slice(-3) + ']';
            });
        });
    }

    ready(function () {
        var forum = document.querySelector('.kb');

        if (!forum) {
            return;
        }

        // ---- Colours: take the page's text and background colours (whatever the site template and its light/dark
        // scheme are), and mark dark pages; again whenever the template switches scheme
        var parseColor = function (value) {
            var match = /rgba?\(([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+%?))?\)/.exec(value || '');

            if (!match) {
                return null;
            }

            var alpha = match[4] === undefined ? 1 : parseFloat(match[4]) / (match[4].indexOf('%') > 0 ? 100 : 1);

            return { r: +match[1], g: +match[2], b: +match[3], a: alpha };
        };

        var adapt = function () {
            var node = forum.parentElement;
            var background = null;

            while (node && node.nodeType === 1) {
                var color = parseColor(getComputedStyle(node).backgroundColor);

                if (color && color.a > 0.5) {
                    background = color;
                    break;
                }

                node = node.parentElement;
            }

            background = background || { r: 255, g: 255, b: 255 };

            var text = getComputedStyle(forum.parentElement).color;
            var page = 'rgb(' + background.r + ', ' + background.g + ', ' + background.b + ')';
            var luminance = (0.2126 * background.r + 0.7152 * background.g + 0.0722 * background.b) / 255;

            forum.style.setProperty('--kb-auto-bg', page);
            forum.style.setProperty('--kb-auto-fg', text);
            forum.classList.toggle('kb--dark', luminance < 0.45);
            forum.classList.toggle('kb--light', luminance >= 0.45);
        };

        adapt();

        if (window.MutationObserver) {
            var pending = 0;
            var later = function () {
                // Again after the template's own colour transition
                window.clearTimeout(pending);
                window.requestAnimationFrame(adapt);
                pending = window.setTimeout(adapt, 400);
            };
            var observer = new MutationObserver(later);

            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-scheme', 'data-bs-theme', 'style'] });

            if (document.body) {
                observer.observe(document.body, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-scheme', 'style'] });
            }

            if (window.matchMedia) {
                var media = window.matchMedia('(prefers-color-scheme: dark)');

                if (media.addEventListener) {
                    media.addEventListener('change', later);
                }
            }
        }

        // ---- Login panel (opens over the content, no layout shift)
        var toggle = forum.querySelector('[data-kb-login-toggle]');
        var panel = forum.querySelector('#kb-login');

        function openLogin(open) {
            if (!toggle || !panel) {
                return;
            }

            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));

            if (open) {
                var field = panel.querySelector('input[name="username"]');

                if (field) {
                    field.focus();
                }
            }
        }

        if (toggle && panel) {
            toggle.addEventListener('click', function () {
                openLogin(panel.hidden);
            });
        }

        each(forum, '[data-kb-open-login]', function (link) {
            link.addEventListener('click', function (event) {
                if (!panel) {
                    return;
                }

                event.preventDefault();
                (toggle || panel).scrollIntoView({ block: 'center' });
                openLogin(true);
            });
        });

        // ---- Menus: one open at a time, closed by an outside click or Escape
        function closeMenus(except) {
            each(forum, 'details.kb-dropdown[open]', function (menu) {
                if (menu !== except) {
                    menu.open = false;
                }
            });
        }

        each(forum, 'details.kb-dropdown', function (menu) {
            menu.addEventListener('toggle', function () {
                if (menu.open) {
                    closeMenus(menu);
                }
            });
        });

        document.addEventListener('click', function (event) {
            each(forum, 'details.kb-dropdown[open]', function (menu) {
                if (!menu.contains(event.target)) {
                    menu.open = false;
                }
            });

            if (panel && !panel.hidden && !panel.contains(event.target) && toggle && !toggle.contains(event.target)
                && !(event.target.closest && event.target.closest('[data-kb-open-login]'))) {
                openLogin(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            closeMenus(null);

            if (panel && !panel.hidden) {
                openLogin(false);
                toggle.focus();
            }
        });

        // ---- Copy link
        each(forum, '[data-kb-copy]', function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();

                var url = new URL(button.getAttribute('data-kb-copy'), window.location.href).href;
                var label = button.querySelector('[data-kb-copy-label]');
                var done = function () {
                    if (label) {
                        var text = label.textContent;

                        label.textContent = button.getAttribute('data-kb-copied') || text;
                        window.setTimeout(function () { label.textContent = text; }, 1600);
                    }
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(done, function () { window.prompt('', url); });
                } else {
                    window.prompt('', url);
                }
            });
        });

        // ---- Bulk actions on thread lists (moderators)
        each(forum, 'form[data-kb-bulk]', function (form) {
            var all = form.querySelector('.kb-checkall');
            var task = form.querySelector('select[name="task"]');
            var target = form.querySelector('select[name="target"]');

            if (all) {
                all.addEventListener('change', function () {
                    each(form, '.kb-check-topic', function (box) { box.checked = all.checked; });
                });
            }

            if (task && target) {
                var sync = function () { target.disabled = task.value !== 'move'; };

                task.addEventListener('change', sync);
                sync();
            }
        });

        // ---- Filters which apply at once
        each(forum, '[data-kb-autosubmit]', function (field) {
            field.addEventListener('change', function () {
                if (field.form) {
                    field.form.submit();
                }
            });
        });

        // ---- Reply box at the bottom of a thread
        var reply = forum.querySelector('#kb-reply form');

        if (reply) {
            var editor = reply.querySelector('textarea[name="message"]');
            var parent = reply.querySelector('[data-kb-parent]');
            var replyTo = reply.querySelector('[data-kb-reply-to]');
            var replyLabel = reply.querySelector('[data-kb-reply-to-label]');

            var setParent = function (id, name) {
                parent.value = id || parent.getAttribute('data-kb-default');

                if (replyTo && replyLabel) {
                    replyTo.hidden = !id;
                    replyLabel.textContent = id ? (replyLabel.getAttribute('data-kb-template') || '%s').replace('%s', name) : '';
                }
            };

            // A post's "Reply": answer that post
            each(forum, '[data-kb-reply]', function (link) {
                link.addEventListener('click', function (event) {
                    var post = link.closest('.kb-post');
                    var author = post ? post.querySelector('.kb-post-author') : null;

                    event.preventDefault();
                    setParent(link.getAttribute('data-kb-reply'), author ? author.textContent.trim() : '');
                    reply.scrollIntoView({ block: 'center' });
                    editor.focus({ preventScroll: true });
                });
            });

            each(reply, '[data-kb-reply-reset]', function (button) {
                button.addEventListener('click', function () {
                    setParent(null, '');
                    editor.focus();
                });
            });

            // "Open the full editor" takes the text along (Kunena's editor reads it back)
            each(reply, '[data-kb-full-editor]', function (link) {
                link.addEventListener('click', function () {
                    try {
                        window.localStorage.setItem('copyKunenaeditor', editor.value);
                    } catch (e) {
                        // Storage blocked: the full editor opens empty
                    }
                });
            });

            reply.addEventListener('submit', function () {
                try {
                    window.localStorage.removeItem('copyKunenaeditor');
                } catch (e) {
                    // Nothing to clean up
                }
            });

            mentions(reply, editor);
        }

        // ---- Lightbox for images (Kunena's "lightbox" option, on by default): a <dialog>; the arrows (buttons or keys)
        // go through the images of the same post; a click on the image or Escape closes it
        var lightbox = null;
        var gallery = [];
        var current = 0;

        var show = function (index) {
            current = (index + gallery.length) % gallery.length;

            var link = gallery[current];
            var img = lightbox.querySelector('img');

            img.src = link.href;
            img.alt = link.title || '';
            lightbox.classList.toggle('kb-lightbox--single', gallery.length < 2);
        };

        var build = function () {
            lightbox = document.createElement('dialog');
            lightbox.className = 'kb-lightbox';
            lightbox.innerHTML = '<img alt="">'
                + '<button type="button" class="kb-lightbox-prev" aria-label="&larr;">&#8249;</button>'
                + '<button type="button" class="kb-lightbox-next" aria-label="&rarr;">&#8250;</button>'
                + '<button type="button" class="kb-lightbox-close" aria-label="&times;" autofocus>&times;</button>';
            lightbox.addEventListener('click', function (event) {
                if (event.target.closest('.kb-lightbox-prev')) {
                    show(current - 1);
                } else if (event.target.closest('.kb-lightbox-next')) {
                    show(current + 1);
                } else {
                    lightbox.close();
                }
            });
            lightbox.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowLeft') {
                    show(current - 1);
                } else if (event.key === 'ArrowRight') {
                    show(current + 1);
                }
            });
            document.body.appendChild(lightbox);
        };

        forum.addEventListener('click', function (event) {
            var link = event.target.closest && event.target.closest('a[data-kb-lightbox]');

            if (!link || !window.HTMLDialogElement || event.ctrlKey || event.metaKey || event.shiftKey) {
                return;
            }

            event.preventDefault();

            if (!lightbox) {
                build();
            }

            gallery = Array.prototype.slice.call((link.closest('.kb-post') || forum).querySelectorAll('a[data-kb-lightbox]'));
            show(Math.max(0, gallery.indexOf(link)));
            lightbox.showModal();
        });

        // ---- Tabs: the sections' titles become a tab bar (without JavaScript every section is shown)
        each(forum, '[data-kb-tabs]', function (box) {
            var panels = Array.prototype.slice.call(box.querySelectorAll(':scope > [data-kb-tab-panel]'));
            var bar = document.createElement('div');

            if (panels.length < 2) {
                return;
            }

            bar.className = 'kb-tabbar';
            bar.setAttribute('role', 'tablist');

            var select = function (index, focus) {
                panels.forEach(function (panel, i) {
                    panel.hidden = i !== index;
                    bar.children[i].setAttribute('aria-selected', String(i === index));
                    bar.children[i].tabIndex = i === index ? 0 : -1;
                });

                if (focus) {
                    bar.children[index].focus();
                }
            };

            panels.forEach(function (panel, i) {
                var tab = document.createElement('button');
                var title = panel.querySelector('[data-kb-tab-title]');

                tab.type = 'button';
                tab.className = 'kb-action';
                tab.setAttribute('role', 'tab');
                tab.setAttribute('aria-controls', panel.id);
                tab.textContent = title ? title.textContent.trim() : String(i + 1);
                tab.addEventListener('click', function () { select(i, false); });
                tab.addEventListener('keydown', function (event) {
                    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                        select((i + (event.key === 'ArrowRight' ? 1 : panels.length - 1)) % panels.length, true);
                    }
                });
                bar.appendChild(tab);
                panel.setAttribute('role', 'tabpanel');

                if (title) {
                    title.classList.add('kb-sr');
                }
            });

            box.insertBefore(bar, box.firstChild);
            box.classList.add('kb-tabs--ready');

            var active = panels.findIndex(function (panel) { return panel.hasAttribute('data-kb-active'); });
            var fromHash = panels.findIndex(function (panel) { return '#' + panel.id === window.location.hash; });

            select(fromHash >= 0 ? fromHash : Math.max(0, active), false);
        });

        // ---- Links and buttons which ask first (e.g. resetting a poll's votes, deleting files)
        each(forum, 'a[data-kb-confirm], button[data-kb-confirm]', function (link) {
            link.addEventListener('click', function (event) {
                if (!window.confirm(link.getAttribute('data-kb-confirm'))) {
                    event.preventDefault();
                }
            });
        });

        // ---- Moderation (move / merge / split): the chosen category's threads, and the fields each choice needs
        each(forum, 'form[data-kb-moderate]', function (form) {
            var categories = form.querySelector('#kmod_categories');
            var topics = form.querySelector('#kmod_topics');
            var targetId = form.querySelector('[data-kb-target-id]');
            var subject = form.querySelector('[data-kb-subject]');

            if (!topics) {
                return;
            }

            var sync = function () {
                if (targetId) {
                    targetId.hidden = topics.value !== '-1';
                }

                if (subject) {
                    subject.hidden = topics.value !== '0';
                }
            };

            topics.addEventListener('change', sync);
            sync();

            if (categories) {
                categories.addEventListener('change', function () {
                    var url = form.getAttribute('data-kb-topics-url');

                    fetch(url + (url.indexOf('?') < 0 ? '?' : '&') + 'catid=' + encodeURIComponent(categories.value), { credentials: 'same-origin' })
                        .then(function (response) { return response.json(); })
                        .then(function (json) {
                            // Keep the first two choices (new topic / enter an ID), then the category's threads
                            while (topics.options.length > 2) {
                                topics.remove(2);
                            }

                            Object.keys(json || {}).forEach(function (key) {
                                var group = json[key];

                                Object.keys(group || {}).forEach(function (index) {
                                    var item = group[index];

                                    if (item && item.id) {
                                        topics.add(new Option(item.subject, item.id));
                                    }
                                });
                            });

                            sync();
                        })
                        .catch(function () {});
                });
            }
        });

        // ---- Profile editing: avatar upload (at once, through Kunena's upload task) and clearing stored drafts
        each(forum, '[data-kb-avatar-upload]', function (box) {
            var input = box.querySelector('[data-kb-avatar-file]');
            var status = box.querySelector('[data-kb-avatar-status]');
            var preview = forum.querySelector('[data-kb-avatar-preview] .kb-avatar');

            if (!input) {
                return;
            }

            input.addEventListener('change', function () {
                var file = input.files[0];
                var data = new FormData();

                if (!file) {
                    return;
                }

                data.append('file', file);
                data.append('userid', box.getAttribute('data-kb-userid'));
                data.append('filename', file.name);
                data.append('size', file.size);
                data.append('mime', file.type);
                status.textContent = '…';

                fetch(box.getAttribute('data-kb-url'), { method: 'POST', body: data, credentials: 'same-origin' })
                    .then(function (response) { return response.json(); })
                    .then(function (result) {
                        if (result && result.success) {
                            status.textContent = box.getAttribute('data-kb-done') || '✓';

                            if (preview) {
                                preview.innerHTML = '';
                                var img = document.createElement('img');

                                img.src = URL.createObjectURL(file);
                                img.alt = '';
                                preview.appendChild(img);
                            }
                        } else {
                            status.textContent = (result && (result.message || (result.messages && result.messages[0] && result.messages[0].message))) || '?';
                        }
                    })
                    .catch(function () { status.textContent = '?'; });

                input.value = '';
            });
        });

        each(forum, '[data-kb-clear-storage]', function (button) {
            button.addEventListener('click', function () {
                try {
                    window.localStorage.clear();
                    button.textContent = button.getAttribute('data-kb-done') || button.textContent;
                } catch (e) {
                    // Storage blocked: nothing stored either
                }
            });
        });

        // ---- Posting form (new topic, reply, edit)
        each(forum, 'form[data-kb-postform]', postForm);
    });
})();
