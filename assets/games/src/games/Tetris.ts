import Phaser from 'phaser';
import { EAGameBase, GameConfig } from '../index';
import { QuestionOverlay } from '../utils/QuestionOverlay';

interface Piece {
    shape: number[][];
    color: string;
    x: number;
    y: number;
}

const PIECES = {
    I: { shape: [[1, 1, 1, 1]], color: '#00f5ff' },
    O: { shape: [[1, 1], [1, 1]], color: '#ffed00' },
    T: { shape: [[0, 1, 0], [1, 1, 1]], color: '#a000f0' },
    S: { shape: [[0, 1, 1], [1, 1, 0]], color: '#00f000' },
    Z: { shape: [[1, 1, 0], [0, 1, 1]], color: '#f00000' },
    J: { shape: [[1, 0, 0], [1, 1, 1]], color: '#0000f0' },
    L: { shape: [[0, 0, 1], [1, 1, 1]], color: '#ff7f00' }
};

/**
 * Tetris Game
 * Classic Tetris with LearnDash integration
 */
export class Tetris extends EAGameBase {
    private readonly BOARD_WIDTH = 10;
    private readonly BOARD_HEIGHT = 20;
    private readonly BLOCK_SIZE = 25;
    
    private board: (string | number)[][] = [];
    private currentPiece: Piece | null = null;
    private nextPiece: Piece | null = null;
    private score: number = 0;
    private level: number = 1;
    private lines: number = 0;
    private highScore: number = 0;
    private dropTime: number = 1000;
    private lastDrop: number = 0;
    private gameState: 'menu' | 'playing' | 'paused' | 'gameOver' = 'menu';
    
    // Graphics
    private graphics?: Phaser.GameObjects.Graphics;
    private boardContainer?: Phaser.GameObjects.Container;
    private nextPieceContainer?: Phaser.GameObjects.Container;
    
    // UI
    private scoreText?: Phaser.GameObjects.Text;
    private levelText?: Phaser.GameObjects.Text;
    private linesText?: Phaser.GameObjects.Text;
    private menuContainer?: Phaser.GameObjects.Container;
    private gameOverContainer?: Phaser.GameObjects.Container;
    
    // Question overlay
    private questionOverlay?: QuestionOverlay;
    private nextQuestionLevel: number = 2;
    
    // Controls
    private cursors?: Phaser.Types.Input.Keyboard.CursorKeys;
    private keys?: Record<string, Phaser.Input.Keyboard.Key>;
    
    constructor(config: string | Phaser.Types.Scenes.SettingsConfig, gameConfig: GameConfig) {
        super(config, gameConfig);
        this.applyPresetSettings();
    }
    
    /**
     * Apply preset settings
     */
    private applyPresetSettings(): void {
        const preset = this.configData.preset;
        switch (preset) {
            case 'chill':
                this.dropTime = 1200;
                break;
            case 'classic':
                this.dropTime = 1000;
                break;
            case 'pro':
                this.dropTime = 600;
                break;
            case 'accessible':
                this.dropTime = 1500;
                break;
        }
    }
    
    preload(): void {
        super.preload();
        // No specific assets needed
    }
    
    create(): void {
        super.create();
        
        const colors = this.getThemeColors();
        const { width, height } = this.cameras.main;
        
        // Background
        this.add.rectangle(width / 2, height / 2, width, height,
            Phaser.Display.Color.HexStringToColor(colors.background).color);
        
        // Initialize board
        this.initializeBoard();
        
        // Create graphics
        this.graphics = this.add.graphics();
        
        // Create containers
        this.boardContainer = this.add.container(250, 50);
        this.nextPieceContainer = this.add.container(550, 100);
        
        // Create UI
        this.createUI();
        
        // Set up input
        this.setupInput();
        
        // Check for gate mode
        if (this.configData.gateMode === 'start' && this.configData.quizId) {
            this.showQuestionGate().then(() => {
                this.showMenu();
            });
        } else {
            this.showMenu();
        }
    }
    
    /**
     * Initialize the board
     */
    private initializeBoard(): void {
        this.board = Array(this.BOARD_HEIGHT).fill(null).map(() => 
            Array(this.BOARD_WIDTH).fill(0)
        );
    }
    
