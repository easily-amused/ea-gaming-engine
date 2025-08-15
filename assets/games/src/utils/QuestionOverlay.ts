import Phaser from 'phaser';

interface Question {
    id: number;
    question: string;
    answers: Array<{
        id: number;
        text: string;
        correct?: boolean;
    }>;
    type: string;
    difficulty?: string;
}

interface QuestionOverlayConfig {
    sessionId: number;
    quizId: number;
    theme: string;
}

interface AnswerResult {
    correct: boolean;
    questionId: number;
    answerId: number;
}

/**
 * QuestionOverlay - Reusable LearnDash Q/A gate for Phaser games
 * Handles fetching questions, displaying modal UI, and validating answers
 */
export class QuestionOverlay {
    private scene: Phaser.Scene;
    private config: QuestionOverlayConfig;
    private container?: Phaser.GameObjects.Container;
    private background?: Phaser.GameObjects.Rectangle;
    private questionText?: Phaser.GameObjects.Text;
    private answerButtons: Phaser.GameObjects.Container[] = [];
    private currentQuestion?: Question;
    private resolvePromise?: (result: AnswerResult) => void;
    
    constructor(scene: Phaser.Scene, config: QuestionOverlayConfig) {
        this.scene = scene;
        this.config = config;
    }
    
    /**
     * Display question and wait for answer
     */
    async ask(): Promise<AnswerResult> {
        // Pause the scene
        this.scene.scene.pause();
        
        // Fetch question
        await this.fetchQuestion();
        
        if (!this.currentQuestion) {
            // No question available, return success
            this.scene.scene.resume();
            return { correct: true, questionId: 0, answerId: 0 };
        }
        
        // Create overlay UI
        this.createOverlay();
        
        // Return promise that resolves when answer is selected
        return new Promise<AnswerResult>((resolve) => {
            this.resolvePromise = resolve;
        });
    }
    
