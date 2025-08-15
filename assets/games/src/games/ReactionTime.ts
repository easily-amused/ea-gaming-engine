import Phaser from 'phaser';
import { EAGameBase, GameConfig } from '../index';

interface ReactionAttempt {
	time: number;
	success: boolean;
}

export class ReactionTime extends EAGameBase {
	private targetCircle?: Phaser.GameObjects.Circle;
	private instructionText?: Phaser.GameObjects.Text;
	private scoreText?: Phaser.GameObjects.Text;
	private bestTimeText?: Phaser.GameObjects.Text;
	private averageTimeText?: Phaser.GameObjects.Text;
	private container?: Phaser.GameObjects.Container;
	private state: 'waiting' | 'ready' | 'go' | 'clicked' | 'tooEarly' | 'complete' = 'waiting';
	private startTime: number = 0;
	private attempts: ReactionAttempt[] = [];
	private currentRound: number = 0;
	private totalRounds: number = 5;
	private waitTimer?: Phaser.Time.TimerEvent;
	private bestTime: number = Infinity;
	private spaceKey?: Phaser.Input.Keyboard.Key;

	constructor(key: string, config: GameConfig) {
		super(key, config);
	}

	create(): void {
		const theme = this.getThemeColors();
		
		// Create container
		this.container = this.add.container(0, 0);
		
		// Background
		const bg = this.add.graphics();
		bg.fillStyle(0x1a1a1a, 1);
		bg.fillRect(0, 0, 800, 600);
		this.container.add(bg);
		
		// Create target circle (initially hidden)
		this.targetCircle = this.add.circle(400, 300, 100, parseInt(theme.danger.replace('#', '0x'), 16));
		this.targetCircle.setInteractive();
		this.targetCircle.visible = false;
		this.container.add(this.targetCircle);
		
		// Create instruction text
		this.instructionText = this.add.text(400, 200, 'Click when the circle turns GREEN!', {
			fontSize: '24px',
			color: theme.primary,
			align: 'center'
		}).setOrigin(0.5);
		this.container.add(this.instructionText);
		
		// Create score displays
		this.scoreText = this.add.text(400, 100, `Round: ${this.currentRound + 1}/${this.totalRounds}`, {
			fontSize: '20px',
			color: theme.secondary
		}).setOrigin(0.5);
		this.container.add(this.scoreText);
		
		this.bestTimeText = this.add.text(10, 10, 'Best: --', {
			fontSize: '18px',
			color: theme.success
		});
		this.container.add(this.bestTimeText);
		
		this.averageTimeText = this.add.text(10, 35, 'Average: --', {
			fontSize: '18px',
			color: theme.primary
		});
		this.container.add(this.averageTimeText);
		
		// Setup input
		this.spaceKey = this.input.keyboard?.addKey(Phaser.Input.Keyboard.KeyCodes.SPACE);
		
		// Circle click handler
		this.targetCircle.on('pointerdown', () => this.handleClick());
		
		// Space key as alternative
		this.input.keyboard?.on('keydown-SPACE', () => this.handleClick());
		
		// Start button
		this.createStartButton();
	}

	private createStartButton(): void {
		const theme = this.getThemeColors();
		
		const startButton = this.add.rectangle(400, 400, 200, 60, parseInt(theme.primary.replace('#', '0x'), 16));
		startButton.setInteractive();
		this.container?.add(startButton);
		
		const startText = this.add.text(400, 400, 'START', {
			fontSize: '24px',
			color: '#FFFFFF',
			fontStyle: 'bold'
		}).setOrigin(0.5);
		this.container?.add(startText);
		
		startButton.on('pointerdown', () => {
			startButton.destroy();
			startText.destroy();
			this.startRound();
		});
	}

