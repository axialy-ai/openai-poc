// /js/publish/index.js
// Minimal placeholder module for future Publish features
var PublishIndexModule = (function() {

  function initializePublishTab() {
    console.log('Publish tab initialized.');

    // 1) Grab references to elements in publish-tab.html
    const submitBtn = document.getElementById('submitPublishIdeaBtn');
    const ideaInput = document.getElementById('publishIdeaInput');
    const confirmationBox = document.getElementById('publishIdeaConfirmation');
    const spinnerOverlay = document.getElementById('spinnerOverlay');

    // 2) Attach the click event
    if (submitBtn) {
      submitBtn.addEventListener('click', () => {
        const ideaText = (ideaInput.value || '').trim();
        if (!ideaText) {
          alert('Please enter your idea before submitting.');
          return;
        }
        // Show spinner
        if (spinnerOverlay) spinnerOverlay.style.display = 'flex';

        // Perform fetch to create an issues record
        fetch('/issue_ajax_actions.php?action=createTicket', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            ticket_title: 'New Idea for the AxiaBA Production Tab!',
            ticket_description: ideaText
          })
        })
        .then(async response => {
          // Hide spinner
          if (spinnerOverlay) spinnerOverlay.style.display = 'none';

          if (!response.ok) {
            const text = await response.text();
            throw new Error(`Request failed: ${response.status} ${text}`);
          }
          return response.json();
        })
        .then(data => {
          if (!data.success) {
            throw new Error(data.message || 'Server returned an error');
          }
          // Show confirmation
          if (confirmationBox) {
            confirmationBox.style.display = 'block';
            confirmationBox.innerHTML = `
              <strong>Thank you!</strong> Your idea has been submitted. 
              You can track its status under <strong>Support Tickets</strong>.
            `;
          }
          // Clear the input
          if (ideaInput) ideaInput.value = '';
        })
        .catch(err => {
          alert('Submission failed: ' + err.message);
        });
      });
    } else {
      console.log("Publish tab elements not found yet.");
    }
  }

  return {
    initializePublishTab: initializePublishTab
  };
})();
