document.addEventListener('DOMContentLoaded', function() {
    const experienceFeedbackBtn = document.getElementById('provide-experience-feedback-btn');
    const experienceOverlay = document.getElementById('experience-feedback-overlay');
    const closeExperienceOverlayBtn = document.getElementById('close-experience-overlay');
    const viewPendingRequestsBtn = document.getElementById('view-pending-requests-btn');
    const pendingRequestsOverlay = document.getElementById('pending-requests-overlay');
    const closePendingOverlayBtn = document.getElementById('close-pending-overlay');

    // Only add event listeners if these elements exist
    if (experienceFeedbackBtn && experienceOverlay && closeExperienceOverlayBtn) {
      experienceFeedbackBtn.addEventListener('click', function() {
          experienceOverlay.style.display = 'flex';
      });
      closeExperienceOverlayBtn.addEventListener('click', function() {
          experienceOverlay.style.display = 'none';
      });
    }

    if (viewPendingRequestsBtn && pendingRequestsOverlay && closePendingOverlayBtn) {
      viewPendingRequestsBtn.addEventListener('click', function() {
          pendingRequestsOverlay.style.display = 'flex';
      });
      closePendingOverlayBtn.addEventListener('click', function() {
          pendingRequestsOverlay.style.display = 'none';
      });
    }

    // Handle Esc key to close overlays if they exist
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            if (experienceOverlay) experienceOverlay.style.display = 'none';
            if (pendingRequestsOverlay) pendingRequestsOverlay.style.display = 'none';
        }
    });
});
