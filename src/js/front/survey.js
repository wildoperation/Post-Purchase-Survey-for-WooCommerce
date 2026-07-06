/**
 * Post-Purchase Survey front-end submission.
 * Progressive enhancement: without this file the form submits to admin-post.php.
 */
(function () {
	"use strict";

	function onReady(fn) {
		if (document.readyState !== "loading") {
			fn();
		} else {
			document.addEventListener("DOMContentLoaded", fn);
		}
	}

	onReady(function () {
		if (typeof pps_survey === "undefined" || !pps_survey.ajax_url) {
			return;
		}

		document.querySelectorAll(".pps-survey__form").forEach(function (form) {
			let submitting = false;

			form.addEventListener("submit", function (e) {
				e.preventDefault();

				if (submitting) {
					return;
				}

				submitting = true;

				const container = form.closest(".pps-survey");
				const button = form.querySelector(".pps-survey__submit");
				const error = form.querySelector(".pps-survey__error");

				if (button) {
					button.disabled = true;
				}

				if (error) {
					error.textContent = "";
				}

				window
					.fetch(pps_survey.ajax_url, {
						method: "POST",
						credentials: "same-origin",
						body: new FormData(form),
					})
					.then(function (response) {
						return response.json();
					})
					.then(function (json) {
						if (json && json.success && json.data && json.data.html) {
							if (container) {
								container.outerHTML = json.data.html;
							}
						} else {
							submitting = false;

							if (button) {
								button.disabled = false;
							}

							if (error) {
								error.textContent =
									json && json.data && json.data.message
										? json.data.message
										: "";
							}
						}
					})
					.catch(function () {
						/* Fall back to a normal POST if AJAX fails. */
						submitting = false;

						if (button) {
							button.disabled = false;
						}

						form.submit();
					});
			});
		});
	});
})();
