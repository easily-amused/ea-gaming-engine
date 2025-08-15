import Phaser from '../utils/PhaserShim';
import { EAGameBase, GameConfig } from '../core/EAGameBase';
import { QuestionOverlay } from '../utils/QuestionOverlay';

interface Position {
    x: number;
    y: number;
}

/**
 * Snake Game
 * Classic snake game with LearnDash integration
 */
export class Snake extends EAGameBase {
    private gridSize: number = 20;
    private boardWidth: number = 20;
    private boardHeight: number = 20;
    private snake: Position[] = [{ x: 10, y: 10 }];
    private direction: Position = { x: 1, y: 0 };
    private food: Position = { x: 15, y: 15 };
    private score: number = 0;
    private bestScore: number = 0;
    private gameRunning: boolean = false;
    private gameOver: boolean = false;
    private stepTimer?: Phaser.Time.TimerEvent;
    private graphics?: Phaser.GameObjects.Graphics;
    private container?: Phaser.GameObjects.Container;
    
    // UI Elements
    private scoreText?: Phaser.GameObjects.Text;
    private bestScoreText?: Phaser.GameObjects.Text;
    private gameStateText?: Phaser.GameObjects.Text;
    private gameOverContainer?: Phaser.GameObjects.Container;
    
    // Game settings based on preset
    private gameSpeed: number = 150;
    private hintsEnabled: boolean = false;
    
    // Question overlay
    private questionOverlay?: QuestionOverlay;
    private nextQuestionScore: number = 50; // Ask question every 50 points
    
    constructor(config: string | Phaser.Types.Scenes.SettingsConfig, gameConfig: GameConfig) {
        super(config, gameConfig);
        this.applyPresetSettings();
    }
    
    /**
     * Apply settings based on preset
     */
    private applyPresetSettings(): void {
        const preset = this.configData.preset;
        switch (preset) {
            case 'chill':
                this.gameSpeed = 200;
                this.hintsEnabled = true;
                break;
            case 'classic':
                this.gameSpeed = 150;
                this.hintsEnabled = false;
                break;
            case 'pro':
                this.gameSpeed = 100;
                this.hintsEnabled = false;
                break;
            case 'accessible':
                this.gameSpeed = 250;
                this.hintsEnabled = true;
                break;
            default:
                this.gameSpeed = 150;
                break;
        }
    }
    
    preload(): void {
        super.preload();
        // No specific assets needed - using graphics primitives
    }
    
    create(): void {
        super.create();
        
        const colors = this.getThemeColors();
        const { width, height } = this.cameras.main;
        
        // Create background
        this.add.rectangle(width / 2, height / 2, width, height, 
            Phaser.Display.Color.HexStringToColor(colors.background).color);
        
        // Create game container
        this.container = this.add.container(width / 2 - 200, height / 2 - 200);
        
        // Create graphics for drawing
        this.graphics = this.add.graphics();
        
        // Create UI
        this.createUI();
        
        // Set up input
        this.setupInput();
        
        // Check for gate mode
        if (this.configData.gateMode === 'start' && this.configData.quizId) {
            this.showQuestionGate().then(() => {
                this.showStartScreen();
            });
        } else {
            this.showStartScreen();
        }
    }
    
    /**
     * Create UI elements
     */
    private createUI(): void {
        const colors = this.getThemeColors();
        
        // Score display
        this.scoreText = this.add.text(20, 20, `Score: ${this.score}`, {
            fontSize: '20px',
            color: colors['text-primary']
        });
        
        // Best score
        this.bestScoreText = this.add.text(20, 50, `Best: ${this.bestScore}`, {
            fontSize: '18px',
            color: colors['text-secondary']
        });
        
        // Game state
        this.gameStateText = this.add.text(400, 550, 'Press SPACE or ENTER to start', {
            fontSize: '16px',
            color: colors['text-secondary'],
            align: 'center'
        }).setOrigin(0.5);
    }
    
