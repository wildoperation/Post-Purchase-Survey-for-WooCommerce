/**
 * The Survey page question picker: AJAX search, sortable selection, max-question limit.
 */
jQuery(document).ready(function ($) {
	"use strict";

	if (typeof ppsfw_survey_admin === "undefined") {
		return;
	}

	const $picker = $(".ppsfw-question-picker");

	if (!$picker.length) {
		return;
	}

	const max = parseInt($picker.data("max"), 10) || 1;
	const $list = $picker.find(".ppsfw-question-picker__selected");
	const $search = $picker.find(".ppsfw-question-picker__search");
	const $searchWrap = $picker.find(".ppsfw-question-picker__search-wrap");
	const $maxNote = $picker.find(".ppsfw-question-picker__max-note");

	function selectedIds() {
		return $list
			.find("input[type=hidden]")
			.map(function () {
				return parseInt($(this).val(), 10);
			})
			.get();
	}

	function refreshState() {
		const atMax = selectedIds().length >= max;

		$searchWrap.toggle(!atMax);
		$maxNote.toggle(atMax);
	}

	function escapeHtml(text) {
		return $("<span></span>").text(text).html();
	}

	function addRow(item) {
		if (selectedIds().indexOf(item.id) !== -1 || selectedIds().length >= max) {
			return;
		}

		let html =
			'<li class="ppsfw-question-picker__question" data-id="' +
			parseInt(item.id, 10) +
			'">' +
			'<input type="hidden" name="' +
			ppsfw_survey_admin.field_name +
			'" value="' +
			parseInt(item.id, 10) +
			'" />' +
			'<span class="ppsfw-question-picker__handle dashicons dashicons-menu" aria-hidden="true"></span>' +
			'<span class="ppsfw-question-picker__title">' +
			escapeHtml(item.title) +
			"</span>";

		if (item.status !== "publish") {
			html +=
				'<span class="ppsfw-badge ppsfw-badge--' +
				escapeHtml(item.status) +
				'">' +
				escapeHtml(item.status_label) +
				"</span>";
		}

		if (item.edit_url) {
			html +=
				'<a href="' +
				escapeHtml(item.edit_url) +
				'" class="ppsfw-question-picker__edit">' +
				escapeHtml(ppsfw_survey_admin.i18n.edit) +
				"</a>";
		}

		html +=
			'<button type="button" class="button-link-delete ppsfw-question-picker__remove">' +
			escapeHtml(ppsfw_survey_admin.i18n.remove) +
			"</button></li>";

		$list.append(html);
		refreshState();
	}

	$list.on("click", ".ppsfw-question-picker__remove", function (e) {
		e.preventDefault();
		$(this).closest("li").remove();
		refreshState();
	});

	$list.sortable({
		handle: ".ppsfw-question-picker__handle",
		axis: "y",
	});

	$search.autocomplete({
		minLength: 0,
		delay: 250,
		source: function (request, response) {
			$.getJSON(
				ppsfw_survey_admin.ajax_url,
				{
					action: "ppsfw_search_questions",
					nonce: ppsfw_survey_admin.nonce,
					q: request.term,
				},
				function (json) {
					if (!json || !json.success) {
						response([]);
						return;
					}

					const existing = selectedIds();

					response(
						$.map(json.data, function (item) {
							if (existing.indexOf(item.id) !== -1) {
								return null;
							}

							return {
								label:
									item.title +
									(item.status !== "publish"
										? " — " + item.status_label
										: ""),
								value: "",
								item: item,
							};
						})
					);
				}
			);
		},
		select: function (event, ui) {
			addRow(ui.item.item);
			$(this).val("");
			return false;
		},
		focus: function () {
			return false;
		},
	});

	$search.on("focus", function () {
		$(this).autocomplete("search", $(this).val());
	});

	refreshState();
});
