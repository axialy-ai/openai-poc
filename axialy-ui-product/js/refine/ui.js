/****************************************************************************
 * /js/refine/ui.js
 *
 * Maintains the Refine Tab UI for focus-area data and ensures that any record
 * flagged as deleted (is_deleted=1) still appears in the final posted payload
 * so the server can properly set it to deleted in the new version. Likewise,
 * any previously existing record (numeric DB ID) is preserved with that same
 * ID, and only brand-new rows use "focusAreaRecordID":"new".
 *
 * We now explicitly capture 'display_order' as an ephemeral field, so that
 * heading labels say “Record [display_order]” properly, even if Show Deleted
 * is false.
 ***************************************************************************/
var RefineUIModule = (function() {

  /***********************************************************************
   * Extract ephemeral vs user-defined fields. ephemeralRecord will hold
   * fields like id, is_deleted, grid_index, display_order, etc.
   * record.axia_properties holds user data from record.properties.
   ***********************************************************************/
  function splitEphemeralFromUserFields(record) {
    // "skipFields" is actually the list of ephemeral fields that we do NOT
    // treat as user-defined. We'll copy them directly into record.ephemeral.
    const skipFields = [
      'id',
      'analysis_package_focus_area_versions_id',
      'analysis_package_headers_id',
      'grid_index',
      'display_order',     // <---- ADDED THIS so ephemeral gets display_order
      'is_deleted',
      'input_text_summaries_id',
      'input_text_title',
      'input_text_summary',
      'input_text',
      '_dbOriginalProps',
      '_changedKeys',
      '_newlyCreated',
      '_originalIsDeleted'
    ];

    let userFields = {};
    if (record.properties && typeof record.properties === 'object') {
      // rename "properties" => "axia_properties"
      userFields = { ...record.properties };
    }
    delete record.properties;

    // ephemeral object to store skipFields
    const ephemeralRecord = {};
    for (const [k, v] of Object.entries(record)) {
      if (skipFields.includes(k)) {
        // ephemeral
        ephemeralRecord[k] = v;
      }
    }

    // assign user fields
    record.axia_properties = userFields;
    return ephemeralRecord;
  }

  /***********************************************************************
   * Renders the list of package tiles in #package-summaries-container
   ***********************************************************************/
  function renderPackages(packages) {
    const container = document.getElementById('package-summaries-container');
    if (!container) return;
    container.innerHTML = '';

    if (!Array.isArray(packages) || packages.length === 0) {
      container.innerHTML = '<p>No packages found.</p>';
      return;
    }
    packages.forEach(pkg => {
      const summaryEl = document.createElement('div');
      summaryEl.classList.add('package-summary');
      summaryEl.dataset.packageId = pkg.id;

      if (pkg.custom_organization_id == null) {
        summaryEl.dataset.customOrgId = 'default';
      } else {
        summaryEl.dataset.customOrgId = String(pkg.custom_organization_id);
      }
      if (pkg.is_deleted === 1) {
        summaryEl.classList.add('soft-deleted-package');
        summaryEl.dataset.isDeleted = '1';
      } else {
        summaryEl.dataset.isDeleted = '0';
      }
      summaryEl.dataset.longDescription = pkg.long_description || '';

      let logoHtml = '';
      if (pkg.logo_path) {
        const serveUrl = '/serve_logo.php?file=' + encodeURIComponent(pkg.logo_path);
        logoHtml =
          '<div class="logo-thumbnail" title="' + (pkg.custom_org_name || '') + '">' +
            '<img src="' + serveUrl + '" alt="Organization Logo" />' +
          '</div>';
      }

      summaryEl.innerHTML =
        logoHtml +
        '<div class="package-summary-header">' +
          '<h3>' + pkg.id + ' - ' + pkg.package_name + '</h3>' +
        '</div>' +
        '<div class="package-summary-subtitle">' +
          '<p>' + (pkg.short_summary || '') + '</p>' +
        '</div>' +
        '<div class="package-summary-metrics summary-metrics">' +
          '<div class="metrics-column">' +
            '<p><strong>Max Focus-Area Version:</strong> ' + (pkg.focus_area_version_number || 0) + '</p>' +
            '<p><strong>Total Focus Areas:</strong> ' + (pkg.focus_areas_count || 0) + '</p>' +
            '<p><strong>Total Records:</strong> ' + (pkg.total_records_count || 0) + '</p>' +
          '</div>' +
          '<div class="metrics-column">' +
            '<p><strong>Stakeholder Requests:</strong> ' + (pkg.feedback_requests_count || 0) + '</p>' +
            '<p><strong>Stakeholder Responses:</strong> ' + (pkg.feedback_responses_count || 0) + '</p>' +
            '<p><strong>Responding Stakeholders:</strong> ' + (pkg.responding_stakeholders_count || 0) + '</p>' +
            '<p><strong>Unreviewed Feedback:</strong> ' + (pkg.unreviewed_feedback_count || 0) + '</p>' +
          '</div>' +
        '</div>';

      container.appendChild(summaryEl);
    });
  }

  /***********************************************************************
   * Renders each focus-area tile with existing records, plus a Save button.
   * Data structure from fetch_analysis_package_focus_area_records is:
   * {
   *   "focus_areas": {
   *     "<focusAreaName>": {
   *       version: ...,
   *       versionId: ...,
   *       records: [...]
   *     }
   *   }
   * }
   ***********************************************************************/
  function renderFocusAreaRecords(data, packageId) {
    const focusAreaRecordsContainer = document.getElementById('focus-area-records-container');
    if (!focusAreaRecordsContainer) return;
    focusAreaRecordsContainer.innerHTML = '';

    const focusAreaGroups = data.focus_areas || {};
    if (Object.keys(focusAreaGroups).length === 0) {
      focusAreaRecordsContainer.innerHTML = '<p>No focus areas found in this package.</p>';
      return;
    }

    Object.entries(focusAreaGroups).forEach(([focusAreaName, faObj]) => {
      const versionNumber = faObj.version  || 0;
      const versionId     = faObj.versionId|| 0;
      const recArray      = Array.isArray(faObj.records) ? faObj.records : [];

      // Create container for this focus area
      const focusAreaElem = document.createElement('div');
      focusAreaElem.classList.add('focus-area-tile', 'focus-area-record-item');
      focusAreaElem.dataset.packageId          = packageId;
      focusAreaElem.dataset.focusAreaName      = focusAreaName;
      focusAreaElem.dataset.focusAreaVersion   = versionNumber;
      focusAreaElem.dataset.focusAreaVersionId = versionId;
      focusAreaElem.dataset.dirty              = 'false';
      focusAreaElem.dataset.exportData         = JSON.stringify(recArray);

      // Build header
      const header = document.createElement('div');
      header.classList.add('focus-area-record-group-header');
      const toggleIcon = document.createElement('span');
      toggleIcon.classList.add('focus-area-toggle');
      toggleIcon.textContent = '➕';

      const titleWrapper = document.createElement('div');
      titleWrapper.style.display = 'inline-flex';
      titleWrapper.style.alignItems = 'center';

      const h3Title = document.createElement('h3');
      h3Title.textContent = focusAreaName + ' (v' + versionNumber + ')';

      const recordCountSpan = document.createElement('span');
      recordCountSpan.classList.add('record-count-lbl');
      recordCountSpan.style.marginLeft = '6px';
      recordCountSpan.textContent = '(' + recArray.length + ')';

      titleWrapper.appendChild(toggleIcon);
      titleWrapper.appendChild(h3Title);
      titleWrapper.appendChild(recordCountSpan);
      header.appendChild(titleWrapper);

      // Buttons on the right side
      const buttonGroup = document.createElement('div');
      buttonGroup.classList.add('button-group');

      // Save changes button
      const saveChangesBtn = document.createElement('button');
      saveChangesBtn.classList.add('focus-area-save-btn');
      saveChangesBtn.title = 'Save changes for this focus area';
      saveChangesBtn.textContent = '💾';
      saveChangesBtn.style.display = 'none';
      saveChangesBtn.onclick = function() {
        saveFocusAreaRecords(packageId, focusAreaName, versionId, recArray);
      };

      // Feedback indicator
      const feedbackIndicator = document.createElement('span');
      feedbackIndicator.classList.add('feedback-indicator');
      feedbackIndicator.style.display = 'none';

      // Refine Data button
      const refineBtn = document.createElement('button');
      refineBtn.classList.add('refine-data-btn');
      refineBtn.textContent = 'Refine Data';
      refineBtn.addEventListener('click', function(evt) {
        evt.stopPropagation();
        if (window.RefineEventsModule && typeof RefineEventsModule.toggleRefineDropdown === 'function') {
          RefineEventsModule.toggleRefineDropdown(focusAreaElem, refineBtn);
        } else {
          console.warn('No refineEventsModule or toggleRefineDropdown found.');
        }
      });

      // Export CSV button
      const exportBtn = document.createElement('button');
      exportBtn.classList.add('export-csv-btn');
      exportBtn.textContent = 'Export CSV';

      // +New record icon
      const newRecordIcon = document.createElement('span');
      newRecordIcon.classList.add('new-record-icon');
      newRecordIcon.title = 'Create a new record for this focus area';
      newRecordIcon.textContent = '➕';
      newRecordIcon.onclick = function() {
        handleNewRecordClick(focusAreaElem, recArray, saveChangesBtn);
      };

      // Append them
      buttonGroup.appendChild(saveChangesBtn);
      buttonGroup.appendChild(feedbackIndicator);
      buttonGroup.appendChild(refineBtn);
      buttonGroup.appendChild(exportBtn);
      buttonGroup.appendChild(newRecordIcon);

      header.appendChild(buttonGroup);
      focusAreaElem.appendChild(header);

      // Convert each record => ephemeral + user fields
      recArray.forEach((recObj) => {
        const splitted = splitEphemeralFromUserFields(recObj);
        recObj.ephemeral = splitted;
        if (!('_dbOriginalProps' in recObj)) {
          recObj._dbOriginalProps = JSON.parse(JSON.stringify(recObj.axia_properties));
        }
        if (!('_changedKeys' in recObj)) {
          recObj._changedKeys = [];
        }
      });

      // Create record cards
      recArray.forEach((r, idx) => {
        const recDiv = createRecordContainer(r, idx, focusAreaElem);
        recDiv.style.display = 'none'; // collapsed
        focusAreaElem.appendChild(recDiv);
      });

      loadFeedbackIndicator(feedbackIndicator, packageId, focusAreaName);
      focusAreaRecordsContainer.appendChild(focusAreaElem);
    });

    // If you have an ExportCSVModule, re-init
    if (window.ExportCSVModule && typeof ExportCSVModule.setupExportButtons === 'function') {
      ExportCSVModule.setupExportButtons();
    }
  }

  /**
   * Creates the DOM for a single record card.
   * Uses ephemeral.display_order for the heading label if present.
   */
  function createRecordContainer(record, index, focusAreaContainer) {
    const recordContainer = document.createElement('div');
    recordContainer.classList.add('focus-area-record-card');

    // Attempt to read ephemeral.display_order, fallback to (index+1) if missing
    let displayOrder = (typeof record.ephemeral.display_order === 'number')
      ? record.ephemeral.display_order
      : (index + 1);

    const heading = document.createElement('h4');
    heading.textContent = 'Record ' + displayOrder;

    // If input_text_summaries_id is present => show copy icon
    if (record.ephemeral.input_text_summaries_id) {
      const idVal     = record.ephemeral.input_text_summaries_id;
      const titleVal  = record.ephemeral.input_text_title   || 'untitled';
      const summaryVal= record.ephemeral.input_text_summary || '';
      const copyIcon  = document.createElement('span');
      copyIcon.style.cursor      = 'pointer';
      copyIcon.style.marginLeft  = '8px';
      copyIcon.textContent       = '📋';
      copyIcon.title =
        'ID: ' + idVal + '\nTitle: ' + titleVal + '\nSummary: ' + summaryVal;
      copyIcon.addEventListener('click', function(e) {
        e.stopPropagation();
        showInputTextOverlay({
          id: idVal,
          title: titleVal,
          summary: summaryVal,
          fullText: record.ephemeral.input_text
        });
      });
      heading.appendChild(copyIcon);
    }
    recordContainer.appendChild(heading);

    refreshRecordDisplay(recordContainer, record);

    // Color-coded background if changed/deleted
    const changedSet = new Set(record._changedKeys || []);
    if (record.is_deleted === 1) {
      if (changedSet.has('is_deleted')) {
        if (record._newlyCreated) {
          recordContainer.style.backgroundColor = '#d1ffd7'; // green
          heading.style.textDecoration = 'line-through';
          heading.style.color = '#000';
        } else {
          recordContainer.style.backgroundColor = '#fff9d4'; // gold
          heading.style.textDecoration = 'line-through';
          heading.style.color = '#000';
        }
      } else {
        recordContainer.classList.add('deleted-record');
      }
    } else if (record._newlyCreated) {
      recordContainer.style.backgroundColor = '#d1ffd7'; // green
    } else if (changedSet.size > 0) {
      recordContainer.style.backgroundColor = '#fff9d4'; // gold
    }

    // Double-click => open edit overlay
    recordContainer.ondblclick = function() {
      if (!window.EditRecordOverlayModule) {
        alert('EditRecordOverlayModule is unavailable.');
        return;
      }
      const dataForOverlay = JSON.parse(JSON.stringify(record));
      window.EditRecordOverlayModule.openEditOverlay(dataForOverlay, function(updated) {
        // Merge the updated data back into record
        Object.assign(record, updated);
        refreshRecordDisplay(recordContainer, record);

        const changed2 = new Set(record._changedKeys || []);
        if (record.is_deleted === 1) {
          if (changed2.has('is_deleted')) {
            if (record._newlyCreated) {
              recordContainer.style.backgroundColor = '#d1ffd7';
              heading.style.textDecoration = 'line-through';
              heading.style.color = '#000';
            } else {
              recordContainer.style.backgroundColor = '#fff9d4';
              heading.style.textDecoration = 'line-through';
              heading.style.color = '#000';
            }
          } else {
            recordContainer.classList.add('deleted-record');
          }
        } else {
          recordContainer.style.backgroundColor = '';
          heading.style.textDecoration = '';
          heading.style.color = '';
          if (record._newlyCreated) {
            recordContainer.style.backgroundColor = '#d1ffd7';
          } else if (changed2.size > 0) {
            recordContainer.style.backgroundColor = '#fff9d4';
          }
        }
        // Also update heading if display_order changed
        if (typeof record.ephemeral.display_order === 'number') {
          heading.textContent = 'Record ' + record.ephemeral.display_order;
        }

        focusAreaContainer.dataset.dirty = 'true';
        const saveBtn = focusAreaContainer.querySelector('.focus-area-save-btn');
        if (saveBtn) {
          saveBtn.style.display = 'inline-block';
        }
      });
    };

    return recordContainer;
  }

  /**
   * Rewrites the DOM content for the record card after user changes
   */
  function refreshRecordDisplay(recordContainer, record) {
    const oldParagraphs = recordContainer.querySelectorAll('p');
    oldParagraphs.forEach(p => p.remove());

    const changedSet = new Set(record._changedKeys || []);
    if (!record.axia_properties) return;

    for (const [propName, propVal] of Object.entries(record.axia_properties)) {
      const p = document.createElement('p');
      if (changedSet.has(propName)) {
        p.innerHTML = `<span style="color:red"><strong>${propName}:</strong> ${propVal}</span>`;
      } else {
        p.innerHTML = `<strong>${propName}:</strong> ${propVal}`;
      }
      recordContainer.appendChild(p);
    }
  }

  /**
   * handleNewRecordClick => user adds a brand-new record in the UI
   * (which will get "focusAreaRecordID":"new" in final JSON).
   */
  function handleNewRecordClick(focusAreaElem, recordsArray, saveBtn) {
    if (!window.NewRecordOverlayModule) {
      alert('NewRecordOverlayModule is unavailable.');
      return;
    }
    // gather property keys from existing records
    const unionKeys = new Set();
    recordsArray.forEach(r => {
      if (r.axia_properties && typeof r.axia_properties === 'object') {
        Object.keys(r.axia_properties).forEach(k => unionKeys.add(k));
      }
    });
    const defaultProps = {};
    unionKeys.forEach(k => {
      defaultProps[k] = '';
    });

    // open overlay
    window.NewRecordOverlayModule.openNewOverlay(function(newRec) {
      newRec._newlyCreated = true;
      if (!newRec._dbOriginalProps) {
        newRec._dbOriginalProps = {};
      }
      if (!Array.isArray(newRec._changedKeys)) {
        newRec._changedKeys = [];
      }
      recordsArray.push(newRec);
      reRenderFocusArea(focusAreaElem, recordsArray);
      keepFocusAreaExpanded(focusAreaElem);

      focusAreaElem.dataset.dirty = 'true';
      saveBtn.style.display = 'inline-block';
    }, defaultProps);
  }

  /**
   * Re-renders the entire tile
   */
  function reRenderFocusArea(focusAreaElem, recordsArray) {
    const oldRecords = focusAreaElem.querySelectorAll('.focus-area-record-card');
    oldRecords.forEach(r => r.remove());

    const countSpan = focusAreaElem.querySelector('.record-count-lbl');
    if (countSpan) {
      countSpan.textContent = '(' + recordsArray.length + ')';
    }

    recordsArray.forEach((r, idx) => {
      const recDiv = createRecordContainer(r, idx, focusAreaElem);
      recDiv.style.display = 'none';
      focusAreaElem.appendChild(recDiv);
    });
  }

  /**
   * Expands the tile to show all records
   */
  function keepFocusAreaExpanded(focusAreaElem) {
    const toggleIcon = focusAreaElem.querySelector('.focus-area-toggle');
    const records    = focusAreaElem.querySelectorAll('.focus-area-record-card');
    if (toggleIcon && records.length > 0) {
      records.forEach(r => { r.style.display = 'block'; });
      toggleIcon.textContent = '➖';
    }
  }

  /**
   * saveFocusAreaRecords => posts the final data to save_revised_records.php.
   * We keep *all* items, including is_deleted=1, so the server can handle them.
   */
  async function saveFocusAreaRecords(packageId, focusAreaName, versionId, records) {
    try {
      if (window.RefineUtilsModule && typeof RefineUtilsModule.showPageMaskSpinner === 'function') {
        RefineUtilsModule.showPageMaskSpinner('Saving changes...');
      }

      const finalRecords = records.map(r => buildRecordPayload(r));
      const payload = {
        package_id:            packageId,
        focus_area_name:       focusAreaName,
        focus_area_version_id: versionId,
        focus_area_records:    finalRecords,
        summary_of_revisions:  null
      };

      const resp = await fetch('save_revised_records.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const json = await resp.json();

      if (window.RefineUtilsModule && typeof RefineUtilsModule.hidePageMaskSpinner === 'function') {
        RefineUtilsModule.hidePageMaskSpinner();
      }

      if (json.status === 'success') {
        if (typeof window.reloadRefineTabAndOpenPackage === 'function') {
          window.reloadRefineTabAndOpenPackage(packageId, focusAreaName);
        } else {
          window.location.reload();
        }
      } else {
        throw new Error(json.message || 'Unknown error saving focus area data');
      }
    } catch (err) {
      if (window.RefineUtilsModule && typeof RefineUtilsModule.hidePageMaskSpinner === 'function') {
        RefineUtilsModule.hidePageMaskSpinner();
      }
      alert('Error saving focus area: ' + err.message);
      console.error('saveFocusAreaRecords error:', err);
    }
  }

  /**
   * buildRecordPayload => uses numeric ID if we have one, else 'new'.
   * Also preserves is_deleted, axia_properties.
   */
  function buildRecordPayload(rec) {
    const props = rec.axia_properties || {};

    let finalId = 'new';
    if (rec.focusAreaRecordID && /^[0-9]+$/.test(rec.focusAreaRecordID)) {
      finalId = rec.focusAreaRecordID;
    } else if (typeof rec.id === 'number') {
      finalId = String(rec.id);
    }
    return {
      focusAreaRecordID: finalId,
      is_deleted: rec.is_deleted || 0,
      axia_properties: props
    };
  }

  /**
   * loadFeedbackIndicator => aggregator for unreviewed feedback
   */
  function loadFeedbackIndicator(feedbackIndicator, packageId, focusAreaName) {
    const queryParams =
      'package_id=' + packageId +
      '&focus_area_name=' + encodeURIComponent(focusAreaName) +
      '&include_all_versions=1';
    const url = 'fetch_stakeholder_feedback.php?' + queryParams;

    fetch(url)
      .then(r => r.json())
      .then(data => {
        if (data.status !== 'success' || !data.summaryData) {
          return;
        }
        const gfArr = data.summaryData.generalFeedback || [];
        const irArr = data.summaryData.itemizedRecords || [];
        let unreviewedCount = 0;

        gfArr.forEach((gItem) => {
          const gfUnrev = (gItem.feedbackCounts && gItem.feedbackCounts.Unreviewed) || 0;
          unreviewedCount += gfUnrev;
        });
        irArr.forEach((iItem) => {
          const irUnrev = (iItem.feedbackCounts && iItem.feedbackCounts.Unreviewed) || 0;
          unreviewedCount += irUnrev;
        });

        if (unreviewedCount > 0) {
          feedbackIndicator.style.display = 'inline-block';
          feedbackIndicator.title =
            unreviewedCount + ' pending feedback item(s)';
          feedbackIndicator.textContent = '⚠ ';
        }
      })
      .catch(function(err) {
        console.error('[ui.js] loadFeedbackIndicator => aggregator fetch failed:', err);
      });
  }

  /**
   * showInputTextOverlay => purely UI for input_text_summaries
   */
  function showInputTextOverlay(opts) {
    const overlay = document.getElementById('input-text-overlay');
    if (!overlay) {
      console.error('Input Text overlay not found in DOM.');
      return;
    }
    overlay.style.display = 'flex';

    const idSpan      = document.getElementById('input-text-id');
    const titleSpan   = document.getElementById('input-text-title');
    const summarySpan = document.getElementById('input-text-summary');
    const textArea    = document.getElementById('input-text-text');
    const copyBtn     = document.getElementById('copy-input-text-btn');
    const closeBtn    = document.getElementById('close-input-text-overlay');

    if (idSpan)      { idSpan.textContent      = String(opts.id || ''); }
    if (titleSpan)   { titleSpan.textContent   = opts.title || ''; }
    if (summarySpan) { summarySpan.textContent = opts.summary || ''; }
    if (textArea)    { textArea.value          = opts.fullText || ''; }

    if (copyBtn) {
      copyBtn.onclick = function() {
        if (textArea) {
          textArea.select();
          document.execCommand('copy');
        }
      };
    }
    if (closeBtn) {
      closeBtn.onclick = function() {
        overlay.style.display = 'none';
      };
    }
  }

  /**
   * setupFocusAreaToggles => expand/collapse toggles for each focus area
   */
  function setupFocusAreaToggles() {
    document.querySelectorAll('.focus-area-tile').forEach(function(item) {
      const toggleIcon = item.querySelector('.focus-area-toggle');
      const records    = item.querySelectorAll('.focus-area-record-card');
      if (toggleIcon && records.length > 0) {
        toggleIcon.addEventListener('click', function() {
          const isExpanded = (toggleIcon.textContent === '➖');
          if (isExpanded) {
            records.forEach(r => { r.style.display = 'none'; });
            toggleIcon.textContent = '➕';
          } else {
            records.forEach(r => { r.style.display = 'block'; });
            toggleIcon.textContent = '➖';
          }
        });
      }
    });
  }

  // Return public methods
  return {
    renderPackages,
    renderFocusAreaRecords,
    setupFocusAreaToggles
  };
})();
