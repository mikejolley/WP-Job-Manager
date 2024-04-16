const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

const files = {
	'js/admin': 'js/admin.js',
	'js/ajax-file-upload': 'js/ajax-file-upload.js',
	'js/ajax-filters': 'js/ajax-filters.js',
	'js/datepicker': 'js/datepicker.js',
	'js/job-application': 'js/job-application.js',
	'js/job-dashboard': 'js/job-dashboard.js',
	'js/job-submission': 'js/job-submission.js',
	'js/multiselect': 'js/multiselect.js',
	'js/term-multiselect': 'js/term-multiselect.js',
	'js/wpjm-stats': 'js/wpjm-stats.js',
	'js/admin/job-editor': 'js/admin/job-editor.js',
	'js/admin/wpjm-notice-dismiss': 'js/admin/wpjm-notice-dismiss.js',
	'js/admin/job-tags-upsell': 'js/admin/job-tags-upsell.js',
	'css/admin': 'css/admin.scss',
	'css/admin-notices': 'css/admin-notices.scss',
	'css/admin-landing': 'css/admin-landing.scss',
	'css/wpjm-brand': 'css/wpjm-brand.scss',
	'css/frontend': 'css/frontend.scss',
	'css/ui': 'css/ui.scss',
	'css/job-listings': 'css/job-listings.scss',
	'css/job-submission': 'css/job-submission.scss',
	'css/job-dashboard': 'css/job-dashboard.scss',
	'css/setup': 'css/setup.scss',
	'css/menu': 'css/menu.scss',
};

const baseDist = 'assets/dist/';

Object.keys(files).forEach(function (key) {
	files[key] = path.resolve('./assets', files[key]);
});

module.exports = {
	...defaultConfig,
	entry: files,
	output: {
		path: path.resolve('.', baseDist),
	},
};
