/****************************************************************************
 * /js/refine/axialyPackageAdvisorModule.js
 *
 * Provides the “Axialy Advisor” logic for a single selected package.
 * If the user clicks “Axialy Advisor” with a package open, we:
 *   1) Gather the package details (including focus areas + records)
 *      from fetch_analysis_package_focus_areas_with_metrics.php
 *   2) Call /api.axialy.ai/axialy_analysis_package_advisor.php
 *      using that data, plus any user context from the "Reconsider" text.
 *   3) Display the returned 'actionable_advisements' in an overlay.
 *      - Double-clicking a tile tries to open that focus area in the Refine UI
 *        and then optionally triggers further logic based on 'action_code'.
 ****************************************************************************/
var AxialyPackageAdvisorModule = (function() {
  /**
   * Called if a package is selected => gather & post the single package data
   * to axialy_analysis_package_advisor.php, then display the results in
   * #axialy-package-advisor-overlay.
   */
  function startAdvisorFlow() {
    RefineUtilsModule.showPageMaskSpinner('Analyzing selected package...');
    fetchCurrentlyOpenPackage()
      .then(function(packageObj) {
        if (!packageObj) {
          alert('Could not gather selected package data. Aborting.');
          throw new Error('No package object was returned.');
        }
        // optionally remove 'long_description' if you don’t want it
        if (packageObj.long_description) {
          delete packageObj.long_description;
        }
        // call the new endpoint with no user context
        return callAxialyPackageAdvisor(packageObj, '');
      })
      .catch(function(err) {
        console.error('[AxialyPackageAdvisor] startAdvisorFlow error:', err);
        alert('Could not complete Axialy Advisor for the open package: ' + err.message);
      })
      .finally(function() {
        RefineUtilsModule.hidePageMaskSpinner();
      });
  }

  /**
   * The user pressed "Reconsider" => gather the same package data plus
   * the text from #axialy-package-advisor-context and call the endpoint again.
   */
  function reconsiderAdvisorFlow() {
    RefineUtilsModule.showPageMaskSpinner('Reassessing package...');
    fetchCurrentlyOpenPackage()
      .then(function(packageObj) {
        if (!packageObj) {
          alert('No package data found to reconsider.');
          throw new Error('No package object was returned for reconsider.');
        }
        if (packageObj.long_description) {
          delete packageObj.long_description;
        }
        var contextEl = document.getElementById('axialy-package-advisor-context');
        var userText  = contextEl ? contextEl.value.trim() : '';
        return callAxialyPackageAdvisor(packageObj, userText);
      })
      .catch(function(err) {
        console.error('[AxialyPackageAdvisor] reconsider flow error:', err);
        alert('Error during Reconsider: ' + err.message);
      })
      .finally(function() {
        RefineUtilsModule.hidePageMaskSpinner();
      });
  }

  /**
   * Actually calls the single-package advisor endpoint with the given package
   * object plus user context, then displays the overlay.
   */
  function callAxialyPackageAdvisor(packageObj, userContext) {
    var apiKey = (window.AxiaBAConfig && window.AxiaBAConfig.api_key)
      ? window.AxiaBAConfig.api_key
      : '';
    var endpointUrl = 'https://api.axialy.ai/axialy_analysis_package_advisor.php';
    var requestBody = {
      analysis_package: packageObj,
      user_context_text: userContext
    };
    return fetch(endpointUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-API-Key': apiKey
      },
      body: JSON.stringify(requestBody)
    })
    .then(function(resp) {
      if (!resp.ok) {
        throw new Error('axialy_analysis_package_advisor returned an error.');
      }
      return resp.json();
    })
    .then(function(data) {
      if (data.error) {
        throw new Error('Axialy advisor error: ' + data.error);
      }
      if (!data.axialy_advice) {
        throw new Error('No axialy_advice in advisor response.');
      }
      displayPackageAdvisorOverlay(data.axialy_advice);
    });
  }

  /**
   * Displays the returned 'actionable_advisements' in #axialy-package-advisor-overlay.
   * The user can double-click a tile to open the relevant focus area and possibly
   * do additional logic based on action_code.
   */
  function displayPackageAdvisorOverlay(axialyAdvice) {
    var overlay    = document.getElementById('axialy-package-advisor-overlay');
    var bodyRegion = document.getElementById('axialy-package-advisor-body');
    if (!overlay || !bodyRegion) {
      console.warn('[AxialyPackageAdvisor] No overlay elements found in DOM.');
      return;
    }
    // Some styling for each tile
    var html = '' +
      '<style>' +
      ' .axialy-package-advisor-tile {' +
      '   border: 1px solid #ccc;' +
      '   padding: 8px;' +
      '   margin: 6px 0;' +
      '   border-radius: 4px;' +
      '   background-color: #fafafa;' +
      '   cursor: pointer;' +
      '   transition: box-shadow 0.2s ease;' +
      ' }' +
      ' .axialy-package-advisor-tile:hover {' +
      '   box-shadow: 0 0 6px rgba(0,0,0,0.15);' +
      '   background-color: #f3fdfd;' +
      ' }' +
      '</style>';

    // scenario_title
    if (axialyAdvice.scenario_title) {
      html += '<h3>' + escapeHtml(axialyAdvice.scenario_title) + '</h3>';
    }
    // recap_text
    if (axialyAdvice.recap_text) {
      html += '<p><strong>Recap:</strong> ' + escapeHtml(axialyAdvice.recap_text) + '</p>';
    }
    // advisement_text
    if (axialyAdvice.advisement_text) {
      html += '<p><strong>Advisement:</strong> ' + escapeHtml(axialyAdvice.advisement_text) + '</p>';
    }
    // actionable_advisements array
    if (Array.isArray(axialyAdvice.actionable_advisements)) {
      html += '<div style="margin-top:1em;"><strong>Actionable Advisements:</strong></div>';
      for (var i = 0; i < axialyAdvice.actionable_advisements.length; i++) {
        var item   = axialyAdvice.actionable_advisements[i];
        var tileId = 'axialy-package-advisor-tile-' + i;
        html += '<div class="axialy-package-advisor-tile" id="' + tileId + '">';
        // 1) Show rank as "#n"
        html += '<p><strong>#' + escapeHtml(item.advisement_rank) + '</strong>';
        // The advisement_action next
        html += ' - ' + escapeHtml(item.advisement_action) + '</p>';
        // 2) More prominent focus_area_name
        if (item.focus_area_name) {
          html += '<h4 style="margin-top:0.5em;">Focus Area: ' + escapeHtml(item.focus_area_name) + '</h4>';
        }
        // The main description
        html += '<p>' + escapeHtml(item.advisement_description) + '</p>';
        // 3) Optionally show the action_code
        if (item.action_code) {
          html += '<p><em>Action Code:</em> ' + escapeHtml(item.action_code) + '</p>';
        }
        html += '</div>';
      }
    }
    // summary_text
    if (axialyAdvice.summary_text) {
      html += '<p><strong>Summary:</strong> ' + escapeHtml(axialyAdvice.summary_text) + '</p>';
    }
    // next_step_text
    if (axialyAdvice.next_step_text) {
      html += '<p><strong>Next Step:</strong> ' + escapeHtml(axialyAdvice.next_step_text) + '</p>';
    }

    bodyRegion.innerHTML = html;
    overlay.style.display = 'block';

    // Double-click => interpret action_code
    if (Array.isArray(axialyAdvice.actionable_advisements)) {
      for (var j = 0; j < axialyAdvice.actionable_advisements.length; j++) {
        (function(idx) {
          var item = axialyAdvice.actionable_advisements[idx];
          var tileEl = document.getElementById('axialy-package-advisor-tile-' + idx);
          if (tileEl) {
            tileEl.addEventListener('dblclick', function() {
              // close overlay
              overlay.style.display = 'none';
              if (!item.action_code) {
                if (item.focus_area_name && typeof window.expandFocusAreaInRefine === 'function') {
                  window.expandFocusAreaInRefine(item.focus_area_name);
                }
                return;
              }
              handleAdvisorActionCode(item);
            });
          }
        })(j);
      }
    }
  }

  /**
   * Interprets the 'action_code' from the advisement tile.
   * Some codes expand the focus area and then do something else, others skip expansion.
   */
  function handleAdvisorActionCode(item) {
    var code   = item.action_code || '';
    var faName = item.focus_area_name || '';
    var pkgId  = RefineStateModule.getSelectedPackageId() || 0;
    // We'll locate a matching .focus-area-record-item to get versionId if we need to expand
    var focusAreaItemEl = null;
    if (faName) {
      focusAreaItemEl = document.querySelector('.focus-area-record-item[data-focus-area-name="'+faName+'"]');
    }

    switch (code) {
      case 'FA_REQUEST': {
        // Expand focus area first
        if (faName && typeof window.expandFocusAreaInRefine === 'function') {
          window.expandFocusAreaInRefine(faName);
        }
        // Then call the refineActivities => "Request Feedback"
        var versionId = 0;
        if (focusAreaItemEl) {
          versionId = parseInt(focusAreaItemEl.dataset.focusAreaVersionId || '0', 10);
        }
        var activity = {
          label: 'Request Feedback',
          actionType: 'overlay',
          action: 'contentReviews',
          description: 'This feature lets users request feedback...'
        };
        if (window.RefineActionsModule && typeof RefineActionsModule.handleActivitySelection === 'function') {
          var actionContent = item.action_content || '';
          RefineActionsModule.handleActivitySelection(activity, faName, versionId, actionContent);
        } else {
          alert('RefineActionsModule or handleActivitySelection not found.');
        }
        break;
      }

      case 'FA_COMPILE': {
        // Expand focus area first
        if (faName && typeof window.expandFocusAreaInRefine === 'function') {
          window.expandFocusAreaInRefine(faName);
        }
        // Then "Compile Feedback (AI)"
        var versionId2 = 0;
        if (focusAreaItemEl) {
          versionId2 = parseInt(focusAreaItemEl.dataset.focusAreaVersionId || '0', 10);
        }
        var activity2 = {
          label: 'Compile Feedback (AI)',
          actionType: 'overlay',
          action: 'collateFeedback',
          description: 'Uses AI to process feedback.'
        };
        if (window.RefineActionsModule && typeof RefineActionsModule.handleActivitySelection === 'function') {
          RefineActionsModule.handleActivitySelection(activity2, faName, versionId2);
        } else {
          alert('RefineActionsModule or handleActivitySelection not found.');
        }
        break;
      }

      case 'FA_CREATE': {
        // No focus area to expand. Directly do "New Focus Area..."
        var versionDummy = 0;
        var pkgName = RefineStateModule.getCurrentPackageName() || '';
        if (window.NewFocusAreaOverlay && typeof NewFocusAreaOverlay.open === 'function') {
          NewFocusAreaOverlay.open(pkgId, pkgName, versionDummy);
        } else {
          alert('NewFocusAreaOverlay not found or not available.');
        }
        break;
      }

      case 'FAR_CREATE': {
        // Expand the existing focus area, then show New Record overlay
        if (faName && typeof window.expandFocusAreaInRefine === 'function') {
          window.expandFocusAreaInRefine(faName);
        }
        var versionId3 = 0;
        if (focusAreaItemEl) {
          versionId3 = parseInt(focusAreaItemEl.dataset.focusAreaVersionId || '0', 10);
        }
        if (window.NewRecordOverlayModule && typeof NewRecordOverlayModule.openNewOverlay === 'function') {
          NewRecordOverlayModule.openNewOverlay(function(newRec) {
            alert('Record created. Please “Save changes” to finalize.');
          }, {});
        } else {
          alert('NewRecordOverlayModule not found or not available.');
        }
        break;
      }

      case 'FAR_EDIT': {
        // Expand focus area, then open "Edit Record Details" overlay
        // but we have no single record ID => show placeholder
        if (faName && typeof window.expandFocusAreaInRefine === 'function') {
          window.expandFocusAreaInRefine(faName);
        }
        alert('FAR_EDIT: No single record to edit. Please manually select a record to edit.');
        break;
      }

      // NEW: 'FA_ENHANCE'
      case 'FA_ENHANCE': {
        // Expand focus area first
        if (faName && typeof window.expandFocusAreaInRefine === 'function') {
          window.expandFocusAreaInRefine(faName);
        }
        // Then "Enhance Focus Area"
        var versionId4 = 0;
        if (focusAreaItemEl) {
          versionId4 = parseInt(focusAreaItemEl.dataset.focusAreaVersionId || '0', 10);
        }
        var enhanceActivity = {
          label: 'Enhance Content (AI)',
          actionType: 'overlay',
          action: 'enhanceFocusAreaContent',
          description: 'Use generative AI to transform or add records.'
        };
        if (window.RefineActionsModule && typeof RefineActionsModule.handleActivitySelection === 'function') {
          var enhanceContent = item.action_content || '';
          RefineActionsModule.handleActivitySelection(enhanceActivity, faName, versionId4, enhanceContent);
        } else {
          alert('RefineActionsModule or handleActivitySelection not found.');
        }
        break;
      }

      default: {
        // If we don’t recognize the code, just expand if possible
        if (faName && typeof window.expandFocusAreaInRefine === 'function') {
          window.expandFocusAreaInRefine(faName);
        }
      }
    }
  }

  /**
   * fetchCurrentlyOpenPackage():
   * Gathers the *real* focus area data for the currently open package,
   * so we can pass it to axialy_analysis_package_advisor.php.
   */
  function fetchCurrentlyOpenPackage() {
    return new Promise(function(resolve, reject) {
      var packageId = RefineStateModule.getSelectedPackageId();
      if (!packageId) {
        return resolve(null);
      }
      var pkgName   = RefineStateModule.getCurrentPackageName() || ('Package ' + packageId);

      var showDeletedEl = document.getElementById('show-deleted-toggle');
      var showDeleted   = (showDeletedEl && showDeletedEl.checked) ? 1 : 0;

      var url = 'fetch_analysis_package_focus_areas_with_metrics.php?package_id=' + packageId +
                '&show_deleted=' + showDeleted;

      fetch(url)
        .then(function(resp) {
          if (!resp.ok) {
            throw new Error('Failed to fetch focus areas for package ' + packageId);
          }
          return resp.json();
        })
        .then(function(jsonData) {
          if (!jsonData || jsonData.status !== 'success' || !jsonData.focus_areas) {
            throw new Error('Invalid data from fetch_analysis_package_focus_areas_with_metrics.php');
          }
          var packageObj = {
            id: packageId,
            package_name: pkgName,
            focus_areas_data: []
          };
          var faObj = jsonData.focus_areas;
          for (var faName in faObj) {
            if (faObj.hasOwnProperty(faName)) {
              var fa = faObj[faName];
              packageObj.focus_areas_data.push({
                focus_area_name: faName,
                version: fa.version,
                versionId: fa.versionId,
                records: fa.records || [],
                unreviewed_feedback_count: fa.unreviewed_feedback_count || 0
              });
            }
          }
          resolve(packageObj);
        })
        .catch(function(err) {
          reject(err);
        });
    });
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  /**
   * Attach event listeners for the single-package overlay’s “x” and “Reconsider” buttons.
   * This function is called in RefineIndexModule.initRefineTab().
   */
  function initSinglePackageOverlayEvents() {
    var singleCloseBtn = document.getElementById('close-axialy-package-advisor-overlay');
    if (singleCloseBtn) {
      singleCloseBtn.addEventListener('click', function() {
        var overlay = document.getElementById('axialy-package-advisor-overlay');
        if (overlay) overlay.style.display = 'none';
      });
    }
    var spReconsiderBtn = document.getElementById('axialy-package-advisor-reconsider-btn');
    if (spReconsiderBtn) {
      spReconsiderBtn.addEventListener('click', function() {
        if (
          window.AxialyPackageAdvisorModule &&
          typeof AxialyPackageAdvisorModule.reconsiderAdvisorFlow === 'function'
        ) {
          AxialyPackageAdvisorModule.reconsiderAdvisorFlow();
        } else {
          alert('Single-package Reconsider function not found.');
        }
      });
    }
  }

  // Expose the module’s public methods
  return {
    startAdvisorFlow: startAdvisorFlow,
    reconsiderAdvisorFlow: reconsiderAdvisorFlow,
    initSinglePackageOverlayEvents: initSinglePackageOverlayEvents
  };
})();
