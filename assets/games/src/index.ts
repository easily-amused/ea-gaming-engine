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
		// Common assets like HUD, background etc. based on theme
		this.load.image('hud', `/assets/games/common/${this.configData.theme}/hud.png`);
	}

	create() {
		// Simple placeholder content; individual games will override
		this.add.text(10, 10, 'EA Game Started', { color: '#ffffff' });
		this.game.events.emit('game-started', this.configData);
	}

	update(time: number, delta: number) {
		// Override this method for per-frame updates in specific games
	}
}

/**
	* Launches a Phaser game instance for the given config and scene.
	*/
export function launchGame(config: GameConfig, sceneClass: typeof EAGameBase) {
	const game = new Phaser.Game({
		type: Phaser.AUTO,
		width: 800,
		height: 600,
		scene: [new sceneClass('default', config)],
		parent: 'ea-game-container'
	});
	return game;
}

// Expose global launcher for frontend/index.js to call
(window as any).EAGameEngine = {
	launch: (config: GameConfig) => {
		// For now, always load the base scene as a placeholder
		launchGame(config, EAGameBase);
	}
};