jQuery(document).ready(function ($) {
	$(".wo-repeater").each(function () {
		let isSortable = true;
		let useArrayKeys = false;
		let nextKey = 1;

		const $repeater = $(this);
		const $tbody = $repeater.find("tbody");
		const $add = $repeater.find(".wometa-repeater-controls--add");
		const $remove = $repeater.find(".wometa-repeater-controls--remove");

		if ($repeater.hasClass("wometa-nosort")) {
			isSortable = false;
		}

		if ($repeater.data("usekeys") === true) {
			useArrayKeys = true;
			nextKey = $repeater.find(".wometa-repeater-row").length;
		}

		if (isSortable) {
			$tbody.sortable();

			if (wometa_repeater.has_sort_handle !== "no") {
				$tbody.sortable(
					"option",
					"handle",
					wometa_repeater.sort_handle_selector
				);
			}
		}

		function resetRowValues($row) {
			$row.find("input").val("");
			$row.find("textarea").text("");
			$row.find("select").each(function () {
				const $this = $(this);
				if ($this.find('option[value=""]').length > 0) {
					$this.val("").change();
				} else {
					$this.val($this.find("option:first").val());
				}
			});

			if (isSortable) {
				$tbody.sortable("refresh");
			}

			$repeater.trigger("resetrows");
		}

		$add.on("click", function (e) {
			e.preventDefault();

			const $row = $(this).closest(".wometa-repeater-row");
			const $newRow = $row.clone(true);

			if (useArrayKeys) {
				$newRow.find("input, select, textarea").each(function () {
					const $this = $(this);
					const name = $this.attr("name");
					const id = $this.attr("id");

					if (name && typeof name !== "undefined") {
						$this.attr("name", name.replace(/\[(\d+)\]/, "[" + nextKey + "]"));
					}

					if (id && typeof id !== "undefined") {
						$this.attr("id", id.replace(/\[(\d+)\]/, "[" + nextKey + "]"));
					}
				});

				nextKey++;
			}

			$newRow.insertAfter($row);

			resetRowValues($newRow);
		});

		$remove.on("click", function (e) {
			e.preventDefault();

			const $row = $(this).closest(".wometa-repeater-row");

			if ($repeater.find(".wometa-repeater-row").length === 1) {
				resetRowValues($row);
			} else {
				$row.remove();
			}
		});
	});
});
