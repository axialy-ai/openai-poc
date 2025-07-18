/****************************************************************************
 * /js/refine/events.js
 *
 * Attaches core event handlers for searching, selecting packages,
 * toggling deleted packages, and handling focus-area actions.
 * Removes references to the old checkbox #show-deleted-toggle
 * and replaces them with a button #show-deleted-btn that toggles state.
 ***************************************************************************/
var RefineEventsModule = (function() {
  let packageActions = [];

  // NEW: Track whether “Show Deleted” is active or not.
  let showDeletedState = false;

  function initEventHandlers() {
    const searchInput               = document.getElementById('search-input');
    const packageSummariesContainer = document.getElementById('package-summaries-container');

    // --- (Removed the old checkbox references) ---
    // Instead, we handle the show-deleted-btn here:
    const showDeletedBtn = document.getElementById('show-deleted-btn');
    if (showDeletedBtn) {
      showDeletedBtn.addEventListener('click', () => {
        showDeletedState = !showDeletedState;
        if (showDeletedState) {
          // Toggled on => “Hide Deleted”
          showDeletedBtn.innerHTML = 'Hide<br>Deleted';
          showDeletedBtn.dataset.state = 'visible';
        } else {
          // Toggled off => “Show Deleted”
          showDeletedBtn.innerHTML = 'Show<br>Deleted';
          showDeletedBtn.dataset.state = 'hidden';
        }
        fetchAndDisplayPackages(searchInput ? searchInput.value.trim() : '');
      });
    }

    // Load /config/package-actions.json for "Actions..." button
    fetch('/config/package-actions.json')
      .then(r => r.json())
      .then(json => {
        if (json.packageActions && Array.isArray(json.packageActions)) {
          packageActions = json.packageActions.filter(item => item.active);
        } else {
          packageActions = [];
        }
      })
      .catch(err => {
        console.warn('[RefineEvents] Could not load package-actions.json:', err);
        packageActions = [];
      });

    const packageActionsBtn = document.getElementById('package-actions-btn');
    if (packageActionsBtn) {
      packageActionsBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        if (!RefineStateModule.getSelectedPackageId()) {
          alert('No analysis package selected.');
          return;
        }
        togglePackageActionsDropdown(packageActionsBtn);
      });
    }

    // Live-search for packages
    if (searchInput) {
      searchInput.addEventListener(
        'input',
        RefineUtilsModule.debounce(function() {
          fetchAndDisplayPackages(searchInput.value.trim());
        }, 300)
      );
    }

    // Selecting or deselecting a package tile
    if (packageSummariesContainer) {
      packageSummariesContainer.addEventListener('click', e => {
        const pkgEl = e.target.closest('.package-summary');
        if (!pkgEl) return;
        const packageId = parseInt(pkgEl.dataset.packageId, 10);
        if (pkgEl.classList.contains('selected')) {
          deselectPackage(pkgEl);
        } else {
          selectPackage(pkgEl, packageId);
        }
      });
    }

    // Focus Area "Refine Data" dropdown inside each focus-area tile
    document.addEventListener('click', e => {
      const button = e.target.closest('.refine-data-btn');
      if (button) {
        e.stopPropagation();
        const focusAreaItem = button.closest('.focus-area-record-item');
        if (focusAreaItem) {
          toggleRefineDropdown(focusAreaItem, button);
        }
      }
    });
  }

  /**
   * Determines whether the user wants to see deleted packages/focus areas
   * (formerly was read from the checkbox).
   */
  function isShowDeleted() {
    return showDeletedState;
  }

  /**
   * Shows the "Actions..." dropdown for the selected package.
   */
  function togglePackageActionsDropdown(parentBtn) {
    RefineUtilsModule.removeExistingDropdown();
    const dropdown = document.createElement('div');
    dropdown.className = 'actions-dropdown visible';

    const selectedTile = document.querySelector('.package-summary.selected');
    const isSoftDeleted = selectedTile && selectedTile.dataset.isDeleted === '1';

    packageActions.forEach(action => {
      const itemDiv = document.createElement('div');
      itemDiv.className = 'action-item';
      let finalLabel = action.label;
      if (finalLabel === 'Remove Package' && isSoftDeleted) {
        finalLabel = 'Recover Package';
      }
      itemDiv.textContent = finalLabel;
      if (action.description) {
        itemDiv.title = action.description;
      }
      itemDiv.addEventListener('click', () => {
        RefineUtilsModule.removeExistingDropdown();
        handlePackageAction(finalLabel);
      });
      dropdown.appendChild(itemDiv);
    });

    parentBtn.style.position = 'relative';
    parentBtn.parentElement.appendChild(dropdown);

    document.addEventListener('click', function outsideClick(evt) {
      if (!dropdown.contains(evt.target) && evt.target !== parentBtn) {
        dropdown.remove();
        document.removeEventListener('click', outsideClick);
      }
    });
  }

  /**
   * Handles the clicked package-level action from the dropdown.
   */
  function handlePackageAction(label) {
    const packageId = RefineStateModule.getSelectedPackageId();
    if (!packageId) {
      alert('No analysis package selected.');
      return;
    }
    switch (label) {
      case 'New Focus Area...': {
        const version     = RefineStateModule.getCurrentVersion() || 0;
        const packageName = RefineStateModule.getCurrentPackageName();
        if (window.NewFocusAreaOverlay && typeof NewFocusAreaOverlay.open === 'function') {
          NewFocusAreaOverlay.open(packageId, packageName, version);
        } else {
          alert('New Focus Area Overlay not available.');
        }
        break;
      }
      case 'Refresh Package Content':
        openPackage(packageId);
        refreshPackageMetrics(packageId);
        break;
      case 'Show Deleted Content':
        alert('That action is now handled by the top-level “Show Deleted” button.');
        break;
      case 'Edit Package Header':
        doEditPackageHeader(packageId);
        break;
      case 'Remove Package':
        removePackageHandler(packageId);
        break;
      case 'Recover Package':
        recoverPackageHandler(packageId);
        break;
      default:
        console.warn('[RefineEvents] Unknown package action:', label);
        break;
    }
  }

  function removePackageHandler(packageId) {
    if (!confirm('Are you sure you want to remove this package?')) return;
    RefineUtilsModule.showPageMaskSpinner('Removing package...');
    fetch('remove_analysis_package.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ package_id: packageId })
    })
      .then(r => r.json())
      .then(resp => {
        RefineUtilsModule.hidePageMaskSpinner();
        if (resp.status === 'success') {
          alert('Package removed successfully.');
          fetchAndDisplayPackages('');
        } else {
          alert('Error removing package: ' + (resp.message || 'Unknown error'));
        }
      })
      .catch(err => {
        RefineUtilsModule.hidePageMaskSpinner();
        console.error('[RefineEvents] Error removing package:', err);
        alert('Error removing package: ' + err.message);
      });
  }

  function recoverPackageHandler(packageId) {
    if (!confirm('Are you sure you want to recover this package?')) return;
    RefineUtilsModule.showPageMaskSpinner('Recovering package...');
    fetch('recover_analysis_package.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ package_id: packageId })
    })
      .then(r => r.json())
      .then(resp => {
        RefineUtilsModule.hidePageMaskSpinner();
        if (resp.status === 'success') {
          alert('Package recovered successfully.');
          fetchAndDisplayPackages('').then(() => {
            setTimeout(() => {
              const pkgEl = document.querySelector(`.package-summary[data-package-id='${packageId}']`);
              if (pkgEl) {
                selectPackage(pkgEl, packageId);
              }
            }, 400);
          });
        } else {
          alert('Error recovering package: ' + (resp.message || 'Unknown error'));
        }
      })
      .catch(err => {
        RefineUtilsModule.hidePageMaskSpinner();
        console.error('[RefineEvents] Error recovering package:', err);
        alert('Error recovering package: ' + err.message);
      });
  }

  /**
   * Opens a small overlay to let the user edit the package header (name, summary, etc.).
   */
  function doEditPackageHeader(packageId) {
    const selectedTile = document.querySelector('.package-summary.selected');
    if (!selectedTile) {
      alert('No package tile is selected.');
      return;
    }
    const headerEl   = selectedTile.querySelector('.package-summary-header h3');
    const rawTitle   = headerEl ? headerEl.textContent.trim() : '';
    let headerTitle  = rawTitle;
    const dashIndex  = rawTitle.indexOf('-');
    if (dashIndex > 0) {
      headerTitle = rawTitle.substring(dashIndex + 1).trim();
    }
    const shortSummaryEl   = selectedTile.querySelector('.package-summary-subtitle p');
    const shortSummary     = shortSummaryEl ? shortSummaryEl.textContent.trim() : '';
    const existingLongDesc = selectedTile.dataset.longDescription || '';
    const storedOrgId      = selectedTile.dataset.customOrgId;
    const customOrgId      = (storedOrgId === 'default' ? null : storedOrgId);

    const headerData = {
      'Header Title':     headerTitle,
      'Short Summary':    shortSummary,
      'Long Description': existingLongDesc,
      'organization_id':  customOrgId
    };
    if (window.OverlayModule && typeof OverlayModule.showHeaderReviewOverlay === 'function') {
      OverlayModule.showHeaderReviewOverlay(
        headerData,
        function(updatedData) {
          doUpdateAnalysisPackage(packageId, updatedData);
        },
        function() {
          console.log('[RefineEvents] Edit Package Header - cancelled');
        }
      );
    } else {
      console.error('[RefineEvents] OverlayModule not available.');
      alert('OverlayModule not available to edit package details.');
    }
  }

  /**
   * Saves any revised package header fields to the server.
   */
  function doUpdateAnalysisPackage(packageId, updatedData) {
    const selectedTile = document.querySelector('.package-summary.selected');
    if (!selectedTile) {
      alert('No selected package to update.');
      return;
    }
    const existingHeader  =
      selectedTile.querySelector('.package-summary-header h3').textContent.split('-')[1].trim();
    const existingSummary =
      selectedTile.querySelector('.package-summary-subtitle p').textContent.trim();
    const existingDesc    = selectedTile.dataset.longDescription;
    const existingOrgId   = (selectedTile.dataset.customOrgId === 'default'
                             ? null
                             : selectedTile.dataset.customOrgId);

    const hasChanges = (
      existingHeader  !== updatedData['Header Title']     ||
      existingSummary !== updatedData['Short Summary']    ||
      existingDesc    !== updatedData['Long Description'] ||
      String(existingOrgId) !== String(updatedData.organization_id)
    );
    if (!hasChanges) {
      console.log('[RefineEvents] No changes to package header.');
      return;
    }

    const payload = {
      package_id:             packageId,
      package_name:           updatedData['Header Title'],
      short_summary:          updatedData['Short Summary'],
      long_description:       updatedData['Long Description'],
      custom_organization_id: updatedData.organization_id
    };
    RefineUtilsModule.showPageMaskSpinner('Updating Analysis Package...');
    fetch('update_analysis_package.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(resp => resp.json())
      .then(json => {
        RefineUtilsModule.hidePageMaskSpinner();
        if (json.status === 'success') {
          alert('Package updated successfully.');
          if (typeof reloadRefineTabAndOpenPackage === 'function') {
            reloadRefineTabAndOpenPackage(packageId);
          }
        } else {
          throw new Error(json.message || 'Error updating package');
        }
      })
      .catch(err => {
        RefineUtilsModule.hidePageMaskSpinner();
        console.error('[RefineEvents] doUpdateAnalysisPackage error:', err);
        alert('Failed to update package: ' + err.message);
      });
  }

  /**
   * Fetches and displays the list of packages matching the search term.
   */
  function fetchAndDisplayPackages(searchTerm) {
    const summaryContainer      = document.getElementById('package-summaries-container');
    const focusRecordsContainer = document.getElementById('focus-area-records-container');
    if (focusRecordsContainer) {
      focusRecordsContainer.innerHTML = '';
    }

    disableControls();

    // Instead of reading a checkbox, we read isShowDeleted():
    const showDeleted = isShowDeleted();

    return RefineApiModule.fetchPackages(searchTerm, showDeleted)
      .then(data => {
        RefineUIModule.renderPackages(data);
        // If a package was previously selected, keep it selected
        const selPkgId = RefineStateModule.getSelectedPackageId();
        if (selPkgId) {
          const found = data.find(pkg => pkg.id == selPkgId);
          if (found) {
            const pkgEl = document.querySelector(`.package-summary[data-package-id='${selPkgId}']`);
            if (pkgEl) {
              selectPackage(pkgEl, selPkgId);
            }
          }
        }
      })
      .catch(err => {
        console.error('[RefineEvents] fetchAndDisplayPackages error:', err);
        if (summaryContainer) {
          summaryContainer.innerHTML = '<p>Error loading packages.</p>';
        }
      });
  }

  function disableControls() {
    const packageActionsBtn = document.getElementById('package-actions-btn');
    if (packageActionsBtn) {
      packageActionsBtn.disabled = true;
      packageActionsBtn.classList.remove('enabled');
    }
  }

  /**
   * Deselects the currently selected package tile, returning to the default state.
   */
  function deselectPackage(pkgEl) {
    pkgEl.classList.remove('selected');
    RefineStateModule.setSelectedPackageId(null);
    RefineStateModule.setCurrentPackageName('');
    RefineStateModule.setCurrentVersion(null);
    RefineStateModule.setCurrentStakeholders([]);
    const ribbonTitle = document.querySelector('.packages-ribbon .ribbon-title');
    if (ribbonTitle) {
      ribbonTitle.textContent = 'Select a package...';
    }
    // Show all packages again
    document.querySelectorAll('.package-summary').forEach(el => {
      el.style.display = '';
    });
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
      searchInput.value = '';
    }
    fetchAndDisplayPackages('');
  }

  /**
   * Selects a package tile, hides others, and loads focus areas for it.
   */
  function selectPackage(pkgEl, packageId) {
    document.querySelectorAll('.package-summary').forEach(el => {
      el.classList.remove('selected');
      el.style.display = 'none';
    });
    pkgEl.classList.add('selected');
    pkgEl.style.display = '';
    RefineStateModule.setSelectedPackageId(packageId);

    const headerEl = pkgEl.querySelector('.package-summary-header h3');
    if (headerEl) {
      RefineStateModule.setCurrentPackageName(headerEl.textContent);
    }
    const ribbonTitle = document.querySelector('.packages-ribbon .ribbon-title');
    if (ribbonTitle && headerEl) {
      ribbonTitle.textContent = headerEl.textContent;
    }
    const actionsBtn = document.getElementById('package-actions-btn');
    if (actionsBtn) {
      actionsBtn.disabled = false;
      actionsBtn.classList.add('enabled');
    }
    openPackage(packageId);
  }

  /**
   * Loads the focus-area records for a selected package.
   */
  function openPackage(packageId) {
    const focusRecordsContainer = document.getElementById('focus-area-records-container');
    if (!focusRecordsContainer) return;
    const pkgEl = document.querySelector(`.package-summary[data-package-id='${packageId}']`);
    const isSoftDeleted = pkgEl && pkgEl.dataset.isDeleted === '1';
    if (isSoftDeleted) {
      focusRecordsContainer.innerHTML = `
        <div class="package-summary selected" style="padding:1em;">
          <h3>${pkgEl.querySelector('.package-summary-header h3').textContent}</h3>
          <p><em>This package is soft-deleted. No focus-area records are available.</em></p>
        </div>
      `;
      return;
    }
    focusRecordsContainer.innerHTML = '<p>Loading focus-area records...</p>';
    const showDeleted = isShowDeleted();
    RefineApiModule.fetchFocusAreaRecords(packageId, showDeleted)
      .then(data => {
        focusRecordsContainer.innerHTML = '';
        RefineStateModule.setCurrentVersion(null);
        RefineStateModule.setCurrentStakeholders([]);
        RefineUIModule.renderFocusAreaRecords(data, packageId);
        RefineUIModule.setupFocusAreaToggles();
      })
      .catch(error => {
        console.error('[RefineEvents] openPackage error fetching focus-area records:', error);
        focusRecordsContainer.innerHTML = '<p>Error loading package data.</p>';
      });
  }

  /**
   * Refreshes the package metrics in the tile after an operation.
   */
  function refreshPackageMetrics(packageId) {
    RefineApiModule.fetchPackages('', false)
      .then(allPkgs => {
        const foundPkg = allPkgs.find(p => p.id == packageId);
        if (!foundPkg) {
          console.warn('[RefineEvents] refreshPackageMetrics: Package not found:', packageId);
          return;
        }
        const selTile = document.querySelector('.package-summary.selected');
        if (!selTile) {
          console.warn('[RefineEvents] No selected package to refresh metrics on.');
          return;
        }
        const metricsCols = selTile.querySelectorAll('.package-summary-metrics .metrics-column');
        if (metricsCols.length === 2) {
          metricsCols[0].innerHTML = `
            <p><strong>Max Focus-Area Version:</strong> ${foundPkg.focus_area_version_number || 0}</p>
            <p><strong>Total Focus Areas:</strong> ${foundPkg.focus_areas_count || 0}</p>
            <p><strong>Total Records:</strong> ${foundPkg.total_records_count || 0}</p>
          `;
          metricsCols[1].innerHTML = `
            <p><strong>Stakeholder Requests:</strong> ${foundPkg.feedback_requests_count || 0}</p>
            <p><strong>Stakeholder Responses:</strong> ${foundPkg.feedback_responses_count || 0}</p>
            <p><strong>Responding Stakeholders:</strong> ${foundPkg.responding_stakeholders_count || 0}</p>
            <p><strong>Unreviewed Feedback:</strong> ${foundPkg.unreviewed_feedback_count || 0}</p>
          `;
        }
      })
      .catch(err => {
        console.error('[RefineEvents] refreshPackageMetrics error:', err);
      });
  }

  /**
   * The "Refine Data" dropdown used inside each focus-area tile.
   */
  function toggleRefineDropdown(focusAreaItem, button) {
    RefineUtilsModule.removeExistingDropdown();
    const dropdown = document.createElement('div');
    dropdown.classList.add('refine-dropdown');

    const activeActs = RefineStateModule.getActiveRefineActivities();
    if (activeActs.length === 0) {
      const noOption = document.createElement('div');
      noOption.textContent = 'No actions available';
      dropdown.appendChild(noOption);
    } else {
      activeActs.forEach(activity => {
        const opt = document.createElement('div');
        opt.textContent = activity.label;
        opt.addEventListener('click', () => {
          RefineUtilsModule.removeExistingDropdown();
          const areaName = focusAreaItem.dataset.focusAreaName;
          let versionOrId = parseInt(focusAreaItem.dataset.focusAreaVersion, 10) || 0;
          if (activity.action === 'deleteFocusAreaData') {
            versionOrId = parseInt(focusAreaItem.dataset.focusAreaVersionId, 10) || 0;
          }
          if (!areaName) return;
          if (activity.actionType === 'link') {
            window.location.href = activity.action;
          } else if (['overlay','confirmation'].includes(activity.actionType)) {
            const pkgId = RefineStateModule.getSelectedPackageId();
            if (pkgId) {
              RefineActionsModule.handleActivitySelection(
                activity,
                areaName,
                versionOrId
              );
            } else {
              alert('No package selected.');
            }
          }
        });
        dropdown.appendChild(opt);
      });
    }

    const btnParent = button.parentElement;
    btnParent.style.position = 'relative';
    btnParent.appendChild(dropdown);

    document.addEventListener('click', function outsideClick(ev) {
      if (!btnParent.contains(ev.target)) {
        dropdown.remove();
        document.removeEventListener('click', outsideClick);
      }
    });
  }

  // Public methods
  return {
    initEventHandlers,
    openPackage,
    fetchAndDisplayPackages,
    selectPackage,
    toggleRefineDropdown
  };
})();
