/****************************************************************************
 * /js/home-tab.js
 *
 * Home Tab logic for:
 *   1) "Get Advice" button -> calls axialy_helper (AI),
 *   2) Display the AI’s Axialy advice in a structured form,
 *   3) "Yes, create this package!" -> store input, request "Analysis_Package_Header",
 *      show “Review” overlay, finalize creation => save to DB.
 *
 * Now we also handle new "focus_area_record_attributes" and "focus_area_records"
 * from the AxiaBA API and display them in the UI. Then we store them in the
 * analysis_package_focus_area_records table (via the server's /save_analysis_package.php).
 ****************************************************************************/
function initializeHomeTab() {
  console.log("initializeHomeTab() called for Axialy home.");
  const userInputEl = document.getElementById('axialy-user-input');
  if (userInputEl) {
    // Clear out any default or leftover whitespace
    userInputEl.value = '';
    userInputEl.focus();
  }

  const submitButton = document.getElementById('axialy-submit-button');
  if (!submitButton) {
    console.warn("No submit button found on home tab.");
    return;
  }

  submitButton.addEventListener('click', async function() {
    const loaderEl = document.getElementById('axialy-loader');
    const formEl   = document.getElementById('axialy-form-container');
    const createPackageContainer = document.getElementById('axialy-create-package-container');
    if (!userInputEl || !loaderEl || !formEl || !createPackageContainer) {
      console.error("Missing DOM elements on the home tab.");
      return;
    }

    const rawUserText = userInputEl.value.trim();
    if (!rawUserText) {
      alert("Please enter some text before pressing 'Get Advice'.");
      return;
    }

    // Prepare user text (with email preamble)
    const email = window.currentUserEmail || 'unknown@example.com';
    const userText =
      `Axialy Request from Analyste user in AxiaBA with email address ${email}: `
      + rawUserText;

    // Disable the “Get Advice” button & show status
    submitButton.disabled = true;
    const originalButtonText = submitButton.textContent;
    submitButton.textContent = "Processing...";

    // Clear previous
    formEl.innerHTML = '';
    formEl.style.display = 'none';
    createPackageContainer.style.display = 'none';

    // Show loader/spinner text
    loaderEl.style.display = 'block';

    // Prepare request
    const requestBody = {
      text: userText,
      template: "Axialy/Axialy_Intro_1"
    };
    const apiKey  = window.AxiaBAConfig?.api_key || '';
    const baseUrl = window.AxiaBAConfig?.api_base_url || "https://api.axialy.ai";
    const endpoint = baseUrl + "/axialy_helper.php";

    try {
      const res = await fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-API-Key": apiKey
        },
        body: JSON.stringify(requestBody)
      });
      // Hide loader once we get a response (even if error)
      loaderEl.style.display = 'none';

      if (!res.ok) {
        throw new Error(`Server returned ${res.status} - ${res.statusText}`);
      }

      const data = await res.json();
      if (data.error) {
        throw new Error(data.error);
      }

      const advice = data.axialy_advice;
      if (!advice) {
        throw new Error("No axialy_advice found in server response.");
      }

      // Render the Axialy advice
      formEl.innerHTML = renderAxialyAdviceForm(advice);
      formEl.style.display = 'block';

      // Show the "Yes, create this package!" button
      createPackageContainer.style.display = 'block';
      const createBtn = document.getElementById('yes-create-package-btn');
      if (createBtn) {
        // On click => create the package
        createBtn.onclick = function() {
          createPackageFromAxialyAdvice(advice);
        };
      }
    } catch (err) {
      console.error("Failed to fetch Axialy advisement:", err);
      alert("Error: " + err.message);
    } finally {
      // Re-enable the button
      submitButton.disabled = false;
      submitButton.textContent = originalButtonText;
    }
  });
}

/**
 * Builds a read-only "form-like" HTML layout for the given axialy_advice object,
 * including new focus_area_record_attributes + focus_area_records.
 */