    /**
     * Create UI elements
     */
    private createUI(): void {
        const colors = this.getThemeColors();
        
        // Score
        this.scoreText = this.add.text(550, 200, 'Score: 0', {
            fontSize: '20px',
            color: colors['text-primary']
        });
        
        // Level
        this.levelText = this.add.text(550, 230, 'Level: 1', {
            fontSize: '18px',
            color: colors['text-secondary']
        });
        
        // Lines
        this.linesText = this.add.text(550, 260, 'Lines: 0', {
            fontSize: '18px',
            color: colors['text-secondary']
        });
        
        // Next piece label
        this.add.text(550, 70, 'Next:', {
            fontSize: '18px',
            color: colors['text-primary']
        });
        
        // Controls hint
        this.add.text(400, 550, 'Arrow Keys: Move/Drop | Up/Space: Rotate', {
            fontSize: '14px',
            color: colors['text-secondary'],
            align: 'center'
        }).setOrigin(0.5);
    }
    
    /**
     * Set up keyboard input
     */
    private setupInput(): void {
        this.cursors = this.input.keyboard?.createCursorKeys();
        this.keys = this.input.keyboard?.addKeys({
            'W': Phaser.Input.Keyboard.KeyCodes.W,
            'A': Phaser.Input.Keyboard.KeyCodes.A,
            'S': Phaser.Input.Keyboard.KeyCodes.S,
            'D': Phaser.Input.Keyboard.KeyCodes.D,
            'SPACE': Phaser.Input.Keyboard.KeyCodes.SPACE,
            'ENTER': Phaser.Input.Keyboard.KeyCodes.ENTER,
            'ESC': Phaser.Input.Keyboard.KeyCodes.ESC
        });
        
        // Movement
        this.cursors?.left.on('down', () => this.movePiece(-1, 0));
        this.cursors?.right.on('down', () => this.movePiece(1, 0));
        this.cursors?.down.on('down', () => this.dropPiece());
        this.cursors?.up.on('down', () => this.rotatePiece());
        
        this.keys?.A.on('down', () => this.movePiece(-1, 0));
        this.keys?.D.on('down', () => this.movePiece(1, 0));
        this.keys?.S.on('down', () => this.dropPiece());
        this.keys?.W.on('down', () => this.rotatePiece());
        this.keys?.SPACE.on('down', () => this.rotatePiece());
        
        // Menu
        this.keys?.ENTER.on('down', () => this.handleEnterKey());
        this.keys?.ESC.on('down', () => this.handleEscKey());
    }
    
    /**
     * Show menu
     */
    private showMenu(): void {
        const colors = this.getThemeColors();
        const { width, height } = this.cameras.main;
        
        this.gameState = 'menu';
        
        // Clear any existing containers
        this.clearContainers();
        
        // Create menu container
        this.menuContainer = this.add.container(width / 2, height / 2);
        
        // Background panel
        const panel = this.add.rectangle(0, 0, 400, 300,
            Phaser.Display.Color.HexStringToColor(colors.surface).color);
        panel.setStrokeStyle(2, Phaser.Display.Color.HexStringToColor(colors.primary).color);
        this.menuContainer.add(panel);
        
        // Title
        const title = this.add.text(0, -100, 'TETRIS', {
            fontSize: '48px',
            color: colors['text-primary'],
            fontStyle: 'bold'
        }).setOrigin(0.5);
        this.menuContainer.add(title);
        
        // High score
        if (this.highScore > 0) {
            const highScoreText = this.add.text(0, -40, `High Score: ${this.highScore}`, {
                fontSize: '18px',
                color: colors['text-secondary']
            }).setOrigin(0.5);
            this.menuContainer.add(highScoreText);
        }
        
        // Start button
        const startBg = this.add.rectangle(0, 30, 150, 40,
            Phaser.Display.Color.HexStringToColor(colors.primary).color);
        startBg.setInteractive();
        this.menuContainer.add(startBg);
        
        const startText = this.add.text(0, 30, 'Start Game', {
            fontSize: '18px',
            color: '#ffffff'
        }).setOrigin(0.5);
        this.menuContainer.add(startText);
        
        startBg.on('pointerdown', () => this.startGame());
        startBg.on('pointerover', () => startBg.setAlpha(0.8));
        startBg.on('pointerout', () => startBg.setAlpha(1));
        
        // Instructions
        const instructions = this.add.text(0, 100, 'Press ENTER to start', {
            fontSize: '14px',
            color: colors['text-secondary']
        }).setOrigin(0.5);
        this.menuContainer.add(instructions);
    }
    
