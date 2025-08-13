import Phaser from 'phaser';
import { GameFactory } from './GameFactory';

export interface GameConfig {
	courseId: number;
	sessionId: number;
	theme: string;
	preset: string;
	gameType?: string;
}

export class EAGameBase extends Phaser.Scene {
	configData: GameConfig;

	constructor(config: string | Phaser.Types.Scenes.SettingsConfig, gameConfig: GameConfig) {
		super(config);
		this.configData = gameConfig;
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

	update(time: number, delta: number) {
		// Override for specific game update logic
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
		width: 800,
		height: 600,
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