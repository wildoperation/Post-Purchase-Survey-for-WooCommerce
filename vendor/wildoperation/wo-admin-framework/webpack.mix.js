const mix = require("laravel-mix");

mix
	.sourceMaps(false, "source-map")
	.webpackConfig({ devtool: "source-map" })
	.js("src/js/repeater.js", "dist/js/repeater.js")
	.js("src/js/media.js", "dist/js/media.js")
	.js("src/js/color-picker.js", "dist/js/color-picker.js")
	.sass("src/scss/admin.scss", "dist/css/")
	.options({
		processCssUrls: false,
	});