    /**
     * Start the game
     */
    private startGame(): void {
        this.clearContainers();
        this.gameState = 'playing';
        
        // Reset game state
        this.initializeBoard();
        this.score = 0;
        this.level = 1;
        this.lines = 0;
        this.dropTime = 1000;
        this.nextQuestionLevel = 2;
        
        // Update UI
        this.updateUI();
        
        // Create pieces
        this.currentPiece = this.createPiece();
        this.nextPiece = this.createPiece();
        
        // Start game loop
        this.lastDrop = this.time.now;
    }
    
    /**
     * Create a new piece
     */
    private createPiece(): Piece {
        const pieceTypes = Object.keys(PIECES) as (keyof typeof PIECES)[];
        const randomType = pieceTypes[Math.floor(Math.random() * pieceTypes.length)];
        const piece = PIECES[randomType];
        
        return {
            shape: piece.shape,
            color: piece.color,
            x: Math.floor(this.BOARD_WIDTH / 2) - Math.floor(piece.shape[0].length / 2),
            y: 0
        };
    }
    
    /**
     * Game update loop
     */
    update(time: number): void {
        super.update();
        
        if (this.gameState !== 'playing' || !this.currentPiece) return;
        
        // Auto drop
        if (time - this.lastDrop > this.dropTime) {
            this.dropPiece();
            this.lastDrop = time;
        }
        
        // Draw everything
        this.draw();
    }
    
    /**
     * Move piece
     */
    private movePiece(dx: number, dy: number): boolean {
        if (this.gameState !== 'playing' || !this.currentPiece) return false;
        
        const newPiece = {
            ...this.currentPiece,
            x: this.currentPiece.x + dx,
            y: this.currentPiece.y + dy
        };
        
        if (this.isValidMove(newPiece)) {
            this.currentPiece = newPiece;
            return true;
        }
        
        return false;
    }
    
    /**
     * Drop piece one row
     */
    private dropPiece(): void {
        if (!this.movePiece(0, 1)) {
            this.placePiece();
        }
    }
    
    /**
     * Rotate piece
     */
    private rotatePiece(): void {
        if (this.gameState !== 'playing' || !this.currentPiece) return;
        
        const rotatedShape = this.currentPiece.shape[0].map((_, index) =>
            this.currentPiece!.shape.map(row => row[index]).reverse()
        );
        
        const rotatedPiece = {
            ...this.currentPiece,
            shape: rotatedShape
        };
        
        if (this.isValidMove(rotatedPiece)) {
            this.currentPiece = rotatedPiece;
        }
    }
    
    /**
     * Check if move is valid
     */
    private isValidMove(piece: Piece): boolean {
        for (let y = 0; y < piece.shape.length; y++) {
            for (let x = 0; x < piece.shape[y].length; x++) {
                if (piece.shape[y][x]) {
                    const newX = piece.x + x;
                    const newY = piece.y + y;
                    
                    if (newX < 0 || newX >= this.BOARD_WIDTH || 
                        newY >= this.BOARD_HEIGHT ||
                        (newY >= 0 && this.board[newY][newX])) {
                        return false;
                    }
                }
            }
        }
        return true;
    }
    
    /**
     * Place piece on board
     */
    private placePiece(): void {
        if (!this.currentPiece) return;
        
        // Add piece to board
        this.currentPiece.shape.forEach((row, y) => {
            row.forEach((value, x) => {
                if (value) {
                    const boardY = this.currentPiece!.y + y;
                    const boardX = this.currentPiece!.x + x;
                    if (boardY >= 0) {
                        this.board[boardY][boardX] = this.currentPiece!.color;
                    }
                }
            });
        });
        
        // Clear lines
        const linesCleared = this.clearLines();
        if (linesCleared > 0) {
            this.lines += linesCleared;
            this.score += linesCleared * 100 * this.level;
            
            // Level up every 10 lines
            const newLevel = Math.floor(this.lines / 10) + 1;
            if (newLevel > this.level) {
                this.level = newLevel;
                this.dropTime = Math.max(100, 1000 - (this.level - 1) * 100);
                
                // Check for question gate on level up
                if (this.configData.gateMode === 'interval' && 
                    this.level >= this.nextQuestionLevel &&
                    this.configData.quizId) {
                    this.pauseForQuestion();
                }
            }
            
            this.updateUI();
        }
        
        // Get next piece
        this.currentPiece = this.nextPiece;
        this.nextPiece = this.createPiece();
        
        // Check game over
        if (this.currentPiece && !this.isValidMove(this.currentPiece)) {
            this.endGame();
        }
    }
    
