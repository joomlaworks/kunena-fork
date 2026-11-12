/**
 * Kunena Component - Tools: long running tasks over AJAX (Empty Trash, Sample Data)
 *
 * A button with the class "kunena-ajax-task" and these data attributes starts a task:
 *  - data-url:      task URL (format=json and a form token included); answers JSON with success, done, status,
 *                   message and errors
 *  - data-confirm:  language key of the confirmation question (optional)
 *  - data-progress: selector of the progress container (with .progress .bar, .kunena-ajax-message, .kunena-ajax-errors)
 *  - data-reload:   reload the page when done (optional)
 *
 * @copyright  Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       https://www.joomlaworks.net
 */
jQuery(function ($) {
	'use strict';

	$('.kunena-ajax-task').on('click', function (event) {
		event.preventDefault();

		var $button = $(this);
		var confirmKey = $button.data('confirm');

		if (confirmKey && !window.confirm(Joomla.JText._(confirmKey))) {
			return;
		}

		var url = $button.data('url');
		var $progress = $($button.data('progress'));
		var $bar = $progress.find('.bar');
		var $message = $progress.find('.kunena-ajax-message');
		var $errors = $progress.find('.kunena-ajax-errors');

		function finish(cssClass) {
			$progress.find('.progress').removeClass('active');
			$bar.addClass(cssClass);
		}

		function showErrors(errors) {
			$errors.empty();

			$.each(errors || [], function (i, error) {
				$('<li>').text(error).appendTo($errors);
			});
		}

		function run(step) {
			$.ajax({
				type: 'POST',
				url: url,
				data: {step: step},
				dataType: 'json',
				cache: false,
				timeout: 180000
			}).done(function (data) {
				if ('status' in data) {
					$bar.css('width', data.status);
				}

				$message.text(data.message || '');
				showErrors(data.errors);

				if (!data.success) {
					finish('bar-danger');

					return;
				}

				if (data.done) {
					finish(data.errors && data.errors.length ? 'bar-warning' : 'bar-success');
					$('[data-kunena-ajax-zero]').text('0');

					if ($button.data('reload')) {
						window.setTimeout(function () {
							window.location.reload();
						}, 2500);
					}

					return;
				}

				run('run');
			}).fail(function (xhr, status) {
				finish('bar-danger');
				$message.text(Joomla.JText._('COM_KUNENA_AJAX_ERROR') + ': ' + (status === 'timeout'
					? Joomla.JText._('COM_KUNENA_AJAXMODAL_ERROR_TIMEOUT_BODY')
					: (xhr.status ? xhr.status + ' ' + xhr.statusText : Joomla.JText._('COM_KUNENA_AJAXMODAL_ERROR_UNKNOWN_BODY'))));
			});
		}

		$('.kunena-ajax-task').prop('disabled', true);
		$progress.show();
		$message.text(Joomla.JText._('COM_KUNENA_AJAX_TASK_WORKING'));
		run('init');
	});
});
