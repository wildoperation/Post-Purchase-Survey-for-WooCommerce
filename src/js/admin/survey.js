/**
 * The Survey page question picker: AJAX search, sortable selection, max-question limit.
 */
jQuery(document).ready(function ($) {
	"use strict";

	if (typeof pps_survey_admin === "undefined") {
		return;
	}

	const $picker = $(".pps-question-picker");

	if (!$picker.length) {
		return;
	}

	const max = parseInt($picker.data("max"), 10) || 1;
	const $list = $picker.find(".pps-question-picker__selected");
	const $search = $picker.find(".pps-question-picker__search");
	const $searchWrap = $picker.find(".pps-question-picker__search-wrap");
	const $maxNote = $picker.find(".pps-question-picker__max-note");

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
			'<li class="pps-question-picker__question" data-id="' +
			parseInt(item.id, 10) +
			'">' +
			'<input type="hidden" name="' +
			pps_survey_admin.field_name +
			'" value="' +
			parseInt(item.id, 10) +
			'" />' +
			'<span class="pps-question-picker__handle dashicons dashicons-menu" aria-hidden="true"></span>' +
			'<span class="pps-question-picker__title">' +
			escapeHtml(item.title) +
			"</span>";

		if (item.status !== "publish") {
			html +=
				'<span class="pps-badge pps-badge--' +
				escapeHtml(item.status) +
				'">' +
				escapeHtml(item.status_label) +
				"</span>";
		}

		if (item.edit_url) {
			html +=
				'<a href="' +
				escapeHtml(item.edit_url) +
				'" class="pps-question-picker__edit">' +
				escapeHtml(pps_survey_admin.i18n.edit) +
				"</a>";
		}

		html +=
			'<button type="button" class="button-link-delete pps-question-picker__remove">' +
			escapeHtml(pps_survey_admin.i18n.remove) +
			"</button></li>";

		$list.append(html);
		refreshState();
	}

	$list.on("click", ".pps-question-picker__remove", function (e) {
		e.preventDefault();
		$(this).closest("li").remove();
		refreshState();
	});

	$list.sortable({
		handle: ".pps-question-picker__handle",
		axis: "y",
	});

	$search.autocomplete({
		minLength: 0,
		delay: 250,
		source: function (request, response) {
			$.getJSON(
				pps_survey_admin.ajax_url,
				{
					action: "pps_search_questions",
					nonce: pps_survey_admin.nonce,
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
