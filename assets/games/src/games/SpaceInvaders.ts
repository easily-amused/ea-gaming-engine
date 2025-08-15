import Phaser from '../utils/PhaserShim';
import { EAGameBase, GameConfig } from '../core/EAGameBase';
import { QuestionOverlay } from '../utils/QuestionOverlay';

interface Invader {
	x: number;
	y: number;
	alive: boolean;
	sprite?: Phaser.GameObjects.Rectangle;
}

interface Bullet {
	x: number;
	y: number;
	active: boolean;
	sprite?: Phaser.GameObjects.Rectangle;
}

export class SpaceInvaders extends EAGameBase {
	private player?: Phaser.GameObjects.Rectangle;
	private playerX: number = 400;
	private invaders: Invader[][] = [];
	private playerBullets: Bullet[] = [];
	private invaderBullets: Bullet[] = [];
	private score: number = 0;
	private lives: number = 3;
	private wave: number = 1;
	private invaderDirection: number = 1;
	private invaderSpeed: number = 1;
	private lastInvaderShot: number = 0;
	private gameRunning: boolean = false;
	private cursors?: Phaser.Types.Input.Keyboard.CursorKeys;
	private spaceKey?: Phaser.Input.Keyboard.Key;
	private scoreText?: Phaser.GameObjects.Text;
	private livesText?: Phaser.GameObjects.Text;
	private waveText?: Phaser.GameObjects.Text;
	private gameOverText?: Phaser.GameObjects.Text;
	private restartButton?: Phaser.GameObjects.Rectangle;
	private restartText?: Phaser.GameObjects.Text;
	private container?: Phaser.GameObjects.Container;
	private graphics?: Phaser.GameObjects.Graphics;
	private overlay?: QuestionOverlay;
	private lastWaveQuestionTime: number = 0;

	constructor(key: string, config: GameConfig) {
		super(key, config);
	}

	preload(): void {
		// Load any assets if needed
	}

	create(): void {
		const theme = this.getThemeColors();
		
		// Create container
		this.container = this.add.container(0, 0);
		
		// Background
		this.graphics = this.add.graphics();
		this.graphics.fillStyle(0x000000, 1);
		this.graphics.fillRect(0, 0, 800, 600);
		
		// Create player
		this.player = this.add.rectangle(this.playerX, 550, 40, 20, parseInt(theme.primary.replace('#', '0x')));
		this.container.add(this.player);
		
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
		
		this.waveText = this.add.text(10, 60, 'Wave: 1', {
			fontSize: '18px',
			color: theme.primary
		});
		this.container.add(this.waveText);
		
		// Setup input
		this.cursors = this.input.keyboard?.createCursorKeys();
		this.spaceKey = this.input.keyboard?.addKey(Phaser.Input.Keyboard.KeyCodes.SPACE);
		
		// Initialize invaders
		this.createInvaders();
		
		// Start game
		this.gameRunning = true;
		
		// Question gate at start if configured
		if (this.configData.gateMode === 'start' && this.configData.quizId) {
			this.pauseGame();
			this.askQuestion();
		}
	}

	update(time: number, delta: number): void {
		if (!this.gameRunning || !this.player) return;
		
		// Player movement
		if (this.cursors?.left.isDown && this.playerX > 20) {
			this.playerX -= 5;
			this.player.x = this.playerX;
		}
		if (this.cursors?.right.isDown && this.playerX < 780) {
			this.playerX += 5;
			this.player.x = this.playerX;
		}
		
		// Shooting
		if (this.spaceKey?.isDown) {
			this.shootPlayerBullet();
		}
		
		// Update invaders
		this.updateInvaders(delta);
		
		// Update bullets
		this.updateBullets(delta);
		
		// Spawn invader bullets
		if (time - this.lastInvaderShot > 2000 / this.wave) {
			this.spawnInvaderBullet();
			this.lastInvaderShot = time;
		}
		
		// Check collisions
		this.checkCollisions();
		
		// Check wave complete
		if (this.isWaveComplete()) {
			this.nextWave();
		}
	}

	private createInvaders(): void {
		const theme = this.getThemeColors();
		this.invaders = [];
		
		for (let row = 0; row < 5; row++) {
			this.invaders[row] = [];
			for (let col = 0; col < 11; col++) {
				const x = 100 + col * 50;
				const y = 100 + row * 40;
				const sprite = this.add.rectangle(x, y, 30, 20, parseInt(theme.secondary.replace('#', '0x')));
				this.container?.add(sprite);
				
				this.invaders[row][col] = {
					x,
					y,
					alive: true,
					sprite
				};
			}
		}
	}

