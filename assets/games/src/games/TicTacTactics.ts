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

interface Cell {
	x: number;
	y: number;
	row: number;
	col: number;
	owner: 'player' | 'ai' | null;
	sprite?: Phaser.GameObjects.Rectangle;
	mark?: Phaser.GameObjects.Graphics | Phaser.GameObjects.Text;
	interactive?: Phaser.GameObjects.Zone;
	isHovered: boolean;
}

interface ApiResponse {
	success: boolean;
	data?: any;
	message?: string;
}

type GameState = 'playing' | 'question' | 'ai_turn' | 'game_over' | 'waiting';
type AIDifficulty = 'easy' | 'medium' | 'hard' | 'accessible';

/**
 * Tic-Tac-Tactics Game
 * Strategic tic-tac-toe where players must answer questions correctly to place their marks
 * Features AI opponents with different difficulty levels based on presets
 */
export class TicTacTactics extends EAGameBase {
	private currentQuestion: Question | null = null;
	private board: Cell[][] = [];
	private gameState: GameState = 'playing';
	private currentPlayer: 'player' | 'ai' = 'player';
	private selectedCell: Cell | null = null;
	private aiDifficulty: AIDifficulty = 'medium';
	
	// Game stats
	private score: number = 0;
	private questionsCorrect: number = 0;
	private questionsTotal: number = 0;
	private gamesWon: number = 0;
	private gamesPlayed: number = 0;
	
	// UI Elements
	private scoreText?: Phaser.GameObjects.Text;
	private gameStateText?: Phaser.GameObjects.Text;
	private questionText?: Phaser.GameObjects.Text;
	private answerContainer?: Phaser.GameObjects.Container;
	private gameOverText?: Phaser.GameObjects.Text;
	private winnerLine?: Phaser.GameObjects.Graphics;
	
	// Question display
	private questionBackground?: Phaser.GameObjects.Rectangle;
	private answerButtons: Phaser.GameObjects.Container[] = [];
	
	// Game settings based on preset
	private hintsEnabled: boolean = false;
	private aiThinkingTime: number = 1000; // milliseconds
	
