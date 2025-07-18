(function() {
  // Expose the initialization function so that it can be called
  // after the Generate tab content is loaded.
  window.initSaveDataEnhancement = function initSaveDataEnhancement() {
    const saveBtn = document.getElementById('save-data-btn');
    if (!saveBtn) {
      console.warn('[save-data-enhancement] #save-data-btn not found.');
      return;
    }
    console.log('[save-data-enhancement] Found #save-data-btn, replacing click handler.');
    
    // Remove existing click listeners by cloning the node.
    const newBtn = saveBtn.cloneNode(true);
    saveBtn.parentNode.replaceChild(newBtn, saveBtn);
    
    // Add our new click handler to show the overlay.
    newBtn.addEventListener('click', (evt) => {
      evt.preventDefault();
      showSaveOptionOverlay();
    });
  };

  /**
   * Show an overlay with two big buttons:
   *   - [Save as NEW Package]
   *   - [Save to EXISTING Package]
   */
  function showSaveOptionOverlay() {
    removeOverlayIfExists('save-option-overlay');
    const overlay = document.createElement('div');
    overlay.id = 'save-option-overlay';
    overlay.className = 'overlay';

    const content = document.createElement('div');
    content.className = 'overlay-content';

    // Close button
    const closeBtn = document.createElement('span');
    closeBtn.className = 'close-overlay';
    closeBtn.innerHTML = '&times;';
    closeBtn.addEventListener('click', () => { overlay.remove(); });

    const title = document.createElement('h2');
    title.textContent = 'Save Data';

    const desc = document.createElement('p');
    desc.textContent = 'Choose how you would like to save your newly generated data.';

    // Button for saving as a NEW package
    const buttonNew = document.createElement('button');
    buttonNew.textContent = 'Save as NEW Analysis Package';
    buttonNew.style.marginRight = '20px';
    buttonNew.addEventListener('click', () => {
      // Clear the overlay immediately
      overlay.remove();
      // Invoke the legacy "Save as NEW Package" functionality directly.
      if (typeof ProcessFeedbackModule !== 'undefined' &&
          typeof ProcessFeedbackModule.handleSaveData === 'function') {
        ProcessFeedbackModule.updateFocusAreaRecordsMessage('Saving to new analysis package...');
        ProcessFeedbackModule.handleSaveData();
      } else {
        console.warn('ProcessFeedbackModule.handleSaveData is not defined.');
      }
    });

    // Button for saving to an EXISTING package
    const buttonExisting = document.createElement('button');
    buttonExisting.textContent = 'Save to EXISTING Package';
    buttonExisting.addEventListener('click', () => {
      overlay.remove();
      showExistingPackagesOverlay();
    });

    content.appendChild(closeBtn);
    content.appendChild(title);
    content.appendChild(desc);
    content.appendChild(buttonNew);
    content.appendChild(buttonExisting);

    overlay.appendChild(content);
    document.body.appendChild(overlay);
  }

  /**
   * Show an overlay that lists all active (non-deleted) packages,
   * letting the user pick one by double-clicking a tile.
   */
  function showExistingPackagesOverlay() {
    removeOverlayIfExists('existing-packages-overlay');
    const overlay = document.createElement('div');
    overlay.id = 'existing-packages-overlay';
    overlay.className = 'overlay';

    const content = document.createElement('div');
    content.className = 'overlay-content';

    const closeBtn = document.createElement('span');
    closeBtn.className = 'close-overlay';
    closeBtn.innerHTML = '&times;';
    closeBtn.addEventListener('click', () => overlay.remove());

    const title = document.createElement('h2');
    title.textContent = 'Choose an Existing Package';

    const desc = document.createElement('p');
    desc.textContent = 'Double-click a tile to save data into that package.';

    // Container for the package tiles
    const tilesContainer = document.createElement('div');
    tilesContainer.style.display = 'flex';
    tilesContainer.style.flexWrap = 'wrap';
    tilesContainer.style.gap = '20px';

    content.appendChild(closeBtn);
    content.appendChild(title);
    content.appendChild(desc);
    content.appendChild(tilesContainer);
    overlay.appendChild(content);
    document.body.appendChild(overlay);

    // Fetch the list of active packages
    fetch('fetch_active_packages_for_save.php')
      .then(r => r.json())
      .then(data => {
        if (data.status !== 'success') {
          throw new Error(data.message || 'Error fetching package list');
        }
        const packages = data.packages || [];
        packages.forEach(pkg => {
          const tile = document.createElement('div');
          tile.style.width = '250px';
          tile.style.border = '1px solid #ccc';
          tile.style.padding = '10px';
          tile.style.cursor = 'pointer';
          tile.style.borderRadius = '6px';
          tile.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
          tile.style.position = 'relative';

          // Display package ID, name, and summary
          const nameEl = document.createElement('h3');
          nameEl.textContent = `#${pkg.package_id}: ${pkg.package_name}`;
          const summEl = document.createElement('p');
          summEl.textContent = pkg.short_summary || '(No summary)';

          tile.appendChild(nameEl);
          tile.appendChild(summEl);

          // Tooltip text
          tile.title = `Double-click to save data under package #${pkg.package_id}`;

          // Hover effect
          tile.addEventListener('mouseover', () => {
            tile.style.boxShadow = '0 4px 8px rgba(0,0,0,0.2)';
          });
          tile.addEventListener('mouseout', () => {
            tile.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
          });

          // On double-click, finalize the save to the existing package
          tile.addEventListener('dblclick', () => {
            overlay.remove();
            finalizeSaveToExistingPackage(pkg.package_id);
          });

          tilesContainer.appendChild(tile);
        });
      })
      .catch(err => {
        console.error('[save-data-enhancement] Error fetching packages:', err);
        desc.textContent = 'Error loading existing packages.';
      });
  }

  /**
   * Calls the endpoint “save_data_existing_package.php” with the newly generated focus-area data.
   */
  function finalizeSaveToExistingPackage(existingPackageId) {
    let collectedData = [];
    if (typeof DynamicRibbonsModule !== 'undefined' &&
        typeof DynamicRibbonsModule.collectRibbonsData === 'function') {
      collectedData = DynamicRibbonsModule.collectRibbonsData();
    }
    if (!collectedData || collectedData.length === 0) {
      alert('No focus-area data found. Please generate data first.');
      return;
    }
    const inputSummaries = (window.inputTextSummariesId || []);
    showSpinnerOverlay('Saving data into existing package...');

    const payload = {
      package_id: existingPackageId,
      collectedData: collectedData,
      input_text_summaries_id: inputSummaries
    };

    fetch('save_data_existing_package.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(r => r.json())
      .then(resp => {
        hideSpinnerOverlay();
        if (resp.status === 'success') {
          alert(`Data saved successfully to Package #${existingPackageId}.`);
          if (typeof DynamicRibbonsModule !== 'undefined') {
            DynamicRibbonsModule.clearRibbons();
          }
        } else {
          throw new Error(resp.message || 'Unknown error');
        }
      })
      .catch(err => {
        hideSpinnerOverlay();
        console.error('[save-data-enhancement] finalizeSaveToExistingPackage error:', err);
        alert('Failed to save data to existing package: ' + err.message);
      });
  }

  function showSpinnerOverlay(msg) {
    removeOverlayIfExists('saveDataSpinnerOverlay');
    const ov = document.createElement('div');
    ov.id = 'saveDataSpinnerOverlay';
    ov.className = 'page-mask';
    const spin = document.createElement('div');
    spin.className = 'spinner';
    const txt = document.createElement('div');
    txt.className = 'spinner-message';
    txt.textContent = msg;
    ov.appendChild(spin);
    ov.appendChild(txt);
    document.body.appendChild(ov);
  }

  function hideSpinnerOverlay() {
    removeOverlayIfExists('saveDataSpinnerOverlay');
  }

  function removeOverlayIfExists(id) {
    const old = document.getElementById(id);
    if (old) old.remove();
  }
})();
