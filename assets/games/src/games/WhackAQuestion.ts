import Phaser from '../utils/PhaserShim';
import { EAGameBase, GameConfig } from '../core/EAGameBase';

interface Question {
	id: number;
	question: string;
	answers: Array<{
		id: number;
		text: string;
		correct?: boolean;
	}>;
	type: string;
	difficulty?: string;
}

interface MoleHole {
	x: number;
	y: number;
	sprite?: Phaser.GameObjects.Sprite;
	text?: Phaser.GameObjects.Text;
	answer?: any;
	isActive: boolean;
	container?: Phaser.GameObjects.Container;
}

interface ApiResponse {
	success: boolean;
	data?: any;
	message?: string;
}

/**
 * Whack-a-Question Game
 * Players click on correct answers that pop up from holes like whack-a-mole
 */
export class WhackAQuestion extends EAGameBase {
	private currentQuestion: Question | null = null;
	private moleHoles: MoleHole[] = [];
	private score: number = 0;
	private questionsCorrect: number = 0;
	private questionsTotal: number = 0;
	private timeLeft: number = 60; // 60 seconds per game
	private gameTimer?: Phaser.Time.TimerEvent;
	private questionTimer?: Phaser.Time.TimerEvent;
	private questionTimeLeft: number = 8; // 8 seconds per question
	private gameOverFlag: boolean = false;
	
	// UI Elements
	private scoreText?: Phaser.GameObjects.Text;
	private timeText?: Phaser.GameObjects.Text;
	private questionText?: Phaser.GameObjects.Text;
	private questionTimeText?: Phaser.GameObjects.Text;
	private gameOverText?: Phaser.GameObjects.Text;
	private restartButton?: Phaser.GameObjects.Text;

	// Game settings based on preset
	private gameSpeed: number = 1.0;
	private hintsEnabled: boolean = false;
	private questionDuration: number = 8000; // milliseconds

	constructor(config: string | Phaser.Types.Scenes.SettingsConfig, gameConfig: GameConfig) {
		super(config, gameConfig);
		this.applyPresetSettings();
	}

	/**
	 * Apply settings based on the current preset
	 */
	private applyPresetSettings(): void {
		const preset = this.configData.preset;
		switch (preset) {
			case 'chill':
				this.gameSpeed = 0.8;
				this.hintsEnabled = true;
				this.questionDuration = 10000;
				break;
			case 'classic':
				this.gameSpeed = 1.0;
				this.hintsEnabled = false;
				this.questionDuration = 8000;
				break;
			case 'pro':
				this.gameSpeed = 1.5;
				this.hintsEnabled = false;
				this.questionDuration = 6000;
				break;
			case 'accessible':
				this.gameSpeed = 0.6;
				this.hintsEnabled = true;
				this.questionDuration = 12000;
				break;
			default:
				// Use classic settings as default
				break;
		}
	}

	/**
	 * Get theme colors based on current theme
	 */
	private getThemeColors(): any {
		// Default colors - should be overridden by actual theme data
		const defaultColors = {
			primary: '#7C3AED',
			secondary: '#EC4899',
			success: '#10B981',
			danger: '#EF4444',
			warning: '#F59E0B',
			background: '#FEF3C7',
			surface: '#FFFFFF',
			'text-primary': '#1F2937',
			'text-secondary': '#6B7280'
		};

		// In a real implementation, this would fetch from theme manager
		// For now, return default playful theme colors
		return defaultColors;
	}

	preload(): void {
		super.preload();

		// Load placeholder sprites
		// In production, these would be proper game assets
		this.load.image('hole', '/assets/games/sprites/whack/hole.png');
		this.load.image('mole', '/assets/games/sprites/whack/mole.png');
		this.load.image('background', '/assets/games/sprites/whack/background.png');
		
		// Create fallback rectangles if images don't exist
		this.load.on('loaderror', () => {
			console.warn('Game sprites not found, using fallback graphics');
		});
	}

	create(): void {
		super.create();

		const colors = this.getThemeColors();

		// Create background
		this.add.rectangle(400, 300, 800, 600, Phaser.Display.Color.HexStringToColor(colors.background).color);

		// Initialize mole holes in a 3x3 grid
		this.initializeMoleHoles();

		// Create UI
		this.createUI(colors);

		// Start the game
		this.startGame();
	}

