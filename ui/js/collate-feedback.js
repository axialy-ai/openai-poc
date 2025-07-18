/****************************************************************************
 * /js/collate-feedback.js
 *
 * 1) Fetch aggregator data from fetch_stakeholder_feedback.php
 * 2) Displays "Stakeholder Feedback Summary" overlay
 * 3) Let user pick "Apply", "Add Instruction", or "Ignore"
 * 4) Press "Apply Revisions" => calls ApplyRevisionsHandler.processRevisions(...)
 * 
 * Updated so we pass a 7th parameter to processRevisions:
 *   (1) packageId
 *   (2) focusAreaVersionId
 *   (3) focusAreaVersionNumber
 *   (4) toProcess array
 *   (5) packageName
 *   (6) focusAreaName
 *   (7) rowRecords
 *
 * This way we can label the Revisions Summary with “(v2)” etc.
 ****************************************************************************/
window.CollateFeedbackModule = (function() {
  // Where we store user-chosen actions
  const CollatedFeedbackStore = {
    recordActions: new Map(),

    storeFeedback(storeKey, feedbackDataArray) {
      this.recordActions.set(storeKey, feedbackDataArray);
    },

    getAllFeedback() {
      // Flatten
      return Array.from(this.recordActions.values()).flat();
    },

    clear() {
      this.recordActions.clear();
    }
  };

  /**
   * Public initialization
   * @param {string} focusAreaName
   * @param {string} packageName
   * @param {number} packageId
   */
  function init(focusAreaName, packageName, packageId) {
    console.log("[collate-feedback.js] init =>", { focusAreaName, packageName, packageId });
    CollatedFeedbackStore.clear();

    fetchStakeholderFeedback(packageName, packageId, focusAreaName)
      .then(data => {
        const { summaryData, rowRecords } = data;
        console.log("[collate-feedback.js] aggregator => summaryData:", summaryData);

        // For itemized feedback, attach originalContent from the aggregator's focusAreaRows
        if (Array.isArray(summaryData.itemizedRecords)) {
          summaryData.itemizedRecords.forEach(item => {
            // aggregator gives item.focusAreaRecordID => we find the matching row
            const matchRow = rowRecords.find(rr =>
              parseInt(rr.focusAreaRecordID, 10) === parseInt(item.focusAreaRecordID, 10)
            );
            // Attach the record's properties to "originalContent"
            item.originalContent = matchRow ? { ...matchRow.properties } : {};
          });
        }

        // For general feedback, we typically just set originalContent = {}
        if (Array.isArray(summaryData.generalFeedback)) {
          summaryData.generalFeedback.forEach(item => {
            item.originalContent = {};
          });
        }

        renderOverlay(focusAreaName, packageName, packageId, summaryData, rowRecords);
      })
      .catch(err => {
        console.error('[collate-feedback.js] init => aggregator error:', err);
        alert('Failed to load feedback data.');
      });
  }

  /**
   * fetchStakeholderFeedback => calls fetch_stakeholder_feedback.php
   */
  function fetchStakeholderFeedback(packageName, packageId, focusAreaName) {
    const url = `fetch_stakeholder_feedback.php`
              + `?package_id=${encodeURIComponent(packageId)}`
              + `&focus_area_name=${encodeURIComponent(focusAreaName)}`;
    console.log("[collate-feedback.js] fetchStakeholderFeedback => URL:", url);

    return fetch(url)
      .then(resp => {
        if (!resp.ok) {
          throw new Error('Network error fetching aggregator');
        }
        return resp.json();
      })
      .then(json => {
        if (json.status !== 'success' || !json.summaryData) {
          throw new Error(json.message || 'No aggregator data found.');
        }
        return {
          summaryData:  json.summaryData,
          rowRecords:   json.focusAreaRows || []
        };
      });
  }

  /**
   * Renders "Stakeholder Feedback Summary" overlay
   */
  function renderOverlay(focusAreaName, packageName, packageId, summaryData, rowRecords) {
    const old = document.getElementById('collate-feedback-overlay');
    if (old) old.remove();

    const overlay = document.createElement('div');
    overlay.id = 'collate-feedback-overlay';
    overlay.classList.add('collate-feedback-overlay');
    overlay.setAttribute('role','dialog');
    overlay.setAttribute('aria-modal','true');

    const formDiv = document.createElement('div');
    formDiv.classList.add('collate-feedback-form');

    const closeBtn = document.createElement('span');
    closeBtn.classList.add('close-collate-feedback-overlay');
    closeBtn.innerHTML = '&times;';
    closeBtn.setAttribute('aria-label','Close Stakeholder Feedback Summary');
    closeBtn.tabIndex = 0;

    const titleEl = document.createElement('h2');
    titleEl.textContent = 'Stakeholder Feedback Summary';

    const subEl = document.createElement('p');
    subEl.textContent = `${focusAreaName} in ${packageName}`;

    const totalCount = summaryData.totalFeedbackItems || 0;
    const totalEl = document.createElement('p');
    totalEl.textContent = `Feedback Items: ${totalCount}`;

    const contentWrap = document.createElement('div');
    contentWrap.classList.add('content-wrapper');

    // 1) General Feedback
    if (Array.isArray(summaryData.generalFeedback) && summaryData.generalFeedback.length > 0) {
      const gfSecTitle = `General Feedback Items (${summaryData.generalCount || 0})`;
      const gfSec = createCollapsibleSection(gfSecTitle, summaryData.generalFeedback, true, rowRecords);
      contentWrap.appendChild(gfSec);
    }

    // 2) Itemized Feedback
    if (Array.isArray(summaryData.itemizedRecords) && summaryData.itemizedRecords.length > 0) {
      const itSecTitle = `Itemized Feedback Items (${summaryData.itemizedItemCount || 0})`;
      const itSec = createCollapsibleSection(itSecTitle, summaryData.itemizedRecords, false, rowRecords);
      contentWrap.appendChild(itSec);
    }

    // Fallback: no feedback
    if ((summaryData.generalCount||0) === 0 && (summaryData.itemizedItemCount||0) === 0) {
      const p = document.createElement('p');
      p.textContent = 'No feedback found for this focus area.';
      contentWrap.appendChild(p);
    }

    // "Apply Revisions" button
    const applyBtn = document.createElement('button');
    applyBtn.type = 'button';
    applyBtn.classList.add('apply-revisions-btn');
    applyBtn.textContent = 'Apply Revisions';
    applyBtn.addEventListener('click', () => {
      // gather selected actions
      const allFeedback = CollatedFeedbackStore.getAllFeedback();
      const toProcess = allFeedback.filter(x =>
        x.action === 'Apply' || x.action === 'Add Instruction' || x.action === 'Ignore'
      );
      if (toProcess.length === 0) {
        alert('No revision instructions selected.');
        return;
      }

      const focusAreaVersionId     = summaryData.focusAreaVersionId     || 0;
      const focusAreaVersionNumber = summaryData.focusAreaVersionNumber || 0;

      if (window.ApplyRevisionsHandler && typeof ApplyRevisionsHandler.processRevisions === 'function') {
        // Now pass 7 parameters:
        //  1) packageId
        //  2) focusAreaVersionId  (the row ID)
        //  3) focusAreaVersionNumber
        //  4) the array toProcess
        //  5) packageName
        //  6) focusAreaName
        //  7) rowRecords
        window.ApplyRevisionsHandler.processRevisions(
          packageId,
          focusAreaVersionId,
          focusAreaVersionNumber,
          toProcess,
          packageName,
          focusAreaName,
          rowRecords
        );
      } else {
        alert('ApplyRevisionsHandler not found.');
      }
    });

    formDiv.appendChild(closeBtn);
    formDiv.appendChild(titleEl);
    formDiv.appendChild(subEl);
    formDiv.appendChild(totalEl);
    formDiv.appendChild(contentWrap);
    formDiv.appendChild(applyBtn);

    overlay.appendChild(formDiv);
    document.body.appendChild(overlay);

    closeBtn.addEventListener('click', closeOverlay);
    closeBtn.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') closeOverlay();
    });
    document.addEventListener('keydown', handleEscKey);
  }

  /**
   * Create collapsible section for either general or itemized feedback
   */
  function createCollapsibleSection(sectionTitle, aggregatorRows, isGeneral, rowRecords) {
    const section = document.createElement('div');
    section.classList.add('collapsible-section');

    const hdr = document.createElement('div');
    hdr.classList.add('collapsible-header');

    const toggleIcon = document.createElement('span');
    toggleIcon.classList.add('toggle-icon');
    toggleIcon.innerHTML = '&#9660;';

    const titleSpan = document.createElement('span');
    titleSpan.classList.add('collapsible-title');
    titleSpan.textContent = sectionTitle;

    hdr.appendChild(toggleIcon);
    hdr.appendChild(titleSpan);

    const body = document.createElement('div');
    body.classList.add('collapsible-body');
    body.style.display = 'block';

    aggregatorRows.forEach((row, idx) => {
      const recordItem = document.createElement('div');
      recordItem.classList.add('record-item');

      if (isGeneral) {
        recordItem.dataset.generalFeedbackRecordId = row.generalFeedbackRecordID || '';
        recordItem.dataset.feedbackSource = 'generalFeedback';
      } else {
        recordItem.dataset.itemizedFeedbackRecordIds = row.itemizedFeedbackRecordIDs || '';
        recordItem.dataset.focusAreaRecordId = row.focusAreaRecordID || '';
        recordItem.dataset.feedbackSource = 'itemizedFeedback';
      }

      const rHeader = document.createElement('div');
      rHeader.classList.add('record-header');

      const rLabel = document.createElement('span');
      rLabel.classList.add('record-label');

      if (isGeneral) {
        // For general feedback, label each item as "Response [n]"
        const responseN = row.responseNumber || (idx + 1);
        rLabel.textContent = `Response ${responseN}`;
      } else {
        // For itemized feedback, label as "Record [display_order]"
        const dispN = row.display_order || row.recordNumber || (idx + 1);
        rLabel.textContent = `Record ${dispN}`;
      }

      const fCounts = document.createElement('div');
      fCounts.classList.add('feedback-counts');

      const revVal = row.feedbackCounts?.Reviewed   || 0;
      const unVal  = row.feedbackCounts?.Unreviewed || 0;
      const penVal = row.feedbackCounts?.Pending    || 0;

      const revSpan = document.createElement('span');
      revSpan.textContent = `Reviewed: ${revVal}`;

      const unSpan = document.createElement('span');
      unSpan.textContent = `Unreviewed: ${unVal}`;
      unSpan.style.cursor='pointer';
      unSpan.addEventListener('click', () => {
        showFeedbackDetailsOverlay(row, isGeneral, rowRecords);
      });

      const penSpan = document.createElement('span');
      penSpan.textContent = `Pending: ${penVal}`;
      penSpan.classList.add('pending-count');
      if (penVal > 0) {
        penSpan.style.color='red';
        penSpan.style.fontWeight='bold';
      }

      fCounts.appendChild(revSpan);
      fCounts.appendChild(unSpan);
      fCounts.appendChild(penSpan);

      rHeader.appendChild(rLabel);
      rHeader.appendChild(fCounts);

      recordItem.appendChild(rHeader);
      body.appendChild(recordItem);
    });

    hdr.addEventListener('click', () => {
      const isClosed = (body.style.display === 'none');
      body.style.display = isClosed ? 'block' : 'none';
      toggleIcon.innerHTML = isClosed ? '&#9660;' : '&#9654;';
    });

    section.appendChild(hdr);
    section.appendChild(body);
    return section;
  }

  /**
   * showFeedbackDetailsOverlay => loads feedback details from fetch_feedback_details
   */
  function showFeedbackDetailsOverlay(row, isGeneral, rowRecords) {
    const old = document.getElementById('feedback-details-overlay');
    if (old) old.remove();

    const overlay = document.createElement('div');
    overlay.id = 'feedback-details-overlay';
    overlay.classList.add('feedback-details-overlay');
    overlay.setAttribute('role','dialog');
    overlay.setAttribute('aria-modal','true');

    const formDiv = document.createElement('div');
    formDiv.classList.add('feedback-details-form');

    const closeBtn = document.createElement('span');
    closeBtn.classList.add('close-feedback-details-overlay');
    closeBtn.innerHTML = '&times;';
    closeBtn.setAttribute('aria-label','Close Feedback Details');
    closeBtn.tabIndex=0;

    let recordNumber = row.display_order || row.recordNumber || (isGeneral ? '' : 'N/A');
    let titleTxt = isGeneral
      ? `General Feedback`
      : `Record ${recordNumber} Itemized Feedback`;

    const titleH2 = document.createElement('h2');
    titleH2.textContent = titleTxt;

    const contentWrap = document.createElement('div');
    contentWrap.classList.add('content-wrapper');

    // Build param => call fetchFeedbackDetails
    fetchFeedbackDetails(row, isGeneral)
      .then(details => {
        if (!details.length) {
          const none = document.createElement('p');
          none.textContent = 'No additional feedback responses for this record.';
          contentWrap.appendChild(none);
          return;
        }

        details.forEach((fb, idx) => {
          const fbItem = document.createElement('div');
          fbItem.classList.add('feedback-response-item');

          const stakeP = document.createElement('p');
          stakeP.innerHTML = `<strong>Stakeholder Email:</strong> ${fb.stakeholder_email||'(unknown)'}`;

          const textP = document.createElement('p');
          textP.innerHTML = `<strong>Feedback:</strong><br>${fb.stakeholder_text||''}`;

          const actionDiv = document.createElement('div');
          actionDiv.classList.add('feedback-action-container');

          const btnApply = document.createElement('button');
          btnApply.type='button';
          btnApply.textContent='Apply';

          const btnInstr = document.createElement('button');
          btnInstr.type='button';
          btnInstr.textContent='Add Instruction';

          const btnIgn = document.createElement('button');
          btnIgn.type='button';
          btnIgn.textContent='Ignore';

          const revText = document.createElement('textarea');
          revText.style.display='none';

          btnApply.addEventListener('click', () => {
            fb.userAction='Apply';
            btnApply.classList.add('selected');
            btnInstr.classList.remove('selected');
            btnIgn.classList.remove('selected');
            revText.value = fb.stakeholder_text||'';
            revText.style.display='block';
          });

          btnInstr.addEventListener('click', () => {
            fb.userAction='Add Instruction';
            btnApply.classList.remove('selected');
            btnInstr.classList.add('selected');
            btnIgn.classList.remove('selected');
            revText.value='';
            revText.placeholder='Enter revision instructions...';
            revText.style.display='block';
          });

          btnIgn.addEventListener('click', () => {
            fb.userAction='Ignore';
            btnApply.classList.remove('selected');
            btnInstr.classList.remove('selected');
            btnIgn.classList.add('selected');
            revText.value='';
            revText.style.display='none';
          });

          revText.addEventListener('input', () => {
            fb.revisionInstructions = revText.value;
          });

          actionDiv.appendChild(btnApply);
          actionDiv.appendChild(btnInstr);
          actionDiv.appendChild(btnIgn);
          actionDiv.appendChild(revText);

          fbItem.appendChild(stakeP);
          fbItem.appendChild(textP);
          fbItem.appendChild(actionDiv);

          contentWrap.appendChild(fbItem);
        });
      })
      .catch(err => {
        console.error('[collate-feedback.js] fetchFeedbackDetails => error:', err);
        const eP = document.createElement('p');
        eP.textContent = 'Error loading detailed feedback.';
        contentWrap.appendChild(eP);
      });

    const doneBtn = document.createElement('button');
    doneBtn.type='button';
    doneBtn.textContent='Done';
    doneBtn.classList.add('done-btn');
    doneBtn.addEventListener('click', () => {
      const fbItems = overlay.querySelectorAll('.feedback-response-item');
      const recordFeedback = [];
      let pendingCount=0;

      fbItems.forEach(item => {
        const sel = item.querySelector('button.selected');
        if (!sel) return;
        const actVal = sel.textContent;
        const txtA  = item.querySelector('textarea');
        const instr = txtA ? (txtA.value||'') : '';
        if (actVal) pendingCount++;

        recordFeedback.push({
          action:       actVal,
          instructions: instr,
          // "generalFeedback" or "itemizedFeedback"
          feedbackSource: isGeneral ? 'generalFeedback' : 'itemizedFeedback',

          // If it's general:
          generalFeedbackRecordID: isGeneral ? row.generalFeedbackRecordID : undefined,
          // If it's itemized:
          itemizedFeedbackRecordID: !isGeneral ? row.itemizedFeedbackRecordIDs : undefined,

          // For concurrency or reference if needed:
          grid_index: row.grid_index || 0,
          recordNumber: row.recordNumber,
          stakeholderEmail: row.stakeholderEmail || '',
          originalContent: row.originalContent || {},

          // *** CRITICAL ***: preserve numeric focusAreaRecordID for itemized
          focusAreaRecordID: isGeneral ? null : row.focusAreaRecordID || 0
        });
      });

      let storeKey;
      if (isGeneral) {
        storeKey = `GF_${row.generalFeedbackRecordID || 'x'}`;
      } else {
        storeKey = `IF_${row.itemizedFeedbackRecordIDs || 'x'}`;
      }
      CollatedFeedbackStore.storeFeedback(storeKey, recordFeedback);

      // update aggregator UI
      let pendingEl;
      if (isGeneral) {
        // data-general-feedback-record-id
        pendingEl = document.querySelector(`[data-general-feedback-record-id="${row.generalFeedbackRecordID}"] .pending-count`);
      } else {
        // data-itemized-feedback-record-ids
        pendingEl = document.querySelector(`[data-itemized-feedback-record-ids="${row.itemizedFeedbackRecordIDs}"] .pending-count`);
      }
      if (pendingEl) {
        pendingEl.textContent = `Pending: ${pendingCount}`;
        if (pendingCount>0) {
          pendingEl.style.color='red';
          pendingEl.style.fontWeight='bold';
        } else {
          pendingEl.style.color='';
          pendingEl.style.fontWeight='';
        }
      }
      if (!row.feedbackCounts) row.feedbackCounts = {};
      row.feedbackCounts.Pending = pendingCount;

      overlay.remove();
    });

    formDiv.appendChild(closeBtn);
    formDiv.appendChild(titleH2);
    formDiv.appendChild(contentWrap);
    formDiv.appendChild(doneBtn);

    overlay.appendChild(formDiv);
    document.body.appendChild(overlay);

    closeBtn.addEventListener('click', () => overlay.remove());
    closeBtn.addEventListener('keydown', e => {
      if(e.key==='Enter' || e.key===' ') overlay.remove();
    });
    document.addEventListener('keydown', e => {
      if(e.key==='Escape') overlay.remove();
    });
  }

  /**
   * fetchFeedbackDetails => calls fetch_feedback_details with new param
   */
  function fetchFeedbackDetails(row, isGeneral) {
    let paramKey, paramVal;

    if (isGeneral) {
      paramKey = 'general_feedback_record_id';
      paramVal = row.generalFeedbackRecordID;
    } else {
      paramKey = 'itemized_feedback_record_id';
      // if aggregator lumps multiple => e.g. "2,3,4"
      paramVal = row.itemizedFeedbackRecordIDs;
    }

    if (!paramVal) {
      return Promise.resolve([]);
    }

    const url = `fetch_feedback_details.php?${paramKey}=${encodeURIComponent(paramVal)}`;
    console.log("[collate-feedback.js] fetchFeedbackDetails => URL:", url);

    return fetch(url)
      .then(r => r.ok ? r.json() : Promise.reject('Error fetching details.'))
      .then(data => {
        if (data.status==='success' && Array.isArray(data.feedbackResponses)) {
          return data.feedbackResponses;
        }
        return [];
      })
      .catch(err => {
        console.error('[collate-feedback.js] fetchFeedbackDetails => error:', err);
        return [];
      });
  }

  function closeOverlay() {
    const ov = document.getElementById('collate-feedback-overlay');
    if (ov) ov.remove();
    document.removeEventListener('keydown', handleEscKey);
  }

  function handleEscKey(e) {
    if(e.key==='Escape'){
      closeOverlay();
    }
  }

  return {
    init
  };
})();
