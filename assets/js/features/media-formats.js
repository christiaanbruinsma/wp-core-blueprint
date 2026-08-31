const svgToggle = document.querySelector('[data-cb-media-formats-svg-toggle]');
const svgProtection = document.querySelector('[data-cb-media-formats-svg-protection]');

if (svgToggle && svgProtection) {
	const sync = () => {
		svgProtection.hidden = !svgToggle.checked;
	};
	svgToggle.addEventListener('change', sync);
	sync();
}
