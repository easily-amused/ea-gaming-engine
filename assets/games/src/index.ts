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
		// Common preload assets (UI, HUD, etc.)
		this.load.image('hud', `/assets/games/common/${this.configData.theme}/hud.png`);
	}

	create() {
		this.add.text(10, 10, 'EA Game Started', { color: '#fff' });
		this.game.events.emit('game-started', this.configData);
	}

	update(time: number, delta: number) {
		// Override in specific games
	}
}

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

// Expose to window for frontend/index.js to call
(window as any).EAGameEngine = {
	launch: (config: GameConfig) => {
		// For now, placeholder: load a basic template game
		launchGame(config, EAGameBase);
	}
};