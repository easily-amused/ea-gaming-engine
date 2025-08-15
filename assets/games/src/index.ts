// assets/games/src/index.ts
import Phaser from './utils/PhaserShim';

import { EAGameBase, GameConfig } from './core/EAGameBase';
import { GameFactory } from './GameFactory';

export { EAGameBase } from './core/EAGameBase';
export type { GameConfig } from './core/EAGameBase';

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

(window as any).EAGameEngine = {
  launch: (config: GameConfig, gameType?: string) => launchGame(config, gameType),
  GameFactory
};