/****************************************************************************
 * /js/apply-revisions-handler.js
 *
 * Key logic for the "Revisions Summary" overlay and final AI submission.
 *
 * FIXES:
 *   - We now send 'focusAreaRecordID' in the JSON (instead of 'recordID')
 *     so the server-side code actually recognizes existing records vs. "new".
 *   - We rename the final 'records' array to 'focus_area_records' to match
 *     the server script, which reads `$data['focus_area_records']`.
 *
 * This ensures the user’s updated field values are applied in the new version.
 ****************************************************************************/
var ApplyRevisionsHandler = (function() {
  let storedFeedbackData          = null;
  let lastPackageId               = 0;
  let lastFocusAreaVersionId      = 0; // DB row ID
  let lastFocusAreaVersionNumber  = 0; // actual version number
  let lastPackageName             = '';
  let lastFocusAreaName           = '';
  let lastSummaryOfRevisions      = '';
  let allFocusAreaRecords         = [];

  // Helper to get client date/time for AI payload
  function getClientDateTime() {
    const now = new Date();
    return {
      datetime: now.toLocaleString(),
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
    };
  }

  /**
   * Called from collateFeedback => "Apply Revisions".
   *
   * @param {number} packageId
   * @param {number} focusAreaVersionId      The DB row ID for the current version
   * @param {number} focusAreaVersionNumber  The user-facing version number
   * @param {Array}  revisionsData           The array of user-chosen feedback actions
   * @param {string} packageName
   * @param {string} focusAreaName
   * @param {Array}  fullRecordSet           The aggregator’s current-version rows
   */
  function processRevisions(
    packageId,
    focusAreaVersionId,
    focusAreaVersionNumber,
    revisionsData,
    packageName,
    focusAreaName,
    fullRecordSet = []
  ) {
    console.log('[apply-revisions-handler.js] processRevisions:', {
      packageId,
      focusAreaVersionId,
      focusAreaVersionNumber,
      revisionsData,
      packageName,
      focusAreaName,
      fullRecordSet
    });

    // Store these globally
    lastPackageId               = packageId;
    lastFocusAreaVersionId      = focusAreaVersionId;
    lastFocusAreaVersionNumber  = focusAreaVersionNumber;
    lastPackageName             = packageName;
    lastFocusAreaName           = focusAreaName;

    // Filter or keep all records for the current version
    allFocusAreaRecords = fullRecordSet.filter(r => {
      if (r.analysis_package_focus_area_versions_id) {
        return parseInt(r.analysis_package_focus_area_versions_id, 10) === parseInt(focusAreaVersionId, 10);
      }
      return true; // fallback if that field is absent
    });

    // Merge feedback items
    storedFeedbackData = mergeFeedbackByRecord(revisionsData);

    showSpinnerOverlay('Preparing specified revisions for integration...');
    setTimeout(() => {
      hideSpinnerOverlay();
      displayRevisionsSummaryOverlay(storedFeedbackData);
    }, 0);
  }

  /**
   * merges feedback items by (feedbackSource + recordId)
   */
  function mergeFeedbackByRecord(revisionsData) {
    const recordMap = new Map();
    revisionsData.forEach(item => {
      // unify them by a key e.g. "itemizedFeedback_12" or "generalFeedback_5"
      const recKey = `${item.feedbackSource}_${item.itemizedFeedbackRecordID || item.generalFeedbackRecordID || '0'}`;
      if (!recordMap.has(recKey)) {
        recordMap.set(recKey, {
          recordKey:             recKey,
          feedbackSource:        item.feedbackSource || 'generalFeedback',
          grid_index:            item.grid_index,
          recordNumber:          item.recordNumber || 0,
          originalContent:       item.originalContent || {},
          itemizedFeedbackRecordID: item.itemizedFeedbackRecordID || null,
          generalFeedbackRecordID:  item.generalFeedbackRecordID  || null,
          focusAreaRecordID:     item.focusAreaRecordID || 0,
          feedbackActions:       []
        });
      }
      recordMap.get(recKey).feedbackActions.push({
        action:       item.action,
        instructions: item.instructions,
        stakeholderEmail: item.stakeholderEmail || '',
        stakeholder_feedback_headers_id: item.stakeholder_feedback_headers_id
      });
    });
    return Array.from(recordMap.values());
  }

  /**
   * Builds the "Revisions Summary" overlay
   * - Sort itemized records by ascending recordNumber
   */
  function displayRevisionsSummaryOverlay(mergedData) {
    removeAnyExistingOverlay('revisions-summary-overlay');

    // separate general vs. itemized
    const generalData  = [];
    const itemizedData = [];
    mergedData.forEach(rec => {
      if (rec.feedbackSource === 'itemizedFeedback') {
        itemizedData.push(rec);
      } else {
        generalData.push(rec);
      }
    });

    // Sort itemized feedback by recordNumber ascending
    itemizedData.sort((a,b) => {
      const an = parseInt(a.recordNumber || 0, 10);
      const bn = parseInt(b.recordNumber || 0, 10);
      return an - bn;
    });

    const overlay = document.createElement('div');
    overlay.className = 'overlay';
    overlay.id        = 'revisions-summary-overlay';

    const content = document.createElement('div');
    content.className = 'overlay-content';

    const title = document.createElement('h2');
    title.textContent = 'Revisions Summary';

    // Show e.g. "Analysis Package Stakeholders (v2) focus area in "My Package""
    const subtitle = document.createElement('p');
    subtitle.textContent = `${lastFocusAreaName} (v${lastFocusAreaVersionNumber}) focus area in "${lastPackageName}"`;

    const closeButton = document.createElement('span');
    closeButton.className = 'close-overlay';
    closeButton.innerHTML = '&times;';
    closeButton.setAttribute('aria-label', 'Close Revisions Summary');
    closeButton.addEventListener('click', () => {
      hideOverlay(overlay);
      const collateOverlay = document.getElementById('collate-feedback-overlay');
      if (collateOverlay) {
        collateOverlay.style.display = 'flex';
      }
    });

    const revisionsContainer = document.createElement('div');
    revisionsContainer.className = 'revisions-list-container';

    // ---- GENERAL FEEDBACK
    const genSectionHeader = document.createElement('h3');
    genSectionHeader.textContent = 'General Feedback Inputs';
    revisionsContainer.appendChild(genSectionHeader);

    const genList = document.createElement('div');
    genList.className = 'revisions-list general-feedback-list';

    if (generalData.length > 0) {
      generalData.forEach((rec, i) => {
        const genContainer = document.createElement('div');
        genContainer.className = 'revision-record';

        const header = document.createElement('div');
        header.className = 'stakeholder-info';
        header.innerHTML =
          `<strong>GENERAL FEEDBACK #${i + 1}</strong> - Stakeholder(s): ` +
          listStakeholderEmails(rec.feedbackActions);

        const actionBlock = document.createElement('div');
        actionBlock.className = 'revision-action';

        rec.feedbackActions.forEach(fa => {
          const itemDiv = document.createElement('div');
          itemDiv.className = 'single-action-item';
          itemDiv.innerHTML = `<br><strong>INSTRUCTION:</strong>`;
          const textarea = document.createElement('textarea');
          textarea.rows = 3;
          textarea.style.width = '100%';
          textarea.value = fa.instructions || '';
          textarea.addEventListener('input', () => {
            fa.instructions = textarea.value;
          });
          itemDiv.appendChild(textarea);
          actionBlock.appendChild(itemDiv);
        });

        genContainer.appendChild(header);
        genContainer.appendChild(actionBlock);
        genList.appendChild(genContainer);
      });
    } else {
      const noneP = document.createElement('p');
      noneP.textContent = 'No general feedback instructions.';
      genList.appendChild(noneP);
    }
    revisionsContainer.appendChild(genList);

    // ---- ITEMIZED FEEDBACK
    const itemSectionHeader = document.createElement('h3');
    itemSectionHeader.textContent = 'Itemized Feedback Inputs';
    revisionsContainer.appendChild(itemSectionHeader);

    const itemList = document.createElement('div');
    itemList.className = 'revisions-list itemized-feedback-list';

    if (itemizedData.length > 0) {
      itemizedData.forEach((rec, i) => {
        const recordDiv = document.createElement('div');
        recordDiv.className = 'revision-record';

        const header = document.createElement('div');
        header.className = 'stakeholder-info';
        const recordLabel = rec.recordNumber || (i + 1);
        header.innerHTML =
          `<strong>RECORD ${recordLabel}</strong> - Stakeholder(s): ` +
          listStakeholderEmails(rec.feedbackActions);

        // Show current content
        const origDiv = document.createElement('div');
        origDiv.className = 'original-content';
        const currentPropsObj = rec.originalContent || {};
        origDiv.innerHTML = `
          <strong>CURRENT CONTENT</strong>
          ${formatProperties(currentPropsObj)}
        `;

        const actionBlock = document.createElement('div');
        actionBlock.className = 'revision-action';

        rec.feedbackActions.forEach((fa, idx2) => {
          const itemDiv = document.createElement('div');
          itemDiv.className = 'single-action-item';
          itemDiv.innerHTML = `<br><strong>INSTRUCTION #${idx2 + 1}:</strong>`;

          const textarea = document.createElement('textarea');
          textarea.rows = 3;
          textarea.style.width = '100%';
          textarea.value = fa.instructions || '';
          textarea.addEventListener('input', () => {
            fa.instructions = textarea.value;
          });
          itemDiv.appendChild(textarea);
          actionBlock.appendChild(itemDiv);
        });

        recordDiv.appendChild(header);
        recordDiv.appendChild(origDiv);
        recordDiv.appendChild(actionBlock);
        itemList.appendChild(recordDiv);
      });
    } else {
      const noneP = document.createElement('p');
      noneP.textContent = 'No itemized feedback instructions.';
      itemList.appendChild(noneP);
    }
    revisionsContainer.appendChild(itemList);

    // "Process Revisions" button
    const processButton = document.createElement('button');
    processButton.className = 'process-revisions-btn';
    processButton.textContent = 'Process Revisions';
    processButton.addEventListener('click', () => {
      hideOverlay(overlay);
      sendRevisionsToAi(storedFeedbackData);
    });

    content.appendChild(closeButton);
    content.appendChild(title);
    content.appendChild(subtitle);
    content.appendChild(revisionsContainer);
    content.appendChild(processButton);

    overlay.appendChild(content);
    document.body.appendChild(overlay);
  }

  function listStakeholderEmails(actions) {
    const uniqueEmails = new Set(actions.map(a => a.stakeholderEmail).filter(Boolean));
    return uniqueEmails.size ? Array.from(uniqueEmails).join(', ') : 'N/A';
  }

  function formatProperties(propsObj) {
    if (!propsObj || typeof propsObj !== 'object') {
      return '';
    }
    let result = '';
    for (const [k, v] of Object.entries(propsObj)) {
      if (k === 'is_deleted') continue;
      if (v && typeof v === 'object') {
        result += `<strong><br>${k}:</strong> ${JSON.stringify(v)}`;
      } else {
        result += `<strong><br>${k}:</strong> ${v}`;
      }
    }
    return result;
  }

  /**
   * Send instructions to AI
   */
  function sendRevisionsToAi(mergedData) {
    showSpinnerOverlay('Awaiting revision recommendations from AI...');

    const inputRecords        = [];
    const generalInstructions = [];

    mergedData.forEach(rec => {
      const instructionsArr = rec.feedbackActions
        .filter(a => a.action === 'Apply' || a.action === 'Add Instruction')
        .map(a => (a.instructions || '').trim())
        .filter(txt => txt.length > 0);

      if (rec.feedbackSource === 'generalFeedback') {
        generalInstructions.push(...instructionsArr);
      } else {
        // "itemizedFeedback"
        const numericId = parseInt(rec.focusAreaRecordID, 10);
        const finalFocusAreaRecordID = (numericId > 0) ? String(numericId) : 'new';
        const finalFocusAreaRecordNumber = String(rec.recordNumber || 0);

        inputRecords.push({
          grid_index:       String(rec.grid_index ?? 0),
          focusAreaRecordID: finalFocusAreaRecordID,         // Important field name
          focusAreaRecordNumber: finalFocusAreaRecordNumber, // used for labeling
          is_deleted:       '0',
          axia_properties:  { ...rec.originalContent },
          axia_item_instructions: instructionsArr
        });
      }
    });

    // If we have general instructions, replicate unaffected records
    if (generalInstructions.length > 0) {
      allFocusAreaRecords.forEach(r => {
        if (String(r.is_deleted) !== '1') {
          const recIdStr = String(r.focusAreaRecordID || '');
          const alreadyIn = inputRecords.some(ir => ir.focusAreaRecordID === recIdStr);
          if (!alreadyIn) {
            inputRecords.push({
              grid_index:       String(r.grid_index || 0),
              focusAreaRecordID: recIdStr || 'new',
              focusAreaRecordNumber: String(r.display_order || 0),
              is_deleted:       '0',
              axia_properties:  { ...r.properties },
              axia_item_instructions: []
            });
          }
        }
      });
    }

    const dateTimeInfo = getClientDateTime();
    const payload = {
      template: 'Compile_Mixed_Feedback',
      text: JSON.stringify({
        axia_data: {
          packageId:         String(lastPackageId),
          focusAreaVersion:  String(lastFocusAreaVersionNumber),
          axia_focus_area:   lastFocusAreaName,
          axia_input: {
            input_records:            inputRecords,
            axia_general_instructions: generalInstructions
          }
        }
      }),
      metadata: {
        datetime:  dateTimeInfo.datetime,
        timezone:  dateTimeInfo.timezone
      }
    };

    const endpoint = window.AxiaBAConfig.api_base_url + '/aii_helper.php';
    fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-API-Key': window.AxiaBAConfig.api_key
      },
      body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
      hideSpinnerOverlay();
      if (data.status !== 'success') {
        throw new Error(data.message || 'Failed to process Compile_Mixed_Feedback from AI');
      }
      const axiaOutput = data.axia_output;
      if (!axiaOutput) {
        throw new Error('Missing axia_output in the AI response');
      }
      const finalRecords = axiaOutput.output_records ?? [];
      lastSummaryOfRevisions = axiaOutput.summary_of_revisions ?? '';
      displayRevisedRecordsOverlay(finalRecords, mergedData);
    })
    .catch(err => {
      hideSpinnerOverlay();
      console.error('[apply-revisions-handler.js] Error in sendRevisionsToAi:', err);
      alert(`Error applying revisions: ${err.message}`);
      displayRevisionsSummaryOverlay(storedFeedbackData);
    });
  }

  /**
   * Show "Revised Records" overlay
   * - Sort finalRecords by focusAreaRecordNumber ascending
   * - Label them as "Record [focusAreaRecordNumber]"
   */
  function displayRevisedRecordsOverlay(finalRecords, actionedFeedback) {
    removeAnyExistingOverlay('revised-records-overlay');

    const overlay = document.createElement('div');
    overlay.className = 'overlay';
    overlay.id        = 'revised-records-overlay';

    const content = document.createElement('div');
    content.className = 'overlay-content';

    const closeButton = document.createElement('span');
    closeButton.className = 'close-overlay';
    closeButton.innerHTML = '&times;';
    closeButton.setAttribute('aria-label', 'Close Revised Records');
    closeButton.addEventListener('click', () => {
      hideOverlay(overlay);
      displayRevisionsSummaryOverlay(storedFeedbackData);
    });

    const title = document.createElement('h2');
    title.textContent = 'Revised Records';

    const subtitle = document.createElement('p');
    subtitle.textContent = `${lastFocusAreaName} focus area in "${lastPackageName}"`;

    // summary box
    const summaryWrapper = document.createElement('div');
    summaryWrapper.style.margin       = '10px 0';
    summaryWrapper.style.padding      = '10px';
    summaryWrapper.style.background   = '#f7f7f7';
    summaryWrapper.style.border       = '1px solid #ddd';
    summaryWrapper.style.borderRadius = '6px';

    const summaryLabel = document.createElement('h3');
    summaryLabel.textContent = 'Summary of Revisions';
    summaryLabel.style.marginTop = '0';

    const summaryText = document.createElement('p');
    summaryText.textContent = lastSummaryOfRevisions || '(No summary provided)';

    summaryWrapper.appendChild(summaryLabel);
    summaryWrapper.appendChild(summaryText);

    // Copy finalRecords so we can sort them
    const combinedRecords = [...finalRecords];

    // Sort them by focusAreaRecordNumber ascending, fallback 9999 if missing
    combinedRecords.sort((a, b) => {
      const aNum = parseInt(a.focusAreaRecordNumber, 10) || 9999;
      const bNum = parseInt(b.focusAreaRecordNumber, 10) || 9999;
      return aNum - bNum;
    });

    const form = document.createElement('form');
    form.id = 'revised-records-form';

    combinedRecords.forEach((record, idx) => {
      const fs = document.createElement('fieldset');
      fs.className = 'record-fieldset';

      const recordType = classifyRecord(record);
      fs.classList.add(`${recordType}-record`);

      const recordNum = parseInt(record.focusAreaRecordNumber, 10) || (idx + 1);
      const legend = document.createElement('legend');
      legend.textContent = `Record ${recordNum} (${recordType})`;
      fs.appendChild(legend);

      // Instead of "recordID", we send "focusAreaRecordID"
      createHidden(fs, `focus_area_records[${idx}][focusAreaRecordID]`, record.focusAreaRecordID ?? 'new');
      createHidden(fs, `focus_area_records[${idx}][grid_index]`, record.grid_index ?? '');
      createHidden(fs, `focus_area_records[${idx}][is_deleted]`, record.is_deleted ?? '0');

      // Render each property as a text field
      if (record.axia_properties && typeof record.axia_properties === 'object') {
        for (const [propKey, propVal] of Object.entries(record.axia_properties)) {
          createTextField(fs, idx, propKey, propVal);
        }
      }

      form.appendChild(fs);
    });

    const buttonContainer = document.createElement('div');
    buttonContainer.style.marginTop = '20px';
    buttonContainer.style.display   = 'flex';
    buttonContainer.style.gap       = '10px';

    const commitButton = document.createElement('button');
    commitButton.type = 'button';
    commitButton.textContent = 'Commit Changes';
    commitButton.className   = 'overlay-button commit-btn';
    commitButton.addEventListener('click', () => {
      saveRevisedRecords(form, actionedFeedback);
    });

    const tryAgainButton = document.createElement('button');
    tryAgainButton.type = 'button';
    tryAgainButton.textContent = 'Try Again';
    tryAgainButton.className   = 'overlay-button cancel-btn';
    tryAgainButton.addEventListener('click', () => {
      hideOverlay(overlay);
      displayRevisionsSummaryOverlay(storedFeedbackData);
    });

    buttonContainer.appendChild(commitButton);
    buttonContainer.appendChild(tryAgainButton);

    content.appendChild(closeButton);
    content.appendChild(title);
    content.appendChild(subtitle);
    content.appendChild(summaryWrapper);
    content.appendChild(form);
    content.appendChild(buttonContainer);

    overlay.appendChild(content);
    document.body.appendChild(overlay);

    addTempRecordStyles();
  }

  function isNewItem(r) {
    const g = String(r.grid_index || '').toLowerCase();
    const i = String(r.focusAreaRecordID   || '').toLowerCase();
    return (g === 'new' || i === 'new');
  }

  function classifyRecord(record) {
    if (String(record.is_deleted) === '1') {
      return 'deleted';
    }
    if (isNewItem(record)) {
      return 'created';
    }
    return 'updated';
  }

  function createTextField(fieldset, idx, propKey, propVal) {
    const fieldDiv = document.createElement('div');
    fieldDiv.className = 'field-container';

    const label = document.createElement('label');
    label.textContent = propKey;
    label.htmlFor = `revrec-${idx}-${propKey}`;

    const input = document.createElement('input');
    input.type  = 'text';
    input.id    = `revrec-${idx}-${propKey}`;
    // Note how we nest inside focus_area_records[...] so the server sees it:
    input.name  = `focus_area_records[${idx}][axia_properties][${propKey}]`;
    input.value = (propVal !== undefined) ? String(propVal) : '';

    fieldDiv.appendChild(label);
    fieldDiv.appendChild(input);
    fieldset.appendChild(fieldDiv);
  }

  function createHidden(fs, name, value) {
    const hidden = document.createElement('input');
    hidden.type  = 'hidden';
    hidden.name  = name;
    hidden.value = String(value);
    fs.appendChild(hidden);
  }

  function addTempRecordStyles() {
    if (document.getElementById('temp-record-styles')) return;
    const style = document.createElement('style');
    style.id = 'temp-record-styles';
    style.innerHTML = `
      fieldset.created-record {
        background-color: #ccffd8; /* green => created */
      }
      fieldset.updated-record {
        background-color: #fff1b8; /* gold => updated */
      }
      fieldset.deleted-record {
        background-color: #ffcfcf; /* red => deleted */
      }
    `;
    document.head.appendChild(style);
  }

  /**
   * Actually POST to /save_revised_records.php
   * The server expects these fields:
   *   package_id
   *   focus_area_name
   *   focus_area_version_id
   *   focus_area_records => [ { focusAreaRecordID, is_deleted, axia_properties, ... }, ... ]
   */
  function saveRevisedRecords(form, actionedFeedback) {
    showSpinnerOverlay('Saving changes and creating new focus-area version...');

    const recordFieldsets = form.querySelectorAll('.record-fieldset');
    const newRecords = [];

    // We'll gather user inputs from each fieldset
    recordFieldsets.forEach(fs => {
      // We'll build an object that matches the server's expected structure:
      // {
      //   focusAreaRecordID:  '123' or 'new',
      //   is_deleted: '0' or '1',
      //   grid_index: '0' (or whatever),
      //   axia_properties: {...}
      // }
      const recObj = { axia_properties: {} };

      const inputs = fs.querySelectorAll('input, textarea');
      inputs.forEach(input => {
        const nm = input.name;
        const val = input.value || '';

        // Example: "focus_area_records[0][axia_properties][Fact]"
        // or "focus_area_records[0][focusAreaRecordID]"
        // We'll parse out the bracket segments:
        const match = nm.match(/^focus_area_records\[(\d+)\]\[([^\]]+)\](?:\[([^\]]+)\])?$/);
        if (!match) return;

        const subKey = match[2]; // e.g. "focusAreaRecordID", "axia_properties"
        const subSub = match[3] || null; // e.g. "Fact"

        if (subKey === 'axia_properties' && subSub) {
          // e.g. axia_properties["Fact"] = ...
          recObj.axia_properties[subSub] = val;
        } else {
          // e.g. recObj["focusAreaRecordID"] = ...
          recObj[subKey] = val;
        }
      });

      newRecords.push(recObj);
    });

    // Build the final payload
    const payload = {
      package_id:             lastPackageId,
      focus_area_name:        lastFocusAreaName,
      focus_area_version_id:  lastFocusAreaVersionId,
      focus_area_records:     newRecords,          // <--- crucial
      summary_of_revisions:   lastSummaryOfRevisions,
      actionedFeedback
    };

    fetch('/save_revised_records.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
      hideSpinnerOverlay();
      if (data.status === 'success') {
        alert('Changes saved successfully. New focus-area version created.');
        removeAnyExistingOverlay('revised-records-overlay');
        removeAnyExistingOverlay('collate-feedback-overlay');
        // e.g. reload or do something else
        window.location.reload();
      } else {
        console.error('[apply-revisions-handler] Error saving changes:', data);
        alert(`Error saving changes: ${data.message}`);
      }
    })
    .catch(error => {
      hideSpinnerOverlay();
      console.error('[apply-revisions-handler] Error in saveRevisedRecords:', error);
      alert(`Error saving changes: ${error.message}`);
    });
  }

  function showSpinnerOverlay(msg) {
    removeAnyExistingOverlay('spinner-overlay');
    const ov = document.createElement('div');
    ov.id = 'spinner-overlay';
    ov.className = 'page-mask';

    const spin = document.createElement('div');
    spin.className = 'spinner';

    const m = document.createElement('div');
    m.className = 'spinner-message';
    m.textContent = msg;

    ov.appendChild(spin);
    ov.appendChild(m);
    document.body.appendChild(ov);
  }

  function hideSpinnerOverlay() {
    removeAnyExistingOverlay('spinner-overlay');
  }

  function removeAnyExistingOverlay(overlayId) {
    const ex = document.getElementById(overlayId);
    if (ex) ex.remove();
  }

  function hideOverlay(ov) {
    if (ov) ov.remove();
  }

  // Public API
  return {
    processRevisions
  };
})();
