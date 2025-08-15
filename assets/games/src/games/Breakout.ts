import Phaser from '../utils/PhaserShim';
import { EAGameBase, GameConfig } from '../core/EAGameBase';
import { QuestionOverlay } from '../utils/QuestionOverlay';

interface Brick {
	x: number;
	y: number;
	width: number;
	height: number;
	hits: number;
	maxHits: number;
	sprite?: Phaser.GameObjects.Rectangle;
}

export class Breakout extends EAGameBase {
	private paddle?: Phaser.GameObjects.Rectangle;
	private ball?: Phaser.GameObjects.Circle;
	private bricks: Brick[] = [];
	private paddleX: number = 400;
	private ballX: number = 400;
	private ballY: number = 450;
	private ballVX: number = 3;
	private ballVY: number = -4;
	private score: number = 0;
	private lives: number = 3;
	private level: number = 1;
	private gameRunning: boolean = false;
	private cursors?: Phaser.Types.Input.Keyboard.CursorKeys;
	private spaceKey?: Phaser.Input.Keyboard.Key;
	private scoreText?: Phaser.GameObjects.Text;
	private livesText?: Phaser.GameObjects.Text;
	private levelText?: Phaser.GameObjects.Text;
	private container?: Phaser.GameObjects.Container;
	private graphics?: Phaser.GameObjects.Graphics;
	private overlay?: QuestionOverlay;
	private ballAttached: boolean = true;

	constructor(key: string, config: GameConfig) {
		super(key, config);
	}

	create(): void {
		const theme = this.getThemeColors();
		
		// Create container
		this.container = this.add.container(0, 0);
		
		// Background
		this.graphics = this.add.graphics();
		this.graphics.fillStyle(0x1a1a1a, 1);
		this.graphics.fillRect(0, 0, 800, 600);
		
		// Create paddle
		this.paddle = this.add.rectangle(this.paddleX, 550, 100, 15, parseInt(theme.primary.replace('#', '0x')));
		this.container.add(this.paddle);
		
		// Create ball
		this.ball = this.add.circle(this.ballX, this.ballY, 8, parseInt(theme.secondary.replace('#', '0x')));
		this.container.add(this.ball);
		
		// Create UI
		this.scoreText = this.add.text(10, 10, 'Score: 0', {
			fontSize: '18px',
			color: theme.primary
		});
		this.container.add(this.scoreText);
		
		this.livesText = this.add.text(10, 35, 'Lives: 3', {
			fontSize: '18px',
			color: theme.primary
		});
		this.container.add(this.livesText);
		
		this.levelText = this.add.text(10, 60, 'Level: 1', {
			fontSize: '18px',
			color: theme.primary
		});
		this.container.add(this.levelText);
		
		// Setup input
		this.cursors = this.input.keyboard?.createCursorKeys();
		this.spaceKey = this.input.keyboard?.addKey(Phaser.Input.Keyboard.KeyCodes.SPACE);
		
		// Create bricks
		this.createBricks();
		
		// Start game
		this.gameRunning = true;
		
		// Show instructions
		this.showInstructions();
		
		// Question gate at start if configured
		if (this.configData.gateMode === 'start' && this.configData.quizId) {
			this.askQuestion();
		}
	}

	update(time: number, delta: number): void {
		if (!this.gameRunning || !this.paddle || !this.ball) return;
		
		// Paddle movement
		if (this.cursors?.left.isDown && this.paddleX > 50) {
			this.paddleX -= 8;
			this.paddle.x = this.paddleX;
			if (this.ballAttached) {
				this.ballX = this.paddleX;
				this.ball.x = this.ballX;
			}
		}
		if (this.cursors?.right.isDown && this.paddleX < 750) {
			this.paddleX += 8;
			this.paddle.x = this.paddleX;
			if (this.ballAttached) {
				this.ballX = this.paddleX;
				this.ball.x = this.ballX;
			}
		}
		
		// Launch ball
		if (this.spaceKey?.isDown && this.ballAttached) {
			this.ballAttached = false;
			this.ballVY = -4 - this.level * 0.5;
		}
		
		// Update ball
		if (!this.ballAttached) {
			this.ballX += this.ballVX;
			this.ballY += this.ballVY;
			this.ball.x = this.ballX;
			this.ball.y = this.ballY;
			
			// Wall collision
			if (this.ballX <= 10 || this.ballX >= 790) {
				this.ballVX *= -1;
			}
			if (this.ballY <= 10) {
				this.ballVY *= -1;
			}
			
			// Paddle collision
			if (this.ballY >= 540 && this.ballY <= 560 && 
			    Math.abs(this.ballX - this.paddleX) < 60) {
				this.ballVY = -Math.abs(this.ballVY);
				// Add spin based on hit position
				const hitPos = (this.ballX - this.paddleX) / 50;
				this.ballVX = hitPos * 5;
			}
			
			// Ball lost
			if (this.ballY > 600) {
				this.loseLife();
			}
			
			// Check brick collisions
			this.checkBrickCollisions();
		}
		
		// Check level complete
		if (this.bricks.every(b => b.hits >= b.maxHits)) {
			this.nextLevel();
		}
	}

