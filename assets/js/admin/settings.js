/**
 * Admin Settings JavaScript
 */

(function($) {
    'use strict';

    // Settings handler
    const EAGamingSettings = {
        
        init: function() {
            this.bindEvents();
            this.initThemeSelector();
            this.initPresetSelector();
            this.initToggles();
            this.initSliders();
        },

        bindEvents: function() {
            // Save settings button
            $('#ea-gaming-save-settings').on('click', this.saveSettings.bind(this));
            
            // Theme selector
            $('.ea-gaming-theme-card').on('click', this.selectTheme.bind(this));
            
            // Preset selector
            $('.ea-gaming-preset-card').on('click', this.selectPreset.bind(this));
            
            // Toggle switches
            $('.ea-gaming-toggle input[type="checkbox"]').on('change', this.handleToggle.bind(this));
            
            // Slider inputs
            $('input[type="range"]').on('input', this.handleSliderChange.bind(this));
            
            // Number inputs
            $('input[type="number"]').on('change', this.handleNumberChange.bind(this));
        },

        initThemeSelector: function() {
            // Highlight selected theme
            const selectedTheme = $('#selected-theme').val();
            $(`.ea-gaming-theme-card[data-theme="${selectedTheme}"]`).addClass('selected');
        },

        initPresetSelector: function() {
            // Highlight selected preset
            const selectedPreset = $('#selected-preset').val();
            $(`.ea-gaming-preset-card[data-preset="${selectedPreset}"]`).addClass('selected');
        },

        initToggles: function() {
            // Style toggle switches
            $('.ea-gaming-toggle input[type="checkbox"]').each(function() {
                const $toggle = $(this);
                const isChecked = $toggle.is(':checked');
                $toggle.closest('.ea-gaming-toggle').toggleClass('active', isChecked);
            });
        },

        initSliders: function() {
            // Initialize slider displays
            $('input[type="range"]').each(function() {
                const $slider = $(this);
                const value = $slider.val();
                const $display = $slider.siblings('.slider-value');
                
                if ($display.length) {
                    $display.text(value);
                } else {
                    // Create display if it doesn't exist
                    $slider.after(`<span class="slider-value">${value}</span>`);
                }
                
                // Update background gradient for visual feedback
                const min = parseFloat($slider.attr('min')) || 0;
                const max = parseFloat($slider.attr('max')) || 100;
                const percentage = ((value - min) / (max - min)) * 100;
                
                $slider.css('background', `linear-gradient(to right, #2271b1 0%, #2271b1 ${percentage}%, #ddd ${percentage}%, #ddd 100%)`);
            });
        },

        selectTheme: function(e) {
            e.preventDefault();
            const $card = $(e.currentTarget);
            const theme = $card.data('theme');
            
            // Update visual selection
            $('.ea-gaming-theme-card').removeClass('selected');
            $card.addClass('selected');
            
            // Update hidden input
            $('#selected-theme').val(theme);
            
            // Show feedback
            this.showNotice('Theme selected: ' + $card.find('h4').text(), 'info');
        },

        selectPreset: function(e) {
            e.preventDefault();
            const $card = $(e.currentTarget);
            const preset = $card.data('preset');
            
            // Update visual selection
            $('.ea-gaming-preset-card').removeClass('selected');
            $card.addClass('selected');
            
            // Update hidden input
            $('#selected-preset').val(preset);
            
            // Show feedback
            this.showNotice('Preset selected: ' + $card.find('h4').text(), 'info');
        },

        handleToggle: function(e) {
            const $toggle = $(e.currentTarget);
            const isChecked = $toggle.is(':checked');
            
            // Update visual state
            $toggle.closest('.ea-gaming-toggle').toggleClass('active', isChecked);
            
            // Special handling for main enable toggle
            if ($toggle.attr('id') === 'ea_gaming_enabled') {
                $('.ea-gaming-settings-section').not(':first').toggleClass('disabled', !isChecked);
                
                if (!isChecked) {
                    this.showNotice('Gaming engine disabled. Settings will be saved but not active.', 'warning');
                }
            }
        },

        handleSliderChange: function(e) {
            const $slider = $(e.currentTarget);
            const value = $slider.val();
            const $display = $slider.siblings('.slider-value');
            
            // Update display
            if ($display.length) {
                $display.text(value);
            }
            
            // Update visual feedback
            const min = parseFloat($slider.attr('min')) || 0;
            const max = parseFloat($slider.attr('max')) || 100;
            const percentage = ((value - min) / (max - min)) * 100;
            
            $slider.css('background', `linear-gradient(to right, #2271b1 0%, #2271b1 ${percentage}%, #ddd ${percentage}%, #ddd 100%)`);
        },

        handleNumberChange: function(e) {
            const $input = $(e.currentTarget);
            const min = parseFloat($input.attr('min'));
            const max = parseFloat($input.attr('max'));
            let value = parseFloat($input.val());
            
            // Validate range
            if (!isNaN(min) && value < min) {
                value = min;
                $input.val(value);
            }
            if (!isNaN(max) && value > max) {
                value = max;
                $input.val(value);
            }
        },

        saveSettings: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const originalText = $button.text();
            
            // Show saving state
            $button.text('Saving...').prop('disabled', true);
            
            // Gather all settings
            const settings = {
                enabled: $('#ea_gaming_enabled').is(':checked'),
                cache_enabled: $('#ea_gaming_cache').is(':checked'),
                debug_mode: $('#ea_gaming_debug').is(':checked'),
                default_theme: $('#selected-theme').val(),
                default_preset: $('#selected-preset').val(),
                hint_settings: {
                    enabled: $('#hint_enabled').is(':checked'),
                    cooldown: $('#hint_cooldown').val(),
                    max_per_session: $('#hint_max').val()
                },
                integration_settings: {
                    learndash_enabled: $('#learndash_enabled').is(':checked'),
                    parent_controls_enabled: $('#parent_controls_enabled').is(':checked'),
                    flashcards_enabled: $('#flashcards_enabled').is(':checked')
                }
            };
            
            // Send AJAX request
            $.ajax({
                url: eaGamingEngineAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ea_gaming_save_settings',
                    nonce: eaGamingEngineAdmin.nonce,
                    settings: settings
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message || 'Settings saved successfully!', 'success');
                    } else {
                        this.showNotice(response.data.message || 'Failed to save settings.', 'error');
                    }
                },
                error: () => {
                    this.showNotice('An error occurred while saving settings.', 'error');
                },
                complete: () => {
                    // Restore button state
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        showNotice: function(message, type = 'info') {
            // Remove existing notices
            $('.ea-gaming-notice').remove();
            
            // Create notice element
            const noticeClass = type === 'error' ? 'notice-error' : 
                               type === 'success' ? 'notice-success' : 
                               type === 'warning' ? 'notice-warning' : 'notice-info';
            
            const $notice = $(`
                <div class="notice ${noticeClass} is-dismissible ea-gaming-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            // Insert after header
            $('.ea-gaming-admin-header').after($notice);
            
            // Bind dismiss button
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(() => $notice.remove());
            });
            
            // Auto-dismiss after 5 seconds for non-error messages
            if (type !== 'error') {
                setTimeout(() => {
                    $notice.fadeOut(() => $notice.remove());
                }, 5000);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.ea-gaming-settings-form').length) {
            EAGamingSettings.init();
        }
    });

})(jQuery);