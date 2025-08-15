import Phaser from 'phaser';
import { GameFactory } from './GameFactory';

export interface GameConfig {
	courseId: number;
	sessionId: number;
	theme: string;
	preset: string;
	gameType?: string;
	width?: number;
	height?: number;
	gateMode?: 'none' | 'start' | 'interval' | 'event';
	quizId?: number;
	adminPreview?: boolean;
}

export class EAGameBase extends Phaser.Scene {
	configData: GameConfig;

	constructor(config: string | Phaser.Types.Scenes.SettingsConfig, gameConfig: GameConfig) {
		super(config);
		this.configData = gameConfig;
	}

	/**
	 * Get theme colors based on current theme
	 */
	protected getThemeColors(): Record<string, string> {
		// Default colors - can be overridden by theme manager
		const themes: Record<string, Record<string, string>> = {
			playful: {
				primary: '#7C3AED',
				secondary: '#EC4899',
				success: '#10B981',
				danger: '#EF4444',
				warning: '#F59E0B',
				background: '#FEF3C7',
				surface: '#FFFFFF',
				'text-primary': '#1F2937',
				'text-secondary': '#6B7280'
			},
			minimal: {
				primary: '#3B82F6',
				secondary: '#6366F1',
				success: '#10B981',
				danger: '#EF4444',
				warning: '#F59E0B',
				background: '#F9FAFB',
				surface: '#FFFFFF',
				'text-primary': '#111827',
				'text-secondary': '#6B7280'
			},
			neon: {
				primary: '#EC4899',
				secondary: '#8B5CF6',
				success: '#10B981',
				danger: '#EF4444',
				warning: '#F59E0B',
				background: '#1F2937',
				surface: '#374151',
				'text-primary': '#F9FAFB',
				'text-secondary': '#D1D5DB'
			}
		};

		return themes[this.configData.theme] || themes.playful;
	}

	preload() {
		// Common assets based on theme
		this.load.image('hud', `/assets/games/common/${this.configData.theme}/hud.png`);
	}

	create() {
		// Placeholder until individual games override
		this.add.text(10, 10, 'EA Game Started', { color: '#ffffff' });
		this.game.events.emit('game-started', this.configData);
	}

	update() {
		// Override for specific game update logic
		// time and delta parameters removed as they're not used
	}
}

/**
 * Launches a Phaser game instance
 */
export function launchGame(config: GameConfig, gameType?: string): Phaser.Game {
	let sceneClass: EAGameBase;
	
	if (gameType) {
		try {
			sceneClass = GameFactory.createGame(gameType, config);
		} catch (error) {
			console.error('Failed to create game:', error);
			sceneClass = new EAGameBase('default', config);
		}
	} else {
		sceneClass = new EAGameBase('default', config);
	}

	const game = new Phaser.Game({
		type: Phaser.AUTO,
		width: config.width || 800,
		height: config.height || 600,
		scene: [sceneClass],
		parent: 'ea-gaming-container',
		physics: {
			default: 'arcade',
			arcade: {
				gravity: { y: 0 },
				debug: false
			}
		}
	});
	
	return game;
}

// Expose launcher for frontend/index.js
(window as any).EAGameEngine = {
	launch: (config: GameConfig, gameType?: string) => {
		return launchGame(config, gameType);
	},
	GameFactory: GameFactory
};