	private startRound(): void {
		this.currentRound++;
		this.updateUI();
		
		// Reset state
		this.state = 'waiting';
		const theme = this.getThemeColors();
		
		// Show red circle
		if (this.targetCircle) {
			this.targetCircle.visible = true;
			this.targetCircle.setFillStyle(parseInt(theme.danger.replace('#', '0x'), 16));
			this.targetCircle.setScale(1);
		}
		
		if (this.instructionText) {
			this.instructionText.text = 'Wait for GREEN...';
			this.instructionText.setColor(theme.danger);
		}
		
		// Random wait time between 1-4 seconds
		const waitTime = 1000 + Math.random() * 3000;
		
		this.waitTimer = this.time.delayedCall(waitTime, () => {
			this.state = 'go';
			this.startTime = Date.now();
			
			if (this.targetCircle) {
				this.targetCircle.setFillStyle(parseInt(theme.success.replace('#', '0x'), 16));
				// Pulse animation
				this.tweens.add({
					targets: this.targetCircle,
					scale: 1.2,
					duration: 200,
					yoyo: true,
					repeat: -1
				});
			}
			
			if (this.instructionText) {
				this.instructionText.text = 'CLICK NOW!';
				this.instructionText.setColor(theme.success);
				this.instructionText.setScale(1.2);
			}
		});
		
		this.state = 'ready';
	}

	private handleClick(): void {
		const theme = this.getThemeColors();
		
		if (this.state === 'ready') {
			// Clicked too early
			this.state = 'tooEarly';
			
			if (this.waitTimer) {
				this.waitTimer.remove();
			}
			
			this.attempts.push({
				time: -1,
				success: false
			});
			
			if (this.targetCircle) {
				this.targetCircle.setFillStyle(parseInt(theme.warning.replace('#', '0x'), 16));
				this.tweens.killTweensOf(this.targetCircle);
				this.targetCircle.setScale(1);
			}
			
			if (this.instructionText) {
				this.instructionText.text = 'Too Early!';
				this.instructionText.setColor(theme.warning);
				this.instructionText.setScale(1);
			}
			
			// Flash effect
			this.cameras.main.flash(200, 255, 100, 100);
			
			// Continue after delay
			this.time.delayedCall(1500, () => {
				this.checkComplete();
			});
			
		} else if (this.state === 'go') {
			// Valid click
			const reactionTime = Date.now() - this.startTime;
			this.state = 'clicked';
			
			this.attempts.push({
				time: reactionTime,
				success: true
			});
			
			if (reactionTime < this.bestTime) {
				this.bestTime = reactionTime;
			}
			
			if (this.targetCircle) {
				this.tweens.killTweensOf(this.targetCircle);
				this.targetCircle.setScale(1);
				
				// Success animation
				this.tweens.add({
					targets: this.targetCircle,
					scale: 0,
					alpha: 0,
					duration: 300
				});
			}
			
			if (this.instructionText) {
				this.instructionText.text = `${reactionTime}ms`;
				this.instructionText.setColor(theme.primary);
				this.instructionText.setScale(1.5);
				
				this.tweens.add({
					targets: this.instructionText,
					scale: 1,
					duration: 300
				});
			}
			
			// Particle effect
			this.createSuccessEffect(400, 300);
			
			// Continue after delay
			this.time.delayedCall(1500, () => {
				this.checkComplete();
			});
		}
	}

	private createSuccessEffect(x: number, y: number): void {
		const theme = this.getThemeColors();
		
		for (let i = 0; i < 12; i++) {
			const particle = this.add.circle(x, y, 5, parseInt(theme.success.replace('#', '0x'), 16));
			this.container?.add(particle);
			
			const angle = (Math.PI * 2 * i) / 12;
			const distance = 100 + Math.random() * 50;
			
			this.tweens.add({
				targets: particle,
				x: x + Math.cos(angle) * distance,
				y: y + Math.sin(angle) * distance,
				alpha: 0,
				scale: 0,
				duration: 600,
				ease: 'Power2',
				onComplete: () => {
					particle.destroy();
				}
			});
		}
	}

	private checkComplete(): void {
		if (this.currentRound >= this.totalRounds) {
			this.showResults();
		} else {
			// Reset for next round
			if (this.targetCircle) {
				this.targetCircle.visible = false;
				this.targetCircle.setAlpha(1);
				this.targetCircle.setScale(1);
			}
			
			// Next round
			this.time.delayedCall(500, () => {
				this.startRound();
			});
		}
	}

