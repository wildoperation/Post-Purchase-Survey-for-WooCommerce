jQuery(document).ready(function ($) {
	const $womedia = $(".woadmin-media-upload");
	$womedia.each(function () {
		let womedia_uploader;
		const $this = $(this);
		const $input = $this.find(".woadmin-media-input");
		const $button = $this.find(".woadmin-media-button");

		$button.on("click", function (e) {
			e.preventDefault();

			//If the uploader object has already been created, reopen the dialog
			if (womedia_uploader) {
				womedia_uploader.open();
				return;
			}

			//Extend the wp.media object
			womedia_uploader = wp.media.frames.file_frame = wp.media({
				title: "Choose Media",
				button: {
					text: "Choose Media",
				},
				multiple: false,
			});

			womedia_uploader.on("select", function () {
				attachment = womedia_uploader.state().get("selection").first().toJSON();
				$input.val(attachment.id);
				const url =
					attachment.sizes && attachment.sizes.thumbnail
						? attachment.sizes.thumbnail.url
						: attachment.url;
				$this
					.siblings(".woadmin-media-preview")
					.html(
						`<img src="${url}" alt="" /><button class="woadmin-media-remove">X</button>`
					);
			});

			//Open the uploader dialog
			womedia_uploader.open();
		});

		const $preview = $this.siblings(".woadmin-media-preview");

		if ($preview.length > 0) {
			$preview.on("click", ".woadmin-media-remove", function (e) {
				e.preventDefault();
				$input.val("");
				$preview.html("");
			});
		}
	});
});