	private updateInvaders(delta: number): void {
		let hitEdge = false;
		
		// Move invaders
		for (let row = 0; row < this.invaders.length; row++) {
			for (let col = 0; col < this.invaders[row].length; col++) {
				const invader = this.invaders[row][col];
				if (!invader.alive || !invader.sprite) continue;
				
				invader.x += this.invaderDirection * this.invaderSpeed;
				invader.sprite.x = invader.x;
				
				if (invader.x <= 50 || invader.x >= 750) {
					hitEdge = true;
				}
			}
		}
		
		// Change direction and move down
		if (hitEdge) {
			this.invaderDirection *= -1;
			this.invaderSpeed += 0.1;
			
			for (let row = 0; row < this.invaders.length; row++) {
				for (let col = 0; col < this.invaders[row].length; col++) {
					const invader = this.invaders[row][col];
					if (!invader.alive || !invader.sprite) continue;
					
					invader.y += 20;
					invader.sprite.y = invader.y;
					
					// Game over if invaders reach player
					if (invader.y > 520) {
						this.gameOver();
					}
				}
			}
		}
	}

	private shootPlayerBullet(): void {
		// Find inactive bullet
		let bullet = this.playerBullets.find(b => !b.active);
		
		if (!bullet) {
			// Create new bullet
			const theme = this.getThemeColors();
			const sprite = this.add.rectangle(this.playerX, 540, 4, 10, parseInt(theme.primary.replace('#', '0x')));
			this.container?.add(sprite);
			
			bullet = {
				x: this.playerX,
				y: 540,
				active: true,
				sprite
			};
			this.playerBullets.push(bullet);
		} else {
			// Reuse bullet
			bullet.x = this.playerX;
			bullet.y = 540;
			bullet.active = true;
			if (bullet.sprite) {
				bullet.sprite.visible = true;
				bullet.sprite.x = bullet.x;
				bullet.sprite.y = bullet.y;
			}
		}
	}

	private spawnInvaderBullet(): void {
		// Find random alive invader
		const aliveInvaders: Invader[] = [];
		for (let row = 0; row < this.invaders.length; row++) {
			for (let col = 0; col < this.invaders[row].length; col++) {
				if (this.invaders[row][col].alive) {
					aliveInvaders.push(this.invaders[row][col]);
				}
			}
		}
		
		if (aliveInvaders.length === 0) return;
		
		const shooter = aliveInvaders[Math.floor(Math.random() * aliveInvaders.length)];
		
		// Find inactive bullet
		let bullet = this.invaderBullets.find(b => !b.active);
		
		if (!bullet) {
			const theme = this.getThemeColors();
			const sprite = this.add.rectangle(shooter.x, shooter.y, 4, 10, parseInt(theme.danger.replace('#', '0x')));
			this.container?.add(sprite);
			
			bullet = {
				x: shooter.x,
				y: shooter.y,
				active: true,
				sprite
			};
			this.invaderBullets.push(bullet);
		} else {
			bullet.x = shooter.x;
			bullet.y = shooter.y;
			bullet.active = true;
			if (bullet.sprite) {
				bullet.sprite.visible = true;
				bullet.sprite.x = bullet.x;
				bullet.sprite.y = bullet.y;
			}
		}
	}

	private updateBullets(delta: number): void {
		// Update player bullets
		for (const bullet of this.playerBullets) {
			if (!bullet.active) continue;
			
			bullet.y -= 8;
			if (bullet.sprite) bullet.sprite.y = bullet.y;
			
			if (bullet.y < 0) {
				bullet.active = false;
				if (bullet.sprite) bullet.sprite.visible = false;
			}
		}
		
		// Update invader bullets
		for (const bullet of this.invaderBullets) {
			if (!bullet.active) continue;
			
			bullet.y += 5;
			if (bullet.sprite) bullet.sprite.y = bullet.y;
			
			if (bullet.y > 600) {
				bullet.active = false;
				if (bullet.sprite) bullet.sprite.visible = false;
			}
		}
	}

