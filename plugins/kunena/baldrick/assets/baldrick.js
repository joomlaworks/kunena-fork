/**
 * Kunena - Baldrick AI: "Fetch models" (a searchable list, kept for an hour on the server) and "Test" buttons of the
 * plugin's settings.
 *
 * @copyright  Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       https://www.joomlaworks.net
 */
(function () {
    'use strict';

    function options() {
        return (window.Joomla && Joomla.getOptions) ? Joomla.getOptions('plg_kunena_baldrick') || {} : {};
    }

    function field(name) {
        return document.getElementById('jform_params_' + name);
    }

    function value(name) {
        var el = field(name);

        return el ? el.value : '';
    }

    // The connection a stage uses: the answer stage may share the triage connection
    function connection(stage) {
        var source = stage;

        if (stage === 'answer') {
            var same = document.querySelector('input[name="jform[params][answer_same_connection]"]:checked');

            if (!same || same.value === '1') {
                source = 'triage';
            }
        }

        return {
            stage: stage,
            provider: value(source + '_provider'),
            base_url: value(source + '_base_url'),
            api_key: value(source + '_api_key')
        };
    }

    function status(box, text, type) {
        var el = box.querySelector('.baldrick-model-status');

        el.className = 'baldrick-model-status small' + (type ? ' text-' + type : '');
        el.textContent = text;
    }

    function post(task, data, done) {
        var opts = options();
        var body = [];

        data[opts.token] = '1';

        Object.keys(data).forEach(function (key) {
            body.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
        });

        var xhr = new XMLHttpRequest();

        xhr.open('POST', opts.url + '&task=' + task, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        // The server gives up on the provider after 30 seconds
        xhr.timeout = 60000;
        xhr.onload = function () {
            var response = null;

            try {
                response = JSON.parse(xhr.responseText);
            } catch (e) {
                response = {success: false, message: opts.failed + ' (HTTP ' + xhr.status + ')'};
            }

            done(response);
        };
        xhr.onerror = xhr.ontimeout = function () {
            done({success: false, message: opts.failed});
        };
        xhr.send(body.join('&'));
    }

    function label(model) {
        var text = model.id;

        if (model.name && model.name !== model.id) {
            text += ' - ' + model.name;
        }

        var extra = [];

        if (model.context) {
            extra.push(Math.round(model.context / 1000) + 'K');
        }

        if (model.price_in !== undefined && model.price_out !== undefined) {
            extra.push('$' + model.price_in + ' / $' + model.price_out);
        }

        return extra.length ? text + ' (' + extra.join(', ') + ')' : text;
    }

    // Joomla's searchable list: Joomla 3.x UTD's CMS.chosen, or the jQuery Chosen of older Joomla 3 versions
    function searchable(list) {
        var opts = {
            search_contains: true,
            disable_search_threshold: 0,
            allow_single_deselect: false,
            width: '100%',
            placeholder_text_single: options().search,
            no_results_text: options().nomatch
        };

        if (window.CMS && CMS.chosen) {
            if (CMS.chosen.get(list)) {
                CMS.chosen.update(list);
            } else {
                list.removeAttribute('data-searchable');
                CMS.chosen.attach(list, opts);
            }
        } else if (window.jQuery && jQuery.fn.chosen) {
            var $list = jQuery(list);

            if ($list.data('chosen')) {
                $list.trigger('liszt:updated').trigger('chosen:updated');
            } else {
                $list.chosen(opts);
            }
        }
    }

    function onChange(list, handler) {
        // Chosen announces changes through jQuery when it is there
        if (window.jQuery) {
            jQuery(list).on('change', handler);
        } else {
            list.addEventListener('change', handler);
        }
    }

    function init(box) {
        var stage = box.getAttribute('data-stage');
        var input = box.querySelector('.baldrick-model-input');
        var picker = box.querySelector('.baldrick-model-picker');
        var list = box.querySelector('.baldrick-model-list');
        var showAll = box.querySelector('.baldrick-show-all');
        var fetchButton = box.querySelector('.baldrick-fetch');
        var models = [];
        var busy = false;

        function render() {
            var shown = models.filter(function (model) {
                return showAll.checked || model.chat !== false || model.id === input.value;
            });

            list.innerHTML = '';

            var first = document.createElement('option');

            first.value = '';
            first.textContent = options().choose;
            list.appendChild(first);

            shown.forEach(function (model) {
                var option = document.createElement('option');

                option.value = model.id;
                option.textContent = label(model);
                option.selected = model.id === input.value;
                list.appendChild(option);
            });

            picker.style.display = '';
            searchable(list);
        }

        function load(refresh) {
            if (busy) {
                return;
            }

            busy = true;
            fetchButton.disabled = true;
            status(box, options().working, 'info');

            var data = connection(stage);

            data.refresh = refresh ? '1' : '0';

            post('models', data, function (response) {
                busy = false;
                fetchButton.disabled = false;

                if (!response.success) {
                    status(box, response.message, 'error');

                    return;
                }

                models = response.models || [];
                render();
                status(box, response.message, 'success');
            });
        }

        fetchButton.addEventListener('click', function () {
            load(false);
        });

        box.querySelector('.baldrick-refresh').addEventListener('click', function (event) {
            event.preventDefault();
            load(true);
        });

        showAll.addEventListener('change', render);

        onChange(list, function () {
            if (!list.value) {
                return;
            }

            input.value = list.value;

            // Providers which publish their prices (OpenRouter): fill them in for the cost estimates
            var model = models.filter(function (item) {
                return item.id === list.value;
            })[0];

            if (model && model.price_in !== undefined && model.price_out !== undefined && field(stage + '_price_in')) {
                field(stage + '_price_in').value = model.price_in;
                field(stage + '_price_out').value = model.price_out;
                status(box, options().prices, 'success');
            }
        });

        box.querySelector('.baldrick-test').addEventListener('click', function () {
            var data = connection(stage);

            data.model = input.value;
            status(box, options().working, 'info');

            post('test', data, function (response) {
                status(box, response.message, response.success ? 'success' : 'error');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.forEach.call(document.querySelectorAll('.baldrick-model'), init);
    });
})();