    /**
     * Set up keyboard input
     */
    private setupInput(): void {
        const cursors = this.input.keyboard?.createCursorKeys();
        const wasd = this.input.keyboard?.addKeys('W,A,S,D');
        const space = this.input.keyboard?.addKey(Phaser.Input.Keyboard.KeyCodes.SPACE);
        const enter = this.input.keyboard?.addKey(Phaser.Input.Keyboard.KeyCodes.ENTER);
        
        // Arrow keys
        cursors?.up.on('down', () => this.changeDirection(0, -1));
        cursors?.down.on('down', () => this.changeDirection(0, 1));
        cursors?.left.on('down', () => this.changeDirection(-1, 0));
        cursors?.right.on('down', () => this.changeDirection(1, 0));
        
        // WASD
        wasd?.W.on('down', () => this.changeDirection(0, -1));
        wasd?.S.on('down', () => this.changeDirection(0, 1));
        wasd?.A.on('down', () => this.changeDirection(-1, 0));
        wasd?.D.on('down', () => this.changeDirection(1, 0));
        
        // Start/restart
        space?.on('down', () => this.handleStartKey());
        enter?.on('down', () => this.handleStartKey());
    }
    
    /**
     * Change snake direction
     */
    private changeDirection(x: number, y: number): void {
        if (!this.gameRunning) return;
        
        // Prevent going back into self
        if (x !== 0 && this.direction.x === -x) return;
        if (y !== 0 && this.direction.y === -y) return;
        
        this.direction = { x, y };
    }
    
    /**
     * Handle start/restart key
     */
    private handleStartKey(): void {
        if (!this.gameRunning && !this.gameOver) {
            this.startGame();
        } else if (this.gameOver) {
            this.resetGame();
        }
    }
    
    /**
     * Show start screen
     */
    private showStartScreen(): void {
        const colors = this.getThemeColors();
        const { width, height } = this.cameras.main;
        
        // Clear any existing game over screen
        if (this.gameOverContainer) {
            this.gameOverContainer.destroy();
            this.gameOverContainer = undefined;
        }
        
        // Draw initial board
        this.drawBoard();
        this.drawSnake();
        this.drawFood();
    }
    
    /**
     * Start the game
     */
    private startGame(): void {
        this.gameRunning = true;
        this.gameOver = false;
        
        if (this.gameStateText) {
            this.gameStateText.setText('Use arrow keys or WASD to move');
        }
        
        // Start game loop
        this.stepTimer = this.time.addEvent({
            delay: this.gameSpeed,
            callback: this.step,
            callbackScope: this,
            loop: true
        });
    }
    
    /**
     * Game step (move snake)
     */
    private step(): void {
        if (!this.gameRunning || this.gameOver) return;
        
        // Calculate new head position
        const head = { ...this.snake[0] };
        head.x += this.direction.x;
        head.y += this.direction.y;
        
        // Check wall collision
        if (head.x < 0 || head.x >= this.boardWidth || 
            head.y < 0 || head.y >= this.boardHeight) {
            this.endGame();
            return;
        }
        
        // Check self collision
        if (this.snake.some(segment => segment.x === head.x && segment.y === head.y)) {
            this.endGame();
            return;
        }
        
        // Add new head
        this.snake.unshift(head);
        
        // Check food collision
        if (head.x === this.food.x && head.y === this.food.y) {
            this.score += 10;
            this.updateScore();
            this.generateFood();
            
            // Check for question gate on interval
            if (this.configData.gateMode === 'interval' && 
                this.score >= this.nextQuestionScore && 
                this.configData.quizId) {
                this.pauseForQuestion();
            }
        } else {
            // Remove tail if no food eaten
            this.snake.pop();
        }
        
        // Redraw
        this.draw();
    }
    
    /**
     * Generate new food position
     */
    private generateFood(): void {
        do {
            this.food = {
                x: Math.floor(Math.random() * this.boardWidth),
                y: Math.floor(Math.random() * this.boardHeight)
            };
        } while (this.snake.some(segment => 
            segment.x === this.food.x && segment.y === this.food.y));
    }
    
