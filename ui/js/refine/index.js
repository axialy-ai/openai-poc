var RefineIndexModule = (function() {
    /**
     * Initialize the Refine Tab features
     */
    function initRefineTab() {
        // 1) Fetch refine activities
        RefineApiModule.fetchRefineActivities()
            .then(acts => {
                RefineStateModule.setActiveRefineActivities(acts);
                // 2) Fetch initial packages
                RefineEventsModule.fetchAndDisplayPackages('');
            })
            .catch(error => {
                console.error('[RefineIndexModule] Error fetching refine activities:', error);
            });

        // 3) Initialize core event handlers
        RefineEventsModule.initEventHandlers();

        // 4) For multi-package logic
        if (typeof AxialyAssessmentModule !== 'undefined' && AxialyAssessmentModule.init) {
            AxialyAssessmentModule.init();
        }

        // 5) If using dynamic ribbons in Refine
        if (typeof DynamicRibbonsModule !== 'undefined' && typeof DynamicRibbonsModule.initializeDynamicRibbons === 'function') {
            DynamicRibbonsModule.initializeDynamicRibbons();
        }
        if (typeof DynamicRibbonsModule !== 'undefined' && typeof DynamicRibbonsModule.setupRibbonToggles === 'function') {
            DynamicRibbonsModule.setupRibbonToggles(false);
        }

        // 6) Possibly enable "packages ribbon" toggling
        if (typeof setupPackagesRibbonToggle === 'function') {
            setupPackagesRibbonToggle();
        }

        // 7) Expose a global function so the single-package overlay can expand a focus area on double-click:
        window.expandFocusAreaInRefine = expandFocusArea;

        // 8) Also attach single-package overlay events so "x" and "Reconsider" work reliably
        if (
            window.AxialyPackageAdvisorModule &&
            typeof AxialyPackageAdvisorModule.initSinglePackageOverlayEvents === 'function'
        ) {
            AxialyPackageAdvisorModule.initSinglePackageOverlayEvents();
        }
    }

    /**
     * Reload the Refine tab & auto-select a package, optionally focusing a specific area.
     * This ensures the user gets the new focus-area version after each save.
     */
    function reloadRefineTabAndOpenPackage(packageId, focusAreaName) {
        console.log('[RefineIndexModule] reloadRefineTabAndOpenPackage => pkgId:', packageId, 'focusArea:', focusAreaName);
        if (window.RefineUtilsModule && typeof RefineUtilsModule.showPageMaskSpinner === 'function') {
            RefineUtilsModule.showPageMaskSpinner('Loading updated data...');
        }
        // Re-fetch the packages
        RefineEventsModule.fetchAndDisplayPackages('');
        setTimeout(() => {
            forceExpandPackagesArea();
            const pkgEl = document.querySelector(`.package-summary[data-package-id='${packageId}']`);
            if (pkgEl && typeof RefineEventsModule.selectPackage === 'function') {
                RefineEventsModule.selectPackage(pkgEl, packageId);
                if (focusAreaName) {
                    setTimeout(() => {
                        expandFocusArea(focusAreaName);
                        if (
                            window.RefineUtilsModule &&
                            typeof RefineUtilsModule.hidePageMaskSpinner === 'function'
                        ) {
                            RefineUtilsModule.hidePageMaskSpinner();
                        }
                    }, 650);
                } else {
                    if (
                        window.RefineUtilsModule &&
                        typeof RefineUtilsModule.hidePageMaskSpinner === 'function'
                    ) {
                        RefineUtilsModule.hidePageMaskSpinner();
                    }
                }
            } else {
                console.error('[RefineIndexModule] Could not find package tile or selectPackage function.');
                if (
                    window.RefineUtilsModule &&
                    typeof RefineUtilsModule.hidePageMaskSpinner === 'function'
                ) {
                    RefineUtilsModule.hidePageMaskSpinner();
                }
            }
        }, 300);
    }

    /**
     * Expand the entire .packages-content area if it was collapsed.
     */
    function forceExpandPackagesArea() {
        const packagesContent = document.querySelector('.packages-content');
        const packagesRibbon  = document.querySelector('.packages-ribbon');
        if (!packagesContent || !packagesRibbon) return;
        if (packagesContent.style.display === 'none') {
            packagesContent.style.display = 'block';
            const toggleIcon = packagesRibbon.querySelector('.toggle-icon');
            if (toggleIcon) {
                toggleIcon.innerHTML = '&#9660;'; // arrow down
            }
        }
    }

    /**
     * Expand a single focus area tile by matching data-focus-area-name
     */
    function expandFocusArea(focusAreaName) {
        console.log('[RefineIndexModule] expandFocusArea =>', focusAreaName);
        // each .focus-area-record-item has data-focus-area-name, e.g. item.dataset.focusAreaName
        const items = document.querySelectorAll('.focus-area-record-item');
        items.forEach(item => {
            const faName = item.dataset.focusAreaName || '';
            if (faName.trim() === focusAreaName.trim()) {
                console.log('[RefineIndexModule] Found matching focusArea => expanding =>', faName);
                // Expand the tile
                const toggleIcon = item.querySelector('.focus-area-toggle');
                const records = item.querySelectorAll('.focus-area-record-card');
                if (toggleIcon && records.length > 0) {
                    if (toggleIcon.textContent.trim() === '➕') {
                        records.forEach(r => {
                            r.style.display = 'block';
                        });
                        toggleIcon.textContent = '➖';
                    }
                }
                // Optionally scroll into view
                item.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }

    // Expose the reload function globally
    window.reloadRefineTabAndOpenPackage = reloadRefineTabAndOpenPackage;

    return {
        initRefineTab: initRefineTab
    };
})();

// Not auto-run here; layout.js calls initRefineTab after DOM load
document.addEventListener('DOMContentLoaded', function() {
    // Typically called from layout...
});
