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
        initVisibilityScore();
        initTips();
        initHealthCheck();
        initRobotsRulesActions();
        initCitationNudge();
        initSeoConflictNotice();
        initWizard();
        initSettingsPage();
        initExportImport();
        initCopyButtons();
        initCollapsibleSections();
        initMediaUpload();
        initLoadMorePosts();
        initCustomCheckboxes();
        initDirtyTracking();
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
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message || `HTTP ${response.status}`);
                    }).catch(() => {
                        throw new Error(`HTTP ${response.status}`);
                    });
                }
                return response.json();
            });
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

    /**
     * Decode HTML entities (v1.5.5)
     * Converts &gt; to >, &lt; to <, etc.
     */
    function decodeHtmlEntities(text) {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value;
    }

    // ==========================================================================
    // Unsaved Changes Warning
    // ==========================================================================

    let isDirty = false;

    /**
     * Initialize dirty state tracking for pages with save buttons
     */
    function initDirtyTracking() {
        const wrap = document.querySelector('.getcited-wrap');
        const saveBtn = document.querySelector('.getcited-actions .button-primary');

        if (!wrap || !saveBtn) return;

        // Track changes on all form inputs
        wrap.addEventListener('input', markDirty);
        wrap.addEventListener('change', markDirty);

        // Warn on page leave if dirty
        window.addEventListener('beforeunload', function(e) {
            if (isDirty) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });
    }

    /**
     * Mark form as dirty (has unsaved changes)
     */
    function markDirty() {
        isDirty = true;
    }

    /**
     * Mark form as clean (changes saved)
     */
    function markClean() {
        isDirty = false;
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

                // Update UI immediately (including ARIA state)
                statusLabel.textContent = this.checked ? 'Allowed' : 'Blocked';
                statusLabel.className = 'status-label ' + (this.checked ? 'allowed' : 'blocked');
                this.setAttribute('aria-checked', this.checked ? 'true' : 'false');

                // Save via API
                api('crawlers/' + encodeURIComponent(crawler), 'POST', { status })
                    .then(response => {
                        if (!response.success) {
                            // Revert on error
                            this.checked = !this.checked;
                            statusLabel.textContent = this.checked ? 'Allowed' : 'Blocked';
                            statusLabel.className = 'status-label ' + (this.checked ? 'allowed' : 'blocked');
                            this.setAttribute('aria-checked', this.checked ? 'true' : 'false');
                        }
                        updateRobotsPreview();
                    })
                    .catch(() => {
                        // Revert on error
                        this.checked = !this.checked;
                        this.setAttribute('aria-checked', this.checked ? 'true' : 'false');
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
            blockAllBtn.addEventListener('click', () => {
                if (confirm('Warning: Blocking all AI crawlers will prevent your content from being discovered and cited by AI systems like ChatGPT, Claude, and Perplexity.\n\nThis is not recommended for most sites. Are you sure you want to block all crawlers?')) {
                    setCrawlersBulk('block');
                }
            });
        }
    }

    function setCrawlersBulk(status) {
        const toggles = document.querySelectorAll('.getcited-crawler-item .getcited-toggle input');
        const crawlers = {};

        toggles.forEach(toggle => {
            const checked = status === 'allow';
            toggle.checked = checked;
            crawlers[toggle.dataset.crawler] = status;

            // Update status label and ARIA state
            const item = toggle.closest('.getcited-crawler-item');
            const statusLabel = item.querySelector('.status-label');
            statusLabel.textContent = checked ? 'Allowed' : 'Blocked';
            statusLabel.className = 'status-label ' + (checked ? 'allowed' : 'blocked');
            toggle.setAttribute('aria-checked', checked ? 'true' : 'false');
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
            markClean();
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
                    <input type="text" name="custom_crawlers[${index}][user_agent]" placeholder="User-agent string" aria-label="User-agent string">
                    <input type="text" name="custom_crawlers[${index}][name]" placeholder="Name (optional)" aria-label="Crawler name">
                    <select name="custom_crawlers[${index}][action]" aria-label="Crawler action">
                        <option value="allow">Allow</option>
                        <option value="block">Block</option>
                    </select>
                    <button type="button" class="button getcited-remove-custom" aria-label="Remove this crawler">×</button>
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
                        markClean();
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
        const templateBtns = document.querySelectorAll('.getcited-load-template');

        if (!textarea) return;

        // Live preview
        if (preview) {
            textarea.addEventListener('input', debounce(() => {
                // Decode HTML entities for proper display (v1.5.5)
                const decoded = decodeHtmlEntities(textarea.value);
                preview.textContent = decoded;
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
                    llms_founder_name: document.getElementById('llms_founder_name')?.value || '',
                    llms_founder_title: document.getElementById('llms_founder_title')?.value || '',
                    llms_site_expertise: document.getElementById('llms_site_expertise')?.value || '',
                    llms_update_frequency: document.getElementById('llms_update_frequency')?.value || '',
                    llms_citation_format: document.getElementById('llms_citation_format')?.value || '',
                    llms_use_cases: document.getElementById('getcited-use-cases')?.value || '',
                    citation_guidelines: {
                        enabled: document.getElementById('getcited-citation-enabled')?.checked || false,
                        citation_format: document.getElementById('getcited-citation-format')?.value || '',
                        accuracy_notes: document.getElementById('getcited-accuracy-notes')?.value || '',
                        restrictions: document.getElementById('getcited-restrictions')?.value || '',
                        freshness_note: document.getElementById('getcited-freshness-note')?.value || '',
                        contact_email: document.getElementById('getcited-contact-email')?.value || ''
                    }
                };

                ajax('getcited_save_settings', {
                    section: 'llms_txt',
                    data: data
                }).then(response => {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Changes';

                    if (response.success) {
                        showStatus(statusEl, getcitedAdmin.strings.saved, 'success');
                        markClean();

                        // Update textarea and preview with regenerated content (v1.9.9.14).
                        if (response.data && response.data.llms_txt_content) {
                            textarea.value = response.data.llms_txt_content;
                            // Update preview if it exists
                            const previewEl = document.getElementById('llms_txt_preview');
                            if (previewEl) {
                                previewEl.textContent = response.data.llms_txt_content;
                            }
                        }
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

                            // Update live preview if exists (decode HTML entities v1.5.5)
                            if (preview) {
                                preview.textContent = decodeHtmlEntities(response.data.content);
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

                            // Update live preview if exists (decode HTML entities v1.5.5)
                            if (preview) {
                                preview.textContent = decodeHtmlEntities(response.data.llms_txt);
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

        // Toggle schema types and warning visibility when enable checkbox changes
        const enableCheckbox = document.getElementById('schema_enabled');
        const typesWrapper = document.querySelector('.getcited-schema-types-wrapper');
        const conflictWarning = document.querySelector('.getcited-schema-conflict-warning');

        if (enableCheckbox && typesWrapper) {
            enableCheckbox.addEventListener('change', () => {
                const isChecked = enableCheckbox.checked;
                typesWrapper.style.display = isChecked ? '' : 'none';
                if (conflictWarning) {
                    conflictWarning.style.display = isChecked ? '' : 'none';
                }
            });
        }

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

            const sameasUrls = [];
            document.querySelectorAll('input[name="organization[sameas_urls][]"]').forEach(input => {
                if (input.value.trim()) {
                    sameasUrls.push(input.value.trim());
                }
            });

            const schemaEnabled = document.getElementById('schema_enabled')?.checked ?? true;
            const data = {
                schema_enabled: schemaEnabled,
                schema_force_enabled: schemaEnabled, // Auto-set force when enabling (handles conflict override)
                schema_types: schemaTypes,
                organization: {
                    name: document.getElementById('org_name')?.value || '',
                    logo_url: document.getElementById('org_logo')?.value || '',
                    social_urls: socialUrls,
                    sameas_urls: sameasUrls,
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
                    markClean();
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

        // Add sameAs URL field
        const addSameasBtn = document.querySelector('.getcited-add-sameas');
        if (addSameasBtn) {
            addSameasBtn.addEventListener('click', () => {
                const container = document.querySelector('.getcited-sameas-urls');
                const input = document.createElement('input');
                input.type = 'url';
                input.name = 'organization[sameas_urls][]';
                input.className = 'regular-text';
                input.placeholder = 'https://yelp.com/biz/... or https://bloomberg.com/profile/...';
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
        // Use event delegation on the posts table for better reliability
        const postsTable = document.querySelector('.getcited-posts-table');
        if (postsTable) {
            postsTable.addEventListener('click', function(e) {
                // Handle analyze button clicks
                const analyzeBtn = e.target.closest('.getcited-analyze-post');
                if (analyzeBtn) {
                    e.preventDefault();
                    analyzePost(analyzeBtn.dataset.postId, analyzeBtn);
                    return;
                }

                // Handle view-analysis link clicks (post title)
                const viewLink = e.target.closest('.getcited-view-analysis');
                if (viewLink) {
                    e.preventDefault();
                    e.stopPropagation();
                    const postId = viewLink.dataset.postId;
                    const row = postsTable.querySelector(`tr[data-post-id="${postId}"]`);
                    if (row) {
                        const btn = row.querySelector('.getcited-analyze-post');
                        if (btn) {
                            analyzePost(postId, btn);
                        }
                    }
                    return;
                }
            });
        }

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

                            // Build full factor breakdown HTML (no point values to avoid score gaming)
                            let factorsHtml = '<div class="getcited-factors-grid">';
                            for (const [key, factor] of Object.entries(data.factors)) {
                                const rubric = data.rubric[key] || {};
                                const icon = factor.passed ? '✓' : '✗';
                                const iconClass = factor.passed ? 'passed' : 'failed';
                                factorsHtml += `
                                    <div class="factor-item ${iconClass}">
                                        <span class="factor-icon">${icon}</span>
                                        <div class="factor-content">
                                            <span class="factor-label">${rubric.label || key}</span>
                                            <span class="factor-message">${factor.message}</span>
                                        </div>
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
                                this.textContent = isHidden
                                    ? (getcitedAdmin.strings?.hide_details || 'Hide Details')
                                    : (getcitedAdmin.strings?.view_full_analysis || 'View Full Analysis');
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
    // AI Visibility Score
    // ==========================================================================

    function initVisibilityScore() {
        const refreshBtn = document.querySelector('.getcited-refresh-score');
        if (!refreshBtn) return;

        refreshBtn.addEventListener('click', function() {
            const originalHTML = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="dashicons dashicons-update spinning"></span> ' + (getcitedAdmin.strings?.refreshing || 'Refreshing...');

            ajax('getcited_refresh_visibility_score', {})
                .then(response => {
                    if (response.success && response.data.score) {
                        // Update the score display
                        const score = response.data.score;
                        const scoreNumber = document.querySelector('.score-number');
                        const tierLabel = document.querySelector('.tier-label');
                        const scoreProgress = document.querySelector('.score-progress');

                        if (scoreNumber) {
                            scoreNumber.textContent = score.total;
                        }

                        if (tierLabel) {
                            tierLabel.textContent = score.tier.label;
                            tierLabel.className = 'tier-label tier-' + score.tier.class;
                        }

                        if (scoreProgress) {
                            const dasharray = (score.total / 100) * 283;
                            scoreProgress.setAttribute('stroke-dasharray', dasharray + ' 283');
                            scoreProgress.style.stroke = score.tier.color;
                        }

                        // Update breakdown cards
                        if (score.breakdown) {
                            const maxPoints = { crawler_access: 25, llms_health: 25, schema: 20, citability: 20, freshness: 10 };
                            Object.keys(score.breakdown).forEach(key => {
                                // Update breakdown cards (new layout)
                                const card = document.querySelector(`.getcited-breakdown-card[data-key="${key}"]`);
                                if (card) {
                                    const cardScore = card.querySelector('.card-score');
                                    const cardStatus = card.querySelector('.card-status, .card-action, .card-meta');
                                    const newScore = score.breakdown[key];
                                    const max = maxPoints[key];
                                    const isPerfect = (newScore === max);

                                    if (cardScore) {
                                        cardScore.innerHTML = newScore + '<span class="score-max">/' + max + '</span>';
                                    }

                                    // Update status indicator
                                    if (cardStatus && key !== 'freshness') {
                                        if (isPerfect) {
                                            cardStatus.className = 'card-status status-complete';
                                            cardStatus.innerHTML = '<span class="dashicons dashicons-yes-alt"></span> Complete';
                                        } else {
                                            cardStatus.className = 'card-action';
                                            cardStatus.textContent = 'Improve →';
                                        }
                                    }
                                }

                                // Update old breakdown bars (if present)
                                const item = document.querySelector(`.breakdown-item[data-key="${key}"]`);
                                if (item) {
                                    const fill = item.querySelector('.breakdown-fill');
                                    const scoreLabel = item.querySelector('.label-score');
                                    const percent = (score.breakdown[key] / maxPoints[key]) * 100;

                                    if (fill) fill.style.width = percent + '%';
                                    if (scoreLabel) scoreLabel.textContent = score.breakdown[key] + '/' + maxPoints[key];
                                }
                            });
                        }

                        // Update recommendation if present
                        if (score.recommendations && score.recommendations.length > 0) {
                            // Check for new layout (inline recommendation)
                            const inlineRec = document.querySelector('.score-recommendation');
                            if (inlineRec) {
                                inlineRec.textContent = score.recommendations[0];
                            }
                            // Check for old layout
                            const recText = document.querySelector('.visibility-score-recommendations p');
                            if (recText) {
                                recText.textContent = score.recommendations[0];
                            }
                        }
                    }

                    // Show success feedback briefly
                    this.innerHTML = '<span class="dashicons dashicons-yes-alt"></span> ' + (getcitedAdmin.strings?.score_updated || 'Score updated!');
                    this.classList.add('getcited-success-feedback');

                    setTimeout(() => {
                        this.disabled = false;
                        this.innerHTML = originalHTML;
                        this.classList.remove('getcited-success-feedback');
                    }, 2000);
                })
                .catch(() => {
                    // Show error feedback briefly
                    this.innerHTML = '<span class="dashicons dashicons-warning"></span> ' + (getcitedAdmin.strings?.refresh_failed || 'Refresh failed');
                    this.classList.add('getcited-error-feedback');

                    setTimeout(() => {
                        this.disabled = false;
                        this.innerHTML = originalHTML;
                        this.classList.remove('getcited-error-feedback');
                    }, 2000);
                });
        });
    }

    // ==========================================================================
    // AI Visibility Tips (v1.6.14)
    // ==========================================================================

    function initTips() {
        const nextTipLink = document.querySelector('.getcited-next-tip');
        if (!nextTipLink) return;

        nextTipLink.addEventListener('click', function(e) {
            e.preventDefault();

            const tipText = document.querySelector('.getcited-tip-bar .tip-text');
            if (!tipText) return;

            // Add loading state
            this.style.pointerEvents = 'none';
            this.style.opacity = '0.5';

            ajax('getcited_next_tip', {})
                .then(response => {
                    if (response.success && response.data.tip) {
                        // Fade out
                        tipText.style.opacity = '0';

                        setTimeout(() => {
                            // Update content with inline format
                            tipText.innerHTML = '<strong>' + response.data.tip.title + ':</strong> ' + response.data.tip.content;

                            // Fade in
                            tipText.style.opacity = '1';
                        }, 150);
                    }

                    // Reset link state
                    this.style.pointerEvents = '';
                    this.style.opacity = '';
                })
                .catch(() => {
                    // Reset link state on error
                    this.style.pointerEvents = '';
                    this.style.opacity = '';
                });
        });

        // Add transition styles
        const tipText = document.querySelector('.getcited-tip-bar .tip-text');
        if (tipText) tipText.style.transition = 'opacity 0.15s ease';
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
        // Auto-write checkbox - when unchecked, remove rules from robots.txt
        const autoWriteCheckbox = document.getElementById('robots_write_physical');
        if (autoWriteCheckbox) {
            autoWriteCheckbox.addEventListener('change', function() {
                if (!this.checked) {
                    // User is unchecking - confirm and remove rules
                    if (!confirm('This will remove GetCited rules from robots.txt. Continue?')) {
                        // User cancelled - re-check the box
                        this.checked = true;
                        // Update custom checkbox visual state
                        const label = this.closest('.getcited-checkbox-custom');
                        if (label) label.classList.add('is-checked');
                        return;
                    }

                    // User confirmed - remove rules
                    const statusEl = document.querySelector('.getcited-robots-status');

                    ajax('getcited_remove_robots_rules')
                        .then(response => {
                            if (response.success) {
                                if (statusEl) showStatus(statusEl, response.data.message || 'Rules removed', 'success');
                                setTimeout(() => window.location.reload(), 1500);
                            } else {
                                // Failed - re-check the box
                                this.checked = true;
                                const label = this.closest('.getcited-checkbox-custom');
                                if (label) label.classList.add('is-checked');
                                if (statusEl) showStatus(statusEl, response.data?.message || 'Failed to remove rules', 'error');
                            }
                        })
                        .catch(() => {
                            // Failed - re-check the box
                            this.checked = true;
                            const label = this.closest('.getcited-checkbox-custom');
                            if (label) label.classList.add('is-checked');
                            if (statusEl) showStatus(statusEl, 'Failed to remove rules', 'error');
                        });
                } else {
                    // User is checking - update visual state (rules written on save)
                    const label = this.closest('.getcited-checkbox-custom');
                    if (label) label.classList.add('is-checked');
                }
            });
        }

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
    // Admin Notice Handlers
    // ==========================================================================

    function initCitationNudge() {
        var notice = document.querySelector('.getcited-citation-nudge');
        if (!notice) return;

        notice.addEventListener('click', function(e) {
            if (e.target.classList.contains('notice-dismiss')) {
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=getcited_dismiss_citation_nudge&nonce=' + notice.dataset.nonce
                });
            }
        });
    }

    function initSeoConflictNotice() {
        var notice = document.querySelector('.getcited-seo-conflict-notice');
        if (!notice) return;

        var nonce = notice.dataset.nonce;
        var removeBtn = notice.querySelector('.getcited-remove-conflict');
        var dismissBtn = notice.querySelector('.getcited-dismiss-notice');

        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                var originalText = this.textContent;
                this.disabled = true;
                this.textContent = notice.dataset.removing || 'Removing...';
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=getcited_delete_conflicting_llms&nonce=' + nonce
                }).then(function(response) {
                    return response.json();
                }).then(function(data) {
                    if (data.success) {
                        notice.innerHTML = '<p><strong>' + (notice.dataset.success || 'Done! GetCited is now serving your AI Visibility Data.') + '</strong></p>';
                        notice.classList.remove('notice-warning');
                        notice.classList.add('notice-success');
                    } else {
                        removeBtn.disabled = false;
                        removeBtn.textContent = notice.dataset.removeText || originalText;
                        alert(data.data ? data.data.message : 'Failed to remove file');
                    }
                });
            });
        }

        if (dismissBtn) {
            dismissBtn.addEventListener('click', function() {
                notice.remove();
            });
        }
    }

    // ==========================================================================
    // Setup Wizard
    // ==========================================================================

    function initWizard() {
        var wizard = document.querySelector('.getcited-wizard');
        if (!wizard) {
            return;
        }

        var steps = ['site_type', 'done'];
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
                stepEl.style.display = 'flex';
                stepEl.style.visibility = 'visible';
                stepEl.style.opacity = '1';
            }

            // Update progress bar
            var progressSteps = wizard.querySelectorAll('.progress-step');
            progressSteps.forEach(function(step, i) {
                step.classList.remove('active', 'completed');
                if (i < index) {
                    step.classList.add('completed');
                } else if (i === index) {
                    step.classList.add('active');
                }
            });

            var progressBar = wizard.querySelector('.getcited-wizard-progress');
            if (progressBar && progressSteps.length > 1) {
                var progressPercent = (index / (progressSteps.length - 1)) * 100;
                progressBar.style.setProperty('--progress-width', progressPercent + '%');
            }

            currentStep = index;
        }

        // Next button — saves site type, runs scan, shows done step
        wizard.querySelectorAll('.getcited-wizard-next').forEach(function(nextBtn) {
            nextBtn.addEventListener('click', function() {
                var clickedBtn = this;
                clickedBtn.disabled = true;

                var siteTypeInput = document.querySelector('input[name="site_type"]:checked');
                var data = {
                    site_type: siteTypeInput ? siteTypeInput.value : 'blog'
                };

                // Show loading state
                var originalText = clickedBtn.textContent;
                clickedBtn.textContent = 'Setting up...';

                ajax('getcited_wizard_save', { step: 'site_type', data: data })
                    .then(function(response) {
                        clickedBtn.disabled = false;
                        clickedBtn.textContent = originalText;

                        // Populate done step with results
                        if (response.success && response.data) {
                            populateDoneStep(wizard, response.data);
                        }

                        showStep(1);
                    })
                    .catch(function() {
                        clickedBtn.disabled = false;
                        clickedBtn.textContent = originalText;
                        // Continue anyway
                        showStep(1);
                    });
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

        // Site type selection — visual update and validation
        var siteTypeStep = stepElements['site_type'];
        var siteTypeNextBtn = siteTypeStep ? siteTypeStep.querySelector('.getcited-wizard-next') : null;

        if (siteTypeNextBtn) {
            var hasSelection = wizard.querySelector('.getcited-site-type input:checked');
            if (!hasSelection) {
                siteTypeNextBtn.disabled = true;
                siteTypeNextBtn.style.opacity = '0.5';
                siteTypeNextBtn.style.cursor = 'not-allowed';
            }
        }

        wizard.querySelectorAll('.getcited-site-type input').forEach(function(input) {
            input.addEventListener('change', function() {
                wizard.querySelectorAll('.site-type-card').forEach(function(card) {
                    card.classList.remove('selected');
                });
                if (this.checked) {
                    this.closest('.getcited-site-type').querySelector('.site-type-card').classList.add('selected');
                    if (siteTypeNextBtn) {
                        siteTypeNextBtn.disabled = false;
                        siteTypeNextBtn.style.opacity = '1';
                        siteTypeNextBtn.style.cursor = 'pointer';
                    }
                }
            });
        });

        // Initialize first step
        showStep(0);
    }

    /**
     * Populate the done step with scan results from the server
     */
    function populateDoneStep(wizard, data) {
        var doneStep = wizard.querySelector('[data-step="done"]');
        if (!doneStep) return;

        // Update crawler count text
        if (data.crawlers) {
            var crawlerText = doneStep.querySelector('.wizard-crawler-text');
            if (crawlerText) {
                crawlerText.textContent = data.crawlers.allowed + ' AI crawlers can now find your site';
            }
        }

        // Update schema text
        if (data.schema) {
            var schemaText = doneStep.querySelector('.wizard-schema-text');
            if (schemaText) {
                if (data.schema.enabled) {
                    schemaText.textContent = 'Schema markup is active';
                } else if (data.schema.source) {
                    schemaText.textContent = 'Schema handled by ' + data.schema.source;
                }
            }
        }
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
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
                keep_on_delete: document.getElementById('keep_on_delete')?.checked || false,
                request_logging_enabled: document.getElementById('request_logging_enabled')?.checked || false,
                request_log_retention: parseInt(document.getElementById('request_log_retention')?.value, 10) || 90
            };

            ajax('getcited_save_settings', {
                section: 'advanced',
                data: data
            }).then(response => {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';

                if (response.success) {
                    showStatus(statusEl, getcitedAdmin.strings.saved, 'success');
                    markClean();
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

        // Clear request log
        const clearLogBtn = document.querySelector('.getcited-clear-request-log');
        if (clearLogBtn) {
            clearLogBtn.addEventListener('click', () => {
                if (!confirm(getcitedAdmin.strings?.confirm_clear_log || 'Are you sure you want to clear all request logs? This action cannot be undone.')) {
                    return;
                }

                const statusEl = clearLogBtn.nextElementSibling;
                const originalText = clearLogBtn.innerHTML;
                clearLogBtn.disabled = true;
                clearLogBtn.textContent = getcitedAdmin.strings?.clearing || 'Clearing...';

                ajax('getcited_clear_request_log', {})
                    .then(response => {
                        clearLogBtn.disabled = false;
                        clearLogBtn.innerHTML = originalText;

                        if (response.success) {
                            showStatus(statusEl, getcitedAdmin.strings?.cleared || 'Log cleared', 'success');
                        } else {
                            showStatus(statusEl, getcitedAdmin.strings?.error || 'Error', 'error');
                        }
                    })
                    .catch(() => {
                        clearLogBtn.disabled = false;
                        clearLogBtn.innerHTML = originalText;
                        showStatus(statusEl, getcitedAdmin.strings?.error || 'Error', 'error');
                    });
            });
        }

        // Inline llms.txt regeneration (v1.6.12)
        const regenerateLinks = document.querySelectorAll('.getcited-regenerate-llms');
        regenerateLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const statusEl = this.nextElementSibling;

                statusEl.textContent = getcitedAdmin.strings?.regenerating || 'Regenerating...';
                statusEl.style.color = '#666';

                ajax('getcited_regenerate_llms', {})
                    .then(response => {
                        if (response.success) {
                            statusEl.textContent = getcitedAdmin.strings?.regenerated || 'Regenerated!';
                            statusEl.style.color = '#46b450';
                        } else {
                            statusEl.textContent = getcitedAdmin.strings?.error || 'Error';
                            statusEl.style.color = '#dc3232';
                        }
                        setTimeout(() => { statusEl.textContent = ''; }, 3000);
                    })
                    .catch(() => {
                        statusEl.textContent = getcitedAdmin.strings?.error || 'Error';
                        statusEl.style.color = '#dc3232';
                        setTimeout(() => { statusEl.textContent = ''; }, 3000);
                    });
            });
        });
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
     * Note: With event delegation on the table, this is now a no-op.
     * Kept for backwards compatibility with the Load More flow.
     */
    function attachAnalyzeHandlers() {
        // Event delegation on .getcited-posts-table handles all clicks now.
        // No individual handlers needed for dynamically loaded rows.
    }

    // ==========================================================================
    // v1.7.2 - Custom Checkbox Component
    // ==========================================================================

    /**
     * Initialize custom checkbox component interactions
     */
    function initCustomCheckboxes() {
        // Toggle is-checked class on custom checkboxes
        document.querySelectorAll('.getcited-checkbox-custom input[type="checkbox"]').forEach(function(input) {
            input.addEventListener('change', function() {
                const label = this.closest('.getcited-checkbox-custom');
                if (label) {
                    label.classList.toggle('is-checked', this.checked);
                }
            });
        });

        // Radio card selection - toggle is-selected class
        document.querySelectorAll('.getcited-radio-card input[type="radio"]').forEach(function(input) {
            input.addEventListener('change', function() {
                const name = this.name;
                // Remove selected from all cards with same name
                document.querySelectorAll('.getcited-radio-card input[name="' + name + '"]').forEach(function(radio) {
                    const card = radio.closest('.getcited-radio-card');
                    if (card) {
                        card.classList.remove('selected', 'is-selected');
                    }
                });
                // Add selected to current card
                const currentCard = this.closest('.getcited-radio-card');
                if (currentCard) {
                    currentCard.classList.add('selected', 'is-selected');
                }
            });
        });
    }

})();