	/**
	 * Initialize the mole holes in a 3x3 grid
	 */
	private initializeMoleHoles(): void {
		const rows = 3;
		const cols = 3;
		const startX = 150;
		const startY = 150;
		const spacingX = 180;
		const spacingY = 120;

		for (let row = 0; row < rows; row++) {
			for (let col = 0; col < cols; col++) {
				const x = startX + col * spacingX;
				const y = startY + row * spacingY;

				const hole: MoleHole = {
					x,
					y,
					isActive: false
				};

				// Create hole sprite (fallback to circle if image not found)
				try {
					hole.sprite = this.add.sprite(x, y, 'hole');
				} catch {
					hole.sprite = this.add.circle(x, y, 30, 0x8B4513); // Brown circle
				}

				// Create container for answer display
				hole.container = this.add.container(x, y);
				hole.container.setVisible(false);

				this.moleHoles.push(hole);
			}
		}
	}

	/**
	 * Create UI elements
	 */
	private createUI(colors: any): void {
		// Score display
		this.scoreText = this.add.text(20, 20, `Score: ${this.score}`, {
			fontSize: '24px',
			color: colors['text-primary']
		});

		// Time display
		this.timeText = this.add.text(20, 50, `Time: ${this.timeLeft}s`, {
			fontSize: '20px',
			color: colors['text-primary']
		});

		// Questions progress
		this.add.text(20, 80, `Questions: ${this.questionsCorrect}/${this.questionsTotal}`, {
			fontSize: '18px',
			color: colors['text-secondary']
		});

		// Question display area
		this.questionText = this.add.text(400, 50, '', {
			fontSize: '18px',
			color: colors['text-primary'],
			align: 'center',
			wordWrap: { width: 600 }
		}).setOrigin(0.5, 0);

		// Question timer
		this.questionTimeText = this.add.text(400, 90, '', {
			fontSize: '16px',
			color: colors.warning,
			align: 'center'
		}).setOrigin(0.5, 0);
	}

	/**
	 * Start the game
	 */
	private startGame(): void {
		// Start main game timer
		this.gameTimer = this.time.addEvent({
			delay: 1000,
			callback: this.updateGameTimer,
			callbackScope: this,
			loop: true
		});

		// Load first question
		this.loadNextQuestion();
	}

	/**
	 * Update the main game timer
	 */
	private updateGameTimer(): void {
		this.timeLeft--;
		if (this.timeText) {
			this.timeText.setText(`Time: ${this.timeLeft}s`);
		}

		if (this.timeLeft <= 0) {
			this.endGame();
		}
	}

	/**
	 * Update the question timer
	 */
	private updateQuestionTimer(): void {
		this.questionTimeLeft--;
		if (this.questionTimeText) {
			this.questionTimeText.setText(`Question time: ${this.questionTimeLeft}s`);
		}

		if (this.questionTimeLeft <= 0) {
			this.onQuestionTimeout();
		}
	}

	/**
	 * Load the next question from the API
	 */
	private async loadNextQuestion(): Promise<void> {
		if (this.gameOverFlag) return;

		try {
			// In production, get quiz_id from game metadata
			const quizId = 1; // Placeholder
			const response = await this.apiRequest('GET', `/wp-json/ea-gaming/v1/questions/${quizId}?session_id=${this.configData.sessionId}`);
			
			if (response.success && response.data) {
				this.currentQuestion = response.data;
				this.displayQuestion();
			} else {
				console.error('Failed to load question:', response.message);
				this.showNoQuestionsAvailable();
			}
		} catch (error) {
			console.error('Error loading question:', error);
			this.showNoQuestionsAvailable();
		}
	}

	/**
	 * Display the current question and answers in mole holes
	 */
	private displayQuestion(): void {
		if (!this.currentQuestion) return;

		// Clear previous question display
		this.clearMoleHoles();

		// Display question text
		if (this.questionText) {
			this.questionText.setText(this.currentQuestion.question);
		}

		// Start question timer
		this.questionTimeLeft = Math.floor(this.questionDuration / 1000);
		this.questionTimer = this.time.addEvent({
			delay: 1000,
			callback: this.updateQuestionTimer,
			callbackScope: this,
			loop: true,
			repeat: this.questionTimeLeft - 1
		});

		// Randomly assign answers to mole holes
		const answers = [...this.currentQuestion.answers];
		const availableHoles = [...this.moleHoles];

		// Show 3-4 answers randomly
		const numAnswersToShow = Math.min(Math.max(3, answers.length), 6);
		
		for (let i = 0; i < numAnswersToShow && answers.length > 0 && availableHoles.length > 0; i++) {
			const answerIndex = Math.floor(Math.random() * answers.length);
			const holeIndex = Math.floor(Math.random() * availableHoles.length);
			
			const answer = answers.splice(answerIndex, 1)[0];
			const hole = availableHoles.splice(holeIndex, 1)[0];
			
			this.showAnswerInHole(hole, answer);
		}
	}

