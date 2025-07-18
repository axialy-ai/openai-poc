// /js/support-tickets.js
(function() {
  let overlay = null;      // the .overlay element
  let content = null;      // .overlay-content container
  let mainArea = null;     // sub-container for dynamic content (list, details, or form)
  let showClosed = false;  // track user preference (Show Closed Tickets)

  /**
   * Called from the "Support Tickets" menu item under Help icon.
   */
  window.openSupportTicketsOverlay = function() {
    // OPTIONAL: dynamically load the new support-tickets.css if not already linked
    // loadSupportTicketsCSS(); 
    createOverlayIfNeeded();
    showTicketList();
  };

  /**
   * Creates the overlay if it doesn't exist. Reuses classes from overlay.css
   * and references new classes from support-tickets.css
   */
    // sample snippet (in support-tickets.js or your JS code)
    function createOverlayIfNeeded() {
      if (overlay) {
        overlay.style.display = 'flex';
        return;
      }
      
      overlay = document.createElement('div');
      overlay.className = 'overlay';
      
      content = document.createElement('div');
      content.className = 'overlay-content';
    
      // 1) A "close row" for the X button alone
      const closeRow = document.createElement('div');
      closeRow.className = 'overlay-close-row';
    
      const closeBtn = document.createElement('span');
      closeBtn.className = 'close-overlay'; 
      closeBtn.innerHTML = '&times;';
      closeBtn.onclick = function() {
        closeOverlay();
      };
      closeRow.appendChild(closeBtn);
      content.appendChild(closeRow);
    
      // 2) Then your "mainArea" (the place where the header bar, table, etc. go)
      mainArea = document.createElement('div');
      mainArea.id = 'supportTicketsMainArea';
      content.appendChild(mainArea);
    
      overlay.appendChild(content);
      document.body.appendChild(overlay);
    
      // ESC key to close
      document.addEventListener('keydown', handleEscKey);
    }


  /**
   * Closes the overlay and cleans up references
   */
  function closeOverlay() {
    if (overlay && overlay.parentNode) {
      overlay.parentNode.removeChild(overlay);
    }
    overlay = null;
    content = null;
    mainArea = null;
    document.removeEventListener('keydown', handleEscKey);
    showClosed = false; // reset the checkbox state next time
  }

  /**
   * If user presses ESC, close overlay
   */
  function handleEscKey(e) {
    if (e.key === 'Escape' && overlay) {
      closeOverlay();
    }
  }

  // ----------------------------------------------------------------
  // 1) SHOW TICKETS LIST
  // ----------------------------------------------------------------
  function showTicketList() {
    if (!mainArea) return;
    mainArea.innerHTML = ''; // Clear existing content

    // Header bar (title + controls)
    const headerBar = document.createElement('div');
    headerBar.className = 'support-tickets-header';

    const titleEl = document.createElement('h2');
    titleEl.textContent = 'Your Support Tickets';

    // Right-side controls
    const controlsDiv = document.createElement('div');
    controlsDiv.className = 'support-tickets-controls';

    // A label + checkbox for “Show Closed Tickets”
    const cbContainer = document.createElement('label');
    cbContainer.className = 'support-tickets-checkbox';

    const closedCheckbox = document.createElement('input');
    closedCheckbox.type = 'checkbox';
    closedCheckbox.checked = showClosed;
    closedCheckbox.onchange = () => {
      showClosed = closedCheckbox.checked;
      fetchAndRenderTickets();
    };

    const cbLabelText = document.createTextNode('Show Closed Tickets');

    cbContainer.appendChild(closedCheckbox);
    cbContainer.appendChild(cbLabelText);

    // “New Support Ticket” button
    const newTicketBtn = document.createElement('button');
    newTicketBtn.className = 'support-tickets-button';
    newTicketBtn.textContent = 'New Support Ticket';
    newTicketBtn.onclick = openNewSupportTicketForm;

    controlsDiv.appendChild(cbContainer);
    controlsDiv.appendChild(newTicketBtn);

    headerBar.appendChild(titleEl);
    headerBar.appendChild(controlsDiv);

    // Table container
    const tableContainer = document.createElement('div');
    tableContainer.style.marginTop = '20px';

    mainArea.appendChild(headerBar);
    mainArea.appendChild(tableContainer);

    // Now fetch and display tickets
    fetchAndRenderTickets();

    function fetchAndRenderTickets() {
      tableContainer.innerHTML = '';

      // Determine which URL to fetch
      const url = showClosed
        ? '/issue_ajax_actions.php?action=listTickets&includeClosed=1'
        : '/issue_ajax_actions.php?action=listTickets';

      fetch(url)
        .then(async (res) => {
          if (!res.ok) {
            const text = await res.text();
            throw new Error(`Request failed: ${res.status} ${text}`);
          }
          return res.json();
        })
        .then(data => {
          if (!data.success) {
            throw new Error(data.message || 'Failed to retrieve tickets');
          }

          if (!data.tickets || data.tickets.length === 0) {
            const emptyMsg = document.createElement('p');
            emptyMsg.textContent = showClosed 
              ? 'No tickets found (including closed).'
              : 'No open tickets found.';
            tableContainer.appendChild(emptyMsg);
            return;
          }

          // Build a table
          const table = document.createElement('table');
          table.className = 'support-tickets-table';

          // Table head
          const thead = document.createElement('thead');
          const trHead = document.createElement('tr');
          ['Support Ticket ID','Title','Status','Submitted On'].forEach(col => {
            const th = document.createElement('th');
            th.textContent = col;
            trHead.appendChild(th);
          });
          thead.appendChild(trHead);
          table.appendChild(thead);

          // Table body
          const tbody = document.createElement('tbody');
          data.tickets.forEach(ticket => {
            const tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            tr.onclick = () => openTicketDetails(ticket.id);

            const tdId = document.createElement('td');
            tdId.textContent = ticket.id;

            const tdTitle = document.createElement('td');
            tdTitle.textContent = ticket.issue_title;

            const tdStatus = document.createElement('td');
            tdStatus.textContent = ticket.status;

            const tdCreated = document.createElement('td');
            tdCreated.textContent = ticket.created_at;

            tr.appendChild(tdId);
            tr.appendChild(tdTitle);
            tr.appendChild(tdStatus);
            tr.appendChild(tdCreated);
            tbody.appendChild(tr);
          });
          table.appendChild(tbody);
          tableContainer.appendChild(table);
        })
        .catch(err => {
          const errorMsg = document.createElement('p');
          errorMsg.style.color = 'red';
          errorMsg.textContent = 'Error retrieving tickets: ' + err.message;
          tableContainer.appendChild(errorMsg);
        });
    }
  }

  // ----------------------------------------------------------------
  // 2) TICKET DETAILS VIEW
  // ----------------------------------------------------------------
  function openTicketDetails(ticketId) {
    if (!mainArea) return;
    mainArea.innerHTML = '';

    // Header with title + “Back to Tickets” button
    const headerBar = document.createElement('div');
    headerBar.className = 'support-tickets-header';

    const titleEl = document.createElement('h2');
    titleEl.textContent = 'Support Ticket Details';

    const controlsDiv = document.createElement('div');
    controlsDiv.className = 'support-tickets-controls';

    const backBtn = document.createElement('button');
    backBtn.className = 'support-tickets-button secondary';
    backBtn.textContent = 'Back to Tickets';
    backBtn.onclick = showTicketList;

    controlsDiv.appendChild(backBtn);

    headerBar.appendChild(titleEl);
    headerBar.appendChild(controlsDiv);

    // Body container
    const detailsContainer = document.createElement('div');
    detailsContainer.style.marginTop = '20px';

    mainArea.appendChild(headerBar);
    mainArea.appendChild(detailsContainer);

    fetch(`/issue_ajax_actions.php?action=getTicketDetails&ticketId=${ticketId}`)
      .then(async (res) => {
        if (!res.ok) {
          const text = await res.text();
          throw new Error(`Request failed: ${res.status} ${text}`);
        }
        return res.json();
      })
      .then(data => {
        if (!data.success || !data.ticket) {
          throw new Error(data.message || 'Unable to load ticket details');
        }

        const ticket = data.ticket;
        const pId = document.createElement('p');
        pId.innerHTML = `<strong>Ticket ID:</strong> ${ticket.id}`;

        const pTitle = document.createElement('p');
        pTitle.innerHTML = `<strong>Title:</strong> ${ticket.issue_title}`;

        const pStatus = document.createElement('p');
        pStatus.innerHTML = `<strong>Status:</strong> ${ticket.status}`;

        const pCreated = document.createElement('p');
        pCreated.innerHTML = `<strong>Submitted On:</strong> ${ticket.created_at}`;

        const pUpdated = document.createElement('p');
        pUpdated.innerHTML = `<strong>Last Updated:</strong> ${ticket.updated_at || 'N/A'}`;

        const pDesc = document.createElement('p');
        pDesc.innerHTML = `<strong>Details:</strong><br>${ticket.issue_description}`;

        detailsContainer.appendChild(pId);
        detailsContainer.appendChild(pTitle);
        detailsContainer.appendChild(pStatus);
        detailsContainer.appendChild(pCreated);
        detailsContainer.appendChild(pUpdated);
        detailsContainer.appendChild(pDesc);
      })
      .catch(err => {
        const eMsg = document.createElement('p');
        eMsg.style.color = 'red';
        eMsg.textContent = 'Error retrieving ticket details: ' + err.message;
        detailsContainer.appendChild(eMsg);
      });
  }

  // ----------------------------------------------------------------
  // 3) NEW TICKET FORM
  // ----------------------------------------------------------------
  function openNewSupportTicketForm() {
    if (!mainArea) return;
    mainArea.innerHTML = '';

    // Header
    const headerBar = document.createElement('div');
    headerBar.className = 'support-tickets-header';

    const titleEl = document.createElement('h2');
    titleEl.textContent = 'New Support Ticket';

    const controlsDiv = document.createElement('div');
    controlsDiv.className = 'support-tickets-controls';

    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'support-tickets-button secondary';
    cancelBtn.textContent = 'Cancel';
    cancelBtn.onclick = showTicketList;

    controlsDiv.appendChild(cancelBtn);

    headerBar.appendChild(titleEl);
    headerBar.appendChild(controlsDiv);

    // Body
    const formContainer = document.createElement('div');
    formContainer.style.marginTop = '20px';

    // Title field
    const lblTitle = document.createElement('label');
    lblTitle.textContent = 'Title';
    lblTitle.style.display = 'block';
    lblTitle.style.marginBottom = '5px';

    const inputTitle = document.createElement('input');
    inputTitle.type = 'text';
    inputTitle.style.width = '100%';
    inputTitle.style.marginBottom = '15px';
    inputTitle.placeholder = 'Short title for your support request';

    // Description field
    const lblDesc = document.createElement('label');
    lblDesc.textContent = 'Details';
    lblDesc.style.display = 'block';
    lblDesc.style.marginBottom = '5px';

    const textarea = document.createElement('textarea');
    textarea.rows = 6;
    textarea.style.width = '100%';
    textarea.placeholder = 'Describe your question, request, or issue...';

    // Submit button
    const submitBtn = document.createElement('button');
    submitBtn.className = 'support-tickets-button success';
    submitBtn.textContent = 'Submit Ticket';
    submitBtn.style.marginTop = '15px';
    submitBtn.onclick = function() {
      const titleVal = inputTitle.value.trim();
      const descVal = textarea.value.trim();
      if (!titleVal || !descVal) {
        alert('Please provide both a title and details for your ticket.');
        return;
      }
      submitNewTicket(titleVal, descVal);
    };

    formContainer.appendChild(lblTitle);
    formContainer.appendChild(inputTitle);

    formContainer.appendChild(lblDesc);
    formContainer.appendChild(textarea);

    formContainer.appendChild(submitBtn);

    mainArea.appendChild(headerBar);
    mainArea.appendChild(formContainer);
  }

  /**
   * Submits a new support ticket to the server
   */
  function submitNewTicket(title, description) {
    fetch('/issue_ajax_actions.php?action=createTicket', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ticket_title: title,
        ticket_description: description
      })
    })
    .then(async (res) => {
      if (!res.ok) {
        const text = await res.text();
        throw new Error(`Request failed: ${res.status} ${text}`);
      }
      return res.json();
    })
    .then(data => {
      if (!data.success) {
        throw new Error(data.message || 'Error creating ticket');
      }
      alert('Your support ticket has been submitted. Thank you!');
      showTicketList();
    })
    .catch(err => {
      alert('Error: ' + err.message);
    });
  }

  /**
   * OPTIONAL dynamic loading of support-tickets.css if not already included in HTML
   */
  function loadSupportTicketsCSS() {
    if (!document.getElementById('support-tickets-css')) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.id = 'support-tickets-css';
      link.href = '/assets/css/support-tickets.css';
      document.head.appendChild(link);
    }
  }
})();