	// Board configuration
	private readonly BOARD_SIZE = 3;
	private readonly CELL_SIZE = 120;
	private readonly BOARD_START_X = 250;
	private readonly BOARD_START_Y = 200;
	
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
				this.aiDifficulty = 'easy';
				this.hintsEnabled = true;
				this.aiThinkingTime = 1500;
				break;
			case 'classic':
				this.aiDifficulty = 'medium';
				this.hintsEnabled = false;
				this.aiThinkingTime = 1000;
				break;
			case 'pro':
				this.aiDifficulty = 'hard';
				this.hintsEnabled = false;
				this.aiThinkingTime = 500;
				break;
			case 'accessible':
				this.aiDifficulty = 'accessible';
				this.hintsEnabled = true;
				this.aiThinkingTime = 2000;
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
			'text-secondary': '#6B7280',
			'board-line': '#8B7355',
			'cell-empty': '#F5F5DC',
			'cell-hover': '#E6E6FA',
			'player-mark': '#0066CC',
			'ai-mark': '#CC0066'
		};

		// In a real implementation, this would fetch from theme manager
		return defaultColors;
	}

	preload(): void {
		super.preload();

		// Skip loading sprites for now - use fallback graphics
		// TODO: Add actual sprite assets when available
	}

	create(): void {
		super.create();

		const colors = this.getThemeColors();

		// Create background
		this.add.rectangle(400, 300, 800, 600, Phaser.Display.Color.HexStringToColor(colors.background).color);

		// Initialize game board
		this.initializeBoard();

		// Create UI
		this.createUI(colors);

		// Start the game
		this.startNewGame();
	}

	/**
	 * Initialize the 3x3 game board
	 */
	private initializeBoard(): void {
		const colors = this.getThemeColors();
		
		// Clear existing board
		this.board = [];
		
		// Create board grid
		for (let row = 0; row < this.BOARD_SIZE; row++) {
			this.board[row] = [];
			for (let col = 0; col < this.BOARD_SIZE; col++) {
				const x = this.BOARD_START_X + col * this.CELL_SIZE;
				const y = this.BOARD_START_Y + row * this.CELL_SIZE;

				const cell: Cell = {
					x,
					y,
					row,
					col,
					owner: null,
					isHovered: false
				};

				// Create cell background
				cell.sprite = this.add.rectangle(x, y, this.CELL_SIZE - 4, this.CELL_SIZE - 4, 
					Phaser.Display.Color.HexStringToColor(colors['cell-empty']).color);
				cell.sprite.setStrokeStyle(2, Phaser.Display.Color.HexStringToColor(colors['board-line']).color);

				// Create interactive zone
				cell.interactive = this.add.zone(x, y, this.CELL_SIZE, this.CELL_SIZE);
				cell.interactive.setInteractive();
				
				// Add hover effects
				cell.interactive.on('pointerover', () => this.onCellHover(cell, true));
				cell.interactive.on('pointerout', () => this.onCellHover(cell, false));
				cell.interactive.on('pointerdown', () => this.onCellClick(cell));

				this.board[row][col] = cell;
			}
		}

		// Draw board lines
		this.drawBoardLines(colors);
	}

	/**
	 * Draw the tic-tac-toe board lines
	 */
	private drawBoardLines(colors: any): void {
		const graphics = this.add.graphics();
		graphics.lineStyle(4, Phaser.Display.Color.HexStringToColor(colors['board-line']).color);

		const startX = this.BOARD_START_X - this.CELL_SIZE / 2;
		const startY = this.BOARD_START_Y - this.CELL_SIZE / 2;
		const endX = startX + this.BOARD_SIZE * this.CELL_SIZE;
		const endY = startY + this.BOARD_SIZE * this.CELL_SIZE;

		// Vertical lines
		for (let i = 1; i < this.BOARD_SIZE; i++) {
			const x = startX + i * this.CELL_SIZE;
			graphics.moveTo(x, startY);
			graphics.lineTo(x, endY);
		}

		// Horizontal lines
		for (let i = 1; i < this.BOARD_SIZE; i++) {
			const y = startY + i * this.CELL_SIZE;
			graphics.moveTo(startX, y);
			graphics.lineTo(endX, y);
		}

		graphics.strokePath();
	}

	/**
	 * Create UI elements
	 */
	private createUI(colors: any): void {
		// Score display
		this.scoreText = this.add.text(20, 20, `Score: ${this.score} | Won: ${this.gamesWon}/${this.gamesPlayed}`, {
			fontSize: '20px',
			color: colors['text-primary']
		});

		// Game state display
		this.gameStateText = this.add.text(20, 50, 'Your turn - Click a cell to place X', {
			fontSize: '18px',
			color: colors['text-secondary']
		});

		// Question display area (initially hidden)
		this.questionText = this.add.text(400, 80, '', {
			fontSize: '16px',
			color: colors['text-primary'],
			align: 'center',
			wordWrap: { width: 500 }
		}).setOrigin(0.5, 0).setVisible(false);

		// Answer container
		this.answerContainer = this.add.container(400, 120).setVisible(false);

		// Instructions
		this.add.text(600, 200, 
			'How to Play:\n\n' +
			'1. Click a cell to select it\n' +
			'2. Answer the question correctly\n' +
			'3. Place your X mark\n' +
			'4. Get 3 in a row to win!\n\n' +
			'You: X (Blue)\n' +
			'AI: O (Red)', {
			fontSize: '14px',
			color: colors['text-secondary'],
			lineSpacing: 5
		});
	}

	/**
	 * Start a new game
	 */
	private startNewGame(): void {
		// Reset board
		this.clearBoard();
		
		// Reset game state
		this.gameState = 'playing';
		this.currentPlayer = 'player';
		this.selectedCell = null;
		
		// Update UI
		this.updateGameStateText();
		this.hideQuestionUI();
		
		// Clear winner line if it exists
		if (this.winnerLine) {
			this.winnerLine.destroy();
			this.winnerLine = undefined;
		}
	}

	/**
	 * Clear all marks from the board
	 */
	private clearBoard(): void {
		for (let row = 0; row < this.BOARD_SIZE; row++) {
			for (let col = 0; col < this.BOARD_SIZE; col++) {
				const cell = this.board[row][col];
				cell.owner = null;
				
				if (cell.mark) {
					cell.mark.destroy();
					cell.mark = undefined;
				}
				
				// Reset cell appearance
				const colors = this.getThemeColors();
				cell.sprite?.setFillStyle(Phaser.Display.Color.HexStringToColor(colors['cell-empty']).color);
			}
		}
	}

	/**
	 * Handle cell hover
	 */
	private onCellHover(cell: Cell, isHovering: boolean): void {
		if (this.gameState !== 'playing' || this.currentPlayer !== 'player' || cell.owner !== null) {
			return;
		}

		const colors = this.getThemeColors();
		cell.isHovered = isHovering;
		
		if (isHovering) {
			cell.sprite?.setFillStyle(Phaser.Display.Color.HexStringToColor(colors['cell-hover']).color);
		} else {
			cell.sprite?.setFillStyle(Phaser.Display.Color.HexStringToColor(colors['cell-empty']).color);
		}
	}

	/**
	 * Handle cell click
	 */
	private onCellClick(cell: Cell): void {
		if (this.gameState !== 'playing' || this.currentPlayer !== 'player' || cell.owner !== null) {
			return;
		}

		this.selectedCell = cell;
		this.gameState = 'question';
		this.loadQuestion();
	}

	/**
	 * Load a question for the selected cell
	 */
	private async loadQuestion(): Promise<void> {
		try {
			// In production, get quiz_id from game metadata
			const quizId = 1; // Placeholder
			const response = await this.apiRequest('GET', `/wp-json/ea-gaming/v1/questions/${quizId}?session_id=${this.configData.sessionId}`);
			
			if (response.success && response.data) {
				this.currentQuestion = response.data;
				this.displayQuestion();
			} else {
				console.error('Failed to load question:', response.message);
				this.gameState = 'playing';
				this.selectedCell = null;
			}
		} catch (error) {
			console.error('Error loading question:', error);
			this.gameState = 'playing';
			this.selectedCell = null;
		}
	}

	/**
	 * Display the current question and answers
	 */
	private displayQuestion(): void {
		if (!this.currentQuestion || !this.questionText || !this.answerContainer) return;

		// Show question UI
		this.questionText.setText(this.currentQuestion.question);
		this.questionText.setVisible(true);
		this.answerContainer.setVisible(true);

		// Create question background
		if (!this.questionBackground) {
			this.questionBackground = this.add.rectangle(400, 160, 600, 200, 0x000000, 0.8);
		}
		this.questionBackground.setVisible(true);

		// Clear previous answer buttons
		this.clearAnswerButtons();

		// Create answer buttons
		const answers = this.currentQuestion.answers;
		const buttonHeight = 40;
		const buttonSpacing = 10;
		const startY = -(answers.length * (buttonHeight + buttonSpacing)) / 2;

		answers.forEach((answer, index) => {
			const y = startY + index * (buttonHeight + buttonSpacing);
			const button = this.createAnswerButton(answer, y);
			this.answerButtons.push(button);
			this.answerContainer.add(button);
		});

		// Update game state
		this.updateGameStateText();
	}

	/**
	 * Create an answer button
	 */
	private createAnswerButton(answer: any, y: number): Phaser.GameObjects.Container {
		const colors = this.getThemeColors();
		const button = this.add.container(0, y);

		// Button background
		const background = this.add.rectangle(0, 0, 500, 35, 
			Phaser.Display.Color.HexStringToColor(colors.surface).color);
		background.setStrokeStyle(2, Phaser.Display.Color.HexStringToColor(colors.primary).color);

		// Button text
		const text = this.add.text(0, 0, answer.text, {
			fontSize: '14px',
			color: colors['text-primary'],
			align: 'center',
			wordWrap: { width: 480 }
		}).setOrigin(0.5);

		button.add([background, text]);

		// Make interactive
		background.setInteractive();
		background.on('pointerover', () => {
			background.setFillStyle(Phaser.Display.Color.HexStringToColor(colors['cell-hover']).color);
		});
		background.on('pointerout', () => {
			background.setFillStyle(Phaser.Display.Color.HexStringToColor(colors.surface).color);
		});
		background.on('pointerdown', () => this.onAnswerSelected(answer));

		return button;
	}

	/**
	 * Handle answer selection
	 */
	private async onAnswerSelected(answer: any): Promise<void> {
		if (!this.currentQuestion || !this.selectedCell) return;

		try {
			// Validate answer with API
			const response = await this.apiRequest('POST', '/wp-json/ea-gaming/v1/validate-answer', {
				question_id: this.currentQuestion.id,
				answer: answer.id,
				session_id: this.configData.sessionId
			});

			if (response.success && response.data) {
				const isCorrect = response.data.correct;
				
				this.questionsTotal++;
				if (isCorrect) {
					this.questionsCorrect++;
					this.onCorrectAnswer();
				} else {
					this.onIncorrectAnswer();
				}

				// Update UI
				this.updateScoreDisplay();
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
		if (!this.selectedCell) return;

		// Award points
		const points = 100;
		this.score += points;

		// Place player mark
		this.placeMark(this.selectedCell, 'player');

		// Show feedback
		this.showFeedback('Correct! X placed!', '#10B981');

		// Hide question UI
		this.hideQuestionUI();

		// Check for win/draw
		const winner = this.checkWinner();
		if (winner) {
			this.endGame(winner);
		} else if (this.isBoardFull()) {
			this.endGame('draw');
		} else {
			// Switch to AI turn
			this.currentPlayer = 'ai';
			this.gameState = 'ai_turn';
			this.updateGameStateText();
			
			// AI makes move after delay
			this.time.delayedCall(this.aiThinkingTime, () => {
				this.makeAIMove();
			});
		}

		this.selectedCell = null;
	}

	/**
	 * Handle incorrect answer
	 */
	private onIncorrectAnswer(): void {
		// Show feedback
		this.showFeedback('Incorrect! Try another cell.', '#EF4444');

		// Hide question UI
		this.hideQuestionUI();

		// Reset game state
		this.gameState = 'playing';
		this.selectedCell = null;
		this.updateGameStateText();
	}

	/**
	 * Place a mark (X or O) on the board
	 */
	private placeMark(cell: Cell, owner: 'player' | 'ai'): void {
		cell.owner = owner;
		
		const colors = this.getThemeColors();
		const markColor = owner === 'player' ? colors['player-mark'] : colors['ai-mark'];
		const markSymbol = owner === 'player' ? 'X' : 'O';

		// Create mark graphic
		cell.mark = this.add.text(cell.x, cell.y, markSymbol, {
			fontSize: '48px',
			color: markColor,
			fontStyle: 'bold'
		}).setOrigin(0.5);

		// Update cell appearance
		cell.sprite?.setFillStyle(Phaser.Display.Color.HexStringToColor(
			owner === 'player' ? '#E6F3FF' : '#FFE6F3'
		).color);

		// Animation
		cell.mark.setScale(0);
		this.tweens.add({
			targets: cell.mark,
			scale: 1,
			duration: 300,
			ease: 'Back.out'
		});
	}

	/**
	 * Make AI move based on difficulty
	 */
	private makeAIMove(): void {
		let move: {row: number, col: number} | null = null;

		switch (this.aiDifficulty) {
			case 'easy':
				move = this.getRandomMove();
				break;
			case 'medium':
				move = this.getMediumAIMove();
				break;
			case 'hard':
				move = this.getHardAIMove();
				break;
			case 'accessible':
				move = this.getAccessibleAIMove();
				break;
		}

		if (move) {
			const cell = this.board[move.row][move.col];
			this.placeMark(cell, 'ai');

			// Check for win/draw
			const winner = this.checkWinner();
			if (winner) {
				this.endGame(winner);
			} else if (this.isBoardFull()) {
				this.endGame('draw');
			} else {
				// Switch back to player
				this.currentPlayer = 'player';
				this.gameState = 'playing';
				this.updateGameStateText();
			}
		}
	}

	/**
	 * Get random move (easy difficulty)
	 */
	private getRandomMove(): {row: number, col: number} | null {
		const emptyCells = this.getEmptyCells();
		if (emptyCells.length === 0) return null;
		
		const randomIndex = Math.floor(Math.random() * emptyCells.length);
		return emptyCells[randomIndex];
	}

	/**
	 * Get medium AI move (blocks wins, takes wins)
	 */
	private getMediumAIMove(): {row: number, col: number} | null {
		// Try to win
		const winMove = this.findWinningMove('ai');
		if (winMove) return winMove;

		// Try to block player win
		const blockMove = this.findWinningMove('player');
		if (blockMove) return blockMove;

		// Otherwise random move
		return this.getRandomMove();
	}

	/**
	 * Get hard AI move (minimax algorithm)
	 */
	private getHardAIMove(): {row: number, col: number} | null {
		return this.minimax('ai', 0, true).move || this.getRandomMove();
	}

	/**
	 * Get accessible AI move (very predictable)
	 */
	private getAccessibleAIMove(): {row: number, col: number} | null {
		// Always try center first, then corners, then edges
		const preferredMoves = [
			{row: 1, col: 1}, // center
			{row: 0, col: 0}, {row: 0, col: 2}, {row: 2, col: 0}, {row: 2, col: 2}, // corners
			{row: 0, col: 1}, {row: 1, col: 0}, {row: 1, col: 2}, {row: 2, col: 1}  // edges
		];

		for (const move of preferredMoves) {
			if (this.board[move.row][move.col].owner === null) {
				return move;
			}
		}

		return null;
	}

	/**
	 * Find winning move for a player
	 */
	private findWinningMove(player: 'player' | 'ai'): {row: number, col: number} | null {
		const emptyCells = this.getEmptyCells();
		
		for (const cell of emptyCells) {
			// Temporarily place mark
			this.board[cell.row][cell.col].owner = player;
			
			// Check if this creates a win
			const isWin = this.checkWinForPlayer(player);
			
			// Remove temporary mark
			this.board[cell.row][cell.col].owner = null;
			
			if (isWin) {
				return cell;
			}
		}
		
		return null;
	}

	/**
	 * Minimax algorithm for hard AI
	 */
	private minimax(player: 'player' | 'ai', depth: number, isMaximizing: boolean): {score: number, move?: {row: number, col: number}} {
		const winner = this.checkWinner();
		
		if (winner === 'ai') return {score: 10 - depth};
		if (winner === 'player') return {score: depth - 10};
		if (this.isBoardFull()) return {score: 0};

		const emptyCells = this.getEmptyCells();
		let bestScore = isMaximizing ? -Infinity : Infinity;
		let bestMove: {row: number, col: number} | undefined;

		for (const cell of emptyCells) {
			// Make move
			this.board[cell.row][cell.col].owner = isMaximizing ? 'ai' : 'player';
			
			// Recurse
			const result = this.minimax(
				isMaximizing ? 'player' : 'ai', 
				depth + 1, 
				!isMaximizing
			);
			
			// Undo move
			this.board[cell.row][cell.col].owner = null;
			
			// Update best score and move
			if (isMaximizing) {
				if (result.score > bestScore) {
					bestScore = result.score;
					bestMove = cell;
				}
			} else {
				if (result.score < bestScore) {
					bestScore = result.score;
					bestMove = cell;
				}
			}
		}

		return {score: bestScore, move: bestMove};
	}

	/**
	 * Get all empty cells
	 */
	private getEmptyCells(): {row: number, col: number}[] {
		const emptyCells: {row: number, col: number}[] = [];
		
		for (let row = 0; row < this.BOARD_SIZE; row++) {
			for (let col = 0; col < this.BOARD_SIZE; col++) {
				if (this.board[row][col].owner === null) {
					emptyCells.push({row, col});
				}
			}
		}
		
		return emptyCells;
	}

	/**
	 * Check if board is full
	 */
	private isBoardFull(): boolean {
		return this.getEmptyCells().length === 0;
	}

	/**
	 * Check for winner
	 */
	private checkWinner(): 'player' | 'ai' | 'draw' | null {
		if (this.checkWinForPlayer('player')) return 'player';
		if (this.checkWinForPlayer('ai')) return 'ai';
		if (this.isBoardFull()) return 'draw';
		return null;
	}

	/**
	 * Check if a specific player has won
	 */
	private checkWinForPlayer(player: 'player' | 'ai'): boolean {
		// Check rows
		for (let row = 0; row < this.BOARD_SIZE; row++) {
			if (this.board[row].every(cell => cell.owner === player)) {
				this.drawWinnerLine(
					{x: this.board[row][0].x - 50, y: this.board[row][0].y},
					{x: this.board[row][2].x + 50, y: this.board[row][2].y}
				);
				return true;
			}
		}

		// Check columns
		for (let col = 0; col < this.BOARD_SIZE; col++) {
			if (this.board.every(row => row[col].owner === player)) {
				this.drawWinnerLine(
					{x: this.board[0][col].x, y: this.board[0][col].y - 50},
					{x: this.board[2][col].x, y: this.board[2][col].y + 50}
				);
				return true;
			}
		}

		// Check diagonals
		if (this.board[0][0].owner === player && 
			this.board[1][1].owner === player && 
			this.board[2][2].owner === player) {
			this.drawWinnerLine(
				{x: this.board[0][0].x - 30, y: this.board[0][0].y - 30},
				{x: this.board[2][2].x + 30, y: this.board[2][2].y + 30}
			);
			return true;
		}

		if (this.board[0][2].owner === player && 
			this.board[1][1].owner === player && 
			this.board[2][0].owner === player) {
			this.drawWinnerLine(
				{x: this.board[0][2].x + 30, y: this.board[0][2].y - 30},
				{x: this.board[2][0].x - 30, y: this.board[2][0].y + 30}
			);
			return true;
		}

		return false;
	}

	/**
	 * Draw winner line
	 */
	private drawWinnerLine(start: {x: number, y: number}, end: {x: number, y: number}): void {
		const colors = this.getThemeColors();
		this.winnerLine = this.add.graphics();
		this.winnerLine.lineStyle(6, Phaser.Display.Color.HexStringToColor(colors.success).color);
		this.winnerLine.moveTo(start.x, start.y);
		this.winnerLine.lineTo(end.x, end.y);
		this.winnerLine.strokePath();
	}

	/**
	 * End the game
	 */
	private endGame(result: 'player' | 'ai' | 'draw'): void {
		this.gameState = 'game_over';
		this.gamesPlayed++;
		
		let message = '';
		let color = '';
		
		switch (result) {
			case 'player':
				this.gamesWon++;
				this.score += 500; // Bonus for winning
				message = 'You Win!';
				color = '#10B981';
				break;
			case 'ai':
				message = 'AI Wins!';
				color = '#EF4444';
				break;
			case 'draw':
				this.score += 100; // Small bonus for draw
				message = 'Draw!';
				color = '#F59E0B';
				break;
		}

		// Show game over message
		this.showGameOverScreen(message, color);
		
		// Update displays
		this.updateScoreDisplay();
	}

	/**
	 * Show game over screen
	 */
	private showGameOverScreen(message: string, color: string): void {
		// Semi-transparent overlay
		this.add.rectangle(400, 300, 800, 600, 0x000000, 0.7);

		// Game over text
		this.gameOverText = this.add.text(400, 250, message, {
			fontSize: '48px',
			color: color,
			align: 'center'
		}).setOrigin(0.5);

		// Stats
		const accuracy = this.questionsTotal > 0 ? Math.round((this.questionsCorrect / this.questionsTotal) * 100) : 0;
		this.add.text(400, 320, 
			`Final Score: ${this.score}\n` +
			`Games Won: ${this.gamesWon}/${this.gamesPlayed}\n` +
			`Question Accuracy: ${accuracy}%`, {
			fontSize: '20px',
			color: '#FFFFFF',
			align: 'center',
			lineSpacing: 5
		}).setOrigin(0.5);

		// Play again button
		const playAgainButton = this.add.text(400, 420, 'Play Again', {
			fontSize: '28px',
			color: '#10B981',
			align: 'center'
		}).setOrigin(0.5);

		playAgainButton.setInteractive();
		playAgainButton.on('pointerdown', () => this.restartGame());
		playAgainButton.on('pointerover', () => playAgainButton.setColor('#34D399'));
		playAgainButton.on('pointerout', () => playAgainButton.setColor('#10B981'));

		// Emit game over event
		this.game.events.emit('game-over', {
			result: message,
			score: this.score,
			gamesWon: this.gamesWon,
			gamesPlayed: this.gamesPlayed,
			questionsCorrect: this.questionsCorrect,
			questionsTotal: this.questionsTotal
		});
	}

	/**
	 * Restart the game
	 */
	private restartGame(): void {
		this.scene.restart();
	}

	/**
	 * Clear answer buttons
	 */
	private clearAnswerButtons(): void {
		this.answerButtons.forEach(button => button.destroy());
		this.answerButtons = [];
	}

	/**
	 * Hide question UI
	 */
	private hideQuestionUI(): void {
		this.questionText?.setVisible(false);
		this.answerContainer?.setVisible(false);
		this.questionBackground?.setVisible(false);
		this.clearAnswerButtons();
		this.gameState = 'playing';
	}

	/**
	 * Update game state text
	 */
	private updateGameStateText(): void {
		if (!this.gameStateText) return;

		let text = '';
		switch (this.gameState) {
			case 'playing':
				text = this.currentPlayer === 'player' ? 
					'Your turn - Click a cell to place X' : 
					'AI is thinking...';
				break;
			case 'question':
				text = 'Answer the question to place your X';
				break;
			case 'ai_turn':
				text = 'AI is making a move...';
				break;
			case 'game_over':
				text = 'Game Over';
				break;
		}

		this.gameStateText.setText(text);
	}

	/**
	 * Update score display
	 */
	private updateScoreDisplay(): void {
		if (this.scoreText) {
			this.scoreText.setText(`Score: ${this.score} | Won: ${this.gamesWon}/${this.gamesPlayed}`);
		}
	}

	/**
	 * Show feedback text
	 */
	private showFeedback(text: string, color: string): void {
		const feedback = this.add.text(400, 150, text, {
			fontSize: '24px',
			color: color,
			align: 'center'
		}).setOrigin(0.5);

		// Animate feedback
		this.tweens.add({
			targets: feedback,
			y: 100,
			alpha: 0,
			duration: 2000,
			ease: 'Power2',
			onComplete: () => feedback.destroy()
		});
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
		this.clearAnswerButtons();
		super.destroy();
	}
}