    /**
     * Draw everything
     */
    private draw(): void {
        if (!this.graphics) return;
        
        this.graphics.clear();
        this.drawBoard();
        this.drawSnake();
        this.drawFood();
    }
    
    /**
     * Draw the game board
     */
    private drawBoard(): void {
        if (!this.graphics) return;
        
        const colors = this.getThemeColors();
        
        // Draw grid
        this.graphics.lineStyle(1, Phaser.Display.Color.HexStringToColor('#e5e7eb').color);
        
        for (let i = 0; i <= this.boardWidth; i++) {
            const x = 200 + i * this.gridSize;
            this.graphics.moveTo(x, 100);
            this.graphics.lineTo(x, 100 + this.boardHeight * this.gridSize);
        }
        
        for (let i = 0; i <= this.boardHeight; i++) {
            const y = 100 + i * this.gridSize;
            this.graphics.moveTo(200, y);
            this.graphics.lineTo(200 + this.boardWidth * this.gridSize, y);
        }
        
        this.graphics.strokePath();
    }
    
    /**
     * Draw the snake
     */
    private drawSnake(): void {
        if (!this.graphics) return;
        
        const colors = this.getThemeColors();
        const snakeColor = Phaser.Display.Color.HexStringToColor(colors.primary).color;
        
        this.snake.forEach((segment, index) => {
            const x = 200 + segment.x * this.gridSize;
            const y = 100 + segment.y * this.gridSize;
            
            this.graphics.fillStyle(snakeColor);
            this.graphics.fillRect(x + 1, y + 1, this.gridSize - 2, this.gridSize - 2);
            
            // Draw eyes on head
            if (index === 0) {
                this.graphics.fillStyle(0xffffff);
                this.graphics.fillCircle(x + 5, y + 5, 2);
                this.graphics.fillCircle(x + 15, y + 5, 2);
            }
        });
    }
    
    /**
     * Draw the food
     */
    private drawFood(): void {
        if (!this.graphics) return;
        
        const colors = this.getThemeColors();
        const x = 200 + this.food.x * this.gridSize;
        const y = 100 + this.food.y * this.gridSize;
        
        this.graphics.fillStyle(Phaser.Display.Color.HexStringToColor(colors.danger).color);
        this.graphics.fillRect(x + 1, y + 1, this.gridSize - 2, this.gridSize - 2);
    }
    
    /**
     * Update score display
     */
    private updateScore(): void {
        if (this.scoreText) {
            this.scoreText.setText(`Score: ${this.score}`);
        }
        
        if (this.score > this.bestScore) {
            this.bestScore = this.score;
            if (this.bestScoreText) {
                this.bestScoreText.setText(`Best: ${this.bestScore}`);
            }
        }
    }
    
    /**
     * Show question gate
     */
    private async showQuestionGate(): Promise<void> {
        if (!this.configData.quizId) return;
        
        this.questionOverlay = new QuestionOverlay(this, {
            sessionId: this.configData.sessionId,
            quizId: this.configData.quizId,
            theme: this.configData.theme
        });
        
        const result = await this.questionOverlay.ask();
        
        if (!result.correct) {
            // Could add penalty or feedback
        }
        
        this.questionOverlay = undefined;
    }
    
    /**
     * Pause for question
     */
    private async pauseForQuestion(): Promise<void> {
        if (!this.configData.quizId) return;
        
        // Pause game
        this.gameRunning = false;
        if (this.stepTimer) {
            this.stepTimer.paused = true;
        }
        
        await this.showQuestionGate();
        
        // Resume game
        this.gameRunning = true;
        if (this.stepTimer) {
            this.stepTimer.paused = false;
        }
        
        // Update next question threshold
        this.nextQuestionScore += 50;
    }
    