	private checkCollisions(): void {
		// Check player bullets hitting invaders
		for (const bullet of this.playerBullets) {
			if (!bullet.active) continue;
			
			for (let row = 0; row < this.invaders.length; row++) {
				for (let col = 0; col < this.invaders[row].length; col++) {
					const invader = this.invaders[row][col];
					if (!invader.alive) continue;
					
					if (Math.abs(bullet.x - invader.x) < 20 && Math.abs(bullet.y - invader.y) < 15) {
						// Hit!
						invader.alive = false;
						if (invader.sprite) invader.sprite.visible = false;
						bullet.active = false;
						if (bullet.sprite) bullet.sprite.visible = false;
						
						// Update score
						this.score += (6 - row) * 10;
						this.updateUI();
						
						// Particle effect
						this.createExplosion(invader.x, invader.y);
					}
				}
			}
		}
		
		// Check invader bullets hitting player
		for (const bullet of this.invaderBullets) {
			if (!bullet.active) continue;
			
			if (Math.abs(bullet.x - this.playerX) < 20 && Math.abs(bullet.y - 550) < 15) {
				// Hit!
				bullet.active = false;
				if (bullet.sprite) bullet.sprite.visible = false;
				
				this.lives--;
				this.updateUI();
				
				if (this.lives <= 0) {
					this.gameOver();
				} else {
					// Flash player
					this.player?.setAlpha(0.5);
					this.time.delayedCall(200, () => {
						this.player?.setAlpha(1);
					});
				}
			}
		}
	}

	private createExplosion(x: number, y: number): void {
		const theme = this.getThemeColors();
		const particles = [];
		
		for (let i = 0; i < 8; i++) {
			const particle = this.add.rectangle(x, y, 4, 4, parseInt(theme.warning.replace('#', '0x')));
			this.container?.add(particle);
			particles.push(particle);
			
			const angle = (Math.PI * 2 * i) / 8;
			const speed = 100 + Math.random() * 50;
			
			this.tweens.add({
				targets: particle,
				x: x + Math.cos(angle) * speed,
				y: y + Math.sin(angle) * speed,
				alpha: 0,
				duration: 500,
				onComplete: () => {
					particle.destroy();
				}
			});
		}
	}

	private isWaveComplete(): boolean {
		for (let row = 0; row < this.invaders.length; row++) {
			for (let col = 0; col < this.invaders[row].length; col++) {
				if (this.invaders[row][col].alive) {
					return false;
				}
			}
		}
		return true;
	}

	private nextWave(): void {
		this.wave++;
		this.invaderSpeed = 1 + this.wave * 0.2;
		this.invaderDirection = 1;
		
		// Clear bullets
		this.playerBullets.forEach(b => {
			if (b.sprite) b.sprite.destroy();
		});
		this.playerBullets = [];
		
		this.invaderBullets.forEach(b => {
			if (b.sprite) b.sprite.destroy();
		});
		this.invaderBullets = [];
		
		// Recreate invaders
		this.createInvaders();
		this.updateUI();
		
		// Question gate between waves if configured
		if (this.configData.gateMode === 'interval' && this.configData.quizId) {
			const now = Date.now();
			if (now - this.lastWaveQuestionTime > 60000) { // Every 60 seconds
				this.lastWaveQuestionTime = now;
				this.pauseGame();
				this.askQuestion();
			}
		}
	}

	private pauseGame(): void {
		this.gameRunning = false;
	}

	private resumeGame(): void {
		this.gameRunning = true;
	}

	private async askQuestion(): Promise<void> {
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
			this.resumeGame();
		}
	}

	private updateUI(): void {
		if (this.scoreText) this.scoreText.text = `Score: ${this.score}`;
		if (this.livesText) this.livesText.text = `Lives: ${this.lives}`;
		if (this.waveText) this.waveText.text = `Wave: ${this.wave}`;
	}

	private gameOver(): void {
		this.gameRunning = false;
		
		const theme = this.getThemeColors();
		
		// Game over overlay
		const overlay = this.add.graphics();
		overlay.fillStyle(0x000000, 0.8);
		overlay.fillRect(0, 0, 800, 600);
		this.container?.add(overlay);
		
		this.gameOverText = this.add.text(400, 250, 'GAME OVER', {
			fontSize: '48px',
			color: theme.danger,
			fontStyle: 'bold'
		}).setOrigin(0.5);
		this.container?.add(this.gameOverText);
		
		const finalScore = this.add.text(400, 320, `Final Score: ${this.score}`, {
			fontSize: '24px',
			color: theme.primary
		}).setOrigin(0.5);
		this.container?.add(finalScore);
		
		// Restart button
		this.restartButton = this.add.rectangle(400, 400, 200, 50, parseInt(theme.primary.replace('#', '0x')));
		this.restartButton.setInteractive();
		this.container?.add(this.restartButton);
		
		this.restartText = this.add.text(400, 400, 'Play Again', {
			fontSize: '20px',
			color: '#FFFFFF'
		}).setOrigin(0.5);
		this.container?.add(this.restartText);
		
		this.restartButton.on('pointerdown', () => {
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
					this.game.events.emit('game-over', { score: this.score });
				}
			} catch (error) {
				console.error('Failed to submit score:', error);
			}
		}
	}
}