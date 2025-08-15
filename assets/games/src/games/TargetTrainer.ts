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

interface Target {
	id: string;
	sprite?: Phaser.GameObjects.Sprite;
	text?: Phaser.GameObjects.Text;
	container?: Phaser.GameObjects.Container;
	answer?: any;
	isActive: boolean;
	x: number;
	y: number;
	moveSpeed: number;
	direction: Phaser.Math.Vector2;
	hitRadius: number;
	isCorrect: boolean;
	timeRemaining: number;
}

interface Bullet {
	sprite: Phaser.GameObjects.Sprite;
	targetId?: string;
	isActive: boolean;
}

interface ApiResponse {
	success: boolean;
	data?: any;
	message?: string;
}

/**
 * Target Trainer Game
 * Players aim and shoot at targets containing correct answers
 * Features moving targets, combo system, and accuracy tracking
 */
export class TargetTrainer extends EAGameBase {
	private currentQuestion: Question | null = null;
	private targets: Target[] = [];
	private bullets: Bullet[] = [];
	private score: number = 0;
	private questionsCorrect: number = 0;
	private questionsTotal: number = 0;
	private shotsTotal: number = 0;
	private shotsHit: number = 0;
	private comboCount: number = 0;
	private comboMultiplier: number = 1;
	private timeLeft: number = 60; // 60 seconds per game
	private gameTimer?: Phaser.Time.TimerEvent;
	private questionTimer?: Phaser.Time.TimerEvent;
	private questionTimeLeft: number = 8; // seconds per question
	private gameOverFlag: boolean = false;
	
	// UI Elements
	private scoreText?: Phaser.GameObjects.Text;
	private timeText?: Phaser.GameObjects.Text;
	private questionText?: Phaser.GameObjects.Text;
	private questionTimeText?: Phaser.GameObjects.Text;
	private accuracyText?: Phaser.GameObjects.Text;
	private comboText?: Phaser.GameObjects.Text;
	private gameOverText?: Phaser.GameObjects.Text;
	private restartButton?: Phaser.GameObjects.Text;
	private crosshair?: Phaser.GameObjects.Sprite;

