const mix = require("laravel-mix");

mix
	.sourceMaps(false, "source-map")
	.js("src/js/front/survey.js", "dist/js/survey.js")
	.js("src/js/admin/survey.js", "dist/js/survey-admin.js")
	.sass("src/scss/front.scss", "dist/css/")
	.sass("src/scss/admin.scss", "dist/css/")
	.options({
		processCssUrls: false,
	});