	private createBricks(): void {
		const theme = this.getThemeColors();
		const colors = [
			parseInt(theme.danger.replace('#', '0x')),
			parseInt(theme.warning.replace('#', '0x')),
			parseInt(theme.success.replace('#', '0x')),
			parseInt(theme.secondary.replace('#', '0x'))
		];
		
		this.bricks = [];
		const rows = 4 + this.level;
		const cols = 10;
		
		for (let row = 0; row < rows; row++) {
			for (let col = 0; col < cols; col++) {
				const x = 100 + col * 65;
				const y = 100 + row * 30;
				const maxHits = Math.max(1, rows - row);
				const sprite = this.add.rectangle(x, y, 60, 25, colors[row % colors.length]);
				this.container?.add(sprite);
				
				this.bricks.push({
					x,
					y,
					width: 60,
					height: 25,
					hits: 0,
					maxHits,
					sprite
				});
			}
		}
	}

	private checkBrickCollisions(): void {
		for (const brick of this.bricks) {
			if (brick.hits >= brick.maxHits) continue;
			
			// Simple AABB collision
			if (this.ballX > brick.x - brick.width/2 - 8 &&
			    this.ballX < brick.x + brick.width/2 + 8 &&
			    this.ballY > brick.y - brick.height/2 - 8 &&
			    this.ballY < brick.y + brick.height/2 + 8) {
				
				// Hit brick
				brick.hits++;
				
				// Determine bounce direction
				const overlapX = Math.min(
					Math.abs(this.ballX - (brick.x - brick.width/2)),
					Math.abs(this.ballX - (brick.x + brick.width/2))
				);
				const overlapY = Math.min(
					Math.abs(this.ballY - (brick.y - brick.height/2)),
					Math.abs(this.ballY - (brick.y + brick.height/2))
				);
				
				if (overlapX < overlapY) {
					this.ballVX *= -1;
				} else {
					this.ballVY *= -1;
				}
				
				// Update brick appearance or destroy
				if (brick.hits >= brick.maxHits) {
					if (brick.sprite) {
						this.createBrickExplosion(brick.x, brick.y);
						brick.sprite.destroy();
					}
					this.score += brick.maxHits * 10;
				} else {
					// Fade brick
					if (brick.sprite) {
						brick.sprite.setAlpha(1 - (brick.hits / brick.maxHits) * 0.5);
					}
					this.score += 5;
				}
				
				this.updateUI();
				break; // Only hit one brick per frame
			}
		}
	}

	private createBrickExplosion(x: number, y: number): void {
		const theme = this.getThemeColors();
		
		for (let i = 0; i < 6; i++) {
			const particle = this.add.rectangle(x, y, 8, 8, parseInt(theme.warning.replace('#', '0x')));
			this.container?.add(particle);
			
			const angle = (Math.PI * 2 * i) / 6;
			const speed = 50 + Math.random() * 50;
			
			this.tweens.add({
				targets: particle,
				x: x + Math.cos(angle) * speed,
				y: y + Math.sin(angle) * speed,
				alpha: 0,
				scale: 0.5,
				duration: 400,
				onComplete: () => {
					particle.destroy();
				}
			});
		}
	}

	private loseLife(): void {
		this.lives--;
		this.updateUI();
		
		if (this.lives <= 0) {
			this.gameOver();
		} else {
			// Reset ball
			this.ballAttached = true;
			this.ballX = this.paddleX;
			this.ballY = 450;
			this.ballVX = 3;
			this.ballVY = -4;
			
			if (this.ball) {
				this.ball.x = this.ballX;
				this.ball.y = this.ballY;
			}
			
			// Flash paddle
			this.paddle?.setAlpha(0.5);
			this.time.delayedCall(200, () => {
				this.paddle?.setAlpha(1);
			});
		}
	}