	private showResults(): void {
		this.state = 'complete';
		const theme = this.getThemeColors();
		
		// Calculate stats
		const validAttempts = this.attempts.filter(a => a.success);
		const averageTime = validAttempts.length > 0 
			? Math.round(validAttempts.reduce((sum, a) => sum + a.time, 0) / validAttempts.length)
			: 0;
		const successRate = Math.round((validAttempts.length / this.attempts.length) * 100);
		
		// Hide game elements
		if (this.targetCircle) this.targetCircle.visible = false;
		if (this.instructionText) this.instructionText.visible = false;
		
		// Results overlay
		const overlay = this.add.graphics();
		overlay.fillStyle(0x000000, 0.9);
		overlay.fillRect(0, 0, 800, 600);
		this.container?.add(overlay);
		
		const titleText = this.add.text(400, 150, 'RESULTS', {
			fontSize: '48px',
			color: theme.primary,
			fontStyle: 'bold'
		}).setOrigin(0.5);
		this.container?.add(titleText);
		
		const statsText = this.add.text(400, 250, 
			`Best Time: ${this.bestTime === Infinity ? '--' : this.bestTime + 'ms'}\n` +
			`Average: ${averageTime}ms\n` +
			`Success Rate: ${successRate}%\n` +
			`Valid Attempts: ${validAttempts.length}/${this.totalRounds}`, {
			fontSize: '24px',
			color: theme.secondary,
			align: 'center',
			lineSpacing: 10
		}).setOrigin(0.5);
		this.container?.add(statsText);
		
		// Rating
		let rating = 'Try Again';
		let ratingColor = theme.danger;
		if (averageTime < 250 && successRate > 80) {
			rating = 'LIGHTNING FAST!';
			ratingColor = theme.success;
		} else if (averageTime < 350 && successRate > 60) {
			rating = 'Great Reflexes!';
			ratingColor = theme.primary;
		} else if (averageTime < 500 && successRate > 40) {
			rating = 'Good Job!';
			ratingColor = theme.warning;
		}
		
		const ratingText = this.add.text(400, 380, rating, {
			fontSize: '32px',
			color: ratingColor,
			fontStyle: 'bold'
		}).setOrigin(0.5);
		this.container?.add(ratingText);
		
		// Play again button
		const playButton = this.add.rectangle(400, 480, 200, 60, parseInt(theme.primary.replace('#', '0x'), 16));
		playButton.setInteractive();
		this.container?.add(playButton);
		
		const playText = this.add.text(400, 480, 'Play Again', {
			fontSize: '24px',
			color: '#FFFFFF'
		}).setOrigin(0.5);
		this.container?.add(playText);
		
		playButton.on('pointerdown', () => {
			this.scene.restart();
		});
		
		// Submit score
		this.submitScore(averageTime);
	}

	private updateUI(): void {
		const theme = this.getThemeColors();
		
		if (this.scoreText) {
			this.scoreText.text = `Round: ${this.currentRound}/${this.totalRounds}`;
		}
		
		if (this.bestTimeText) {
			this.bestTimeText.text = `Best: ${this.bestTime === Infinity ? '--' : this.bestTime + 'ms'}`;
		}
		
		const validAttempts = this.attempts.filter(a => a.success);
		if (this.averageTimeText && validAttempts.length > 0) {
			const avg = Math.round(validAttempts.reduce((sum, a) => sum + a.time, 0) / validAttempts.length);
			this.averageTimeText.text = `Average: ${avg}ms`;
		}
	}

	private async submitScore(averageTime: number): Promise<void> {
		if (this.configData.sessionId) {
			// Score based on average time (lower is better)
			const score = Math.max(0, Math.round(1000 - averageTime));
			
			try {
				const response = await fetch(`/wp-json/ea-gaming/v1/sessions/${this.configData.sessionId}`, {
					method: 'DELETE',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': (window as any).eaGamingNonce || ''
					},
					body: JSON.stringify({
						score: score,
						questions_correct: 0,
						questions_total: 0
					})
				});
				
				if (response.ok) {
					this.game.events.emit('game-over', { 
						score: score,
						averageTime: averageTime,
						bestTime: this.bestTime === Infinity ? null : this.bestTime
					});
				}
			} catch (error) {
				console.error('Failed to submit score:', error);
			}
		}
	}
}