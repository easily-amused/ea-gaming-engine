import Phaser from 'phaser';
import { EAGameBase, GameConfig } from '../index';

export class Pong extends EAGameBase {
	private playerPaddle?: Phaser.GameObjects.Rectangle;
	private aiPaddle?: Phaser.GameObjects.Rectangle;
	private ball?: Phaser.GameObjects.Circle;
	private playerY: number = 300;
	private aiY: number = 300;
	private ballX: number = 400;
	private ballY: number = 300;
	private ballVX: number = 4;
	private ballVY: number = 3;
	private playerScore: number = 0;
	private aiScore: number = 0;
	private gameRunning: boolean = false;
	private serving: boolean = true;
	private cursors?: Phaser.Types.Input.Keyboard.CursorKeys;
	private spaceKey?: Phaser.Input.Keyboard.Key;
	private playerScoreText?: Phaser.GameObjects.Text;
	private aiScoreText?: Phaser.GameObjects.Text;
	private centerLine?: Phaser.GameObjects.Graphics;
	private container?: Phaser.GameObjects.Container;
	private maxScore: number = 11;
	private aiSpeed: number = 3;
	private paddleHeight: number = 80;
	private paddleWidth: number = 12;

	constructor(key: string, config: GameConfig) {
		super(key, config);
	}

	create(): void {
		const theme = this.getThemeColors();
		
		// Create container
		this.container = this.add.container(0, 0);
		
		// Background
		const bg = this.add.graphics();
		bg.fillStyle(0x0a0a0a, 1);
		bg.fillRect(0, 0, 800, 600);
		this.container.add(bg);
		
		// Center line
		this.centerLine = this.add.graphics();
		this.centerLine.lineStyle(2, parseInt(theme.secondary.replace('#', '0x'), 16), 0.5);
		for (let i = 0; i < 600; i += 20) {
			this.centerLine.moveTo(400, i);
			this.centerLine.lineTo(400, i + 10);
		}
		this.centerLine.strokePath();
		this.container.add(this.centerLine);
		
		// Create paddles
		this.playerPaddle = this.add.rectangle(50, this.playerY, this.paddleWidth, this.paddleHeight, 
			parseInt(theme.primary.replace('#', '0x'), 16));
		this.container.add(this.playerPaddle);
		
		this.aiPaddle = this.add.rectangle(750, this.aiY, this.paddleWidth, this.paddleHeight, 
			parseInt(theme.danger.replace('#', '0x'), 16));
		this.container.add(this.aiPaddle);
		
		// Create ball
		this.ball = this.add.circle(this.ballX, this.ballY, 8, 
			parseInt(theme.secondary.replace('#', '0x'), 16));
		this.container.add(this.ball);
		
		// Create scores
		this.playerScoreText = this.add.text(320, 30, '0', {
			fontSize: '48px',
			color: theme.primary,
			fontStyle: 'bold'
		});
		this.container.add(this.playerScoreText);
		
		this.aiScoreText = this.add.text(460, 30, '0', {
			fontSize: '48px',
			color: theme.danger,
			fontStyle: 'bold'
		});
		this.container.add(this.aiScoreText);
		
		// Setup input
		this.cursors = this.input.keyboard?.createCursorKeys();
		this.spaceKey = this.input.keyboard?.addKey(Phaser.Input.Keyboard.KeyCodes.SPACE);
		
		// Show instructions
		this.showInstructions();
		
		// Start game
		this.gameRunning = true;
	}

	update(time: number, delta: number): void {
		if (!this.gameRunning) return;
		
		// Player paddle movement
		if (this.cursors?.up.isDown && this.playerY > 40) {
			this.playerY -= 6;
			if (this.playerPaddle) this.playerPaddle.y = this.playerY;
		}
		if (this.cursors?.down.isDown && this.playerY < 560) {
			this.playerY += 6;
			if (this.playerPaddle) this.playerPaddle.y = this.playerY;
		}
		
		// AI paddle movement
		this.updateAI();
		
		// Ball serving
		if (this.serving && this.spaceKey?.isDown) {
			this.serving = false;
			// Randomize initial direction
			this.ballVX = (Math.random() > 0.5 ? 1 : -1) * 4;
			this.ballVY = (Math.random() - 0.5) * 6;
		}
		
		// Ball movement
		if (!this.serving) {
			this.ballX += this.ballVX;
			this.ballY += this.ballVY;
			
			if (this.ball) {
				this.ball.x = this.ballX;
				this.ball.y = this.ballY;
			}
			
			// Top/bottom wall collision
			if (this.ballY <= 10 || this.ballY >= 590) {
				this.ballVY *= -1;
				this.createBallTrail();
			}
			
			// Player paddle collision
			if (this.ballX <= 60 && this.ballX >= 40 && 
			    Math.abs(this.ballY - this.playerY) < this.paddleHeight/2 + 8) {
				this.ballVX = Math.abs(this.ballVX) * 1.05; // Speed up slightly
				// Add spin based on hit position
				const hitPos = (this.ballY - this.playerY) / (this.paddleHeight/2);
				this.ballVY = hitPos * 5;
				this.createPaddleHitEffect(50, this.playerY);
			}
			
			// AI paddle collision
			if (this.ballX >= 740 && this.ballX <= 760 && 
			    Math.abs(this.ballY - this.aiY) < this.paddleHeight/2 + 8) {
				this.ballVX = -Math.abs(this.ballVX) * 1.05; // Speed up slightly
				// Add spin based on hit position
				const hitPos = (this.ballY - this.aiY) / (this.paddleHeight/2);
				this.ballVY = hitPos * 5;
				this.createPaddleHitEffect(750, this.aiY);
			}
			
			// Score checking
			if (this.ballX < 0) {
				this.aiScore++;
				this.updateScores();
				this.resetBall();
			} else if (this.ballX > 800) {
				this.playerScore++;
				this.updateScores();
				this.resetBall();
			}
		}
		
		// Check win condition
		if (this.playerScore >= this.maxScore || this.aiScore >= this.maxScore) {
			this.gameOver();
		}
	}

