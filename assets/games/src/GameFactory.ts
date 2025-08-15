import { EAGameBase, GameConfig } from './index';
import { WhackAQuestion } from './games/WhackAQuestion';
import { TicTacTactics } from './games/TicTacTactics';
import { TargetTrainer } from './games/TargetTrainer';
import { Snake } from './games/Snake';
import { Tetris } from './games/Tetris';
import { SpaceInvaders } from './games/SpaceInvaders';
import { Breakout } from './games/Breakout';
import { Pong } from './games/Pong';
import { ReactionTime } from './games/ReactionTime';

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
			case 'snake':
				return new Snake('snake', config);
			case 'tetris':
				return new Tetris('tetris', config);
			case 'space_invaders':
				return new SpaceInvaders('space-invaders', config);
			case 'breakout':
				return new Breakout('breakout', config);
			case 'pong':
				return new Pong('pong', config);
			case 'reaction_time':
				return new ReactionTime('reaction-time', config);
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
			},
			{
				id: 'snake',
				name: 'Snake',
				description: 'Classic snake game - grow longer by eating food'
			},
			{
				id: 'tetris',
				name: 'Tetris',
				description: 'Stack falling blocks to clear lines and score high'
			},
			{
				id: 'space_invaders',
				name: 'Space Invaders',
				description: 'Defend Earth from waves of alien invaders'
			},
			{
				id: 'breakout',
				name: 'Breakout',
				description: 'Break all the bricks with your ball and paddle'
			},
			{
				id: 'pong',
				name: 'Pong',
				description: 'The timeless arcade classic - play against AI'
			},
			{
				id: 'reaction_time',
				name: 'Reaction Time',
				description: 'Test your reflexes - how fast can you click?'
			}
		];
	}
}