function renderAxialyAdviceForm(advice) {
  const {
    scenario_title,
    recap_text,
    advisement_text,
    focus_areas,
    stakeholders_focus_area,
    summary_text,
    next_step_text
  } = advice;

  let html = '<div class="axialy-form-title">Axialy Advisement</div>';

  // Scenario Title
  if (scenario_title) {
    html += `
      <div class="axialy-form-section">
        <label>Scenario Title:</label>
        <div><strong>${escapeHtml(scenario_title)}</strong></div>
      </div>`;
  }

  // Recap
  if (recap_text) {
    html += `
      <div class="axialy-form-section">
        <label>Recap:</label>
        <div>${escapeHtml(recap_text)}</div>
      </div>`;
  }

  // Advisement
  if (advisement_text) {
    html += `
      <div class="axialy-form-section">
        <label>Advisement:</label>
        <div>${escapeHtml(advisement_text)}</div>
      </div>`;
  }

  // Focus Areas
  if (Array.isArray(focus_areas) && focus_areas.length > 0) {
    html += `<div class="axialy-form-section"><label>Focus Areas:</label>`;
    focus_areas.forEach((fa, i) => {
      html += `
        <div style="margin:0.75rem 0; padding:0.5rem; border:1px solid #ccc; border-radius:4px;">
          <strong>Focus Area ${i+1}: ${escapeHtml(fa.focus_area_name || '')}</strong><br>
          <div style="margin-top:0.5rem;"><em>${escapeHtml(fa.focus_area_value || '')}</em></div>
          <div style="margin-top:0.5rem;">
            <label>Collaboration Approach:</label><br>
            ${escapeHtml(fa.focus_area_collaboration_approach || '')}
          </div>
          ${renderStakeholderSubform(fa.focus_area_stakeholders || [])}
          ${renderFocusAreaRecordAttributes(fa.focus_area_record_attributes || [])}
          ${renderFocusAreaRecords(fa.focus_area_records || [])}
        </div>
      `;
    });
    html += `</div>`;
  }

  // Stakeholders Focus Area
  if (stakeholders_focus_area) {
    const {
      focus_area_name,
      focus_area_value,
      focus_area_collaboration_approach,
      analysis_package_stakeholders
    } = stakeholders_focus_area;

    html += `
      <div class="axialy-form-section">
        <label>${escapeHtml(focus_area_name || 'Analysis Package Stakeholders')}</label>
        <div><em>${escapeHtml(focus_area_value || '')}</em></div>
        <div style="margin-top:0.5rem;">
          <label>Collaboration Approach:</label><br>
          ${escapeHtml(focus_area_collaboration_approach || '')}
        </div>
    `;

    if (Array.isArray(analysis_package_stakeholders) && analysis_package_stakeholders.length > 0) {
      html += `
        <div style="margin-top:1rem;">
          <label>Stakeholders:</label>
          <table class="axialy-stakeholder-table">
            <thead>
              <tr>
                <th>Email</th>
                <th>Identity</th>
                <th>Persona</th>
                <th>Codename</th>
                <th>Analysis Context</th>
              </tr>
            </thead>
            <tbody>
      `;
      analysis_package_stakeholders.forEach(st => {
        html += `
          <tr>
            <td>${escapeHtml(st.Email || '')}</td>
            <td>${escapeHtml(st.Identity || '')}</td>
            <td>${escapeHtml(st.Persona || '')}</td>
            <td>${escapeHtml(st.Codename || '')}</td>
            <td>${escapeHtml(st["Analysis Context"] || '')}</td>
          </tr>
        `;
      });
      html += `</tbody></table></div>`;
    }
    html += `</div>`;
  }

  // Summary
  if (summary_text) {
    html += `
      <div class="axialy-form-section">
        <label>Summary:</label>
        <div>${escapeHtml(summary_text)}</div>
      </div>`;
  }

  // Next Step
  if (next_step_text) {
    html += `
      <div class="axialy-form-section">
        <label>Next Step:</label>
        <div>${escapeHtml(next_step_text)}</div>
      </div>`;
  }

  return html;
}

