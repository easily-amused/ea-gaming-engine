/**
 * Admin Games Preview Handler
 * Manages game preview modal and Phaser game launching
 */
(function($) {
    'use strict';

    let currentGame = null;
    let currentSessionId = null;
    
    /**
     * Initialize games preview functionality
     */
    function init() {
        // Add preview buttons to game cards
        setupPreviewButtons();
        
        // Setup modal handlers
        setupModal();
        
        // Setup control handlers
        setupControls();
    }
    
    /**
     * Setup preview buttons on game cards
     */
    function setupPreviewButtons() {
        $('.ea-gaming-game-card').each(function() {
            const $card = $(this);
            const gameId = $card.data('game-id');
            const gameName = $card.find('h3').text();
            
            // Add preview button if not already present
            if (!$card.find('.preview-game').length) {
                const $footer = $('<div class="ea-gaming-game-card-footer"></div>');
                const $previewBtn = $('<button class="button button-primary preview-game">Preview Game</button>');
                
                $previewBtn.on('click', function(e) {
                    e.preventDefault();
                    openPreview(gameId, { name: gameName });
                });
                
                $footer.append($previewBtn);
                $card.append($footer);
            }
        });
    }
    
    /**
     * Setup modal
     */
    function setupModal() {
        // Create modal if it doesn't exist
        if (!$('#ea-gaming-preview-modal').length) {
            const modalHtml = `
                <div id="ea-gaming-preview-modal" style="display:none;">
                    <div class="ea-gaming-modal-overlay"></div>
                    <div class="ea-gaming-modal-content">
                        <div class="ea-gaming-modal-header">
                            <h2 id="ea-gaming-modal-title"></h2>
                            <button id="ea-gaming-modal-close" class="button-link">×</button>
                        </div>
                        <div class="ea-gaming-preview-controls">
                            <div class="control-group">
                                <label>Course</label>
                                <select id="ea-gaming-course">
                                    <option value="0">Demo Course</option>
                                </select>
                            </div>
                            <div class="control-group">
                                <label>Preset</label>
                                <select id="ea-gaming-preset">
                                    <option value="chill">Chill</option>
                                    <option value="classic" selected>Classic</option>
                                    <option value="pro">Pro</option>
                                    <option value="accessible">Accessible</option>
                                </select>
                            </div>
                            <div class="control-group">
                                <label>Theme</label>
                                <select id="ea-gaming-theme">
                                    <option value="playful" selected>Playful</option>
                                    <option value="minimal">Minimal Pro</option>
                                    <option value="neon">Neon Cyber</option>
                                </select>
                            </div>
                            <div class="control-group">
                                <label>Gate Mode</label>
                                <select id="ea-gaming-gate">
                                    <option value="none" selected>None</option>
                                    <option value="start">At Start</option>
                                    <option value="interval">Every 60s</option>
                                    <option value="event">On Fail</option>
                                </select>
                            </div>
                        </div>
                        <div id="ea-gaming-container" style="width: 800px; height: 600px; margin: 20px auto; border: 1px solid #ddd;"></div>
                        <div class="ea-gaming-preview-toolbar">
                            <button id="ea-gaming-restart" class="button">Restart Game</button>
                            <button id="ea-gaming-stop" class="button">Stop Game</button>
                            <span id="ea-gaming-status" style="margin-left: 20px;"></span>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
        }
        
        // Close button handler
        $('#ea-gaming-modal-close').on('click', closePreview);
        
        // Overlay click handler
        $('.ea-gaming-modal-overlay').on('click', closePreview);
        
        // Escape key handler
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#ea-gaming-preview-modal').is(':visible')) {
                closePreview();
            }
        });
    }
    
    /**
     * Setup control handlers
     */
    function setupControls() {
        // Restart button
        $('#ea-gaming-restart').on('click', function() {
            if (currentGame) {
                restartGame();
            }
        });
        
        // Stop button
        $('#ea-gaming-stop').on('click', function() {
            stopGame();
        });
        
        // Control changes trigger restart
        $('#ea-gaming-preset, #ea-gaming-theme, #ea-gaming-gate').on('change', function() {
            if (currentGame) {
                restartGame();
            }
        });
    }
    
    /**
     * Open game preview modal
     */
    async function openPreview(gameId, meta) {
        // Show modal
        $('#ea-gaming-preview-modal').fadeIn();
        $('#ea-gaming-modal-title').text(meta.name || 'Game Preview');
        
        // Lock body scroll
        $('body').css('overflow', 'hidden');
        
        // Create session
        try {
            const session = await createSession(
                $('#ea-gaming-course').val() || 0,
                gameId
            );
            
            if (session && session.session_id) {
                currentSessionId = session.session_id;
                
                // Start game
                startGame(gameId, {
                    courseId: $('#ea-gaming-course').val() || 0,
                    sessionId: session.session_id,
                    preset: $('#ea-gaming-preset').val(),
                    theme: $('#ea-gaming-theme').val(),
                    gateMode: $('#ea-gaming-gate').val(),
                    quizId: session.quiz_id || null
                });
            }
        } catch (error) {
            console.error('Failed to create session:', error);
            showStatus('Failed to create game session', 'error');
        }
    }
    
    /**
     * Create game session via API
     */
    async function createSession(courseId, gameType) {
        const response = await fetch('/wp-json/ea-gaming/v1/sessions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.eaGamingAdmin?.nonce || ''
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                course_id: courseId,
                game_type: gameType,
                preset: $('#ea-gaming-preset').val(),
                theme: $('#ea-gaming-theme').val(),
                gate_mode: $('#ea-gaming-gate').val()
            })
        });
        
        if (!response.ok) {
            throw new Error('Failed to create session');
        }
        
        const data = await response.json();
        return data.data || data;
    }
    
    /**
     * Start game
     */
    function startGame(gameId, config) {
        // Stop any existing game
        stopGame();
        
        // Check if EAGameEngine is loaded
        if (!window.EAGameEngine) {
            console.error('Game engine not loaded');
            showStatus('Game engine not loaded', 'error');
            return;
        }
        
        // Add admin preview flag
        config.adminPreview = true;
        config.width = 800;
        config.height = 600;
        
        try {
            // Launch game
            currentGame = window.EAGameEngine.launch(config, gameId);
            
            // Listen for game events
            if (currentGame && currentGame.events) {
                currentGame.events.on('game-started', function(cfg) {
                    showStatus('Game started', 'success');
                });
                
                currentGame.events.on('game-over', function(payload) {
                    showStatus(`Game Over! Score: ${payload.score || 0}`, 'info');
                });
                
                currentGame.events.on('qa-opened', function() {
                    showStatus('Question gate opened', 'info');
                });
                
                currentGame.events.on('qa-closed', function(result) {
                    showStatus(result.correct ? 'Correct answer!' : 'Incorrect answer', 
                              result.correct ? 'success' : 'warning');
                });
            }
            
            showStatus('Game loading...', 'info');
        } catch (error) {
            console.error('Failed to start game:', error);
            showStatus('Failed to start game', 'error');
        }
    }
    
    /**
     * Restart current game
     */
    function restartGame() {
        if (currentGame) {
            const gameId = $('#ea-gaming-modal-title').text().toLowerCase().replace(/ /g, '_');
            
            // Restart with current settings
            startGame(gameId, {
                courseId: $('#ea-gaming-course').val() || 0,
                sessionId: currentSessionId,
                preset: $('#ea-gaming-preset').val(),
                theme: $('#ea-gaming-theme').val(),
                gateMode: $('#ea-gaming-gate').val()
            });
        }
    }
    
    /**
     * Stop current game
     */
    function stopGame() {
        if (currentGame) {
            try {
                currentGame.destroy(true);
            } catch (error) {
                console.error('Error destroying game:', error);
            }
            currentGame = null;
        }
        
        // Clear container
        $('#ea-gaming-container').empty();
        showStatus('Game stopped', 'info');
    }
    
    /**
     * Close preview modal
     */
    function closePreview() {
        // Stop game
        stopGame();
        
        // Hide modal
        $('#ea-gaming-preview-modal').fadeOut();
        
        // Restore body scroll
        $('body').css('overflow', '');
        
        // Clear session
        currentSessionId = null;
    }
    
    /**
     * Show status message
     */
    function showStatus(message, type = 'info') {
        const $status = $('#ea-gaming-status');
        const colors = {
            'info': '#2271b1',
            'success': '#00a32a',
            'warning': '#d63638',
            'error': '#d63638'
        };
        
        $status.text(message).css('color', colors[type] || colors.info);
        
        // Auto-hide after 3 seconds
        setTimeout(() => {
            $status.fadeOut(() => {
                $status.text('').show();
            });
        }, 3000);
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Only initialize on games admin page
        if ($('.ea-gaming-games-grid').length) {
            init();
        }
    });
    
})(jQuery);