	private nextLevel(): void {
		this.level++;
		this.updateUI();
		
		// Reset ball
		this.ballAttached = true;
		this.ballX = this.paddleX;
		this.ballY = 450;
		this.ballVX = 3;
		this.ballVY = -4 - this.level * 0.5;
		
		if (this.ball) {
			this.ball.x = this.ballX;
			this.ball.y = this.ballY;
		}
		
		// Clear old bricks
		this.bricks.forEach(b => {
			if (b.sprite) b.sprite.destroy();
		});
		
		// Create new bricks
		this.createBricks();
		
		// Question gate between levels if configured
		if (this.configData.gateMode === 'interval' && this.configData.quizId) {
			this.askQuestion();
		}
	}

	private showInstructions(): void {
		const theme = this.getThemeColors();
		
		const instructions = this.add.text(400, 300, 'Press SPACE to launch ball\nArrow keys to move paddle', {
			fontSize: '20px',
			color: theme.primary,
			align: 'center'
		}).setOrigin(0.5);
		this.container?.add(instructions);
		
		this.time.delayedCall(3000, () => {
			instructions.destroy();
		});
	}

	private async askQuestion(): Promise<void> {
		this.gameRunning = false;
		
		if (!this.overlay && this.configData.quizId) {
			this.overlay = new QuestionOverlay(this, {
				sessionId: this.configData.sessionId,
				quizId: this.configData.quizId,
				theme: this.configData.theme
			});
		}
		
		if (this.overlay) {
			const result = await this.overlay.ask();
			this.game.events.emit('qa-closed', result);
			this.gameRunning = true;
		}
	}

	private updateUI(): void {
		if (this.scoreText) this.scoreText.text = `Score: ${this.score}`;
		if (this.livesText) this.livesText.text = `Lives: ${this.lives}`;
		if (this.levelText) this.levelText.text = `Level: ${this.level}`;
	}

	private gameOver(): void {
		this.gameRunning = false;
		
		const theme = this.getThemeColors();
		
		// Game over overlay
		const overlay = this.add.graphics();
		overlay.fillStyle(0x000000, 0.8);
		overlay.fillRect(0, 0, 800, 600);
		this.container?.add(overlay);
		
		const gameOverText = this.add.text(400, 250, 'GAME OVER', {
			fontSize: '48px',
			color: theme.danger,
			fontStyle: 'bold'
		}).setOrigin(0.5);
		this.container?.add(gameOverText);
		
		const finalScore = this.add.text(400, 320, `Final Score: ${this.score}`, {
			fontSize: '24px',
			color: theme.primary
		}).setOrigin(0.5);
		this.container?.add(finalScore);
		
		const finalLevel = this.add.text(400, 360, `Level Reached: ${this.level}`, {
			fontSize: '20px',
			color: theme.secondary
		}).setOrigin(0.5);
		this.container?.add(finalLevel);
		
		// Restart button
		const restartButton = this.add.rectangle(400, 440, 200, 50, parseInt(theme.primary.replace('#', '0x')));
		restartButton.setInteractive();
		this.container?.add(restartButton);
		
		const restartText = this.add.text(400, 440, 'Play Again', {
			fontSize: '20px',
			color: '#FFFFFF'
		}).setOrigin(0.5);
		this.container?.add(restartText);
		
		restartButton.on('pointerdown', () => {
			this.scene.restart();
		});
		
		// Submit score
		this.submitScore();
	}

	private async submitScore(): Promise<void> {
		if (this.configData.sessionId) {
			try {
				const response = await fetch(`/wp-json/ea-gaming/v1/sessions/${this.configData.sessionId}`, {
					method: 'DELETE',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': (window as any).eaGamingNonce || ''
					},
					body: JSON.stringify({
						score: this.score,
						questions_correct: 0,
						questions_total: 0
					})
				});
				
				if (response.ok) {
					this.game.events.emit('game-over', { 
						score: this.score,
						level: this.level
					});
				}
			} catch (error) {
				console.error('Failed to submit score:', error);
			}
		}
	}
}