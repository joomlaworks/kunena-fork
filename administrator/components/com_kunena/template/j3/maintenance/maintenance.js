/**
 * Kunena > Maintenance: runs each job with fetch() and shows its result below its Run button. Jobs that take long
 * answer "done": false and are called again (step=run) until they finish.
 *
 * @copyright  Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU/GPL
 */
(function () {
    'use strict';

    var MAX_STEPS = 10000;

    function text(tag, value, className) {
        var node = document.createElement(tag);

        node.textContent = value;

        if (className) {
            node.className = className;
        }

        return node;
    }

    function table(columns, rows) {
        var wrap = document.createElement('table'),
            head = wrap.createTHead().insertRow(),
            body = wrap.createTBody();

        wrap.className = 'table table-striped table-condensed';

        Object.keys(columns).forEach(function (key) {
            head.appendChild(text('th', columns[key]));
        });

        rows.forEach(function (row) {
            var tr = body.insertRow();

            Object.keys(columns).forEach(function (key) {
                tr.insertCell().textContent = row[key] === undefined ? '' : String(row[key]);
            });
        });

        return wrap;
    }

    function show(result, data, running) {
        result.hidden = false;
        result.className = 'kunena-maintenance-result alert ' + (running ? 'alert-info' : (data.success ? 'alert-success' : 'alert-error'));
        result.textContent = '';
        result.appendChild(text('span', (running && data.status ? data.status + ' - ' : '') + (data.message || '')));

        if (data.errors && data.errors.length) {
            var list = document.createElement('ul');

            data.errors.forEach(function (error) {
                list.appendChild(text('li', error));
            });
            result.appendChild(list);
        }

        if (data.rows && data.rows.length && data.columns) {
            result.appendChild(table(data.columns, data.rows));
        }
    }

    function run(form) {
        var button = form.querySelector('button[type="submit"]'),
            result = form.querySelector('.kunena-maintenance-result'),
            steps = 0;

        function step(name) {
            var body = new FormData(form);

            body.set('step', name);

            return fetch(form.action, {method: 'POST', body: body, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function (response) {
                    return response.text().then(function (raw) {
                        try {
                            return JSON.parse(raw);
                        } catch (e) {
                            return {success: false, done: true, message: 'HTTP ' + response.status + ': ' + raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300)};
                        }
                    });
                })
                .then(function (data) {
                    steps++;

                    if (data.success && !data.done && steps < MAX_STEPS) {
                        show(result, data, true);

                        return step('run');
                    }

                    show(result, data, false);
                });
        }

        button.disabled = true;
        show(result, {success: true, message: form.getAttribute('data-working') || '…'}, true);

        step('init')
            .catch(function (error) {
                show(result, {success: false, message: String(error)}, false);
            })
            .then(function () {
                button.disabled = false;
            });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!form.classList || !form.classList.contains('kunena-maintenance-job')) {
            return;
        }

        event.preventDefault();

        if (form.getAttribute('data-confirm') && !window.confirm(form.getAttribute('data-confirm'))) {
            return;
        }

        run(form);
    });
}());
