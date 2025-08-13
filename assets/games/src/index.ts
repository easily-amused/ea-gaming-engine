import Phaser from 'phaser';

export interface GameConfig {
	courseId: number;
	sessionId: number;
	theme: string;
	preset: string;
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
export function launchGame(config: GameConfig, sceneClass: typeof EAGameBase) {
	const game = new Phaser.Game({
		type: Phaser.AUTO,
		width: 800,
		height: 600,
		scene: [new sceneClass('default', config)],
		parent: 'ea-gaming-container'
	});
	return game;
}

// Expose launcher for frontend/index.js
(window as any).EAGameEngine = {
	launch: (config: GameConfig) => {
		launchGame(config, EAGameBase);
	}
};