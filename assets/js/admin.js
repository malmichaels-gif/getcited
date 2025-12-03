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
        initRobotsRulesActions();
        initWaitlistForm();
        initCompactWaitlistButtons();
        initSampleModal();
        initWizard();
        initSettingsPage();
        initExportImport();
        initCopyButtons();
        initCollapsibleSections();
        initSourceToggle();
        initMediaUpload();
        initLoadMorePosts();
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
    // Copy to Clipboard
    // ==========================================================================

    function initCopyButtons() {
        document.querySelectorAll('.getcited-copy-content').forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.dataset.target;
                const targetEl = document.getElementById(targetId);

                if (!targetEl) return;

                const text = targetEl.textContent;
                const originalHTML = this.innerHTML;

                navigator.clipboard.writeText(text).then(() => {
                    this.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + (getcitedAdmin.strings?.copied || 'Copied!');
                    this.classList.add('copied');

                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.classList.remove('copied');
                    }, 2000);
                }).catch(err => {
                    // Fallback for older browsers
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);

                    this.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + (getcitedAdmin.strings?.copied || 'Copied!');
                    this.classList.add('copied');

                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.classList.remove('copied');
                    }, 2000);
                });
            });
        });
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

        // Preserve custom crawlers when doing bulk action
        const customCrawlers = [];
        const container = document.querySelector('.getcited-custom-list');
        if (container) {
            container.querySelectorAll('.getcited-custom-item').forEach(item => {
                const userAgent = item.querySelector('input[name*="[user_agent]"]')?.value?.trim();
                const name = item.querySelector('input[name*="[name]"]')?.value?.trim();
                const action = item.querySelector('select')?.value || 'allow';

                if (userAgent) {
                    customCrawlers.push({
                        user_agent: userAgent,
                        name: name || '',
                        action: action
                    });
                }
            });
        }

        // Save all at once (including custom crawlers)
        ajax('getcited_save_settings', {
            section: 'crawlers',
            data: {
                crawlers: crawlers,
                custom_crawlers: customCrawlers
            }
        }).then(() => {
            updateRobotsPreview();
        });
    }

    function initCustomCrawlers() {
        const addBtn = document.querySelector('.getcited-add-custom');
        const container = document.querySelector('.getcited-custom-list');
        const saveBtn = document.querySelector('.getcited-save-crawlers');

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

        // Save custom crawlers
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                const statusEl = saveBtn.nextElementSibling;
                saveBtn.disabled = true;
                saveBtn.textContent = getcitedAdmin.strings?.saving || 'Saving...';

                // Collect all crawler toggle states
                const crawlers = {};
                document.querySelectorAll('.getcited-crawler-item .getcited-toggle input').forEach(toggle => {
                    crawlers[toggle.dataset.crawler] = toggle.checked ? 'allow' : 'block';
                });

                // Collect custom crawlers
                const customCrawlers = [];
                container.querySelectorAll('.getcited-custom-item').forEach(item => {
                    const userAgent = item.querySelector('input[name*="[user_agent]"]')?.value?.trim();
                    const name = item.querySelector('input[name*="[name]"]')?.value?.trim();
                    const action = item.querySelector('select')?.value || 'allow';

                    if (userAgent) {
                        customCrawlers.push({
                            user_agent: userAgent,
                            name: name || '',
                            action: action
                        });
                    }
                });

                // Get the auto-write toggle
                const robotsWritePhysical = document.getElementById('robots_write_physical');

                ajax('getcited_save_settings', {
                    section: 'crawlers',
                    data: {
                        crawlers: crawlers,
                        custom_crawlers: customCrawlers,
                        robots_write_physical: robotsWritePhysical ? robotsWritePhysical.checked : false
                    }
                }).then(response => {
                    saveBtn.disabled = false;
                    saveBtn.textContent = getcitedAdmin.strings?.save_changes || 'Save Changes';

                    if (response.success) {
                        showStatus(statusEl, getcitedAdmin.strings?.saved || 'Saved!', 'success');
                        updateRobotsPreview();
                    } else {
                        showStatus(statusEl, getcitedAdmin.strings?.error || 'Error saving', 'error');
                    }
                }).catch(() => {
                    saveBtn.disabled = false;
                    saveBtn.textContent = getcitedAdmin.strings?.save_changes || 'Save Changes';
                    showStatus(statusEl, getcitedAdmin.strings?.error || 'Error saving', 'error');
                });
            });
        }
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
        const writePhysicalToggle = document.getElementById('llms_write_physical');
        const templateBtns = document.querySelectorAll('.getcited-load-template');

        if (!textarea) return;

        // Live preview
        if (preview) {
            textarea.addEventListener('input', debounce(() => {
                preview.textContent = textarea.value;
            }, 300));
        }

        // Save button - collects all llms.txt settings
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                const statusEl = saveBtn.nextElementSibling;
                saveBtn.disabled = true;
                saveBtn.textContent = getcitedAdmin.strings.saving;

                // Collect all llms.txt related settings
                const data = {
                    llms_txt_enabled: enabledToggle ? enabledToggle.checked : true,
                    llms_txt_content: textarea.value,
                    llms_write_physical: writePhysicalToggle ? writePhysicalToggle.checked : false,
                    llms_founder_name: document.getElementById('llms_founder_name')?.value || '',
                    llms_founder_title: document.getElementById('llms_founder_title')?.value || '',
                    llms_site_expertise: document.getElementById('llms_site_expertise')?.value || '',
                    llms_update_frequency: document.getElementById('llms_update_frequency')?.value || '',
                    llms_citation_format: document.getElementById('llms_citation_format')?.value || ''
                };

                ajax('getcited_save_settings', {
                    section: 'llms_txt',
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
        }

        // Write llms.txt file button (manual trigger)
        const writeFileBtn = document.querySelector('.getcited-write-llms-file');
        if (writeFileBtn) {
            writeFileBtn.addEventListener('click', function() {
                const statusEl = this.nextElementSibling;
                const originalHTML = this.innerHTML;

                this.disabled = true;
                this.innerHTML = '<span class="dashicons dashicons-update getcited-spinning"></span> Writing...';

                ajax('getcited_write_llms_file')
                    .then(response => {
                        this.disabled = false;
                        this.innerHTML = originalHTML;

                        if (response.success) {
                            showStatus(statusEl, response.data.message || 'File written successfully!', 'success');
                            // Update file status display if present
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            showStatus(statusEl, response.data?.message || 'Failed to write file', 'error');
                        }
                    })
                    .catch(() => {
                        this.disabled = false;
                        this.innerHTML = originalHTML;
                        showStatus(statusEl, 'Failed to write file', 'error');
                    });
            });
        }

        // Delete llms.txt file button
        const deleteFileBtn = document.querySelector('.getcited-delete-llms-file');
        if (deleteFileBtn) {
            deleteFileBtn.addEventListener('click', function() {
                if (!confirm('Delete the physical llms.txt file? The dynamic version will still work.')) {
                    return;
                }

                const statusEl = this.nextElementSibling;
                const originalHTML = this.innerHTML;

                this.disabled = true;
                this.innerHTML = '<span class="dashicons dashicons-update getcited-spinning"></span> Deleting...';

                ajax('getcited_delete_llms_file')
                    .then(response => {
                        this.disabled = false;
                        this.innerHTML = originalHTML;

                        if (response.success) {
                            showStatus(statusEl, response.data.message || 'File deleted!', 'success');
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            showStatus(statusEl, response.data?.message || 'Failed to delete file', 'error');
                        }
                    })
                    .catch(() => {
                        this.disabled = false;
                        this.innerHTML = originalHTML;
                        showStatus(statusEl, 'Failed to delete file', 'error');
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

        // Site scanner button
        const scanBtn = document.querySelector('.getcited-scan-site');
        if (scanBtn) {
            scanBtn.addEventListener('click', function() {
                const statusEl = document.querySelector('.getcited-scan-status');
                const originalHTML = this.innerHTML;

                // Confirm if textarea has content
                if (textarea.value.trim() && !confirm('This will replace your current content with scanned data. Continue?')) {
                    return;
                }

                this.disabled = true;
                this.innerHTML = '<span class="dashicons dashicons-update getcited-spinning"></span> ' + (getcitedAdmin.strings.scanning || 'Scanning...');
                if (statusEl) {
                    statusEl.textContent = '';
                    statusEl.className = 'getcited-scan-status';
                }

                ajax('getcited_scan_site')
                    .then(response => {
                        this.disabled = false;
                        this.innerHTML = originalHTML;

                        if (response.success) {
                            // Update the editor with generated content
                            textarea.value = response.data.llms_txt;

                            // Update live preview if exists
                            if (preview) {
                                preview.textContent = response.data.llms_txt;
                            }

                            // Mark as having unsaved changes
                            markUnsaved();

                            if (statusEl) {
                                statusEl.textContent = getcitedAdmin.strings.scan_success || 'Site scanned successfully!';
                                statusEl.className = 'getcited-scan-status success';
                            }

                            // Show scan summary
                            showScanSummary(response.data.scan_data);
                        } else {
                            if (statusEl) {
                                statusEl.textContent = getcitedAdmin.strings.scan_failed || 'Scan failed. Please try again.';
                                statusEl.className = 'getcited-scan-status error';
                            }
                        }
                    })
                    .catch(error => {
                        this.disabled = false;
                        this.innerHTML = originalHTML;
                        if (statusEl) {
                            statusEl.textContent = getcitedAdmin.strings.scan_failed || 'Scan failed. Please try again.';
                            statusEl.className = 'getcited-scan-status error';
                        }
                        console.error('GetCited: Scan failed', error);
                    });
            });
        }
    }

    /**
     * Mark the editor as having unsaved changes
     */
    function markUnsaved() {
        const saveBtn = document.querySelector('.getcited-save-llms-txt');
        if (saveBtn && !saveBtn.classList.contains('has-changes')) {
            saveBtn.classList.add('has-changes');
            // Add visual indicator
            const statusEl = saveBtn.nextElementSibling;
            if (statusEl) {
                statusEl.textContent = getcitedAdmin.strings.unsaved || 'Unsaved changes';
                statusEl.className = 'getcited-save-status warning';
            }
        }
    }

    /**
     * Display scan results summary
     */
    function showScanSummary(data) {
        // Remove existing summary
        const existingSummary = document.querySelector('.getcited-scan-summary');
        if (existingSummary) {
            existingSummary.remove();
        }

        const stats = [
            { label: getcitedAdmin.strings.pages || 'pages', count: Object.keys(data.pages || {}).length },
            { label: getcitedAdmin.strings.posts || 'posts', count: (data.posts || []).length },
            { label: getcitedAdmin.strings.categories || 'categories', count: (data.categories || []).length },
            { label: getcitedAdmin.strings.menu_items || 'menu items', count: (data.menu || []).length },
            { label: getcitedAdmin.strings.social_links || 'social links', count: Object.keys(data.social || {}).length },
        ];

        const statsHtml = stats
            .filter(s => s.count > 0)
            .map(s => `<span class="stat"><strong>${s.count}</strong> ${s.label}</span>`)
            .join(' · ');

        if (!statsHtml) return; // No stats to show

        const summaryHtml = `
            <div class="getcited-scan-summary">
                <div class="summary-header">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <strong>${getcitedAdmin.strings.scan_complete || 'Scan Complete'}</strong>
                </div>
                <div class="summary-stats">${statsHtml}</div>
                <p class="description">${getcitedAdmin.strings.scan_review || 'Review the generated content and save when ready.'}</p>
            </div>
        `;

        // Insert after scan description
        const scanDesc = document.querySelector('.getcited-scan-description');
        if (scanDesc) {
            scanDesc.insertAdjacentHTML('afterend', summaryHtml);
        }
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
                schema_force_enabled: document.getElementById('schema_force_enabled')?.checked ?? false,
                schema_types: schemaTypes,
                organization: {
                    name: document.getElementById('org_name')?.value || '',
                    logo_url: document.getElementById('org_logo')?.value || '',
                    social_urls: socialUrls,
                    linkedin_company: document.getElementById('org_linkedin_company')?.value || '',
                    wikipedia: document.getElementById('org_wikipedia')?.value || '',
                    crunchbase: document.getElementById('org_crunchbase')?.value || ''
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

        // Schema detection re-scan button
        const rescanBtn = document.querySelector('.getcited-rescan-schema');
        if (rescanBtn) {
            rescanBtn.addEventListener('click', function() {
                const originalText = this.textContent;
                this.disabled = true;
                this.textContent = getcitedAdmin.strings.rescanning || 'Rescanning...';

                ajax('getcited_rescan_schema', {})
                    .then(response => {
                        this.disabled = false;
                        this.textContent = originalText;

                        if (response.success) {
                            // Update the status indicator
                            const statusIndicator = document.querySelector('.getcited-status-indicator');
                            const statusMessage = statusIndicator?.querySelector('.status-message');
                            const statusIcon = statusIndicator?.querySelector('.dashicons');

                            if (statusMessage) {
                                statusMessage.textContent = response.data.status.message;
                            }

                            if (statusIcon) {
                                if (response.data.status.status === 'active') {
                                    statusIcon.className = 'dashicons dashicons-yes-alt';
                                    statusIcon.style.color = '#46b450';
                                } else {
                                    statusIcon.className = 'dashicons dashicons-warning';
                                    statusIcon.style.color = '#f0b849';
                                }
                            }

                            // Update the last scan time
                            const lastScanTime = document.querySelector('.last-scan-time');
                            if (lastScanTime) {
                                lastScanTime.textContent = response.data.last_scan;
                            }

                            // Show success briefly
                            this.textContent = getcitedAdmin.strings.rescan_complete || 'Scan complete';
                            setTimeout(() => {
                                this.textContent = originalText;
                            }, 2000);

                            // Reload page if status changed to show/hide force enable option
                            if (response.data.detection.should_disable !== undefined) {
                                setTimeout(() => {
                                    location.reload();
                                }, 1500);
                            }
                        } else {
                            this.textContent = getcitedAdmin.strings.rescan_failed || 'Scan failed';
                            setTimeout(() => {
                                this.textContent = originalText;
                            }, 2000);
                        }
                    })
                    .catch(() => {
                        this.disabled = false;
                        this.textContent = getcitedAdmin.strings.rescan_failed || 'Scan failed';
                        setTimeout(() => {
                            this.textContent = originalText;
                        }, 2000);
                    });
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

                        // Collapse any previously expanded row (one at a time)
                        document.querySelectorAll('.getcited-expanded-row').forEach(el => el.remove());

                        // Create inline expanded row with recommendations
                        if (data.recommendations && data.recommendations.length) {
                            const expandedRow = document.createElement('tr');
                            expandedRow.className = 'getcited-expanded-row';

                            // Build top 3 recommendations list
                            const top3 = data.recommendations.slice(0, 3).map(r => `<li>${r}</li>`).join('');

                            // Build full factor breakdown HTML
                            let factorsHtml = '<div class="getcited-factors-grid">';
                            for (const [key, factor] of Object.entries(data.factors)) {
                                const rubric = data.rubric[key] || {};
                                const icon = factor.passed ? '✓' : '✗';
                                const iconClass = factor.passed ? 'passed' : 'failed';
                                factorsHtml += `
                                    <div class="factor-item ${iconClass}">
                                        <span class="factor-icon">${icon}</span>
                                        <span class="factor-label">${rubric.label || key}</span>
                                        <span class="factor-score">${factor.score}/${rubric.max_points || '?'}</span>
                                        <span class="factor-message">${factor.message}</span>
                                    </div>
                                `;
                            }
                            factorsHtml += '</div>';

                            expandedRow.innerHTML = `
                                <td colspan="4">
                                    <div class="getcited-inline-results">
                                        <div class="getcited-recommendations-brief">
                                            <strong>${getcitedAdmin.strings?.top_recommendations || 'Top 3 Recommendations'}:</strong>
                                            <ol>${top3}</ol>
                                        </div>
                                        <button type="button" class="button getcited-expand-details">
                                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                                            ${getcitedAdmin.strings?.view_full_analysis || 'View Full Analysis'}
                                        </button>
                                        <div class="getcited-full-details" style="display: none;">
                                            ${factorsHtml}
                                        </div>
                                    </div>
                                </td>
                            `;
                            row.after(expandedRow);

                            // Add toggle handler for full details
                            const toggleBtn = expandedRow.querySelector('.getcited-expand-details');
                            const fullDetails = expandedRow.querySelector('.getcited-full-details');
                            toggleBtn.addEventListener('click', function() {
                                const isHidden = fullDetails.style.display === 'none';
                                fullDetails.style.display = isHidden ? 'block' : 'none';
                                this.querySelector('.dashicons').className = isHidden
                                    ? 'dashicons dashicons-arrow-up-alt2'
                                    : 'dashicons dashicons-arrow-down-alt2';
                                this.childNodes[1].textContent = isHidden
                                    ? (getcitedAdmin.strings?.hide_details || ' Hide Details')
                                    : (getcitedAdmin.strings?.view_full_analysis || ' View Full Analysis');
                            });
                        }
                    }

                    // Update meta box if exists
                    const metaBox = document.querySelector('.getcited-meta-box');
                    if (metaBox) {
                        const scoreDisplay = metaBox.querySelector('.getcited-score-display');
                        if (scoreDisplay) {
                            let metaHtml = `
                                <div class="getcited-score-number">
                                    <span class="score">${data.score}</span>
                                    <span class="max">/100</span>
                                </div>
                            `;

                            // Add top 3 recommendations to meta box
                            if (data.recommendations && data.recommendations.length) {
                                metaHtml += '<div class="getcited-meta-recommendations">';
                                metaHtml += '<strong>Top Recommendations:</strong><ol>';
                                data.recommendations.slice(0, 3).forEach(rec => {
                                    metaHtml += `<li>${rec}</li>`;
                                });
                                metaHtml += '</ol></div>';
                            }

                            scoreDisplay.innerHTML = metaHtml;
                        }
                    }
                }
            })
            .catch(() => {
                button.disabled = false;
                button.textContent = originalText;
            });
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

        // Expand/collapse health details (details now nested inside .getcited-health-item)
        healthSection.querySelectorAll('.getcited-health-expand').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const item = this.closest('.getcited-health-item');
                if (!item) {
                    console.warn('GetCited: Could not find parent .getcited-health-item');
                    return;
                }

                // Details is now nested inside the health item
                const details = item.querySelector('.getcited-health-details');
                if (!details) {
                    console.warn('GetCited: Could not find details element');
                    return;
                }

                // Toggle visibility
                const isHidden = details.style.display === 'none' || !details.style.display;

                if (isHidden) {
                    details.style.display = 'block';
                    this.classList.add('expanded');
                    this.setAttribute('aria-expanded', 'true');
                    item.classList.add('expanded');
                } else {
                    details.style.display = 'none';
                    this.classList.remove('expanded');
                    this.setAttribute('aria-expanded', 'false');
                    item.classList.remove('expanded');
                }

                // Rotate arrow icon
                const icon = this.querySelector('.dashicons');
                if (icon) {
                    icon.classList.toggle('dashicons-arrow-down-alt2', !isHidden);
                    icon.classList.toggle('dashicons-arrow-up-alt2', isHidden);
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
                    this.textContent = isHidden ? getcitedAdmin.strings.hide_rules : getcitedAdmin.strings.show_rules;
                }
            });
        });
    }

    // ==========================================================================
    // Robots.txt Rules Actions
    // ==========================================================================

    function initRobotsRulesActions() {
        // Write robots.txt file button (on crawlers page)
        document.querySelectorAll('.getcited-write-robots-file').forEach(btn => {
            btn.addEventListener('click', function() {
                const statusEl = document.querySelector('.getcited-robots-status');
                const originalHTML = this.innerHTML;

                this.disabled = true;
                this.innerHTML = '<span class="dashicons dashicons-update getcited-spinning"></span> Writing...';

                ajax('getcited_add_robots_rules')
                    .then(response => {
                        if (response.success) {
                            if (statusEl) showStatus(statusEl, response.data.message || 'Rules written!', 'success');
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            this.disabled = false;
                            this.innerHTML = originalHTML;
                            if (statusEl) showStatus(statusEl, response.data?.message || 'Failed to write rules', 'error');
                        }
                    })
                    .catch(() => {
                        this.disabled = false;
                        this.innerHTML = originalHTML;
                        if (statusEl) showStatus(statusEl, 'Failed to write rules', 'error');
                    });
            });
        });

        // Add rules button (on health check page)
        document.querySelectorAll('.getcited-add-robots-rules').forEach(btn => {
            btn.addEventListener('click', function() {
                const statusEl = this.nextElementSibling;
                const originalText = this.innerHTML;

                this.disabled = true;
                this.innerHTML = '<span class="dashicons dashicons-update spin"></span> ' + (getcitedAdmin.strings?.adding || 'Adding...');

                ajax('getcited_add_robots_rules')
                    .then(response => {
                        if (response.success) {
                            showStatus(statusEl, response.data.message, 'success');
                            // Reload after short delay to show updated health check
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            this.disabled = false;
                            this.innerHTML = originalText;
                            showStatus(statusEl, response.data.message || getcitedAdmin.strings.error, 'error');

                            // If manual fallback needed, show the preview
                            if (response.data.show_manual_fallback) {
                                const preview = this.closest('.details-actions').querySelector('.getcited-rules-preview');
                                if (preview) {
                                    preview.style.display = 'block';
                                }
                            }
                        }
                    })
                    .catch(() => {
                        this.disabled = false;
                        this.innerHTML = originalText;
                        showStatus(statusEl, getcitedAdmin.strings.error, 'error');
                    });
            });
        });

        // Remove rules button (if we add one in the future)
        document.querySelectorAll('.getcited-remove-robots-rules').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm(getcitedAdmin.strings.confirm_remove_rules)) {
                    return;
                }

                const statusEl = this.nextElementSibling;
                const originalText = this.innerHTML;

                this.disabled = true;
                this.innerHTML = '<span class="dashicons dashicons-update spin"></span> ' + getcitedAdmin.strings.removing;

                ajax('getcited_remove_robots_rules')
                    .then(response => {
                        if (response.success) {
                            showStatus(statusEl, response.data.message, 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            this.disabled = false;
                            this.innerHTML = originalText;
                            showStatus(statusEl, response.data.message || getcitedAdmin.strings.error, 'error');
                        }
                    })
                    .catch(() => {
                        this.disabled = false;
                        this.innerHTML = originalText;
                        showStatus(statusEl, getcitedAdmin.strings.error, 'error');
                    });
            });
        });

        // Toggle rules preview button
        document.querySelectorAll('.getcited-toggle-rules').forEach(btn => {
            btn.addEventListener('click', function() {
                const preview = this.nextElementSibling;
                if (preview && preview.classList.contains('getcited-rules-preview')) {
                    const isHidden = preview.style.display === 'none';
                    preview.style.display = isHidden ? 'block' : 'none';
                    this.textContent = isHidden ? getcitedAdmin.strings.hide_rules : getcitedAdmin.strings.preview_rules;
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
        var wizard = document.querySelector('.getcited-wizard');
        if (!wizard) {
            return;
        }

        var steps = ['welcome', 'site_type', 'organization', 'crawlers', 'complete'];
        var currentStep = 0;

        // Get all step elements upfront
        var stepElements = {};
        steps.forEach(function(stepName) {
            var el = wizard.querySelector('.getcited-wizard-step[data-step="' + stepName + '"]');
            if (el) {
                stepElements[stepName] = el;
            }
        });

        function showStep(index) {
            // Validate index
            if (index < 0 || index >= steps.length) {
                return;
            }

            var stepName = steps[index];
            var stepEl = stepElements[stepName];

            // Hide all steps first
            Object.keys(stepElements).forEach(function(key) {
                stepElements[key].style.display = 'none';
            });

            // Show current step
            if (stepEl) {
                stepEl.style.display = 'block';
                stepEl.style.visibility = 'visible';
                stepEl.style.opacity = '1';
            }

            // Update progress bar
            wizard.querySelectorAll('.progress-step').forEach(function(step, i) {
                step.classList.remove('active', 'completed');
                if (i < index) {
                    step.classList.add('completed');
                } else if (i === index) {
                    step.classList.add('active');
                }
            });

            currentStep = index;
        }

        // Next buttons - handle click on each button
        wizard.querySelectorAll('.getcited-wizard-next').forEach(function(nextBtn) {
            nextBtn.addEventListener('click', function() {
                var stepName = steps[currentStep];
                var clickedBtn = this;

                // Disable button to prevent double-clicks
                clickedBtn.disabled = true;

                // For site_type step, run scan with progress UI
                if (stepName === 'site_type') {
                    saveWizardStepWithScan(stepName, wizard, function() {
                        clickedBtn.disabled = false;
                        if (currentStep < steps.length - 1) {
                            showStep(currentStep + 1);
                        }
                    });
                    return;
                }

                // For other steps, just save and continue
                saveWizardStep(stepName).then(function() {
                    clickedBtn.disabled = false;
                    if (currentStep < steps.length - 1) {
                        showStep(currentStep + 1);
                    }
                }).catch(function() {
                    clickedBtn.disabled = false;
                    // Continue anyway to prevent getting stuck
                    if (currentStep < steps.length - 1) {
                        showStep(currentStep + 1);
                    }
                });
            });
        });

        // Back buttons
        wizard.querySelectorAll('.getcited-wizard-back').forEach(function(backBtn) {
            backBtn.addEventListener('click', function() {
                if (currentStep > 0) {
                    showStep(currentStep - 1);
                }
            });
        });

        // Skip wizard button
        var skipBtn = wizard.querySelector('.getcited-wizard-skip');
        if (skipBtn) {
            skipBtn.addEventListener('click', function(e) {
                e.preventDefault();
                ajax('getcited_wizard_skip').then(function() {
                    window.location.href = window.location.href.replace('&wizard=1', '');
                });
            });
        }

        // Complete button
        var completeBtn = wizard.querySelector('.getcited-wizard-complete');
        if (completeBtn) {
            completeBtn.addEventListener('click', function() {
                ajax('getcited_wizard_complete').then(function(response) {
                    if (response.success && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    }
                });
            });
        }

        // Site type selection - visual update
        wizard.querySelectorAll('.getcited-site-type input').forEach(function(input) {
            input.addEventListener('change', function() {
                wizard.querySelectorAll('.site-type-card').forEach(function(card) {
                    card.classList.remove('selected');
                });
                if (this.checked) {
                    this.closest('.getcited-site-type').querySelector('.site-type-card').classList.add('selected');
                }
            });
        });

        // Crawler choice selection - visual update
        wizard.querySelectorAll('.getcited-radio-card input').forEach(function(input) {
            input.addEventListener('change', function() {
                wizard.querySelectorAll('.getcited-radio-card').forEach(function(card) {
                    card.classList.remove('selected');
                });
                this.closest('.getcited-radio-card').classList.add('selected');
            });
        });

        // Initialize first step - show it immediately
        showStep(0);
    }

    /**
     * Save wizard step data (simple version for most steps)
     */
    function saveWizardStep(stepName) {
        var data = {};

        switch (stepName) {
            case 'organization':
                var nameEl = document.getElementById('wizard_org_name');
                var logoEl = document.getElementById('wizard_org_logo');
                data.name = nameEl ? nameEl.value : '';
                data.logo_url = logoEl ? logoEl.value : '';
                break;

            case 'crawlers':
                var choice = document.querySelector('input[name="crawler_choice"]:checked');
                data.allow_all = (choice && choice.value === 'allow_all') ? 'true' : 'false';
                break;

            default:
                // welcome step - no data to save
                return Promise.resolve();
        }

        return ajax('getcited_wizard_save', { step: stepName, data: data });
    }

    /**
     * Save site_type step and run scan with progress UI
     */
    function saveWizardStepWithScan(stepName, wizard, onComplete) {
        var siteTypeInput = document.querySelector('input[name="site_type"]:checked');
        var data = {
            site_type: siteTypeInput ? siteTypeInput.value : 'blog'
        };

        // Get UI elements
        var progressContainer = wizard.querySelector('.getcited-scan-progress');
        var progressFill = wizard.querySelector('.scan-progress-fill');
        var statusText = wizard.querySelector('.scan-status-text');
        var skipLink = wizard.querySelector('.getcited-skip-scan');
        var stepEl = wizard.querySelector('[data-step="site_type"]');
        var nextBtn = stepEl ? stepEl.querySelector('.getcited-wizard-next') : null;
        var backBtn = stepEl ? stepEl.querySelector('.getcited-wizard-back') : null;

        // First save the step
        ajax('getcited_wizard_save', { step: stepName, data: data })
            .then(function() {
                // Show progress UI
                if (progressContainer) progressContainer.style.display = 'block';
                if (skipLink) skipLink.style.display = 'inline';
                if (nextBtn) nextBtn.disabled = true;
                if (backBtn) backBtn.disabled = true;

                // Status text rotation
                var messages = ['Finding your pages...', 'Analyzing content...', 'Building your llms.txt...'];
                var msgIndex = 0;
                var statusInterval = setInterval(function() {
                    msgIndex = (msgIndex + 1) % messages.length;
                    if (statusText) statusText.textContent = messages[msgIndex];
                }, 2000);

                // Progress bar animation
                var progress = 0;
                var progressInterval = setInterval(function() {
                    progress += 2;
                    if (progress > 90) progress = 90;
                    if (progressFill) progressFill.style.width = progress + '%';
                }, 200);

                // Timeout after 30 seconds
                var timeoutId = setTimeout(function() {
                    if (statusText) statusText.textContent = 'This is taking longer than expected...';
                    if (skipLink) skipLink.classList.add('prominent');
                }, 30000);

                // Cleanup function
                function cleanup() {
                    clearInterval(statusInterval);
                    clearInterval(progressInterval);
                    clearTimeout(timeoutId);
                    if (progressContainer) progressContainer.style.display = 'none';
                    if (skipLink) skipLink.style.display = 'none';
                    if (nextBtn) nextBtn.disabled = false;
                    if (backBtn) backBtn.disabled = false;
                }

                // Skip scan handler
                function handleSkip(e) {
                    e.preventDefault();
                    cleanup();
                    if (skipLink) skipLink.removeEventListener('click', handleSkip);
                    onComplete();
                }
                if (skipLink) skipLink.addEventListener('click', handleSkip);

                // Run the scan
                ajax('getcited_wizard_scan')
                    .then(function(response) {
                        cleanup();
                        if (progressFill) progressFill.style.width = '100%';
                        if (skipLink) skipLink.removeEventListener('click', handleSkip);

                        // Store scan data and populate Step 5
                        if (response.success && response.data) {
                            populateWizardStep5(wizard, response.data);
                        }

                        onComplete();
                    })
                    .catch(function() {
                        cleanup();
                        if (skipLink) skipLink.removeEventListener('click', handleSkip);
                        onComplete(); // Continue even on error
                    });
            })
            .catch(function() {
                // If save fails, still continue
                onComplete();
            });
    }

    /**
     * Populate wizard Step 5 with scan results
     */
    function populateWizardStep5(wizard, scanData) {
        var step5 = wizard.querySelector('[data-step="complete"]');
        if (!step5) return;

        var llmsTxt = scanData.llms_txt || '';
        var data = scanData.scan_data || {};

        // Find or create the preview container
        var previewContainer = step5.querySelector('.getcited-wizard-preview');
        var summaryContainer = step5.querySelector('.getcited-setup-summary');
        var statsContainer = step5.querySelector('.getcited-scan-stats');
        var subtitle = step5.querySelector('.wizard-subtitle');

        // If we have llms.txt content, show the preview version
        if (llmsTxt) {
            // Update subtitle
            if (subtitle) {
                subtitle.textContent = "We scanned your site and created your llms.txt. Here's what AI systems will see:";
            }

            // Hide the generic summary if it exists
            if (summaryContainer) {
                summaryContainer.style.display = 'none';
            }

            // Create or update preview
            if (!previewContainer) {
                previewContainer = document.createElement('div');
                previewContainer.className = 'getcited-wizard-preview';
                previewContainer.innerHTML = '<div class="preview-header"><span class="dashicons dashicons-media-text"></span><span>Your llms.txt</span></div><pre class="preview-content"></pre>';

                var wizardContent = step5.querySelector('.wizard-content');
                if (wizardContent && subtitle) {
                    subtitle.insertAdjacentElement('afterend', previewContainer);
                }
            }
            previewContainer.style.display = 'block';

            var preContent = previewContainer.querySelector('.preview-content');
            if (preContent) {
                preContent.textContent = llmsTxt;
            }

            // Create or update stats
            if (!statsContainer) {
                statsContainer = document.createElement('div');
                statsContainer.className = 'getcited-scan-stats';
                previewContainer.insertAdjacentElement('afterend', statsContainer);
            }
            statsContainer.style.display = 'flex';

            var pages = (data.pages || []).length;
            var categories = (data.categories || []).length;
            var posts = (data.posts || []).length;
            var menu = (data.menu || []).length;
            var social = Object.keys(data.social || {}).length;

            statsContainer.innerHTML =
                '<div class="stat-item"><span class="stat-number">' + pages + '</span><span class="stat-label">Pages</span></div>' +
                '<div class="stat-item"><span class="stat-number">' + categories + '</span><span class="stat-label">Categories</span></div>' +
                '<div class="stat-item"><span class="stat-number">' + posts + '</span><span class="stat-label">Posts</span></div>' +
                '<div class="stat-item"><span class="stat-number">' + menu + '</span><span class="stat-label">Menu Items</span></div>' +
                '<div class="stat-item"><span class="stat-number">' + social + '</span><span class="stat-label">Social Links</span></div>';

            // Add description if not present
            var description = step5.querySelector('.wizard-content > p.description');
            if (!description) {
                description = document.createElement('p');
                description.className = 'description';
                description.style.textAlign = 'center';
                description.textContent = 'You can edit this anytime from the llms.txt Editor page.';
                statsContainer.insertAdjacentElement('afterend', description);
            }

            // Show the secondary action link
            var secondaryAction = step5.querySelector('.wizard-secondary-action');
            if (!secondaryAction) {
                var wizardActions = step5.querySelector('.wizard-actions');
                if (wizardActions) {
                    secondaryAction = document.createElement('p');
                    secondaryAction.className = 'wizard-secondary-action';
                    secondaryAction.innerHTML = '<a href="' + (getcitedAdmin.adminUrl || '/wp-admin/') + 'admin.php?page=getcited-llms-txt">or edit your llms.txt first</a>';
                    wizardActions.appendChild(secondaryAction);
                }
            }
            if (secondaryAction) {
                secondaryAction.style.display = 'block';
            }
        }
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

    // ==========================================================================
    // Collapsible Sections
    // ==========================================================================

    function initCollapsibleSections() {
        document.querySelectorAll('.getcited-collapsible-header').forEach(header => {
            header.addEventListener('click', function() {
                const section = this.closest('.getcited-collapsible');
                const content = section.querySelector('.getcited-collapsible-content');
                const isCollapsed = section.dataset.collapsed === 'true';

                if (isCollapsed) {
                    content.style.display = 'block';
                    section.dataset.collapsed = 'false';
                } else {
                    content.style.display = 'none';
                    section.dataset.collapsed = 'true';
                }
            });
        });
    }

    // ==========================================================================
    // Source Toggle (llms.txt)
    // ==========================================================================

    function initSourceToggle() {
        const sourceRadios = document.querySelectorAll('input[name="llms_txt_source"]');
        if (!sourceRadios.length) return;

        sourceRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                // Save preference immediately
                ajax('getcited_save_settings', {
                    section: 'llms_txt',
                    data: {
                        llms_txt_source: this.value
                    }
                }).then(response => {
                    if (response.success) {
                        // Show brief feedback
                        const label = this.closest('.getcited-radio-option');
                        if (label) {
                            const feedback = document.createElement('span');
                            feedback.className = 'getcited-inline-saved';
                            feedback.textContent = '✓';
                            feedback.style.cssText = 'color: #10b981; margin-left: 8px;';
                            label.appendChild(feedback);
                            setTimeout(() => feedback.remove(), 2000);
                        }
                    }
                });
            });
        });
    }

    // ==========================================================================
    // Page Teaser Waitlist Forms
    // ==========================================================================

    function initCompactWaitlistButtons() {
        // Handle inline teaser forms
        document.querySelectorAll('.getcited-teaser-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const emailInput = this.querySelector('input[type="email"]');
                const submitBtn = this.querySelector('button[type="submit"]');
                const email = emailInput.value.trim();

                if (!email) return;

                // Disable form while submitting
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                emailInput.disabled = true;
                submitBtn.textContent = 'Joining...';

                ajax('getcited_waitlist_signup', { email: email })
                    .then(response => {
                        if (response.success) {
                            // Replace form with confirmation
                            this.outerHTML = '<span class="teaser-joined"><span class="dashicons dashicons-yes-alt"></span> On the list!</span>';
                        } else {
                            submitBtn.disabled = false;
                            emailInput.disabled = false;
                            submitBtn.textContent = originalText;
                            // Show error inline
                            const errorMsg = response.data?.message || 'Failed to join waitlist.';
                            emailInput.setCustomValidity(errorMsg);
                            emailInput.reportValidity();
                            setTimeout(() => emailInput.setCustomValidity(''), 3000);
                        }
                    })
                    .catch(() => {
                        submitBtn.disabled = false;
                        emailInput.disabled = false;
                        submitBtn.textContent = originalText;
                        emailInput.setCustomValidity('Failed to join. Please try again.');
                        emailInput.reportValidity();
                        setTimeout(() => emailInput.setCustomValidity(''), 3000);
                    });
            });
        });
    }

    // ==========================================================================
    // Media Upload (Logo Picker)
    // ==========================================================================

    function initMediaUpload() {
        document.querySelectorAll('.getcited-upload-logo').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();

                // Find the associated input field
                const wrapper = this.closest('.input-with-button');
                const input = wrapper?.querySelector('input[type="url"]')
                           || document.getElementById('org_logo')
                           || document.getElementById('wizard_org_logo');

                if (!input) return;

                // Check if wp.media is available
                if (typeof wp === 'undefined' || typeof wp.media !== 'function') {
                    console.warn('WordPress media library not available');
                    return;
                }

                const frame = wp.media({
                    title: getcitedAdmin.strings?.select_logo || 'Select Logo',
                    button: { text: getcitedAdmin.strings?.use_logo || 'Use as Logo' },
                    multiple: false,
                    library: { type: 'image' }
                });

                frame.on('select', function() {
                    const attachment = frame.state().get('selection').first().toJSON();
                    input.value = attachment.url;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });

                frame.open();
            });
        });
    }

    // ==========================================================================
    // Load More Posts (Citability)
    // ==========================================================================

    function initLoadMorePosts() {
        const loadMoreBtn = document.querySelector('.getcited-load-more-posts');
        if (!loadMoreBtn) return;

        loadMoreBtn.addEventListener('click', function() {
            const offset = parseInt(this.dataset.offset, 10) || 5;
            const originalText = this.textContent;

            this.disabled = true;
            this.textContent = getcitedAdmin.strings?.loading || 'Loading...';

            ajax('getcited_load_more_posts', { offset })
                .then(response => {
                    if (response.success && response.data.html) {
                        // Append new rows to the table
                        const tbody = document.querySelector('.getcited-posts-table tbody');
                        if (tbody) {
                            tbody.insertAdjacentHTML('beforeend', response.data.html);
                            // Re-attach analyze handlers to new buttons
                            attachAnalyzeHandlers();
                        }

                        // Update offset or hide button if no more posts
                        if (response.data.has_more) {
                            this.dataset.offset = offset + 5;
                            this.disabled = false;
                            this.textContent = originalText;
                        } else {
                            this.textContent = getcitedAdmin.strings?.no_more_posts || 'No more posts';
                            this.disabled = true;
                        }
                    } else {
                        this.disabled = false;
                        this.textContent = originalText;
                    }
                })
                .catch(() => {
                    this.disabled = false;
                    this.textContent = originalText;
                });
        });
    }

    /**
     * Attach analyze handlers to buttons (used after loading more posts)
     */
    function attachAnalyzeHandlers() {
        document.querySelectorAll('.getcited-analyze-post').forEach(btn => {
            if (btn.dataset.handlerAttached) return;
            btn.dataset.handlerAttached = 'true';

            btn.addEventListener('click', function() {
                const postId = this.dataset.postId;
                analyzePost(postId, this);
            });
        });
    }

})();