/** Renders a simple list of "focus_area_record_attributes". */
function renderFocusAreaRecordAttributes(attrArray) {
  if (!Array.isArray(attrArray) || attrArray.length === 0) return '';
  let html = `
    <div style="margin-top:1rem;">
      <label>Focus Area Record Attributes:</label>
      <ul style="margin-left:1.2rem;">
  `;
  attrArray.forEach(attr => {
    const name = escapeHtml(attr.attribute_name || '');
    const desc = escapeHtml(attr.attribute_description || '');
    html += `
      <li>
        <strong>${name}</strong>: <em>${desc}</em>
      </li>
    `;
  });
  html += `</ul></div>`;
  return html;
}

/** Renders a table of "focus_area_records" (each record is an object). */
function renderFocusAreaRecords(recordsArray) {
  if (!Array.isArray(recordsArray) || recordsArray.length === 0) return '';
  // If the first item is an object with known keys, let's gather them
  const firstRecord = recordsArray[0];
  const allKeys = Object.keys(firstRecord);

  let html = `
    <div style="margin-top:1rem;">
      <label>Focus Area Records:</label>
      <table class="axialy-stakeholder-table" style="margin-top:0.5rem;">
        <thead>
          <tr>
  `;
  allKeys.forEach(k => {
    html += `<th>${escapeHtml(k)}</th>`;
  });
  html += `</tr></thead><tbody>`;

  // Now each record is a row
  recordsArray.forEach(rec => {
    html += `<tr>`;
    allKeys.forEach(k => {
      html += `<td>${escapeHtml(rec[k] || '')}</td>`;
    });
    html += `</tr>`;
  });

  html += `</tbody></table></div>`;
  return html;
}

/** Renders sub-table for "focus_area_stakeholders" in a single focus area. */
function renderStakeholderSubform(stakeholdersArray) {
  if (!Array.isArray(stakeholdersArray) || stakeholdersArray.length === 0) {
    return '';
  }
  let html = `
    <div style="margin-top:1rem;">
      <label>Stakeholders:</label>
      <table class="axialy-stakeholder-table">
        <thead>
          <tr>
            <th>Persona</th>
            <th>Identity</th>
            <th>Context</th>
          </tr>
        </thead>
        <tbody>
  `;
  stakeholdersArray.forEach(s => {
    html += `
      <tr>
        <td>${escapeHtml(s.stakeholder_persona || '')}</td>
        <td>${escapeHtml(s.stakeholder_identity || '')}</td>
        <td>${escapeHtml(s.stakeholder_context || '')}</td>
      </tr>
    `;
  });
  html += `</tbody></table></div>`;
  return html;
}

/**
 * Updated HTML escaper that handles any value (string, object, etc.) safely.
 * - If it's not already a string, convert to string (or JSON).
 * - Then replace special chars.
 */