	// Game settings based on preset
	private gameSpeed: number = 1.0;
	private hintsEnabled: boolean = false;
	private questionDuration: number = 8000; // milliseconds
	private targetMoveSpeed: number = 50; // pixels per second
	private targetSize: number = 1.0;
	private maxTargets: number = 4;

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
				this.targetMoveSpeed = 0; // Static targets
				this.targetSize = 1.2;
				this.maxTargets = 3;
				break;
			case 'classic':
				this.gameSpeed = 1.0;
				this.hintsEnabled = false;
				this.questionDuration = 8000;
				this.targetMoveSpeed = 30;
				this.targetSize = 1.0;
				this.maxTargets = 4;
				break;
			case 'pro':
				this.gameSpeed = 1.5;
				this.hintsEnabled = false;
				this.questionDuration = 5000;
				this.targetMoveSpeed = 80;
				this.targetSize = 0.8;
				this.maxTargets = 5;
				break;
			case 'accessible':
				this.gameSpeed = 0.6;
				this.hintsEnabled = true;
				this.questionDuration = 15000;
				this.targetMoveSpeed = 0; // Static targets
				this.targetSize = 1.5;
				this.maxTargets = 3;
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
		return defaultColors;
	}

	preload(): void {
		super.preload();

		// Load placeholder sprites
		this.load.image('target', '/assets/games/sprites/target/target.png');
		this.load.image('target-hit', '/assets/games/sprites/target/target-hit.png');
		this.load.image('crosshair', '/assets/games/sprites/target/crosshair.png');
		this.load.image('bullet', '/assets/games/sprites/target/bullet.png');
		this.load.image('background', '/assets/games/sprites/target/background.png');
		
		// Create fallback graphics if images don't exist
		this.load.on('loaderror', () => {
			console.warn('Target Trainer sprites not found, using fallback graphics');
		});
	}

	create(): void {
		super.create();

		const colors = this.getThemeColors();

		// Create background
		this.add.rectangle(400, 300, 800, 600, Phaser.Display.Color.HexStringToColor(colors.background).color);

		// Create UI
		this.createUI(colors);

		// Create crosshair
		this.createCrosshair();

		// Set up input
		this.setupInput();

		// Start the game
		this.startGame();
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

		// Accuracy display
		this.accuracyText = this.add.text(20, 80, `Accuracy: 100%`, {
			fontSize: '18px',
			color: colors['text-secondary']
		});

		// Combo display
		this.comboText = this.add.text(20, 110, `Combo: x1`, {
			fontSize: '18px',
			color: colors.warning
		});

		// Question display area
		this.questionText = this.add.text(400, 30, '', {
			fontSize: '18px',
			color: colors['text-primary'],
			align: 'center',
			wordWrap: { width: 600 }
		}).setOrigin(0.5, 0);

		// Question timer
		this.questionTimeText = this.add.text(400, 70, '', {
			fontSize: '16px',
			color: colors.warning,
			align: 'center'
		}).setOrigin(0.5, 0);

		// Instructions
		this.add.text(400, 550, 'Click to shoot at the correct answer!', {
			fontSize: '16px',
			color: colors['text-secondary'],
			align: 'center'
		}).setOrigin(0.5);
	}

	/**
	 * Create crosshair cursor
	 */
	private createCrosshair(): void {
		try {
			this.crosshair = this.add.sprite(0, 0, 'crosshair');
		} catch {
			// Fallback crosshair
			this.crosshair = this.add.sprite(0, 0, '');
			
			// Create fallback crosshair graphics
			const graphics = this.add.graphics();
			graphics.lineStyle(2, 0xFFFFFF);
			graphics.strokeCircle(0, 0, 16);
			graphics.moveTo(-20, 0);
			graphics.lineTo(20, 0);
			graphics.moveTo(0, -20);
			graphics.lineTo(0, 20);
			graphics.stroke();
			
			this.crosshair.setTexture(graphics.generateTexture('crosshair-fallback'));
			graphics.destroy();
		}

		this.crosshair.setScale(0.8);
		this.crosshair.setDepth(1000);
	}

	/**
	 * Set up input handling
	 */
	private setupInput(): void {
		// Hide default cursor and use crosshair
		this.input.setDefaultCursor('none');
		
		// Track mouse movement
		this.input.on('pointermove', (pointer: Phaser.Input.Pointer) => {
			if (this.crosshair) {
				this.crosshair.setPosition(pointer.x, pointer.y);
			}
		});

		// Handle clicking/shooting
		this.input.on('pointerdown', (pointer: Phaser.Input.Pointer) => {
			this.shoot(pointer.x, pointer.y);
		});
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

		// Update target time remaining
		this.targets.forEach(target => {
			if (target.isActive) {
				target.timeRemaining--;
				if (target.timeRemaining <= 0) {
					this.destroyTarget(target);
				}
			}
		});

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
	 * Display the current question and create targets with answers
	 */
	private displayQuestion(): void {
		if (!this.currentQuestion) return;

		// Clear previous targets
		this.clearTargets();

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

		// Create targets with answers
		this.createTargetsWithAnswers();
	}

	/**
	 * Create targets with answers
	 */
	private createTargetsWithAnswers(): void {
		if (!this.currentQuestion) return;

		const answers = [...this.currentQuestion.answers];
		const numTargets = Math.min(this.maxTargets, answers.length);

		for (let i = 0; i < numTargets; i++) {
			if (answers.length === 0) break;

			const answerIndex = Math.floor(Math.random() * answers.length);
			const answer = answers.splice(answerIndex, 1)[0];

			this.createTarget(answer);
		}
	}

	/**
	 * Create a target with an answer
	 */
	private createTarget(answer: any): void {
		const margin = 100;
		const x = Phaser.Math.Between(margin, 800 - margin);
		const y = Phaser.Math.Between(margin + 100, 600 - margin);

		const target: Target = {
			id: `target_${Date.now()}_${Math.random()}`,
			x,
			y,
			moveSpeed: this.targetMoveSpeed,
			direction: new Phaser.Math.Vector2(
				Phaser.Math.Between(-1, 1),
				Phaser.Math.Between(-1, 1)
			).normalize(),
			hitRadius: 40 * this.targetSize,
			isActive: true,
			isCorrect: answer.correct || false,
			answer,
			timeRemaining: this.questionTimeLeft
		};

		// Create target sprite (fallback to circle if image not found)
		try {
			target.sprite = this.add.sprite(x, y, 'target');
		} catch {
			target.sprite = this.add.circle(x, y, 40 * this.targetSize, target.isCorrect ? 0x10B981 : 0xEF4444);
		}

		target.sprite.setScale(this.targetSize);
		target.sprite.setInteractive();

		// Create answer text
		target.text = this.add.text(x, y, answer.text, {
			fontSize: `${Math.floor(14 * this.targetSize)}px`,
			color: '#FFFFFF',
			align: 'center',
			wordWrap: { width: 120 * this.targetSize },
			stroke: '#000000',
			strokeThickness: 2
		}).setOrigin(0.5);

		// Create container
		target.container = this.add.container(x, y, [target.sprite, target.text]);

		// Add entrance animation
		target.container.setScale(0);
		this.tweens.add({
			targets: target.container,
			scale: 1,
			duration: 300,
			ease: 'Back.out'
		});

		this.targets.push(target);
	}

	/**
	 * Update target positions (for moving targets)
	 */
	update(time: number, delta: number): void {
		super.update(time, delta);

		if (this.gameOverFlag) return;

		// Update target movements
		this.targets.forEach(target => {
			if (!target.isActive || target.moveSpeed === 0) return;

			const moveDistance = (target.moveSpeed * delta) / 1000;
			target.x += target.direction.x * moveDistance;
			target.y += target.direction.y * moveDistance;

			// Bounce off walls
			const margin = 50;
			if (target.x <= margin || target.x >= 800 - margin) {
				target.direction.x *= -1;
				target.x = Phaser.Math.Clamp(target.x, margin, 800 - margin);
			}
			if (target.y <= margin + 100 || target.y >= 600 - margin) {
				target.direction.y *= -1;
				target.y = Phaser.Math.Clamp(target.y, margin + 100, 600 - margin);
			}

			// Update container position
			if (target.container) {
				target.container.setPosition(target.x, target.y);
			}
		});

		// Update bullets
		this.updateBullets(delta);
	}

	/**
	 * Shoot at a target
	 */
	private shoot(x: number, y: number): void {
		if (this.gameOverFlag) return;

		this.shotsTotal++;

		// Find target at position
		const hitTarget = this.getTargetAtPosition(x, y);

		if (hitTarget) {
			this.onTargetHit(hitTarget);
		} else {
			this.onTargetMiss(x, y);
		}

		// Update accuracy display
		this.updateAccuracyDisplay();

		// Create bullet visual effect
		this.createBulletEffect(x, y, hitTarget);
	}

	/**
	 * Get target at specified position
	 */
	private getTargetAtPosition(x: number, y: number): Target | null {
		for (const target of this.targets) {
			if (!target.isActive) continue;

			const distance = Phaser.Math.Distance.Between(x, y, target.x, target.y);
			if (distance <= target.hitRadius) {
				return target;
			}
		}
		return null;
	}

	/**
	 * Handle target hit
	 */
	private async onTargetHit(target: Target): Promise<void> {
		if (!this.currentQuestion) return;

		this.shotsHit++;

		// Destroy target immediately
		this.destroyTarget(target);

		// Cancel question timer
		if (this.questionTimer) {
			this.questionTimer.destroy();
		}

		try {
			// Validate answer with API
			const response = await this.apiRequest('POST', '/wp-json/ea-gaming/v1/validate-answer', {
				question_id: this.currentQuestion.id,
				answer: target.answer.id,
				session_id: this.configData.sessionId
			});

			if (response.success && response.data) {
				const isCorrect = response.data.correct;
				
				if (isCorrect) {
					this.onCorrectAnswer();
				} else {
					this.onIncorrectAnswer();
					this.comboCount = 0; // Reset combo on wrong answer
					this.comboMultiplier = 1;
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
	 * Handle target miss
	 */
	private onTargetMiss(x: number, y: number): void {
		// Reset combo on miss
		this.comboCount = 0;
		this.comboMultiplier = 1;
		this.updateComboDisplay();

		// Show miss effect
		this.showMissEffect(x, y);
	}

	/**
	 * Handle correct answer
	 */
	private onCorrectAnswer(): void {
		// Increase combo
		this.comboCount++;
		this.comboMultiplier = Math.min(1 + (this.comboCount * 0.2), 3.0); // Max 3x multiplier

		const basePoints = 100;
		const timeBonus = Math.max(0, this.questionTimeLeft * 10);
		const accuracyBonus = this.getAccuracyPercentage() > 80 ? 50 : 0;
		const points = Math.floor((basePoints + timeBonus + accuracyBonus) * this.comboMultiplier * this.gameSpeed);
		
		this.score += points;
		
		// Visual feedback
		this.showFeedback(`Correct! +${points}`, '#10B981');
		
		// Update displays
		if (this.scoreText) {
			this.scoreText.setText(`Score: ${this.score}`);
		}
		this.updateComboDisplay();
	}

	/**
	 * Handle incorrect answer
	 */
	private onIncorrectAnswer(): void {
		// Visual feedback
		this.showFeedback('Incorrect!', '#EF4444');
		this.updateComboDisplay();
	}

	/**
	 * Create bullet visual effect
	 */
	private createBulletEffect(targetX: number, targetY: number, hitTarget?: Target): void {
		const startX = this.crosshair?.x || 400;
		const startY = this.crosshair?.y || 300;

		let bullet: Phaser.GameObjects.Sprite;
		try {
			bullet = this.add.sprite(startX, startY, 'bullet');
		} catch {
			bullet = this.add.circle(startX, startY, 4, 0xFFFFFF) as any;
		}

		// Animate bullet to target
		this.tweens.add({
			targets: bullet,
			x: targetX,
			y: targetY,
			duration: 200,
			ease: 'Power2',
			onComplete: () => {
				// Create hit/miss effect
				if (hitTarget) {
					this.showHitEffect(targetX, targetY);
				} else {
					this.showMissEffect(targetX, targetY);
				}
				bullet.destroy();
			}
		});
	}

	/**
	 * Update bullets (placeholder for future bullet physics)
	 */
	private updateBullets(): void {
		// Currently using tween animations instead of physics
		// This method is for future enhancement if needed
		// delta parameter removed as it's not used
	}

	/**
	 * Show hit effect
	 */
	private showHitEffect(x: number, y: number): void {
		// Create explosion effect
		const particles = this.add.particles(x, y, 'target', {
			scale: { start: 0.5, end: 0 },
			speed: { min: 50, max: 150 },
			lifespan: 300,
			quantity: 8
		});

		// Clean up after animation
		this.time.delayedCall(300, () => {
			particles.destroy();
		});
	}

	/**
	 * Show miss effect
	 */
	private showMissEffect(x: number, y: number): void {
		// Create small dust effect
		const missSprite = this.add.circle(x, y, 8, 0x888888, 0.7);
		
		this.tweens.add({
			targets: missSprite,
			alpha: 0,
			scale: 2,
			duration: 500,
			ease: 'Power2',
			onComplete: () => missSprite.destroy()
		});
	}

	/**
	 * Show feedback text
	 */
	private showFeedback(text: string, color: string): void {
		const feedback = this.add.text(400, 200, text, {
			fontSize: '32px',
			color: color,
			align: 'center',
			stroke: '#000000',
			strokeThickness: 2
		}).setOrigin(0.5);

		// Animate feedback
		this.tweens.add({
			targets: feedback,
			y: 150,
			alpha: 0,
			scale: 1.5,
			duration: 1500,
			ease: 'Power2',
			onComplete: () => feedback.destroy()
		});
	}

	/**
	 * Update accuracy display
	 */
	private updateAccuracyDisplay(): void {
		const accuracy = this.getAccuracyPercentage();
		if (this.accuracyText) {
			this.accuracyText.setText(`Accuracy: ${accuracy}%`);
		}
	}

	/**
	 * Update combo display
	 */
	private updateComboDisplay(): void {
		if (this.comboText) {
			this.comboText.setText(`Combo: x${this.comboMultiplier.toFixed(1)}`);
		}
	}

	/**
	 * Get accuracy percentage
	 */
	private getAccuracyPercentage(): number {
		return this.shotsTotal > 0 ? Math.round((this.shotsHit / this.shotsTotal) * 100) : 100;
	}

	/**
	 * Destroy a target
	 */
	private destroyTarget(target: Target): void {
		target.isActive = false;
		
		if (target.container) {
			this.tweens.add({
				targets: target.container,
				scale: 0,
				duration: 200,
				ease: 'Back.in',
				onComplete: () => {
					target.container?.destroy();
				}
			});
		}

		// Remove from array
		const index = this.targets.indexOf(target);
		if (index > -1) {
			this.targets.splice(index, 1);
		}
	}

	/**
	 * Clear all targets
	 */
	private clearTargets(): void {
		this.targets.forEach(target => {
			this.destroyTarget(target);
		});
		this.targets = [];
	}

	/**
	 * Handle question timeout
	 */
	private onQuestionTimeout(): void {
		this.questionsTotal++;
		this.clearTargets();
		
		// Reset combo on timeout
		this.comboCount = 0;
		this.comboMultiplier = 1;
		
		// Show timeout feedback
		this.showFeedback('Time\'s up!', '#F59E0B');
		
		this.updateProgressDisplay();
		this.updateComboDisplay();
		
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
		this.clearTargets();

		// Restore default cursor
		this.input.setDefaultCursor('default');

		// Calculate final stats
		const accuracy = this.getAccuracyPercentage();
		const perfect = accuracy === 100 && this.questionsTotal > 0 && this.shotsTotal > 0;

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
				perfect: perfect,
				accuracy: this.getAccuracyPercentage(),
				shots_total: this.shotsTotal,
				shots_hit: this.shotsHit,
				max_combo: Math.max(this.comboCount, 0)
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
		this.gameOverText = this.add.text(400, 150, 'Mission Complete!', {
			fontSize: '48px',
			color: '#FFFFFF',
			align: 'center'
		}).setOrigin(0.5);

		// Stats
		this.add.text(400, 280, 
			`Final Score: ${this.score}\n` +
			`Questions Correct: ${this.questionsCorrect}/${this.questionsTotal}\n` +
			`Shooting Accuracy: ${accuracy}%\n` +
			`Shots: ${this.shotsHit}/${this.shotsTotal}\n` +
			`Best Combo: x${this.comboMultiplier.toFixed(1)}`, {
			fontSize: '20px',
			color: '#FFFFFF',
			align: 'center',
			lineSpacing: 8
		}).setOrigin(0.5);

		// Performance rating
		let rating = 'Good Job!';
		let ratingColor = '#10B981';
		
		if (accuracy >= 90 && this.questionsCorrect === this.questionsTotal) {
			rating = 'PERFECT MARKSMAN!';
			ratingColor = '#F59E0B';
		} else if (accuracy >= 80) {
			rating = 'Excellent Shooting!';
			ratingColor = '#10B981';
		} else if (accuracy >= 60) {
			rating = 'Good Accuracy!';
			ratingColor = '#3B82F6';
		}

		this.add.text(400, 220, rating, {
			fontSize: '24px',
			color: ratingColor,
			align: 'center'
		}).setOrigin(0.5);

		// Restart button
		this.restartButton = this.add.text(400, 420, 'Train Again', {
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
			accuracy: accuracy,
			shotsTotal: this.shotsTotal,
			shotsHit: this.shotsHit,
			maxCombo: this.comboMultiplier
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
		this.shotsTotal = 0;
		this.shotsHit = 0;
		this.comboCount = 0;
		this.comboMultiplier = 1;
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

		// Restore default cursor
		this.input.setDefaultCursor('default');

		super.destroy();
	}
}