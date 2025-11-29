/**
 * PunktePass Profile Lite - Tabs Module
 * Contains: Tab switching, URL hash persistence, localStorage fallback
 * Depends on: pp-profile-core.js
 */
(function() {
    'use strict';

    // Guard against multiple loads
    if (window.PPV_PROFILE_TABS_LOADED) return;
    window.PPV_PROFILE_TABS_LOADED = true;

    // ============================================================
    // TAB MANAGER CLASS
    // ============================================================
    class TabManager {
        constructor() {
            this.activeTab = null;
        }

        /**
         * Bind click events to all tab buttons
         */
        bindTabs() {
            document.querySelectorAll('.ppv-tab-btn').forEach(btn => {
                // Remove existing listeners to prevent duplicates
                btn.removeEventListener('click', this.handleTabClick);
                btn.addEventListener('click', this.handleTabClick.bind(this));
            });
        }

        /**
         * Handle tab button click
         */
        handleTabClick(e) {
            const tabName = e.currentTarget.dataset.tab;
            if (tabName) {
                this.switchTab(tabName);
            }
        }

        /**
         * Switch to a specific tab
         */
        switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.ppv-tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab content
            const targetTab = document.getElementById(`tab-${tabName}`);
            if (targetTab) {
                targetTab.classList.add('active');
            }

            // Update button states
            document.querySelectorAll('.ppv-tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === tabName);
            });

            // Store active tab name
            this.activeTab = tabName;

            // Persist to URL hash (survives page refresh)
            if (history.replaceState) {
                history.replaceState(null, '', '#tab-' + tabName);
            } else {
                window.location.hash = 'tab-' + tabName;
            }

            // Also save to localStorage as fallback
            try {
                localStorage.setItem('ppv_profile_active_tab', tabName);
            } catch (e) {
                // localStorage might be unavailable
            }
        }

        /**
         * Restore tab from URL hash or localStorage
         */
        restoreTab() {
            let restoredTab = null;

            // First try URL hash
            if (window.location.hash?.startsWith('#tab-')) {
                restoredTab = window.location.hash.replace('#tab-', '');
            } else {
                // Fallback to localStorage
                try {
                    restoredTab = localStorage.getItem('ppv_profile_active_tab');
                } catch (e) {
                    // localStorage might be unavailable
                }
            }

            if (restoredTab) {
                // Verify the tab exists
                const tabExists = document.getElementById(`tab-${restoredTab}`);
                if (tabExists) {
                    this.switchTab(restoredTab);
                    return true;
                }
            }

            return false;
        }

        /**
         * Get current active tab name
         */
        getActiveTab() {
            const activeBtn = document.querySelector('.ppv-tab-btn.active');
            return activeBtn?.dataset.tab || this.activeTab;
        }
    }

    // ============================================================
    // EXPORT TO GLOBAL
    // ============================================================
    window.PPV_PROFILE = window.PPV_PROFILE || {};
    window.PPV_PROFILE.TabManager = TabManager;

    console.log('[Profile-Tabs] Module loaded v3.0');

})();