    /**
     * Fetch question from API
     */
    private async fetchQuestion(): Promise<void> {
        try {
            const response = await fetch(
                `/wp-json/ea-gaming/v1/questions/${this.config.quizId}?session_id=${this.config.sessionId}`,
                {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': (window as any).eaGamingNonce || ''
                    },
                    credentials: 'same-origin'
                }
            );
            
            if (response.ok) {
                const data = await response.json();
                this.currentQuestion = data;
            }
        } catch (error) {
            console.error('Failed to fetch question:', error);
        }
    }
    
    /**
     * Create the overlay UI
     */
    private createOverlay(): void {
        const { width, height } = this.scene.cameras.main;
        
        // Create container
        this.container = this.scene.add.container(0, 0);
        this.container.setDepth(10000);
        
        // Semi-transparent background
        this.background = this.scene.add.rectangle(
            width / 2,
            height / 2,
            width,
            height,
            0x000000,
            0.8
        );
        this.container.add(this.background);
        
        // Question panel
        const panel = this.scene.add.rectangle(
            width / 2,
            height / 2,
            Math.min(600, width - 40),
            Math.min(400, height - 40),
            0xffffff,
            1
        );
        panel.setStrokeStyle(2, this.getThemeColor());
        this.container.add(panel);
        
        // Question text
        if (this.currentQuestion) {
            this.questionText = this.scene.add.text(
                width / 2,
                height / 2 - 100,
                this.currentQuestion.question,
                {
                    fontSize: '18px',
                    color: '#000000',
                    align: 'center',
                    wordWrap: { width: Math.min(550, width - 60) }
                }
            ).setOrigin(0.5);
            this.container.add(this.questionText);
            
            // Create answer buttons
            this.createAnswerButtons();
        }
        
        // Emit event
        this.scene.game.events.emit('qa-opened', this.config);
    }
    
    /**
     * Create answer buttons
     */
    private createAnswerButtons(): void {
        if (!this.currentQuestion || !this.container) return;
        
        const { width, height } = this.scene.cameras.main;
        const answers = this.currentQuestion.answers;
        const buttonHeight = 40;
        const buttonSpacing = 10;
        const startY = height / 2 - 20;
        
        answers.forEach((answer, index) => {
            const y = startY + index * (buttonHeight + buttonSpacing);
            const button = this.createAnswerButton(answer, width / 2, y);
            this.answerButtons.push(button);
            this.container!.add(button);
        });
    }
    
    /**
     * Create individual answer button
     */
    private createAnswerButton(answer: any, x: number, y: number): Phaser.GameObjects.Container {
        const button = this.scene.add.container(x, y);
        
        // Button background
        const bg = this.scene.add.rectangle(0, 0, 500, 35, 0xf0f0f0);
        bg.setStrokeStyle(1, 0x999999);
        bg.setInteractive();
        
        // Button text
        const text = this.scene.add.text(0, 0, answer.text, {
            fontSize: '14px',
            color: '#333333',
            align: 'center'
        }).setOrigin(0.5);
        
        button.add([bg, text]);
        
        // Hover effects
        bg.on('pointerover', () => {
            bg.setFillStyle(this.getThemeColor(0.2));
        });
        
        bg.on('pointerout', () => {
            bg.setFillStyle(0xf0f0f0);
        });
        
        // Click handler
        bg.on('pointerdown', () => this.onAnswerSelected(answer));
        
        return button;
    }
    
    /**
     * Handle answer selection
     */
    private async onAnswerSelected(answer: any): Promise<void> {
        if (!this.currentQuestion) return;
        
        try {
            // Validate answer with API
            const response = await fetch('/wp-json/ea-gaming/v1/validate-answer', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': (window as any).eaGamingNonce || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    question_id: this.currentQuestion.id,
                    answer: answer.id,
                    session_id: this.config.sessionId
                })
            });
            
            const result = await response.json();
            const isCorrect = result.data?.correct || false;
            
            // Show feedback
            this.showFeedback(isCorrect);
            
            // Wait a moment then close
            setTimeout(() => {
                this.close({
                    correct: isCorrect,
                    questionId: this.currentQuestion!.id,
                    answerId: answer.id
                });
            }, 1500);
            
        } catch (error) {
            console.error('Error validating answer:', error);
            this.close({
                correct: false,
                questionId: this.currentQuestion.id,
                answerId: answer.id
            });
        }
    }
    
    /**
     * Show feedback after answer
     */
    private showFeedback(correct: boolean): void {
        if (!this.container) return;
        
        const { width, height } = this.scene.cameras.main;
        
        const feedbackText = this.scene.add.text(
            width / 2,
            height / 2 + 50,
            correct ? '✓ Correct!' : '✗ Incorrect',
            {
                fontSize: '24px',
                color: correct ? '#10B981' : '#EF4444',
                fontStyle: 'bold'
            }
        ).setOrigin(0.5);
        
        this.container.add(feedbackText);
        
        // Animate feedback
        this.scene.tweens.add({
            targets: feedbackText,
            scale: { from: 0, to: 1.2 },
            duration: 300,
            ease: 'Back.out'
        });
    }
    
    /**
     * Close overlay and resolve promise
     */
    private close(result: AnswerResult): void {
        // Clean up UI
        this.destroy();
        
        // Resume scene
        this.scene.scene.resume();
        
        // Emit event
        this.scene.game.events.emit('qa-closed', result);
        
        // Resolve promise
        if (this.resolvePromise) {
            this.resolvePromise(result);
        }
    }
    
    /**
     * Get theme color
     */
    private getThemeColor(alpha: number = 1): number {
        const colors: Record<string, number> = {
            playful: 0x7C3AED,
            minimal: 0x3B82F6,
            neon: 0xEC4899,
            default: 0x7C3AED
        };
        
        const color = colors[this.config.theme] || colors.default;
        
        if (alpha < 1) {
            // Return with alpha (approximate)
            return color;
        }
        
        return color;
    }
    
    /**
     * Clean up
     */
    destroy(): void {
        // Remove answer buttons
        this.answerButtons.forEach(button => button.destroy());
        this.answerButtons = [];
        
        // Remove container
        if (this.container) {
            this.container.destroy();
            this.container = undefined;
        }
        
        this.background = undefined;
        this.questionText = undefined;
        this.currentQuestion = undefined;
        this.resolvePromise = undefined;
        
        // Emit event
        this.scene.game.events.emit('qa-destroyed', this.config);
    }
}