    /**
     * End the game
     */
    private endGame(): void {
        this.gameRunning = false;
        this.gameOver = true;
        
        // Stop timer
        if (this.stepTimer) {
            this.stepTimer.destroy();
            this.stepTimer = undefined;
        }
        
        // Submit score
        this.submitScore();
        
        // Show game over screen
        this.showGameOverScreen();
        
        // Emit game over event
        this.game.events.emit('game-over', {
            score: this.score,
            bestScore: this.bestScore
        });
    }
    
    /**
     * Submit score to API
     */
    private async submitScore(): Promise<void> {
        try {
            await fetch(`/wp-json/ea-gaming/v1/sessions/${this.configData.sessionId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': (window as any).eaGamingNonce || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    score: this.score,
                    questions_correct: 0,
                    questions_total: 0,
                    perfect: false
                })
            });
        } catch (error) {
            console.error('Error submitting score:', error);
        }
    }
    
    /**
     * Show game over screen
     */
    private showGameOverScreen(): void {
        const colors = this.getThemeColors();
        const { width, height } = this.cameras.main;
        
        // Create game over container
        this.gameOverContainer = this.add.container(width / 2, height / 2);
        
        // Semi-transparent overlay
        const overlay = this.add.rectangle(0, 0, width, height, 0x000000, 0.7);
        this.gameOverContainer.add(overlay);
        
        // Game over panel
        const panel = this.add.rectangle(0, 0, 400, 300, 
            Phaser.Display.Color.HexStringToColor(colors.surface).color);
        panel.setStrokeStyle(2, Phaser.Display.Color.HexStringToColor(colors.primary).color);
        this.gameOverContainer.add(panel);
        
        // Title
        const title = this.add.text(0, -100, 'Game Over!', {
            fontSize: '32px',
            color: colors['text-primary'],
            fontStyle: 'bold'
        }).setOrigin(0.5);
        this.gameOverContainer.add(title);
        
        // Score
        const scoreText = this.add.text(0, -30, `Score: ${this.score}`, {
            fontSize: '24px',
            color: colors['text-primary']
        }).setOrigin(0.5);
        this.gameOverContainer.add(scoreText);
        
        // Best score
        const bestText = this.add.text(0, 10, `Best: ${this.bestScore}`, {
            fontSize: '20px',
            color: colors['text-secondary']
        }).setOrigin(0.5);
        this.gameOverContainer.add(bestText);
        
        // Restart button
        const restartBg = this.add.rectangle(0, 80, 150, 40, 
            Phaser.Display.Color.HexStringToColor(colors.primary).color);
        restartBg.setInteractive();
        this.gameOverContainer.add(restartBg);
        
        const restartText = this.add.text(0, 80, 'Play Again', {
            fontSize: '18px',
            color: '#ffffff'
        }).setOrigin(0.5);
        this.gameOverContainer.add(restartText);
        
        restartBg.on('pointerdown', () => this.resetGame());
        restartBg.on('pointerover', () => restartBg.setAlpha(0.8));
        restartBg.on('pointerout', () => restartBg.setAlpha(1));
        
        // Update state text
        if (this.gameStateText) {
            this.gameStateText.setText('Press SPACE or ENTER to play again');
        }
    }
    
    /**
     * Reset the game
     */
    private resetGame(): void {
        // Reset game state
        this.snake = [{ x: 10, y: 10 }];
        this.direction = { x: 1, y: 0 };
        this.food = { x: 15, y: 15 };
        this.score = 0;
        this.gameRunning = false;
        this.gameOver = false;
        this.nextQuestionScore = 50;
        
        // Clear game over screen
        if (this.gameOverContainer) {
            this.gameOverContainer.destroy();
            this.gameOverContainer = undefined;
        }
        
        // Update score
        this.updateScore();
        
        // Redraw
        this.draw();
        
        // Show start screen
        this.showStartScreen();
    }
    
    /**
     * Clean up
     */
    destroy(): void {
        if (this.stepTimer) {
            this.stepTimer.destroy();
        }
        
        if (this.questionOverlay) {
            this.questionOverlay.destroy();
        }
        
        super.destroy();
    }
}