	private updateAI(): void {
		if (!this.aiPaddle || !this.ball) return;
		
		// Simple AI that follows the ball with some lag
		const targetY = this.ballY;
		const diff = targetY - this.aiY;
		
		// Add some imperfection to AI
		const speedModifier = 1 - (this.playerScore / this.maxScore) * 0.3; // AI gets worse as player leads
		
		if (Math.abs(diff) > 5) {
			if (diff > 0 && this.aiY < 560) {
				this.aiY += this.aiSpeed * speedModifier;
			} else if (diff < 0 && this.aiY > 40) {
				this.aiY -= this.aiSpeed * speedModifier;
			}
			this.aiPaddle.y = this.aiY;
		}
	}

	private createBallTrail(): void {
		const theme = this.getThemeColors();
		const trail = this.add.circle(this.ballX, this.ballY, 8, 
			parseInt(theme.secondary.replace('#', '0x'), 16));
		trail.setAlpha(0.5);
		this.container?.add(trail);
		
		this.tweens.add({
			targets: trail,
			alpha: 0,
			scale: 0.5,
			duration: 300,
			onComplete: () => {
				trail.destroy();
			}
		});
	}

	private createPaddleHitEffect(x: number, y: number): void {
		const theme = this.getThemeColors();
		
		for (let i = 0; i < 4; i++) {
			const particle = this.add.circle(x, y + (Math.random() - 0.5) * 40, 3, 
				parseInt(theme.warning.replace('#', '0x'), 16));
			this.container?.add(particle);
			
			this.tweens.add({
				targets: particle,
				x: x + (x < 400 ? -30 : 30),
				alpha: 0,
				scale: 0.5,
				duration: 200,
				onComplete: () => {
					particle.destroy();
				}
			});
		}
	}

	private resetBall(): void {
		this.serving = true;
		this.ballX = 400;
		this.ballY = 300;
		this.ballVX = 4;
		this.ballVY = 3;
		
		if (this.ball) {
			this.ball.x = this.ballX;
			this.ball.y = this.ballY;
		}
		
		// Flash score
		if (this.playerScore > this.aiScore) {
			this.playerScoreText?.setScale(1.5);
			this.tweens.add({
				targets: this.playerScoreText,
				scale: 1,
				duration: 300
			});
		} else if (this.aiScore > this.playerScore) {
			this.aiScoreText?.setScale(1.5);
			this.tweens.add({
				targets: this.aiScoreText,
				scale: 1,
				duration: 300
			});
		}
	}

	private updateScores(): void {
		if (this.playerScoreText) this.playerScoreText.text = this.playerScore.toString();
		if (this.aiScoreText) this.aiScoreText.text = this.aiScore.toString();
	}

	private showInstructions(): void {
		const theme = this.getThemeColors();
		
		const instructions = this.add.text(400, 300, 'PONG\n\nUse UP/DOWN arrows to move\nPress SPACE to serve\nFirst to 11 wins!', {
			fontSize: '20px',
			color: theme.primary,
			align: 'center'
		}).setOrigin(0.5);
		this.container?.add(instructions);
		
		this.time.delayedCall(3000, () => {
			this.tweens.add({
				targets: instructions,
				alpha: 0,
				duration: 500,
				onComplete: () => {
					instructions.destroy();
				}
			});
		});
	}

	private gameOver(): void {
		this.gameRunning = false;
		
		const theme = this.getThemeColors();
		const winner = this.playerScore >= this.maxScore;
		
		// Game over overlay
		const overlay = this.add.graphics();
		overlay.fillStyle(0x000000, 0.8);
		overlay.fillRect(0, 0, 800, 600);
		this.container?.add(overlay);
		
		const resultText = this.add.text(400, 250, winner ? 'YOU WIN!' : 'AI WINS!', {
			fontSize: '48px',
			color: winner ? theme.success : theme.danger,
			fontStyle: 'bold'
		}).setOrigin(0.5);
		this.container?.add(resultText);
		
		const finalScore = this.add.text(400, 320, 
			`${this.playerScore} - ${this.aiScore}`, {
			fontSize: '36px',
			color: theme.primary
		}).setOrigin(0.5);
		this.container?.add(finalScore);
		
		// Restart button
		const restartButton = this.add.rectangle(400, 420, 200, 50, 
			parseInt(theme.primary.replace('#', '0x'), 16));
		restartButton.setInteractive();
		this.container?.add(restartButton);
		
		const restartText = this.add.text(400, 420, 'Play Again', {
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
			const finalScore = this.playerScore * 100 - this.aiScore * 10;
			
			try {
				const response = await fetch(`/wp-json/ea-gaming/v1/sessions/${this.configData.sessionId}`, {
					method: 'DELETE',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': (window as any).eaGamingNonce || ''
					},
					body: JSON.stringify({
						score: Math.max(0, finalScore),
						questions_correct: 0,
						questions_total: 0
					})
				});
				
				if (response.ok) {
					this.game.events.emit('game-over', { 
						score: finalScore,
						playerScore: this.playerScore,
						aiScore: this.aiScore,
						won: this.playerScore >= this.maxScore
					});
				}
			} catch (error) {
				console.error('Failed to submit score:', error);
			}
		}
	}
}