	/**
	 * Show an answer in a specific mole hole
	 */
	private showAnswerInHole(hole: MoleHole, answer: any): void {
		if (!hole.container) return;

		hole.isActive = true;
		hole.answer = answer;

		// Create answer background
		const background = this.add.rectangle(0, 0, 140, 60, 0x654321);
		background.setStrokeStyle(2, 0x8B4513);

		// Create answer text
		const answerText = this.add.text(0, 0, answer.text, {
			fontSize: '14px',
			color: '#FFFFFF',
			align: 'center',
			wordWrap: { width: 130 }
		}).setOrigin(0.5);

		// Add to container
		hole.container.add([background, answerText]);
		hole.container.setVisible(true);

		// Make it interactive
		background.setInteractive();
		background.on('pointerdown', () => this.onAnswerClicked(hole));

		// Add pop-up animation
		hole.container.setScale(0);
		this.tweens.add({
			targets: hole.container,
			scale: 1,
			duration: 300 * this.gameSpeed,
			ease: 'Back.out'
		});

		// Auto-hide after some time
		this.time.delayedCall(this.questionDuration * 0.8, () => {
			if (hole.isActive) {
				this.hideAnswerInHole(hole);
			}
		});
	}

	/**
	 * Hide an answer in a specific mole hole
	 */
	private hideAnswerInHole(hole: MoleHole): void {
		if (!hole.container || !hole.isActive) return;

		this.tweens.add({
			targets: hole.container,
			scale: 0,
			duration: 200 * this.gameSpeed,
			ease: 'Back.in',
			onComplete: () => {
				hole.container?.setVisible(false);
				hole.container?.removeAll(true);
				hole.isActive = false;
				hole.answer = null;
			}
		});
	}

	/**
	 * Clear all mole holes
	 */
	private clearMoleHoles(): void {
		this.moleHoles.forEach(hole => {
			if (hole.isActive) {
				this.hideAnswerInHole(hole);
			}
		});
	}

	/**
	 * Handle answer click
	 */
	private async onAnswerClicked(hole: MoleHole): Promise<void> {
		if (!hole.answer || !this.currentQuestion || this.gameOverFlag) return;

		// Hide the clicked answer immediately
		this.hideAnswerInHole(hole);

		// Cancel question timer
		if (this.questionTimer) {
			this.questionTimer.destroy();
		}

		try {
			// Validate answer with API
			const response = await this.apiRequest('POST', '/wp-json/ea-gaming/v1/validate-answer', {
				question_id: this.currentQuestion.id,
				answer: hole.answer.id,
				session_id: this.configData.sessionId
			});

			if (response.success && response.data) {
				const isCorrect = response.data.correct;
				
				if (isCorrect) {
					this.onCorrectAnswer();
				} else {
					this.onIncorrectAnswer();
				}

				this.questionsTotal++;
				if (isCorrect) {
					this.questionsCorrect++;
				}

				// Update UI
				this.updateProgressDisplay();

				// Short delay before next question
				this.time.delayedCall(1000, () => {
					if (!this.gameOverFlag) {
						this.loadNextQuestion();
					}
				});
			}
		} catch (error) {
			console.error('Error validating answer:', error);
			this.onIncorrectAnswer();
		}
	}

	/**
	 * Handle correct answer
	 */
	private onCorrectAnswer(): void {
		const basePoints = 100;
		const timeBonus = Math.max(0, this.questionTimeLeft * 10);
		const points = Math.floor((basePoints + timeBonus) * this.gameSpeed);
		
		this.score += points;
		
		// Visual feedback
		this.showFeedback('Correct! +' + points, '#10B981');
		
		// Update score display
		if (this.scoreText) {
			this.scoreText.setText(`Score: ${this.score}`);
		}
	}

	/**
	 * Handle incorrect answer
	 */
	private onIncorrectAnswer(): void {
		// Visual feedback
		this.showFeedback('Incorrect!', '#EF4444');
	}

	/**
	 * Show feedback text
	 */
	private showFeedback(text: string, color: string): void {
		const feedback = this.add.text(400, 200, text, {
			fontSize: '32px',
			color: color,
			align: 'center'
		}).setOrigin(0.5);

		// Animate feedback
		this.tweens.add({
			targets: feedback,
			y: 150,
			alpha: 0,
			duration: 1500,
			ease: 'Power2',
			onComplete: () => feedback.destroy()
		});
	}

	/**
	 * Handle question timeout
	 */
	private onQuestionTimeout(): void {
		this.questionsTotal++;
		this.clearMoleHoles();
		
		// Show timeout feedback
		this.showFeedback('Time\'s up!', '#F59E0B');
		
		this.updateProgressDisplay();
		
		// Load next question after delay
		this.time.delayedCall(1000, () => {
			if (!this.gameOverFlag) {
				this.loadNextQuestion();
			}
		});
	}

