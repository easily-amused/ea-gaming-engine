import { EAGameBase, GameConfig } from './index';
import { WhackAQuestion } from './games/WhackAQuestion';
import { TicTacTactics } from './games/TicTacTactics';
import { TargetTrainer } from './games/TargetTrainer';

/**
 * Game Factory - Creates game instances based on game type
 */
export class GameFactory {
	/**
	 * Create a game instance based on the game type
	 */
	static createGame(gameType: string, config: GameConfig): EAGameBase {
		switch (gameType) {
			case 'whack_a_question':
				return new WhackAQuestion('whack-a-question', config);
			case 'tic_tac_tactics':
				return new TicTacTactics('tic-tac-tactics', config);
			case 'target_trainer':
				return new TargetTrainer('target-trainer', config);
			default:
				throw new Error(`Unknown game type: ${gameType}`);
		}
	}

	/**
	 * Get list of available games
	 */
	static getAvailableGames(): Array<{id: string, name: string, description: string}> {
		return [
			{
				id: 'whack_a_question',
				name: 'Whack-a-Question',
				description: 'Fast-paced question answering game'
			},
			{
				id: 'tic_tac_tactics',
				name: 'Tic-Tac-Tactics',
				description: 'Strategic quiz-based tic-tac-toe'
			},
			{
				id: 'target_trainer',
				name: 'Target Trainer',
				description: 'Aim and shoot at targets with correct answers to score points'
			}
		];
	}
}