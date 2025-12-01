/**
 * GetCited Admin JavaScript
 * 
 * Handles all admin interactions without jQuery
 */

(function() {
    'use strict';

    // Wait for DOM
    document.addEventListener('DOMContentLoaded', init);

    /**
     * Initialize all handlers
     */
    function init() {
        initCrawlerToggles();
        initBulkActions();
        initCustomCrawlers();
        initLlmsTxtEditor();
        initSchemaSettings();
        initCitabilityAnalysis();
        initHealthCheck();
        initWaitlistForm();
        initSampleModal();
        initWizard();
        initSettingsPage();
        initExportImport();
    }

    // ==========================================================================
    // Utility Functions
    // ==========================================================================

    /**
     * Make AJAX request
     */
    function ajax(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', getcitedAdmin.nonce);
        
        for (const key in data) {
            if (typeof data[key] === 'object') {
                formData.append(key, JSON.stringify(data[key]));
            } else {
                formData.append(key, data[key]);
            }
        }

        return fetch(getcitedAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(response => response.json());
    }

    /**
     * Make REST API request
     */
    function api(endpoint, method = 'GET', data = null) {
        const options = {
            method,
            headers: {
                'X-WP-Nonce': getcitedAdmin.restNonce,
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin'
        };

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }

        return fetch(getcitedAdmin.restUrl + endpoint, options)
            .then(response => response.json());
    }

    /**
     * Show save status
     */
    function showStatus(element, message, type = 'success') {
        element.textContent = message;
        element.className = 'getcited-save-status ' + type;
        
        if (type === 'success') {
            setTimeout(() => {
                element.textContent = '';
                element.className = 'getcited-save-status';
            }, 3000);
        }
    }

    /**
     * Debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // ==========================================================================
    // Crawler Management
    // ==========================================================================

    function initCrawlerToggles() {
        const toggles = document.querySelectorAll('.getcited-crawler-item .getcited-toggle input');
        
        toggles.forEach(toggle => {
            toggle.addEventListener('change', function() {
                const crawler = this.dataset.crawler;
                const status = this.checked ? 'allow' : 'block';
                const item = this.closest('.getcited-crawler-item');
                const statusLabel = item.querySelector('.status-label');

                // Update UI immediately
                statusLabel.textContent = this.checked ? 'Allowed' : 'Blocked';
                statusLabel.className = 'status-label ' + (this.checked ? 'allowed' : 'blocked');

                // Save via API
                api('crawlers/' + encodeURIComponent(crawler), 'POST', { status })
                    .then(response => {
                        if (!response.success) {
                            // Revert on error
                            this.checked = !this.checked;
                            statusLabel.textContent = this.checked ? 'Allowed' : 'Blocked';
                            statusLabel.className = 'status-label ' + (this.checked ? 'allowed' : 'blocked');
                        }
                        updateRobotsPreview();
                    })
                    .catch(() => {
                        // Revert on error
                        this.checked = !this.checked;
                    });
            });
        });
    }

    function initBulkActions() {
        const allowAllBtn = document.querySelector('.getcited-allow-all');
        const blockAllBtn = document.querySelector('.getcited-block-all');

        if (allowAllBtn) {
            allowAllBtn.addEventListener('click', () => setCrawlersBulk('allow'));
        }

        if (blockAllBtn) {
            blockAllBtn.addEventListener('click', () => setCrawlersBulk('block'));
        }
    }

    function setCrawlersBulk(status) {
        const toggles = document.querySelectorAll('.getcited-crawler-item .getcited-toggle input');
        const crawlers = {};

        toggles.forEach(toggle => {
            const checked = status === 'allow';
            toggle.checked = checked;
            crawlers[toggle.dataset.crawler] = status;

            // Update status label
            const item = toggle.closest('.getcited-crawler-item');
            const statusLabel = item.querySelector('.status-label');
            statusLabel.textContent = checked ? 'Allowed' : 'Blocked';
            statusLabel.className = 'status-label ' + (checked ? 'allowed' : 'blocked');
        });

        // Save all at once
        ajax('getcited_save_settings', {
            section: 'crawlers',
            data: { crawlers }
        }).then(() => {
            updateRobotsPreview();
        });
    }

    function initCustomCrawlers() {
        const addBtn = document.querySelector('.getcited-add-custom');
        const container = document.querySelector('.getcited-custom-list');

        if (!addBtn || !container) return;

        addBtn.addEventListener('click', () => {
            const index = container.children.length;
            const html = `
                <div class="getcited-custom-item" data-index="${index}">
                    <input type="text" name="custom_crawlers[${index}][user_agent]" placeholder="User-agent string">
                    <input type="text" name="custom_crawlers[${index}][name]" placeholder="Name (optional)">
                    <select name="custom_crawlers[${index}][action]">
                        <option value="allow">Allow</option>
                        <option value="block">Block</option>
                    </select>
                    <button type="button" class="button getcited-remove-custom">×</button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        });

        container.addEventListener('click', (e) => {
            if (e.target.classList.contains('getcited-remove-custom')) {
                e.target.closest('.getcited-custom-item').remove();
            }
        });
    }

    function updateRobotsPreview() {
        const preview = document.querySelector('.getcited-robots-preview .getcited-preview-code');
        if (!preview) return;

        api('status').then(response => {
            // Refresh the page section or rebuild preview
            // For now, we'll just indicate it needs refresh
            preview.style.opacity = '0.5';
            setTimeout(() => {
                preview.style.opacity = '1';
            }, 300);
        });
    }

    // ==========================================================================
    // llms.txt Editor
    // ==========================================================================

    function initLlmsTxtEditor() {
        const textarea = document.getElementById('llms_txt_content');
        const preview = document.getElementById('llms_txt_preview');
        const saveBtn = document.querySelector('.getcited-save-llms-txt');
        const enabledToggle = document.getElementById('llms_txt_enabled');
        const templateBtns = document.querySelectorAll('.getcited-load-template');

        if (!textarea) return;

        // Live preview
        if (preview) {
            textarea.addEventListener('input', debounce(() => {
                preview.textContent = textarea.value;
            }, 300));
        }

        // Save button
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                const statusEl = saveBtn.nextElementSibling;
                saveBtn.disabled = true;
                saveBtn.textContent = getcitedAdmin.strings.saving;

                ajax('getcited_save_settings', {
                    section: 'llms_txt',
                    data: {
                        llms_txt_enabled: enabledToggle ? enabledToggle.checked : true,
                        llms_txt_content: textarea.value
                    }
                }).then(response => {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Changes';
                    
                    if (response.success) {
                        showStatus(statusEl, getcitedAdmin.strings.saved, 'success');
                    } else {
                        showStatus(statusEl, getcitedAdmin.strings.error, 'error');
                    }
                }).catch(() => {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Changes';
                    showStatus(statusEl, getcitedAdmin.strings.error, 'error');
                });
            });
        }

        // Template buttons
        templateBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const type = btn.dataset.type;

                // Confirm if textarea has content
                if (textarea.value.trim() && !confirm('This will replace your current content. Continue?')) {
                    return;
                }

                // Show loading state
                btn.disabled = true;
                const originalText = btn.textContent;
                btn.textContent = 'Loading...';

                // Fetch template from server
                ajax('getcited_load_template', { type: type })
                    .then(response => {
                        btn.disabled = false;
                        btn.textContent = originalText;

                        if (response.success && response.data.content) {
                            // Populate textarea
                            textarea.value = response.data.content;

                            // Update live preview if exists
                            if (preview) {
                                preview.textContent = response.data.content;
                            }
                        } else {
                            console.error('Failed to load template:', response);
                        }
                    })
                    .catch(error => {
                        btn.disabled = false;
                        btn.textContent = originalText;
                        console.error('Template load error:', error);
                    });
            });
        });
    }

    // ==========================================================================
    // Schema Settings
    // ==========================================================================

    function initSchemaSettings() {
        const saveBtn = document.querySelector('.getcited-save-schema');
        
        if (!saveBtn) return;

        saveBtn.addEventListener('click', () => {
            const statusEl = saveBtn.nextElementSibling;
            saveBtn.disabled = true;
            saveBtn.textContent = getcitedAdmin.strings.saving;

            const schemaTypes = {};
            document.querySelectorAll('input[name^="schema_types"]').forEach(input => {
                const match = input.name.match(/\[(\w+)\]/);
                if (match) {
                    schemaTypes[match[1]] = input.checked;
                }
            });

            const socialUrls = [];
            document.querySelectorAll('input[name="organization[social_urls][]"]').forEach(input => {
                if (input.value.trim()) {
                    socialUrls.push(input.value.trim());
                }
            });

            const data = {
                schema_enabled: document.getElementById('schema_enabled')?.checked ?? true,
                schema_types: schemaTypes,
                organization: {
                    name: document.getElementById('org_name')?.value || '',
                    logo_url: document.getElementById('org_logo')?.value || '',
                    social_urls: socialUrls
                }
            };

            ajax('getcited_save_settings', {
                section: 'schema',
                data: data
            }).then(response => {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
                
                if (response.success) {
                    showStatus(statusEl, getcitedAdmin.strings.saved, 'success');
                } else {
                    showStatus(statusEl, getcitedAdmin.strings.error, 'error');
                }
            }).catch(() => {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
                showStatus(statusEl, getcitedAdmin.strings.error, 'error');
            });
        });

        // Add social URL field
        const addSocialBtn = document.querySelector('.getcited-add-social');
        if (addSocialBtn) {
            addSocialBtn.addEventListener('click', () => {
                const container = document.querySelector('.getcited-social-urls');
                const input = document.createElement('input');
                input.type = 'url';
                input.name = 'organization[social_urls][]';
                input.className = 'regular-text';
                input.placeholder = 'https://...';
                container.appendChild(input);
            });
        }
    }

    // ==========================================================================
    // Citability Analysis
    // ==========================================================================

    function initCitabilityAnalysis() {
        // Table analyze buttons
        document.querySelectorAll('.getcited-analyze-post').forEach(btn => {
            btn.addEventListener('click', function() {
                analyzePost(this.dataset.postId, this);
            });
        });

        // Meta box analyze button
        const metaBoxBtn = document.querySelector('.getcited-meta-box .getcited-analyze-btn');
        if (metaBoxBtn) {
            metaBoxBtn.addEventListener('click', function() {
                analyzePost(this.dataset.postId, this);
            });
        }
    }

    function analyzePost(postId, button) {
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = getcitedAdmin.strings.analyzing;

        ajax('getcited_analyze_post', { post_id: postId })
            .then(response => {
                button.disabled = false;
                button.textContent = originalText;

                if (response.success) {
                    const data = response.data;
                    
                    // Update table row if exists
                    const row = document.querySelector(`tr[data-post-id="${postId}"]`);
                    if (row) {
                        const scoreCell = row.querySelector('.score-cell');
                        const scoreClass = data.score >= 70 ? 'good' : (data.score >= 40 ? 'ok' : 'low');
                        scoreCell.innerHTML = `<span class="getcited-score-badge ${scoreClass}">${data.score}/100</span>`;
                    }

                    // Update meta box if exists
                    const metaBox = document.querySelector('.getcited-meta-box');
                    if (metaBox) {
                        const scoreDisplay = metaBox.querySelector('.getcited-score-display');
                        if (scoreDisplay) {
                            scoreDisplay.innerHTML = `
                                <div class="getcited-score-number">
                                    <span class="score">${data.score}</span>
                                    <span class="max">/100</span>
                                </div>
                            `;
                        }
                    }

                    // Show detailed results
                    showAnalysisResults(data);
                }
            })
            .catch(() => {
                button.disabled = false;
                button.textContent = originalText;
            });
    }

    function showAnalysisResults(data) {
        const resultsSection = document.querySelector('.getcited-analysis-results');
        const resultsContent = document.querySelector('.getcited-results-content');
        
        if (!resultsSection || !resultsContent) return;

        let html = `
            <div class="getcited-score-display large" style="text-align: center; margin-bottom: 20px;">
                <span class="score">${data.score}</span>
                <span class="max">/100</span>
            </div>
            <div class="getcited-factors">
        `;

        for (const [key, factor] of Object.entries(data.factors)) {
            const rubric = data.rubric[key] || {};
            const icon = factor.passed ? '✓' : '✗';
            const iconClass = factor.passed ? 'passed' : 'failed';
            
            html += `
                <div class="factor-item ${iconClass}">
                    <span class="factor-icon">${icon}</span>
                    <span class="factor-label">${rubric.label || key}</span>
                    <span class="factor-score">${factor.score}/${rubric.max_points || '?'}</span>
                    <span class="factor-message">${factor.message}</span>
                </div>
            `;
        }

        html += '</div>';

        if (data.recommendations && data.recommendations.length) {
            html += '<h3>Top Recommendations</h3><ol>';
            data.recommendations.forEach(rec => {
                html += `<li>${rec}</li>`;
            });
            html += '</ol>';
        }

        resultsContent.innerHTML = html;
        resultsSection.style.display = 'block';
        resultsSection.scrollIntoView({ behavior: 'smooth' });
    }

    // ==========================================================================
    // Health Check
    // ==========================================================================

    function initHealthCheck() {
        const healthSection = document.querySelector('.getcited-health-section');
        if (!healthSection) return;

        // Run health check button
        const runBtn = healthSection.querySelector('.getcited-run-health-check');
        if (runBtn) {
            runBtn.addEventListener('click', () => {
                runBtn.disabled = true;
                runBtn.textContent = getcitedAdmin.strings.checking;

                ajax('getcited_health_check')
                    .then(response => {
                        runBtn.disabled = false;
                        runBtn.textContent = 'Run Check';

                        if (response.success) {
                            // Reload page to show updated results
                            window.location.reload();
                        }
                    })
                    .catch(() => {
                        runBtn.disabled = false;
                        runBtn.textContent = 'Run Check';
                    });
            });
        }

        // Expand/collapse health details
        healthSection.querySelectorAll('.getcited-health-expand').forEach(btn => {
            btn.addEventListener('click', function() {
                const item = this.closest('.getcited-health-item');
                const checkKey = item.dataset.check;
                const details = healthSection.querySelector(`.getcited-health-details[data-check="${checkKey}"]`);

                if (details) {
                    const isExpanded = this.getAttribute('aria-expanded') === 'true';
                    this.setAttribute('aria-expanded', !isExpanded);
                    details.style.display = isExpanded ? 'none' : 'block';

                    // Rotate arrow icon
                    const icon = this.querySelector('.dashicons');
                    icon.classList.toggle('dashicons-arrow-down-alt2', isExpanded);
                    icon.classList.toggle('dashicons-arrow-up-alt2', !isExpanded);
                }
            });
        });

        // Copy rules to clipboard
        healthSection.querySelectorAll('.getcited-copy-rules').forEach(btn => {
            btn.addEventListener('click', function() {
                const rules = this.dataset.rules;

                navigator.clipboard.writeText(rules).then(() => {
                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + getcitedAdmin.strings.copied;
                    this.classList.add('copied');

                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.classList.remove('copied');
                    }, 2000);
                }).catch(err => {
                    console.error('Failed to copy:', err);
                    // Fallback for older browsers
                    const textarea = document.createElement('textarea');
                    textarea.value = rules;
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);

                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + getcitedAdmin.strings.copied;
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                    }, 2000);
                });
            });
        });

        // Show/hide rules toggle
        healthSection.querySelectorAll('.getcited-show-rules').forEach(btn => {
            btn.addEventListener('click', function() {
                const preview = this.nextElementSibling;
                if (preview && preview.classList.contains('getcited-rules-preview')) {
                    const isHidden = preview.style.display === 'none';
                    preview.style.display = isHidden ? 'block' : 'none';
                    this.textContent = isHidden ? 'Hide Rules' : 'Show Rules';
                }
            });
        });
    }

    // ==========================================================================
    // Waitlist Form
    // ==========================================================================

    function initWaitlistForm() {
        const form = document.getElementById('getcited-waitlist-form');
        if (!form) return;

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const emailInput = form.querySelector('input[type="email"]');
            const submitBtn = form.querySelector('button[type="submit"]');
            const messageEl = document.querySelector('.getcited-waitlist-message');
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Joining...';

            ajax('getcited_waitlist_signup', { email: emailInput.value })
                .then(response => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Reserve Your Spot';

                    if (response.success) {
                        form.style.display = 'none';
                        messageEl.style.display = 'block';
                        messageEl.innerHTML = `<p style="color: #10b981;">✓ ${response.data.message}</p>`;
                        
                        // Only show/update count if 100+
                        const countEl = document.querySelector('.getcited-waitlist-count');
                        if (countEl && response.data.count && response.data.count >= 100) {
                            countEl.querySelector('.count').textContent = response.data.count.toLocaleString();
                            countEl.style.display = 'block';
                        }
                    } else {
                        messageEl.style.display = 'block';
                        messageEl.innerHTML = `<p style="color: #ef4444;">${response.data.message}</p>`;
                    }
                })
                .catch(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Reserve Your Spot';
                });
        });
    }

    // ==========================================================================
    // Sample Modal
    // ==========================================================================

    function initSampleModal() {
        const openBtns = document.querySelectorAll('.getcited-view-sample');
        const modal = document.getElementById('getcited-sample-modal');
        const closeBtn = modal?.querySelector('.getcited-modal-close');

        if (!modal) return;

        openBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                modal.style.display = 'flex';
            });
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Handle "Join Waitlist" buttons in modal
        document.querySelectorAll('.getcited-join-waitlist').forEach(btn => {
            btn.addEventListener('click', () => {
                modal.style.display = 'none';
                const waitlistForm = document.getElementById('getcited-waitlist-form');
                if (waitlistForm) {
                    waitlistForm.scrollIntoView({ behavior: 'smooth' });
                    waitlistForm.querySelector('input[type="email"]')?.focus();
                }
            });
        });
    }

    // ==========================================================================
    // Setup Wizard
    // ==========================================================================

    function initWizard() {
        const wizard = document.querySelector('.getcited-wizard');
        if (!wizard) return;

        const steps = ['welcome', 'site_type', 'organization', 'crawlers', 'complete'];
        let currentStep = 0;

        function showStep(index) {
            // Hide all steps
            wizard.querySelectorAll('.getcited-wizard-step').forEach(step => {
                step.style.display = 'none';
            });

            // Show current step
            const stepEl = wizard.querySelector(`[data-step="${steps[index]}"]`);
            if (stepEl) {
                stepEl.style.display = 'block';
            }

            // Update progress
            wizard.querySelectorAll('.progress-step').forEach((step, i) => {
                step.classList.remove('active', 'completed');
                if (i < index) {
                    step.classList.add('completed');
                } else if (i === index) {
                    step.classList.add('active');
                }
            });

            currentStep = index;
        }

        // Next buttons
        wizard.querySelectorAll('.getcited-wizard-next').forEach(btn => {
            btn.addEventListener('click', () => {
                const currentStepName = steps[currentStep];
                
                // Save current step data
                saveWizardStep(currentStepName).then(() => {
                    if (currentStep < steps.length - 1) {
                        showStep(currentStep + 1);
                    }
                });
            });
        });

        // Back buttons
        wizard.querySelectorAll('.getcited-wizard-back').forEach(btn => {
            btn.addEventListener('click', () => {
                if (currentStep > 0) {
                    showStep(currentStep - 1);
                }
            });
        });

        // Skip button
        const skipBtn = wizard.querySelector('.getcited-wizard-skip');
        if (skipBtn) {
            skipBtn.addEventListener('click', (e) => {
                e.preventDefault();
                ajax('getcited_wizard_skip').then(() => {
                    window.location.href = window.location.href.replace('&wizard=1', '');
                });
            });
        }

        // Complete button
        const completeBtn = wizard.querySelector('.getcited-wizard-complete');
        if (completeBtn) {
            completeBtn.addEventListener('click', () => {
                ajax('getcited_wizard_complete').then(response => {
                    if (response.success && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    }
                });
            });
        }

        // Site type selection
        wizard.querySelectorAll('.getcited-site-type input').forEach(input => {
            input.addEventListener('change', function() {
                // Update visual selection
                wizard.querySelectorAll('.site-type-card').forEach(card => {
                    card.closest('.getcited-site-type').querySelector('input').checked 
                        ? card.classList.add('selected') 
                        : card.classList.remove('selected');
                });
            });
        });

        // Crawler choice selection
        wizard.querySelectorAll('.getcited-radio-card input').forEach(input => {
            input.addEventListener('change', function() {
                wizard.querySelectorAll('.getcited-radio-card').forEach(card => {
                    card.classList.remove('selected');
                });
                this.closest('.getcited-radio-card').classList.add('selected');
            });
        });
    }

    function saveWizardStep(stepName) {
        const data = {};

        switch (stepName) {
            case 'site_type':
                const siteType = document.querySelector('input[name="site_type"]:checked');
                if (siteType) {
                    data.site_type = siteType.value;
                }
                break;

            case 'organization':
                data.name = document.getElementById('wizard_org_name')?.value || '';
                data.logo_url = document.getElementById('wizard_org_logo')?.value || '';
                break;

            case 'crawlers':
                const choice = document.querySelector('input[name="crawler_choice"]:checked');
                data.allow_all = choice?.value === 'allow_all' ? 'true' : 'false';
                break;

            default:
                return Promise.resolve();
        }

        return ajax('getcited_wizard_save', { step: stepName, data });
    }

    // ==========================================================================
    // Settings Page
    // ==========================================================================

    function initSettingsPage() {
        const saveBtn = document.querySelector('.getcited-save-settings');
        if (!saveBtn) return;

        saveBtn.addEventListener('click', () => {
            const statusEl = saveBtn.nextElementSibling;
            saveBtn.disabled = true;
            saveBtn.textContent = getcitedAdmin.strings.saving;

            const data = {
                site_type: document.getElementById('site_type')?.value || 'blog',
                debug_mode: document.getElementById('debug_mode')?.checked || false,
                keep_on_delete: document.getElementById('keep_on_delete')?.checked || false
            };

            ajax('getcited_save_settings', {
                section: 'advanced',
                data: data
            }).then(response => {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
                
                if (response.success) {
                    showStatus(statusEl, getcitedAdmin.strings.saved, 'success');
                } else {
                    showStatus(statusEl, getcitedAdmin.strings.error, 'error');
                }
            }).catch(() => {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
                showStatus(statusEl, getcitedAdmin.strings.error, 'error');
            });
        });

        // Copy system info
        const copyBtn = document.querySelector('.getcited-copy-system-info');
        if (copyBtn) {
            copyBtn.addEventListener('click', () => {
                const textarea = document.getElementById('getcited-system-info');
                textarea.select();
                document.execCommand('copy');
                
                const originalText = copyBtn.textContent;
                copyBtn.textContent = 'Copied!';
                setTimeout(() => {
                    copyBtn.textContent = originalText;
                }, 2000);
            });
        }
    }

    // ==========================================================================
    // Import/Export
    // ==========================================================================

    function initExportImport() {
        const exportBtn = document.querySelector('.getcited-export-settings');
        const importBtn = document.querySelector('.getcited-import-settings');
        const importFile = document.getElementById('getcited-import-file');

        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                api('settings').then(settings => {
                    // Remove sensitive data
                    delete settings.license_key;
                    delete settings.site_uuid;
                    
                    // Add metadata
                    settings._export = {
                        version: '1.0.0',
                        date: new Date().toISOString(),
                        site_url: window.location.origin
                    };

                    const json = JSON.stringify(settings, null, 2);
                    const blob = new Blob([json], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'getcited-settings-' + new Date().toISOString().split('T')[0] + '.json';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                });
            });
        }

        if (importBtn && importFile) {
            importBtn.addEventListener('click', () => {
                importFile.click();
            });

            importFile.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = (event) => {
                    try {
                        const data = JSON.parse(event.target.result);
                        
                        if (!confirm('This will replace your current settings. Continue?')) {
                            return;
                        }

                        api('settings', 'POST', data).then(() => {
                            alert('Settings imported successfully. The page will now reload.');
                            window.location.reload();
                        }).catch(() => {
                            alert('Failed to import settings. Please check the file format.');
                        });
                    } catch (err) {
                        alert('Invalid JSON file. Please check the file format.');
                    }
                };
                reader.readAsText(file);
            });
        }
    }

})();
