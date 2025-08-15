// assets/games/src/core/EAGameBase.ts
import Phaser from '../utils/PhaserShim';

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

  protected getThemeColors(): Record<string, string> {
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
    // Skip loading HUD for now - assets not yet created
    // TODO: Add HUD assets when available
  }

  create() {
    this.add.text(10, 10, 'EA Game Started', { color: '#ffffff' });
    this.game.events.emit('game-started', this.configData);
  }

  update() {
    // No-op by default
  }
}