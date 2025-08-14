import domReady from '@wordpress/dom-ready';
import apiFetch from '@wordpress/api-fetch';

domReady(() => {
	console.log('EA Gaming Engine frontend initializing…');

	// Utilities
	const $ = (sel, ctx = document) => ctx.querySelector(sel);
	const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

	// Modal controls
	const modal = $('#ea-gaming-modal');
	const overlay = modal ? $('.ea-gaming-modal-overlay', modal) : null;
	const closeBtn = modal ? $('.ea-gaming-modal-close', modal) : null;
	const quitBtn = modal ? $('.ea-gaming-quit', modal) : null;
	const container = $('#ea-gaming-container');

	function openModal(titleText = 'Game') {
		if (!modal) return;
		const titleEl = $('#ea-gaming-modal-title', modal);
		if (titleEl) {
			titleEl.textContent = titleText;
		}
		modal.style.display = 'block';
		document.body.classList.add('ea-gaming-modal-open');
	}

	function closeModal() {
		if (!modal) return;
		modal.style.display = 'none';
		document.body.classList.remove('ea-gaming-modal-open');
		// Optionally cleanup container between sessions
		if (container) {
			container.innerHTML = '';
		}
	}

	if (overlay) overlay.addEventListener('click', closeModal);
	if (closeBtn) closeBtn.addEventListener('click', closeModal);
	if (quitBtn) quitBtn.addEventListener('click', closeModal);

	// Helper: launch game via REST, then mount Phaser
	async function launchGameFlow({ courseId = 0, quizId = 0, gameType = 'whack_a_question', title = 'Game', theme = '', preset = '' }) {
		try {
			// Create session and get game configuration
			const resp = await apiFetch({
				path: '/ea-gaming/v1/games/launch',
				method: 'POST',
				data: {
					game_type: gameType,
					course_id: courseId ? parseInt(courseId, 10) : 0,
					quiz_id: quizId ? parseInt(quizId, 10) : 0
				}
				});

			// REST returns session_id; ensure we read it properly
			const sessionId = resp && (resp.session_id || resp.id);
			if (!sessionId) {
				throw new Error('Invalid session response');
			}

			// Determine theme/preset IDs for engine (object or string)
			const themeId = (resp.theme && resp.theme.id) ? resp.theme.id : (theme || 'playful');
			const presetId = (resp.preset && resp.preset.id) ? resp.preset.id : (preset || 'classic');

			// Open modal and mount Phaser game
			openModal(title);
			if (window.EAGameEngine && typeof window.EAGameEngine.launch === 'function') {
				window.EAGameEngine.launch({
					courseId: courseId ? parseInt(courseId, 10) : 0,
					sessionId: sessionId,
					theme: themeId,
					preset: presetId
				});
			} else {
				console.error('EAGameEngine launcher not available on window');
				alert('Game engine not loaded. Please refresh the page.');
			}
		} catch (err) {
			console.error('Error launching game', err);
			alert('Could not start game. Please try again.');
		}
	}

	// 1) Legacy block wrapper launcher (.ea-gaming-launcher) with "Start Game" button
	$$('.ea-gaming-launcher').forEach((launcher) => {
		const courseId = launcher.dataset.courseId || 0;
		const theme = launcher.dataset.theme || 'playful';
		const preset = launcher.dataset.preset || 'classic';
		const startBtn = $('.ea-gaming-start', launcher);
		if (startBtn) {
			startBtn.addEventListener('click', () => {
				launchGameFlow({
					courseId,
					gameType: 'whack_a_question',
					title: 'Gaming Mode',
					theme,
					preset
				});
			});
		}
	});

	// 2) PHP-rendered launcher shortcode block button (.ea-gaming-launcher-btn)
	$$('.ea-gaming-launcher-btn').forEach((btn) => {
		btn.addEventListener('click', () => {
			const courseId = btn.getAttribute('data-course-id') || '0';
			const quizId = btn.getAttribute('data-quiz-id') || '0';
			const gameType = btn.getAttribute('data-game-type') || 'whack_a_question';
			launchGameFlow({
				courseId,
				quizId,
				gameType,
				title: 'Gaming Mode'
			});
		});
	});

	// 3) Arcade game cards (.ea-gaming-play-btn) inside [ea_gaming_arcade]
	$$('.ea-gaming-arcade').forEach((arcade) => {
		const courseId = arcade.getAttribute('data-course-id') || '0';
		const quizId = arcade.getAttribute('data-quiz-id') || '0';
		$$('.ea-gaming-play-btn', arcade).forEach((playBtn) => {
			playBtn.addEventListener('click', () => {
				const card = playBtn.closest('.ea-gaming-card');
				const gameType = card ? card.getAttribute('data-game-type') : 'whack_a_question';
				launchGameFlow({
					courseId,
					quizId,
					gameType,
					title: 'Arcade'
				});
			});
		});
	});

	// 4) Quiz page game option (.ea-gaming-quiz-game-btn)
	$$('.ea-gaming-quiz-game-btn').forEach((btn) => {
		btn.addEventListener('click', () => {
			const quizId = btn.getAttribute('data-quiz-id') || '0';
			let courseId = '0';
			const courseEl = document.querySelector('[data-course-id]');
			if (courseEl) {
				courseId = courseEl.getAttribute('data-course-id') || '0';
			}
			launchGameFlow({
				courseId,
				quizId,
				gameType: 'whack_a_question',
				title: 'Quiz Game'
			});
		});
	});

	// 5) Lesson mini-game (.ea-gaming-mini-game-btn)
	$$('.ea-gaming-mini-game-btn').forEach((btn) => {
		btn.addEventListener('click', () => {
			// Lesson ID available for future use if needed
			// const lessonId = btn.getAttribute('data-lesson-id') || '0';
			let courseId = '0';
			const courseEl = document.querySelector('[data-course-id]');
			if (courseEl) {
				courseId = courseEl.getAttribute('data-course-id') || '0';
			}
			// For lesson challenges we may not have a specific quiz; start general mode
			launchGameFlow({
				courseId,
				quizId: 0,
				gameType: 'whack_a_question',
				title: 'Challenge Gate'
			});
		});
	});

	// 6) Course page big launcher (.ea-gaming-launch-btn)
	$$('.ea-gaming-launch-btn').forEach((btn) => {
		btn.addEventListener('click', () => {
			const courseId = btn.getAttribute('data-course-id') || '0';
			launchGameFlow({
				courseId,
				quizId: 0,
				gameType: 'whack_a_question',
				title: 'Gaming Mode'
			});
		});
	});
});

// Unused function - kept for potential future use
/*
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
*/