function escapeHtml(value) {
  if (value == null) {
    return '';
  }
  // If it’s already a string, fine. If not, do our best to convert it.
  if (typeof value !== 'string') {
    try {
      // For objects/arrays, produce JSON text;
      // for numbers/booleans, just .toString().
      if (typeof value === 'object') {
        value = JSON.stringify(value);
      } else {
        value = String(value);
      }
    } catch (e) {
      // Fallback
      value = String(value);
    }
  }
  return value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

/****************************************************************************
 * “Yes, create this package!” flow:
 *   1) Store user Axialy input -> input_text_summaries
 *   2) Request "Analysis_Package_Header" from AI
 *   3) Show "Review Analysis Package" overlay
 *   4) On "Save Now" => calls /save_analysis_package.php
 *   5) THEN show "Analysis Package saved successfully" overlay with link
 *   6) Also store the entire #axialy-form-container HTML in "axialy_outputs"
 ****************************************************************************/
async function createPackageFromAxialyAdvice(axialyAdvice) {
  try {
    // 1) store user input
    const userInputEl = document.getElementById('axialy-user-input');
    if (!userInputEl) {
      throw new Error("Could not find #axialy-user-input field.");
    }
    const rawUserInput = userInputEl.value.trim();
    if (!rawUserInput) {
      alert("No user input found.");
      return;
    }

    const summaryPayload = {
      input_text_title: "Axialy Input (Home Tab)",
      input_text_summary: "Auto-saved from Home Tab",
      input_text: rawUserInput,
      api_utc: new Date().toISOString().replace('T',' ').substring(0,19)
    };

    const res1 = await fetch("/store_summary.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(summaryPayload)
    });
    if (!res1.ok) {
      throw new Error(`store_summary failed: ${res1.status} - ${res1.statusText}`);
    }

    const data1 = await res1.json();
    if (data1.status !== "success") {
      throw new Error("Failed to store summary: " + data1.message);
    }

    window.inputTextSummariesId = Array.isArray(data1.input_text_summaries_ids)
      ? data1.input_text_summaries_ids[0]
      : data1.input_text_summaries_ids;

    // 2) request "Analysis_Package_Header" from AI
    OverlayModule.showLoadingOverlay("Requesting Analysis Package Header...");

    const apHeaderBody = {
      text: JSON.stringify(axialyAdvice),
      template: "Analysis_Package_Header"
    };

    const apiKey = window.AxiaBAConfig?.api_key || '';
    const baseUrl = window.AxiaBAConfig?.api_base_url || "https://api.axialy.ai";
    const endpoint = baseUrl + "/ai_helper.php";

    const res2 = await fetch(endpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-API-Key": apiKey
      },
      body: JSON.stringify(apHeaderBody)
    });

    if (!res2.ok) {
      OverlayModule.hideOverlay();
      throw new Error(`AI helper error: ${res2.status} - ${res2.statusText}`);
    }

    const data2 = await res2.json();
    OverlayModule.hideOverlay();

    if (data2.status !== 'success' || !data2.data || !data2.data["Analysis Package Header"]) {
      throw new Error("AI returned invalid package header. " + data2.message);
    }

    const headerObject = data2.data["Analysis Package Header"][0] || {};

    // 3) show “Review Analysis Package Summary” overlay
    OverlayModule.showHeaderReviewOverlay(
      headerObject,
      // onSave => proceed to final creation
      async (updatedHeader) => {
        OverlayModule.showLoadingMask("Saving new analysis package...");
        try {
          // 4) Build “collectedData” from the Axialy advice, including the new record arrays
          const collectedData = buildFocusAreaData(axialyAdvice);

          // Create the package in /save_analysis_package.php
          const payload = {
            headerData: updatedHeader,
            collectedData,
            input_text_summaries_id: window.inputTextSummariesId,
            // For axialy_outputs
            axialyOutputs: {
              scenarioTitle: axialyAdvice.scenario_title || "",
              outputDocument: document.getElementById('axialy-form-container').innerHTML || ""
            }
          };

          const res3 = await fetch("/save_analysis_package.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
          });

          const data3 = await res3.json();
          if (data3.status !== "success") {
            throw new Error("Failed to save analysis package: " + data3.message);
          }

          // 5) Show success overlay + link
          const messageHtml =
            `Analysis Package saved successfully.<br>` +
            `ID: ${data3.analysis_package_headers_id}<br>` +
            `Package Name: ${data3.package_name}<br><br>` +
            `<a href="#" id="openRefineLink" 
               style="color:#007bff; text-decoration:underline; font-weight:bold;">
               Open Package in Refine Tab
             </a>`;

          if (OverlayModule && OverlayModule.showMessageOverlay) {
            OverlayModule.showMessageOverlay(
              messageHtml,
              () => {
                console.log('User closed the success overlay in Home tab.');
              },
              true
            );
            // Wire up the "Open Package in Refine Tab" link
            setTimeout(() => {
              const link = document.getElementById('openRefineLink');
              if (link) {
                link.onclick = (evt) => {
                  evt.preventDefault();
                  clearHomeTabInputs();
                  if (typeof window.openRefineTabAndSelectPackage === 'function') {
                    window.openRefineTabAndSelectPackage(
                      data3.analysis_package_headers_id,
                      data3.package_name
                    );
                  } else {
                    alert('Refine tab function not found. Please open it manually.');
                  }
                  if (OverlayModule.hideOverlay) {
                    OverlayModule.hideOverlay();
                  }
                };
              }
            }, 400);
          } else {
            // Fallback if overlay module is missing
            alert(`Analysis Package saved successfully!
ID: ${data3.analysis_package_headers_id}
Name: ${data3.package_name}`);
          }
        } catch (err) {
          alert("Error creating package: " + err.message);
          OverlayModule.hideOverlay();
        }
      },
      // onCancel => user canceled
      () => {
        console.log("User canceled from the 'Review Package Summary' overlay.");
      }
    );
  } catch (err) {
    alert("Error while creating package: " + err.message);
  }
}

