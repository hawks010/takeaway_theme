document.addEventListener('DOMContentLoaded', function () {
	var legacyMenu = document.getElementById('mainmenu');
	if (legacyMenu) {
		legacyMenu.setAttribute('hidden', 'hidden');
		legacyMenu.setAttribute('aria-hidden', 'true');
	}

	if (document.getElementById('content')) {
		return;
	}

	var target = document.querySelector(
		'main, [role="main"], #primary, .site-main, .elementor-location-single, .elementor-location-archive, .elementor-location-page, .elementor.elementor-location-header + .elementor'
	);

	if (!target) {
		target = document.querySelector('.elementor[data-elementor-type="wp-page"], .elementor[data-elementor-type="single-page"], .elementor-page');
	}

	if (!target) {
		return;
	}

	target.id = 'content';
	target.setAttribute('tabindex', '-1');
});
