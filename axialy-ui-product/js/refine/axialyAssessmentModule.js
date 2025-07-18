/****************************************************************************
 * /js/refine/axialyAssessmentModule.js
 *
 * Provides the “Axialy Advisor” button logic for the Refine tab:
 *
 *  1) If a package is selected => calls single-package "AxialyPackageAdvisorModule"
 *     if available. If not, alerts user.
 *  2) If no package is selected => gather the array from
 *     /api/get_analysis_packages_with_metrics.php, call
 *     /api.axialy.ai/axialy_analysis_package_assessor.php, and display
 *     the returned advice in the #axialy-assessment-overlay.
 *
 *  3) The user can add more context and press "Reconsider".
 *  4) Double-click any displayed “package” tile in the overlay to open that
 *     package in the Refine tab (closing the overlay).
 *  5) We strip out the `long_description` from each package before sending
 *     to the multi-package assessor, per requirement.
 *
 * This version avoids arrow functions, optional chaining, and async/await,
 * which can trigger cPanel lint warnings in older environments.
 ***************************************************************************/

var AxialyAssessmentModule = (function() {

  /**
   * Initializes the Axialy Advisor button + overlay event handlers.
   */
  function initAxialyAdvisorButton() {
    var advisorBtn = document.getElementById('axialy-advisor-btn');
    if (!advisorBtn) {
      return;
    }

    // On click => check if a package is selected
    advisorBtn.addEventListener('click', function() {
      var pkgId = RefineStateModule.getSelectedPackageId();
      if (pkgId) {
        // Single-package scenario
        if (window.AxialyPackageAdvisorModule &&
            typeof window.AxialyPackageAdvisorModule.startAdvisorFlow === 'function') {
          window.AxialyPackageAdvisorModule.startAdvisorFlow();
        } else {
          alert("Single-package Axialy Advisor is not ready or missing.");
        }
        return;
      }

      // Otherwise => multi-package scenario
      RefineUtilsModule.showPageMaskSpinner('Loading analysis packages...');
      var showDeletedEl = document.getElementById('show-deleted-toggle');
      var showDeleted = (showDeletedEl && showDeletedEl.checked) ? 1 : 0;
      var url = 'api/get_analysis_packages_with_metrics.php?showDeleted=' + showDeleted;

      fetch(url)
        .then(function(response) {
          if (!response.ok) {
            throw new Error('Failed to fetch packages data.');
          }
          return response.json();
        })
        .then(function(packagesData) {
          // Strip out 'long_description'
          for (var i = 0; i < packagesData.length; i++) {
            delete packagesData[i].long_description;
          }
          // call assessor with empty userContext
          return callAxialyAnalysisPackageAssessor(packagesData, '');
        })
        .catch(function(err) {
          console.error('[AxialyAssessmentModule] Error:', err);
          alert('Could not load Axialy Assessment: ' + err.message);
        })
        .finally(function() {
          RefineUtilsModule.hidePageMaskSpinner();
        });
    });

    // Close overlay
    var closeBtn = document.getElementById('close-axialy-assessment-overlay');
    if (closeBtn) {
      closeBtn.addEventListener('click', function() {
        var overlay = document.getElementById('axialy-assessment-overlay');
        if (overlay) {
          overlay.style.display = 'none';
        }
      });
    }

    // “Reconsider” button
    var reconsiderBtn = document.getElementById('axialy-reconsider-btn');
    if (reconsiderBtn) {
      reconsiderBtn.addEventListener('click', function() {
        RefineUtilsModule.showPageMaskSpinner('Reassessing packages...');
        var showDeletedEl = document.getElementById('show-deleted-toggle');
        var showDeleted = (showDeletedEl && showDeletedEl.checked) ? 1 : 0;
        var url = 'api/get_analysis_packages_with_metrics.php?showDeleted=' + showDeleted;

        fetch(url)
          .then(function(response) {
            if (!response.ok) {
              throw new Error('Failed to re-fetch packages data.');
            }
            return response.json();
          })
          .then(function(packagesData) {
            // Remove long_description
            for (var i = 0; i < packagesData.length; i++) {
              delete packagesData[i].long_description;
            }
            var contextEl = document.getElementById('axialy-assessment-additional-context');
            var userText = contextEl ? contextEl.value.trim() : '';
            return callAxialyAnalysisPackageAssessor(packagesData, userText);
          })
          .catch(function(err) {
            console.error('[AxialyAssessmentModule] Reconsider error:', err);
            alert('Error during Reconsider: ' + err.message);
          })
          .finally(function() {
            RefineUtilsModule.hidePageMaskSpinner();
          });
      });
    }
  }

  /**
   * Call the multi-package assessor with the array + user context.
   * Returns a Promise that resolves when overlay is displayed or fails.
   */
  function callAxialyAnalysisPackageAssessor(analysisPackagesArray, userContext) {
    var apiKey = (window.AxiaBAConfig && window.AxiaBAConfig.api_key)
      ? window.AxiaBAConfig.api_key
      : '';
    var assessorUrl = 'https://api.axialy.ai/axialy_analysis_package_assessor.php';

    var requestBody = {
      analysis_packages_array: analysisPackagesArray,
      user_context_text: userContext
    };

    return fetch(assessorUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-API-Key': apiKey
      },
      body: JSON.stringify(requestBody)
    })
    .then(function(assessorResp) {
      if (!assessorResp.ok) {
        throw new Error('axialy_analysis_package_assessor returned an error.');
      }
      return assessorResp.json();
    })
    .then(function(assessorData) {
      if (assessorData.error) {
        throw new Error('Axialy assessor error: ' + assessorData.error);
      }
      if (!assessorData.axialy_advice) {
        throw new Error('No axialy_advice in assessor response.');
      }
      showAxialyAssessmentOverlay(assessorData.axialy_advice);
    });
  }

  /**
   * Renders the Axialy advice in the overlay, including “package” tiles
   * that can be double-clicked to open that package in the Refine tab.
   */
  function showAxialyAssessmentOverlay(axialyAdvice) {
    var overlay    = document.getElementById('axialy-assessment-overlay');
    var bodyRegion = document.getElementById('axialy-assessment-body');
    if (!overlay || !bodyRegion) {
      return;
    }

    var html = '' +
      '<style>' +
      '.axialy-package-tile {' +
      '  border: 1px solid #ccc;' +
      '  padding: 8px;' +
      '  margin: 6px 0;' +
      '  border-radius: 4px;' +
      '  transition: box-shadow 0.2s ease;' +
      '  cursor: pointer;' +
      '  background-color: #fafafa;' +
      '}' +
      '.axialy-package-tile:hover {' +
      '  box-shadow: 0 0 6px rgba(0,0,0,0.15);' +
      '  background-color: #f3fdfd;' +
      '}' +
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

    // package_assessments
    if (Array.isArray(axialyAdvice.package_assessments)) {
      html += '<div style="margin-top:1em;"><strong>Package Assessments:</strong></div>';
      for (var i = 0; i < axialyAdvice.package_assessments.length; i++) {
        var pa = axialyAdvice.package_assessments[i];
        var tileId = 'axialy-package-tile-' + (pa.package_id || i);

        html += '<div class="axialy-package-tile" id="' + tileId + '">';
        html += '<p><strong>Rank #' + (i+1) + ' - ' + escapeHtml(pa.assessment_ranking) + '</strong></p>';

        if (pa.package_id || pa.package_name) {
          var showId   = pa.package_id   ? escapeHtml(pa.package_id)   : '??';
          var showName = pa.package_name ? escapeHtml(pa.package_name) : 'Unnamed';
          html += '<p><em>Package:</em> [' + showId + '] ' + showName + '</p>';
        }
        html += '<p>' + escapeHtml(pa.assessment_advisement) + '</p>';
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

    // Double-click logic
    if (Array.isArray(axialyAdvice.package_assessments)) {
      for (var j = 0; j < axialyAdvice.package_assessments.length; j++) {
        (function(idx) {
          var pa = axialyAdvice.package_assessments[idx];
          var tileId = 'axialy-package-tile-' + (pa.package_id || idx);
          var tileEl = document.getElementById(tileId);
          if (tileEl) {
            tileEl.addEventListener('dblclick', function() {
              if (pa.package_id && pa.package_name) {
                console.log('[AxialyAssessment] dblclick => open package', pa.package_id, pa.package_name);
                if (typeof window.openRefineTabAndSelectPackage === 'function') {
                  // close overlay, open package
                  overlay.style.display = 'none';
                  window.openRefineTabAndSelectPackage(pa.package_id, pa.package_name);
                } else {
                  alert('No openRefineTabAndSelectPackage function found.');
                }
              } else {
                alert('Cannot open package. ID or name is missing.');
              }
            });
          }
        })(j);
      }
    }
  }

  function escapeHtml(str) {
    if (!str) {
      return '';
    }
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  // Expose the init method
  return {
    init: initAxialyAdvisorButton
  };
})();