/**
 * Convert Axialy advice => “collectedData” array for /save_analysis_package.php.
 *
 * Includes focus_area_records so each record is stored as a separate entry
 * in analysis_package_focus_area_records.
 *
 * For example, if "focus_area_records" is an array of objects, we map
 * each object => row in analysis_package_focus_area_records.
 */
function buildFocusAreaData(axialyAdvice) {
  const collected = [];

  // A helper to transform a single record object => {properties: recordObj}
  function recordToObject(recordObj) {
    return {
      input_text_summaries_id: window.inputTextSummariesId,
      properties: recordObj
    };
  }

  // 1) For each normal focus area
  if (Array.isArray(axialyAdvice.focus_areas)) {
    axialyAdvice.focus_areas.forEach((fa) => {
      // We'll build a single object that includes these text fields
      // plus arrays for stakeholderRecords and "focusAreaRecords"
      const faObj = {
        focus_area_label: fa.focus_area_name || "Unnamed Focus Area",
        focus_area_value: fa.focus_area_value || "",
        collaboration_approach: fa.focus_area_collaboration_approach || "",
        stakeholderRecords: [],
        // brand-new array for actual data rows:
        focusAreaRecords: []
      };

      // 1a) If there's a stakeholder array, map them into stakeholderRecords
      if (Array.isArray(fa.focus_area_stakeholders)) {
        faObj.stakeholderRecords = fa.focus_area_stakeholders.map(st => {
          return {
            input_text_summaries_id: window.inputTextSummariesId,
            properties: st
          };
        });
      }

      // 1b) If there's a focus_area_records array, map each record
      if (Array.isArray(fa.focus_area_records)) {
        faObj.focusAreaRecords = fa.focus_area_records.map(r => recordToObject(r));
      }

      collected.push(faObj);
    });
  }

  // 2) For the “Analysis Package Stakeholders” focus area
  if (axialyAdvice.stakeholders_focus_area) {
    const sfa = axialyAdvice.stakeholders_focus_area;
    const focusAreaName = sfa.focus_area_name || "Analysis Package Stakeholders";
    const sfaObj = {
      focus_area_label: focusAreaName,
      focus_area_value: sfa.focus_area_value || "",
      collaboration_approach: sfa.focus_area_collaboration_approach || "",
      stakeholderRecords: [],
      focusAreaRecords: []
    };

    // If there's an array of analysis_package_stakeholders => stakeholderRecords
    if (Array.isArray(sfa.analysis_package_stakeholders)) {
      sfaObj.stakeholderRecords = sfa.analysis_package_stakeholders.map(stk => ({
        input_text_summaries_id: window.inputTextSummariesId,
        properties: stk
      }));
    }

    collected.push(sfaObj);
  }

  return collected;
}

/** Clears user input + hidden containers, same as before. */
function clearHomeTabInputs() {
  const userInputEl = document.getElementById('axialy-user-input');
  if (userInputEl) userInputEl.value = '';

  const formEl = document.getElementById('axialy-form-container');
  if (formEl) {
    formEl.innerHTML = '';
    formEl.style.display = 'none';
  }

  const createPackageContainer = document.getElementById('axialy-create-package-container');
  if (createPackageContainer) {
    createPackageContainer.style.display = 'none';
  }

  window.inputTextSummariesId = null;
}

/** Called once user navigates to Home in layout.js (if needed). */
function loadHomeTab() {
  console.log("loadHomeTab() => Loading Axialy home with final overlay enhancements.");
  const overviewPanel = document.getElementById('overview-panel');

  fetch('content/home-tab.html')
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok ' + response.statusText);
      }
      return response.text();
    })
    .then(html => {
      overviewPanel.innerHTML = html;
      initializeHomeTab();
    })
    .catch(error => {
      console.error('Error loading home-tab.html:', error);
      overviewPanel.innerHTML = '<p>Error loading home content.</p>';
    });
}

window.loadHomeTab = loadHomeTab;
