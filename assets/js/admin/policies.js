/**
 * Admin Policies JavaScript
 */

(function($) {
    'use strict';

    const EAGamingPolicies = {
        
        templates: {
            free_play: {
                name: 'Free Play Window',
                rule_type: 'free_play',
                priority: 10,
                conditions: {
                    start_time: '15:00',
                    end_time: '17:00',
                    days: ['mon', 'tue', 'wed', 'thu', 'fri']
                },
                actions: {
                    allow_free_play: true,
                    no_tickets_required: true
                }
            },
            quiet_hours: {
                name: 'Quiet Hours',
                rule_type: 'quiet_hours',
                priority: 5,
                conditions: {
                    start_time: '22:00',
                    end_time: '07:00'
                },
                actions: {
                    block_access: true,
                    message: 'Games are not available during quiet hours.'
                }
            },
            daily_limit: {
                name: 'Daily Game Limit',
                rule_type: 'daily_limit',
                priority: 15,
                conditions: {
                    max_games_per_day: 10,
                    max_time_per_day: 3600
                },
                actions: {
                    block_access: true,
                    message: 'You have reached your daily limit.'
                }
            },
            study_first: {
                name: 'Study First',
                rule_type: 'study_first',
                priority: 20,
                conditions: {
                    require_lesson_view: true,
                    minimum_time: 600
                },
                actions: {
                    redirect_to_lesson: true,
                    message: 'Please complete the lesson before playing games.'
                }
            }
        },
        
        init: function() {
            this.bindEvents();
            this.initModal();
        },

        bindEvents: function() {
            // Add new policy button
            $('#add-new-policy').on('click', this.openNewPolicyModal.bind(this));
            
            // Template buttons
            $('.add-template-policy').on('click', this.addTemplatePolicy.bind(this));
            
            // Edit policy buttons
            $(document).on('click', '.edit-policy', this.editPolicy.bind(this));
            
            // Toggle policy status
            $(document).on('click', '.toggle-policy-status', this.togglePolicyStatus.bind(this));
            
            // Delete policy
            $(document).on('click', '.delete-policy', this.deletePolicy.bind(this));
            
            // Form submission
            $('#policy-form').on('submit', this.savePolicy.bind(this));
        },

        initModal: function() {
            const modal = $('#policy-modal');
            
            // Close button
            $('.ea-gaming-modal-close, .cancel-policy').on('click', function() {
                modal.fadeOut();
            });
            
            // Click outside to close
            $(window).on('click', function(e) {
                if ($(e.target).is(modal)) {
                    modal.fadeOut();
                }
            });
        },

        openNewPolicyModal: function(e) {
            e.preventDefault();
            
            // Reset form
            $('#policy-form')[0].reset();
            $('#policy-id').val('');
            $('#policy-modal-title').text('Create New Policy');
            $('#policy-conditions').val('{}');
            $('#policy-actions').val('{}');
            
            // Show modal
            $('#policy-modal').fadeIn();
        },

        addTemplatePolicy: function(e) {
            e.preventDefault();
            
            const template = $(e.currentTarget).data('template');
            const policyData = this.templates[template];
            
            if (!policyData) {
                this.showNotice('Template not found', 'error');
                return;
            }
            
            // Fill form with template data
            $('#policy-form')[0].reset();
            $('#policy-id').val('');
            $('#policy-modal-title').text('Create Policy from Template');
            $('#policy-name').val(policyData.name);
            $('#policy-type').val(policyData.rule_type);
            $('#policy-priority').val(policyData.priority);
            $('#policy-conditions').val(JSON.stringify(policyData.conditions, null, 2));
            $('#policy-actions').val(JSON.stringify(policyData.actions, null, 2));
            $('#policy-active').prop('checked', true);
            
            // Show modal
            $('#policy-modal').fadeIn();
        },

        editPolicy: function(e) {
            e.preventDefault();
            
            const policyId = $(e.currentTarget).data('policy-id');
            
            // Load policy data via AJAX
            $.ajax({
                url: eaGamingEngineAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ea_gaming_get_policy',
                    policy_id: policyId,
                    nonce: eaGamingEngineAdmin.nonce
                },
                success: (response) => {
                    if (response.success && response.data) {
                        const policy = response.data;
                        
                        $('#policy-id').val(policy.id);
                        $('#policy-modal-title').text('Edit Policy');
                        $('#policy-name').val(policy.name);
                        $('#policy-type').val(policy.rule_type);
                        $('#policy-priority').val(policy.priority);
                        $('#policy-conditions').val(policy.conditions);
                        $('#policy-actions').val(policy.actions);
                        $('#policy-active').prop('checked', policy.active == 1);
                        
                        $('#policy-modal').fadeIn();
                    } else {
                        this.showNotice('Failed to load policy data', 'error');
                    }
                },
                error: () => {
                    this.showNotice('Error loading policy', 'error');
                }
            });
        },

        savePolicy: function(e) {
            e.preventDefault();
            
            const formData = {
                policy_id: $('#policy-id').val(),
                name: $('#policy-name').val(),
                rule_type: $('#policy-type').val(),
                priority: $('#policy-priority').val(),
                active: $('#policy-active').is(':checked') ? 1 : 0,
                conditions: $('#policy-conditions').val(),
                actions: $('#policy-actions').val()
            };
            
            // Validate JSON
            try {
                JSON.parse(formData.conditions);
                JSON.parse(formData.actions);
            } catch {
                this.showNotice('Invalid JSON in conditions or actions', 'error');
                return;
            }
            
            // Save via AJAX
            $.ajax({
                url: eaGamingEngineAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ea_gaming_save_policy',
                    policy: formData,
                    nonce: eaGamingEngineAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('Policy saved successfully', 'success');
                        $('#policy-modal').fadeOut();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        this.showNotice(response.data.message || 'Failed to save policy', 'error');
                    }
                },
                error: () => {
                    this.showNotice('Error saving policy', 'error');
                }
            });
        },

        togglePolicyStatus: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const policyId = $button.data('policy-id');
            const currentStatus = $button.data('status');
            const newStatus = currentStatus == 1 ? 0 : 1;
            
            $.ajax({
                url: eaGamingEngineAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ea_gaming_toggle_policy',
                    policy_id: policyId,
                    active: newStatus,
                    nonce: eaGamingEngineAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        location.reload();
                    } else {
                        this.showNotice('Failed to toggle policy status', 'error');
                    }
                },
                error: () => {
                    this.showNotice('Error updating policy', 'error');
                }
            });
        },

        deletePolicy: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to delete this policy?')) {
                return;
            }
            
            const policyId = $(e.currentTarget).data('policy-id');
            
            $.ajax({
                url: eaGamingEngineAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ea_gaming_delete_policy',
                    policy_id: policyId,
                    nonce: eaGamingEngineAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('Policy deleted successfully', 'success');
                        $(`#policy-${policyId}`).fadeOut(() => {
                            $(`#policy-${policyId}`).remove();
                        });
                    } else {
                        this.showNotice('Failed to delete policy', 'error');
                    }
                },
                error: () => {
                    this.showNotice('Error deleting policy', 'error');
                }
            });
        },

        showNotice: function(message, type = 'info') {
            // Remove existing notices
            $('.ea-gaming-notice').remove();
            
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
            
            $('.ea-gaming-admin-header').after($notice);
            
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(() => $notice.remove());
            });
            
            if (type !== 'error') {
                setTimeout(() => {
                    $notice.fadeOut(() => $notice.remove());
                }, 5000);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#ea-gaming-policies-table').length) {
            EAGamingPolicies.init();
        }
    });

})(jQuery);