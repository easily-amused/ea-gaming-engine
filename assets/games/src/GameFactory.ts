import { EAGameBase, GameConfig } from './core/EAGameBase';
import { TapAQuestion } from './games/TapAQuestion';
import { TicTacTactics } from './games/TicTacTactics';
import { TargetTrainer } from './games/TargetTrainer';
import { Snake } from './games/Snake';
import { BlockStack } from './games/BlockStack';
import { StarDefender } from './games/StarDefender';
import { BrickBreaker } from './games/BrickBreaker';
import { PaddleRally } from './games/PaddleRally';
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
			case 'tap_a_question':
				return new TapAQuestion('tap-a-question', config);
			case 'tic_tac_tactics':
				return new TicTacTactics('tic-tac-tactics', config);
			case 'target_trainer':
				return new TargetTrainer('target-trainer', config);
			case 'snake':
				return new Snake('snake', config);
			case 'block_stack':
				return new BlockStack('block-stack', config);
			case 'star_defender':
				return new StarDefender('star-defender', config);
			case 'brick_breaker':
				return new BrickBreaker('brick-breaker', config);
			case 'paddle_rally':
				return new PaddleRally('paddle-rally', config);
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
				id: 'tap_a_question',
				name: 'Tap-a-Question',
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
				description: 'Grow longer by eating food and avoid hitting walls'
			},
			{
				id: 'block_stack',
				name: 'Block Stack',
				description: 'Stack falling blocks to clear lines and score high'
			},
			{
				id: 'star_defender',
				name: 'Star Defender',
				description: 'Defend the galaxy from waves of incoming attackers'
			},
			{
				id: 'brick_breaker',
				name: 'Brick Breaker',
				description: 'Break all the bricks with your ball and paddle'
			},
			{
				id: 'paddle_rally',
				name: 'Paddle Rally',
				description: 'Timeless paddle-and-ball duel - play against AI'
			},
			{
				id: 'reaction_time',
				name: 'Reaction Time',
				description: 'Test your reflexes - how fast can you click?'
			}
		];
	}
}