	/**
	 * Update progress display
	 */
	private updateProgressDisplay(): void {
		// This would update a progress UI element if it exists
		console.log(`Progress: ${this.questionsCorrect}/${this.questionsTotal}`);
	}

	/**
	 * Show message when no questions are available
	 */
	private showNoQuestionsAvailable(): void {
		this.add.text(400, 300, 'No more questions available!\nGame ending...', {
			fontSize: '24px',
			color: '#EF4444',
			align: 'center'
		}).setOrigin(0.5);

		this.time.delayedCall(2000, () => {
			this.endGame();
		});
	}

	/**
	 * End the game
	 */
	private endGame(): void {
		this.gameOverFlag = true;

		// Clean up timers
		if (this.gameTimer) {
			this.gameTimer.destroy();
		}
		if (this.questionTimer) {
			this.questionTimer.destroy();
		}

		// Clear active elements
		this.clearMoleHoles();

		// Calculate final stats
		const accuracy = this.questionsTotal > 0 ? Math.round((this.questionsCorrect / this.questionsTotal) * 100) : 0;
		const perfect = accuracy === 100 && this.questionsTotal > 0;

		// Send final score to API
		this.submitFinalScore(perfect);

		// Show game over screen
		this.showGameOverScreen(accuracy);
	}

	/**
	 * Submit final score to API
	 */
	private async submitFinalScore(perfect: boolean): Promise<void> {
		try {
			await this.apiRequest('DELETE', `/wp-json/ea-gaming/v1/sessions/${this.configData.sessionId}`, {
				score: this.score,
				questions_correct: this.questionsCorrect,
				questions_total: this.questionsTotal,
				perfect: perfect
			});
		} catch (error) {
			console.error('Error submitting final score:', error);
		}
	}

	/**
	 * Show game over screen
	 */
	private showGameOverScreen(accuracy: number): void {
		// Semi-transparent overlay
		this.add.rectangle(400, 300, 800, 600, 0x000000, 0.7);

		// Game over text
		this.gameOverText = this.add.text(400, 200, 'Game Over!', {
			fontSize: '48px',
			color: '#FFFFFF',
			align: 'center'
		}).setOrigin(0.5);

		// Stats
		this.add.text(400, 280, 
			`Final Score: ${this.score}\n` +
			`Questions Correct: ${this.questionsCorrect}/${this.questionsTotal}\n` +
			`Accuracy: ${accuracy}%`, {
			fontSize: '24px',
			color: '#FFFFFF',
			align: 'center',
			lineSpacing: 10
		}).setOrigin(0.5);

		// Restart button
		this.restartButton = this.add.text(400, 400, 'Play Again', {
			fontSize: '32px',
			color: '#10B981',
			align: 'center'
		}).setOrigin(0.5);

		this.restartButton.setInteractive();
		this.restartButton.on('pointerdown', () => this.restartGame());
		this.restartButton.on('pointerover', () => this.restartButton?.setColor('#34D399'));
		this.restartButton.on('pointerout', () => this.restartButton?.setColor('#10B981'));

		// Emit game over event
		this.game.events.emit('game-over', {
			score: this.score,
			questionsCorrect: this.questionsCorrect,
			questionsTotal: this.questionsTotal,
			accuracy: accuracy
		});
	}

	/**
	 * Restart the game
	 */
	private restartGame(): void {
		// Reset game state
		this.score = 0;
		this.questionsCorrect = 0;
		this.questionsTotal = 0;
		this.timeLeft = 60;
		this.gameOverFlag = false;

		// Clear screen and restart
		this.scene.restart();
	}

	/**
	 * Make API request
	 */
	private async apiRequest(method: string, endpoint: string, data?: any): Promise<ApiResponse> {
		try {
			const options: RequestInit = {
				method: method,
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': (window as any).eaGamingNonce || ''
				},
				credentials: 'same-origin'
			};

			if (data && (method === 'POST' || method === 'PUT' || method === 'DELETE')) {
				options.body = JSON.stringify(data);
			}

			const response = await fetch(endpoint, options);
			const result = await response.json();

			return {
				success: response.ok,
				data: result,
				message: result.message || ''
			};
		} catch (error) {
			return {
				success: false,
				message: error instanceof Error ? error.message : 'Unknown error'
			};
		}
	}

	/**
	 * Clean up when scene is destroyed
	 */
	destroy(): void {
		if (this.gameTimer) {
			this.gameTimer.destroy();
		}
		if (this.questionTimer) {
			this.questionTimer.destroy();
		}
		super.destroy();
	}
}