    /**
     * Clear completed lines
     */
    private clearLines(): number {
        let linesCleared = 0;
        
        for (let y = this.BOARD_HEIGHT - 1; y >= 0; y--) {
            if (this.board[y].every(cell => cell !== 0)) {
                this.board.splice(y, 1);
                this.board.unshift(Array(this.BOARD_WIDTH).fill(0));
                linesCleared++;
                y++; // Check same row again
            }
        }
        
        return linesCleared;
    }
    
    /**
     * Draw everything
     */
    private draw(): void {
        if (!this.graphics) return;
        
        this.graphics.clear();
        
        const colors = this.getThemeColors();
        
        // Draw board border
        this.graphics.lineStyle(2, Phaser.Display.Color.HexStringToColor(colors.primary).color);
        this.graphics.strokeRect(
            248, 48,
            this.BOARD_WIDTH * this.BLOCK_SIZE + 4,
            this.BOARD_HEIGHT * this.BLOCK_SIZE + 4
        );
        
        // Draw board grid
        this.graphics.lineStyle(1, Phaser.Display.Color.HexStringToColor('#e5e7eb').color);
        for (let x = 0; x <= this.BOARD_WIDTH; x++) {
            this.graphics.moveTo(250 + x * this.BLOCK_SIZE, 50);
            this.graphics.lineTo(250 + x * this.BLOCK_SIZE, 50 + this.BOARD_HEIGHT * this.BLOCK_SIZE);
        }
        for (let y = 0; y <= this.BOARD_HEIGHT; y++) {
            this.graphics.moveTo(250, 50 + y * this.BLOCK_SIZE);
            this.graphics.lineTo(250 + this.BOARD_WIDTH * this.BLOCK_SIZE, 50 + y * this.BLOCK_SIZE);
        }
        this.graphics.strokePath();
        
        // Draw placed blocks
        this.board.forEach((row, y) => {
            row.forEach((value, x) => {
                if (value !== 0) {
                    this.graphics.fillStyle(Phaser.Display.Color.HexStringToColor(value as string).color);
                    this.graphics.fillRect(
                        250 + x * this.BLOCK_SIZE + 1,
                        50 + y * this.BLOCK_SIZE + 1,
                        this.BLOCK_SIZE - 2,
                        this.BLOCK_SIZE - 2
                    );
                }
            });
        });
        
        // Draw current piece
        if (this.currentPiece) {
            this.graphics.fillStyle(Phaser.Display.Color.HexStringToColor(this.currentPiece.color).color);
            this.currentPiece.shape.forEach((row, y) => {
                row.forEach((value, x) => {
                    if (value) {
                        this.graphics.fillRect(
                            250 + (this.currentPiece!.x + x) * this.BLOCK_SIZE + 1,
                            50 + (this.currentPiece!.y + y) * this.BLOCK_SIZE + 1,
                            this.BLOCK_SIZE - 2,
                            this.BLOCK_SIZE - 2
                        );
                    }
                });
            });
        }
        
        // Draw next piece
        if (this.nextPiece) {
            this.graphics.fillStyle(Phaser.Display.Color.HexStringToColor(this.nextPiece.color).color);
            this.nextPiece.shape.forEach((row, y) => {
                row.forEach((value, x) => {
                    if (value) {
                        this.graphics.fillRect(
                            550 + x * 20,
                            120 + y * 20,
                            18,
                            18
                        );
                    }
                });
            });
        }
    }
    
    /**
     * Update UI texts
     */
    private updateUI(): void {
        if (this.scoreText) this.scoreText.setText(`Score: ${this.score}`);
        if (this.levelText) this.levelText.setText(`Level: ${this.level}`);
        if (this.linesText) this.linesText.setText(`Lines: ${this.lines}`);
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
        
        if (!result.correct && this.gameState === 'playing') {
            // Could add penalty
        }
        
        this.questionOverlay = undefined;
    }
    
