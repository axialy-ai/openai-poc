/****************************************************************************
 * content-review.js
 *
 * Enhanced to populate the 'Email Personal Message' field from `actionContent`
 * if provided by the 'FA_REQUEST' tile's advisement.
 *
 * Updated (April 3) to add a two-step “preview” flow:
 *   1) The user enters/selects stakeholder email(s) and clicks “Submit.” 
 *      Instead of sending, we open a new "preview overlay."
 *   2) The new overlay lists all records for the focus area. The user toggles 
 *      which record tiles to include. 
 *   3) The user can “Include All,” “Remove All,” or “Send Requests.” 
 *      Only included records’ grid_index values are sent to process_content_review_request.php.
 ****************************************************************************/

(function() {
  let feedbackResponseTypes = [];

  /**
   * Initialize the content review overlay as before (the first overlay).
   *
   * But now the actual sending to the server is *NOT* triggered in handleSubmit().
   * Instead, handleSubmit() opens the new preview overlay.
   */
  function init(focusAreaName, packageName, packageId, defaultPersonalMessage = '') {
    // If packageName looks like "1 - Something", remove the "1 - "
    const match = packageName.match(/^(\d+)\s*-\s*(.+)$/);
    if (match) {
      packageName = match[2].trim();
      console.log('[ContentReview] Stripped numeric prefix, new packageName:', packageName);
    }

    // 1) Load feedback-response-types
    loadResponseTypes()
      // 2) Then fetch row ID + numeric version for the focusAreaName
      .then(() => fetchFocusAreaVersionData(packageId, focusAreaName))
      // 3) Then fetch stakeholder emails
      .then(({ versionId, versionNumber }) =>
        Promise.all([
          versionId,
          versionNumber,
          fetchStakeholderFocusAreaEmails(packageId)
        ])
      )
      .then(([focusAreaVersionId, focusAreaNumber, stakeholderEmails]) => {
        console.log('[ContentReview] Resolved focusAreaVersionId:', focusAreaVersionId);
        console.log('[ContentReview] Resolved focusAreaNumber:', focusAreaNumber);

        renderInitialOverlay({
          packageId,
          packageName,
          focusAreaName,
          focusAreaVersionId,
          focusAreaDisplayVersion: focusAreaNumber,  // numeric
          stakeholderEmails,
          prefilledMessage: defaultPersonalMessage
        });
      })
      .catch(err => {
        console.error('[ContentReview] Error initializing content review:', err);
        // Show overlay anyway, but might have versionId=0
        renderInitialOverlay({
          packageId,
          packageName,
          focusAreaName,
          focusAreaVersionId: 0,
          focusAreaDisplayVersion: null,
          stakeholderEmails: [],
          prefilledMessage: defaultPersonalMessage
        });
      });
  }

  function loadResponseTypes() {
    return fetch('config/feedback-response-types.json')
      .then(resp => resp.json())
      .then(data => {
        if (data.feedbackResponseTypes && Array.isArray(data.feedbackResponseTypes)) {
          feedbackResponseTypes = data.feedbackResponseTypes.filter(t => t.active !== false);
        } else {
          feedbackResponseTypes = [];
        }
      })
      .catch(() => {
        feedbackResponseTypes = [];
      });
  }

  function fetchFocusAreaVersionData(packageId, focusAreaName) {
    return new Promise((resolve) => {
      const url = `fetch_analysis_package_focus_area_records.php?package_id=${encodeURIComponent(packageId)}&show_deleted=0`;
      fetch(url)
        .then(r => r.ok ? r.json() : Promise.reject('Error fetching focus areas.'))
        .then(data => {
          if (!data || !data.focus_areas) {
            return resolve({ versionId: 0, versionNumber: 0 });
          }
          const faObj = data.focus_areas[focusAreaName];
          if (!faObj) {
            return resolve({ versionId: 0, versionNumber: 0 });
          }
          const versionId = faObj.versionId || 0;
          const versionNumber = faObj.version || 0;
          resolve({ versionId, versionNumber });
        })
        .catch(err => {
          console.error('[ContentReview] fetchFocusAreaVersionData error:', err);
          resolve({ versionId: 0, versionNumber: 0 });
        });
    });
  }

  function fetchStakeholderFocusAreaEmails(packageId) {
    return new Promise((resolve, reject) => {
      const url = `fetch_analysis_package_focus_area_records.php?package_id=${encodeURIComponent(packageId)}&show_deleted=0`;
      fetch(url)
        .then(r => r.ok ? r.json() : Promise.reject('Error fetching stakeholder focus area.'))
        .then(data => {
          if (!data || !data.focus_areas) {
            return resolve([]);
          }
          const faObj = data.focus_areas['Analysis Package Stakeholders'];
          if (!faObj || !Array.isArray(faObj.records) || faObj.records.length === 0) {
            return resolve([]);
          }
          const emails = [];
          faObj.records.forEach(rec => {
            if (rec.properties && typeof rec.properties === 'object' && rec.properties.Email) {
              const e = String(rec.properties.Email).trim();
              if (e && e.includes('@')) {
                emails.push(e);
              }
            }
          });
          resolve(Array.from(new Set(emails)));
        })
        .catch(err => reject(err));
    });
  }

  /**
   * Renders the initial overlay (where user picks stakeholder emails, personal message, etc.).
   */
  function renderInitialOverlay({
    packageId,
    packageName,
    focusAreaName,
    focusAreaVersionId,
    focusAreaDisplayVersion,
    stakeholderEmails,
    prefilledMessage
  }) {
    removeOverlayById('content-review-overlay');
    let safeVersion = 'N/A';
    if (typeof focusAreaDisplayVersion === 'number' && focusAreaDisplayVersion > 0) {
      safeVersion = `v${focusAreaDisplayVersion}`;
    }
    const overlay = document.createElement('div');
    overlay.id = 'content-review-overlay';
    overlay.classList.add('overlay');
    overlay.setAttribute('role','dialog');
    overlay.setAttribute('aria-modal','true');

    const formDiv = document.createElement('div');
    formDiv.classList.add('content-review-form');

    const closeBtn = document.createElement('span');
    closeBtn.classList.add('close-overlay');
    closeBtn.setAttribute('aria-label','Close');
    closeBtn.innerHTML = '&times;';
    closeBtn.addEventListener('click', () => removeOverlayById('content-review-overlay'));

    const titleEl = document.createElement('h2');
    titleEl.textContent = 'Content Review Request';
    const subP = document.createElement('p');
    subP.classList.add('overlay-subtitle');
    subP.textContent =
      `Specify details for stakeholder feedback requests regarding ${focusAreaName} focus area (${safeVersion}) ` +
      `from the ${packageName} analysis package.`;

    const stakeLabel = document.createElement('label');
    stakeLabel.textContent = 'Analysis Package Stakeholders:';
    stakeLabel.classList.add('required-field');
    stakeLabel.setAttribute('for', 'stakeholders-select');

    const stakeSelect = document.createElement('select');
    stakeSelect.id = 'stakeholders-select';
    stakeSelect.multiple = true;
    stakeSelect.required = true;
    if (stakeholderEmails && stakeholderEmails.length > 0) {
      stakeholderEmails.forEach(email => {
        const opt = document.createElement('option');
        opt.value = email;
        opt.textContent = email;
        stakeSelect.appendChild(opt);
      });
    } else {
      const noOpt = document.createElement('option');
      noOpt.disabled = true;
      noOpt.selected = true;
      noOpt.textContent = 'No "Analysis Package Stakeholders" with valid Email.';
      stakeSelect.appendChild(noOpt);
      stakeSelect.disabled = true;
    }

    const adHocLabel = document.createElement('label');
    adHocLabel.textContent = 'Additional Ad-Hoc Emails (comma-separated):';
    adHocLabel.setAttribute('for', 'ad-hoc-emails');

    const adHocInput = document.createElement('input');
    adHocInput.type = 'text';
    adHocInput.id = 'ad-hoc-emails';
    adHocInput.placeholder = 'e.g. user1@example.com, user2@example.org';

    const infoMsg = document.createElement('p');
    infoMsg.classList.add('informative-message');
    infoMsg.textContent =
      `Specified stakeholders will receive a feedback request for "${focusAreaName}" ` +
      `in the "${packageName}" analysis package.`;

    const pmLabel = document.createElement('label');
    pmLabel.textContent = 'Email Personal Message (Optional)';
    pmLabel.setAttribute('for', 'personal-message');

    const pmTextarea = document.createElement('textarea');
    pmTextarea.id = 'personal-message';
    pmTextarea.rows = 4;
    pmTextarea.placeholder = 'Enter your personal message here...';
    pmTextarea.value = prefilledMessage || '';

    // "Form Type" selection
    const formTypeDiv = document.createElement('div');
    formTypeDiv.classList.add('form-type-selector');

    const formTypeLabel = document.createElement('label');
    formTypeLabel.classList.add('form-type-label');
    formTypeLabel.textContent = 'Feedback Form Type:';

    const formTypeOptions = document.createElement('div');
    formTypeOptions.classList.add('form-type-options');

    const generalWrap = document.createElement('div');
    const generalRadio = document.createElement('input');
    generalRadio.type = 'radio';
    generalRadio.id = 'form-type-general';
    generalRadio.name = 'formType';
    generalRadio.value = 'General';
    generalRadio.checked = true;
    const generalLbl = document.createElement('label');
    generalLbl.setAttribute('for', 'form-type-general');
    generalLbl.textContent = 'General Feedback';
    generalWrap.appendChild(generalRadio);
    generalWrap.appendChild(generalLbl);

    const itemizedWrap = document.createElement('div');
    const itemizedRadio = document.createElement('input');
    itemizedRadio.type = 'radio';
    itemizedRadio.id = 'form-type-itemized';
    itemizedRadio.name = 'formType';
    itemizedRadio.value = 'Itemized';
    const itemizedLbl = document.createElement('label');
    itemizedLbl.setAttribute('for', 'form-type-itemized');
    itemizedLbl.textContent = 'Itemized Feedback';
    itemizedWrap.appendChild(itemizedRadio);
    itemizedWrap.appendChild(itemizedLbl);

    formTypeOptions.appendChild(generalWrap);
    formTypeOptions.appendChild(itemizedWrap);

    formTypeDiv.appendChild(formTypeLabel);
    formTypeDiv.appendChild(formTypeOptions);

    // The itemized controls
    const itemizedControls = document.createElement('div');
    itemizedControls.id = 'itemized-controls';
    itemizedControls.classList.add('itemized-controls');

    const ifLabel = document.createElement('label');
    ifLabel.classList.add('required-field');
    ifLabel.textContent = 'Select Feedback Response Type:';
    ifLabel.setAttribute('for', 'feedback-response-type');

    const ifSelect = document.createElement('select');
    ifSelect.id = 'feedback-response-type';

    const ifDesc = document.createElement('p');
    ifDesc.id = 'response-type-description';
    ifDesc.classList.add('type-description');

    const primaryLbl = document.createElement('label');
    primaryLbl.classList.add('required-field');
    primaryLbl.textContent = 'Primary Response:';
    primaryLbl.setAttribute('for', 'primary-response-option');
    const primaryInput = document.createElement('input');
    primaryInput.type = 'text';
    primaryInput.id = 'primary-response-option';
    primaryInput.disabled = true;
    primaryInput.required = true;

    const secondaryLbl = document.createElement('label');
    secondaryLbl.textContent = 'Secondary Response (Optional):';
    secondaryLbl.setAttribute('for', 'secondary-response-option');
    const secondaryInput = document.createElement('input');
    secondaryInput.type = 'text';
    secondaryInput.id = 'secondary-response-option';
    secondaryInput.disabled = true;

    itemizedControls.appendChild(ifLabel);
    itemizedControls.appendChild(ifSelect);
    itemizedControls.appendChild(ifDesc);
    itemizedControls.appendChild(primaryLbl);
    itemizedControls.appendChild(primaryInput);
    itemizedControls.appendChild(secondaryLbl);
    itemizedControls.appendChild(secondaryInput);

    // “Submit” button now just opens the PREVIEW overlay:
    const submitBtn = document.createElement('button');
    submitBtn.type = 'button';
    submitBtn.classList.add('submit-btn');
    submitBtn.id = 'submit-content-review-btn';
    submitBtn.textContent = 'Submit';

    formDiv.appendChild(closeBtn);
    formDiv.appendChild(titleEl);
    formDiv.appendChild(subP);
    formDiv.appendChild(stakeLabel);
    formDiv.appendChild(stakeSelect);
    formDiv.appendChild(adHocLabel);
    formDiv.appendChild(adHocInput);
    formDiv.appendChild(infoMsg);
    formDiv.appendChild(pmLabel);
    formDiv.appendChild(pmTextarea);
    formDiv.appendChild(formTypeDiv);
    formDiv.appendChild(itemizedControls);
    formDiv.appendChild(submitBtn);

    overlay.appendChild(formDiv);
    document.body.appendChild(overlay);

    // Hide the itemized controls by default
    itemizedControls.style.display = 'none';

    populateResponseTypeDropdown(ifSelect, primaryInput, secondaryInput, ifDesc);

    generalRadio.addEventListener('change', () => {
      if (generalRadio.checked) {
        itemizedControls.style.display = 'none';
      }
    });
    itemizedRadio.addEventListener('change', () => {
      if (itemizedRadio.checked) {
        itemizedControls.style.display = 'block';
        resetItemizedSection(ifSelect, primaryInput, secondaryInput, ifDesc);
      }
    });
    ifSelect.addEventListener('change', () => {
      handleResponseTypeChange(ifSelect, primaryInput, secondaryInput, ifDesc);
    });

    submitBtn.addEventListener('click', () => {
      const selectedEmails = getSelectedEmails();
      if (!selectedEmails || selectedEmails.length === 0) {
        alert('Please select at least one stakeholder or add an ad-hoc email.');
        return;
      }
      const pmValue = (pmTextarea.value || '').trim();
      const formType = document.getElementById('form-type-general').checked ? 'General' : 'Itemized';

      let primaryVal = '';
      let secondaryVal = '';
      if (formType === 'Itemized') {
        if (ifSelect.value === 'custom') {
          primaryVal = primaryInput.value.trim();
          secondaryVal = secondaryInput.value.trim();
          if (!primaryVal) {
            alert('Primary Response is required for a custom itemized feedback form.');
            return;
          }
        } else {
          const idx = parseInt(ifSelect.value, 10);
          if (!isNaN(idx) && feedbackResponseTypes[idx]) {
            primaryVal = feedbackResponseTypes[idx].primaryResponse   || '';
            secondaryVal = feedbackResponseTypes[idx].secondaryResponse || '';
          }
        }
      }

      // Now open the new PREVIEW overlay (step 2)
      removeOverlayById('content-review-preview-overlay');
      openPreviewOverlay({
        packageId,
        packageName,
        focusAreaName,
        focusAreaVersionId,
        formType,
        personalMessage: pmValue,
        primaryResponse: primaryVal,
        secondaryResponse: secondaryVal,
        stakeholders: selectedEmails
      });
    });
  }

  function getSelectedEmails() {
    const sel = document.getElementById('stakeholders-select');
    let selectedEmails = [];
    if (sel && !sel.disabled) {
      selectedEmails = Array.from(sel.selectedOptions).map(o => o.value);
    }
    const adHocField = document.getElementById('ad-hoc-emails');
    let adHocEmails = [];
    if (adHocField) {
      const raw = (adHocField.value || '').trim();
      if (raw) {
        adHocEmails = raw.split(',').map(s => s.trim()).filter(e => e !== '');
      }
    }
    return [...selectedEmails, ...adHocEmails];
  }

  /**
   * Step 2: Open the preview overlay showing all records for the chosen focus area version,
   * letting the user select/deselect which records to include, then “Send Requests.”
   */
  function openPreviewOverlay({
    packageId,
    packageName,
    focusAreaName,
    focusAreaVersionId,
    formType,
    personalMessage,
    primaryResponse,
    secondaryResponse,
    stakeholders
  }) {
    showPageMaskSpinner('Fetching records...');
    const url = `fetch_analysis_package_focus_area_records.php?package_id=${encodeURIComponent(packageId)}&show_deleted=0`;
    fetch(url)
      .then(r => r.ok ? r.json() : Promise.reject('Error fetching records.'))
      .then(data => {
        hidePageMaskSpinner();
        if (!data || !data.focus_areas || !data.focus_areas[focusAreaName]) {
          alert('No focus area records found for preview.');
          return;
        }
        const faObj = data.focus_areas[focusAreaName];
        const records = faObj.records || [];
        renderPreviewOverlay({
          packageId,
          packageName,
          focusAreaName,
          focusAreaVersionId,
          formType,
          personalMessage,
          primaryResponse,
          secondaryResponse,
          stakeholders,
          records
        });
      })
      .catch(err => {
        hidePageMaskSpinner();
        console.error('openPreviewOverlay error:', err);
        alert('Failed to load focus area records for preview.');
      });
  }

  function renderPreviewOverlay({
    packageId,
    packageName,
    focusAreaName,
    focusAreaVersionId,
    formType,
    personalMessage,
    primaryResponse,
    secondaryResponse,
    stakeholders,
    records
  }) {
    removeOverlayById('content-review-preview-overlay');

    const overlay = document.createElement('div');
    overlay.id = 'content-review-preview-overlay';
    overlay.classList.add('overlay');
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');

    const formDiv = document.createElement('div');
    formDiv.classList.add('content-review-form');

    const closeBtn = document.createElement('span');
    closeBtn.classList.add('close-overlay');
    closeBtn.setAttribute('aria-label','Close');
    closeBtn.innerHTML = '&times;';
    closeBtn.addEventListener('click', () => removeOverlayById('content-review-preview-overlay'));

    const titleEl = document.createElement('h2');
    titleEl.textContent = 'Preview & Select Records';

    const subP = document.createElement('p');
    subP.classList.add('overlay-subtitle');
    subP.textContent =
      `Below is a preview of the ${records.length} records. Toggle any tile to include or exclude it from the stakeholder’s feedback request.`;

    // A container for the record tiles
    const recordsContainer = document.createElement('div');
    recordsContainer.style.marginTop = '1em';

    // We'll track selected indexes in a Set (defaults to include everything).
    const includedIndexes = new Set();
    records.forEach(rec => {
      if (typeof rec.grid_index === 'number') {
        includedIndexes.add(rec.grid_index);
      }
    });

    records.forEach(rec => {
      const tile = document.createElement('div');
      tile.classList.add('preview-record-tile');
      // We'll color it "selected" by default
      tile.classList.add('selected');

      // Make the tile clickable
      tile.addEventListener('click', () => {
        if (tile.classList.contains('selected')) {
          tile.classList.remove('selected');
          includedIndexes.delete(rec.grid_index);
        } else {
          tile.classList.add('selected');
          includedIndexes.add(rec.grid_index);
        }
      });

      // Label
      const gridLabel = document.createElement('strong');
      gridLabel.textContent = `Record #${rec.display_order ?? rec.grid_index} `;
      tile.appendChild(gridLabel);

      // Show properties in a small list
      const props = rec.properties || {};
      Object.entries(props).forEach(([k, v]) => {
        const pLine = document.createElement('p');
        pLine.innerHTML = `<em>${k}:</em> ${v}`;
        tile.appendChild(pLine);
      });

      recordsContainer.appendChild(tile);
    });

    // The “Include All” button
    const includeAllBtn = document.createElement('button');
    includeAllBtn.type = 'button';
    includeAllBtn.classList.add('submit-btn');
    includeAllBtn.style.marginTop = '1em';
    includeAllBtn.textContent = 'Include All';
    includeAllBtn.addEventListener('click', () => {
      // Toggle all to "selected"
      recordsContainer.querySelectorAll('.preview-record-tile').forEach(tile => {
        tile.classList.add('selected');
      });
      records.forEach(r => includedIndexes.add(r.grid_index));
    });

    // NEW: The “Remove All” button
    const removeAllBtn = document.createElement('button');
    removeAllBtn.type = 'button';
    removeAllBtn.classList.add('submit-btn');
    removeAllBtn.style.marginTop = '1em';
    removeAllBtn.textContent = 'Remove All';
    removeAllBtn.addEventListener('click', () => {
      // Toggle all to "unselected"
      recordsContainer.querySelectorAll('.preview-record-tile').forEach(tile => {
        tile.classList.remove('selected');
      });
      includedIndexes.clear();
    });

    // The “Send Requests” button
    const sendBtn = document.createElement('button');
    sendBtn.type = 'button';
    sendBtn.classList.add('submit-btn');
    sendBtn.style.marginTop = '1em';
    sendBtn.textContent = 'Send Requests';
    sendBtn.addEventListener('click', () => {
      // Actually send to process_content_review_request
      handleSendRequests({
        packageId,
        focusAreaName,
        focusAreaVersionId,
        formType,
        personalMessage,
        primaryResponse,
        secondaryResponse,
        stakeholders,
        selectedGridIndexes: Array.from(includedIndexes).sort((a,b) => a - b)
      });
    });

    formDiv.appendChild(closeBtn);
    formDiv.appendChild(titleEl);
    formDiv.appendChild(subP);
    formDiv.appendChild(recordsContainer);

    // Buttons row
    const btnRow = document.createElement('div');
    btnRow.style.display = 'flex';
    btnRow.style.justifyContent = 'center';
    btnRow.style.gap = '1em';
    btnRow.appendChild(includeAllBtn);
    // Insert the new 'Remove All' button here
    btnRow.appendChild(removeAllBtn);
    btnRow.appendChild(sendBtn);

    formDiv.appendChild(btnRow);

    overlay.appendChild(formDiv);
    document.body.appendChild(overlay);
  }

  function handleSendRequests({
    packageId,
    focusAreaName,
    focusAreaVersionId,
    formType,
    personalMessage,
    primaryResponse,
    secondaryResponse,
    stakeholders,
    selectedGridIndexes
  }) {
    if (selectedGridIndexes.length === 0) {
      if (!confirm('You have selected zero records. This will send a feedback request with no records. Proceed?')) {
        return;
      }
    }
    showPageMaskSpinner('Sending Content Review Requests...');

    const payload = {
      stakeholders,
      focus_area_name: focusAreaName,
      package_name: '', // not used by the backend, so we omit or empty
      package_id: packageId,
      focus_area_version_id: focusAreaVersionId,
      personal_message: personalMessage,
      form_type: formType,
      primary_response_option: primaryResponse || '',
      secondary_response_option: secondaryResponse || '',
      // The new column:
      stakeholder_request_grid_indexes: selectedGridIndexes.join(',')
    };

    console.log('[ContentReview] handleSendRequests payload =>', payload);

    fetch('/process_content_review_request.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(r => (r.ok ? r.json() : Promise.reject('Server error')))
      .then(data => {
        hidePageMaskSpinner();
        if (data.status === 'success') {
          alert('Content Review Requests sent successfully.');
          removeOverlayById('content-review-overlay');
          removeOverlayById('content-review-preview-overlay');
        } else {
          console.error('[ContentReview] Submit error:', data);
          alert('Failed to submit. ' + (data.message || 'An unknown error occurred.'));
        }
      })
      .catch(err => {
        hidePageMaskSpinner();
        console.error('[ContentReview] Submit catch:', err);
        alert('An error occurred while submitting your request.');
      });
  }

  function populateResponseTypeDropdown(selectEl, primaryInput, secondaryInput, descEl) {
    selectEl.innerHTML = '';
    feedbackResponseTypes.forEach((typeDef, idx) => {
      if (typeDef.active !== false) {
        const opt = document.createElement('option');
        opt.value = String(idx);
        opt.textContent = typeDef.label;
        selectEl.appendChild(opt);
      }
    });
    const customOpt = document.createElement('option');
    customOpt.value = 'custom';
    customOpt.textContent = '(Custom)';
    selectEl.appendChild(customOpt);

    if (selectEl.options.length > 0) {
      selectEl.selectedIndex = 0;
      handleResponseTypeChange(selectEl, primaryInput, secondaryInput, descEl);
    }
  }

  function resetItemizedSection(selectEl, primaryInput, secondaryInput, descEl) {
    if (selectEl.options.length > 0) {
      selectEl.selectedIndex = 0;
      handleResponseTypeChange(selectEl, primaryInput, secondaryInput, descEl);
    }
  }

  function handleResponseTypeChange(selectEl, primaryInput, secondaryInput, descEl) {
    const val = selectEl.value;
    if (val === 'custom') {
      primaryInput.disabled   = false;
      primaryInput.value      = '';
      secondaryInput.disabled = false;
      secondaryInput.value    = '';
      descEl.textContent      = 'Define custom responses. Primary is required; secondary is optional.';
      return;
    }
    const idx = parseInt(val, 10);
    if (!isNaN(idx) && feedbackResponseTypes[idx]) {
      const chosen = feedbackResponseTypes[idx];
      primaryInput.disabled   = true;
      primaryInput.value      = chosen.primaryResponse   || '';
      secondaryInput.disabled = true;
      secondaryInput.value    = chosen.secondaryResponse || '';
      descEl.textContent      = chosen.description || '';
    }
  }

  function removeOverlayById(id) {
    const existing = document.getElementById(id);
    if (existing) existing.remove();
  }

  function showPageMaskSpinner(message) {
    if (document.getElementById('page-mask')) return;
    const mask = document.createElement('div');
    mask.id = 'page-mask';
    mask.className = 'page-mask';

    const spinner = document.createElement('div');
    spinner.classList.add('spinner');

    const msgDiv = document.createElement('div');
    msgDiv.classList.add('spinner-message');
    msgDiv.textContent = message;

    mask.appendChild(spinner);
    mask.appendChild(msgDiv);
    document.body.appendChild(mask);
  }

  function hidePageMaskSpinner() {
    const pm = document.getElementById('page-mask');
    if (pm) pm.remove();
  }

  // Expose a single public method
  window.ContentReviewModule = { init };

})();
