import domReady from '@wordpress/dom-ready';
import apiFetch from '@wordpress/api-fetch';

domReady(() => {
	console.log('EA Gaming Engine frontend initializing…');

	const launcherEls = document.querySelectorAll('.ea-gaming-launcher');
	launcherEls.forEach((launcher) => {
		const courseId = launcher.dataset.courseId;
		const theme = launcher.dataset.theme || 'playful';
		const preset = launcher.dataset.preset || 'classic';

		const startBtn = launcher.querySelector('.ea-gaming-start');
		if (startBtn) {
			startBtn.addEventListener('click', () => {
				startGame(courseId, theme, preset);
			});
		}
	});
});

async function startGame(courseId, theme, preset) {
	try {
		// Create session via EA Gaming Engine REST API
		const sessionResp = await apiFetch({
			path: '/ea-gaming/v1/sessions',
			method: 'POST',
			data: { course_id: courseId, theme, preset }
		});

		console.log('Game session started', sessionResp);

		// Launch Phaser game instance
		if (window.EAGameEngine && typeof window.EAGameEngine.launch === 'function') {
			window.EAGameEngine.launch({
				courseId,
				sessionId: sessionResp.id,
				theme,
				preset
			});
		} else {
			console.error('EAGameEngine launcher not available on window');
		}
	} catch (err) {
		console.error('Error starting game session', err);
		alert('Could not start game. Please try again.');
	}
}