    /**
     * Pause for question
     */
    private async pauseForQuestion(): Promise<void> {
        if (!this.configData.quizId) return;
        
        const prevState = this.gameState;
        this.gameState = 'paused';
        
        await this.showQuestionGate();
        
        this.gameState = prevState;
        this.nextQuestionLevel += 2;
        this.lastDrop = this.time.now; // Reset drop timer
    }
    
    /**
     * End the game
     */
    private endGame(): void {
        this.gameState = 'gameOver';
        
        if (this.score > this.highScore) {
            this.highScore = this.score;
        }
        
        // Submit score
        this.submitScore();
        
        // Show game over screen
        this.showGameOverScreen();
        
        // Emit event
        this.game.events.emit('game-over', {
            score: this.score,
            level: this.level,
            lines: this.lines
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
                    perfect: false,
                    level: this.level,
                    lines: this.lines
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
        
        this.clearContainers();
        
        // Create game over container
        this.gameOverContainer = this.add.container(width / 2, height / 2);
        
        // Overlay
        const overlay = this.add.rectangle(0, 0, width, height, 0x000000, 0.7);
        this.gameOverContainer.add(overlay);
        
        // Panel
        const panel = this.add.rectangle(0, 0, 400, 350,
            Phaser.Display.Color.HexStringToColor(colors.surface).color);
        panel.setStrokeStyle(2, Phaser.Display.Color.HexStringToColor(colors.primary).color);
        this.gameOverContainer.add(panel);
        
        // Title
        const title = this.add.text(0, -120, 'Game Over', {
            fontSize: '32px',
            color: colors['text-primary'],
            fontStyle: 'bold'
        }).setOrigin(0.5);
        this.gameOverContainer.add(title);
        
        // Stats
        const stats = this.add.text(0, -40, 
            `Score: ${this.score}\nLevel: ${this.level}\nLines: ${this.lines}`, {
            fontSize: '20px',
            color: colors['text-primary'],
            align: 'center',
            lineSpacing: 8
        }).setOrigin(0.5);
        this.gameOverContainer.add(stats);
        
        // High score
        const highScoreText = this.add.text(0, 40, `High Score: ${this.highScore}`, {
            fontSize: '18px',
            color: colors['text-secondary']
        }).setOrigin(0.5);
        this.gameOverContainer.add(highScoreText);
        
        // Play again button
        const playAgainBg = this.add.rectangle(0, 100, 150, 40,
            Phaser.Display.Color.HexStringToColor(colors.primary).color);
        playAgainBg.setInteractive();
        this.gameOverContainer.add(playAgainBg);
        
        const playAgainText = this.add.text(0, 100, 'Play Again', {
            fontSize: '18px',
            color: '#ffffff'
        }).setOrigin(0.5);
        this.gameOverContainer.add(playAgainText);
        
        playAgainBg.on('pointerdown', () => this.startGame());
        playAgainBg.on('pointerover', () => playAgainBg.setAlpha(0.8));
        playAgainBg.on('pointerout', () => playAgainBg.setAlpha(1));
    }
    
    /**
     * Clear containers
     */
    private clearContainers(): void {
        if (this.menuContainer) {
            this.menuContainer.destroy();
            this.menuContainer = undefined;
        }
        if (this.gameOverContainer) {
            this.gameOverContainer.destroy();
            this.gameOverContainer = undefined;
        }
    }
    
    /**
     * Handle enter key
     */
    private handleEnterKey(): void {
        if (this.gameState === 'menu') {
            this.startGame();
        } else if (this.gameState === 'gameOver') {
            this.startGame();
        }
    }
    
    /**
     * Handle escape key
     */
    private handleEscKey(): void {
        if (this.gameState === 'playing') {
            this.gameState = 'paused';
        } else if (this.gameState === 'paused') {
            this.gameState = 'playing';
            this.lastDrop = this.time.now;
        } else if (this.gameState === 'gameOver') {
            this.showMenu();
        }
    }
    
    /**
     * Clean up
     */
    destroy(): void {
        this.clearContainers();
        
        if (this.questionOverlay) {
            this.questionOverlay.destroy();
        }
        
        super.destroy();
    }
}