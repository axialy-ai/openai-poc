<?php
session_name('axialy_admin_session');
session_start();

require_once __DIR__ . '/includes/admin_auth.php';
requireAdminAuth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Axialy Admin - Issues</title>
  <style>
    body {
      font-family: sans-serif;
      margin: 0;
      padding: 0;
      height: 100vh;
      display: flex;
      flex-direction: row;
      overflow: hidden;
      background: #f9f9f9;
    }
    /* Left panel */
    #left-panel {
      border-right: 1px solid #ccc;
      transition: width 0.2s ease;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      background: #fff;
    }
    #left-panel.expanded {
      width: 300px;
      min-width: 200px;
      max-width: 400px;
    }
    #left-panel.collapsed {
      width: 0;
      min-width: 0;
      max-width: 0;
    }
    #panel-header {
      background: #f8f8f8;
      padding: 8px;
      border-bottom: 1px solid #ccc;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    #panel-header img {
      height: 40px;
    }
    #panel-header h1 {
      font-size: 1.1rem;
      margin: 0;
    }
    #issue-list-container {
      flex: 1;
      overflow-y: auto;
      padding: 10px;
    }
    .issue-item {
      padding: 8px 4px;
      border-bottom: 1px solid #eee;
      cursor: pointer;
    }
    .issue-item:hover {
      background: #f0f0f0;
    }
    /* Right panel */
    #right-panel {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    #right-header {
      background: #f8f8f8;
      padding: 8px;
      border-bottom: 1px solid #ccc;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    #right-header-left {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    #right-header-left img {
      height: 30px;
    }
    #issue-detail-container {
      flex: 1;
      overflow: auto;
      padding: 10px;
    }
    #footer {
      border-top: 1px solid #ccc;
      background: #fafafa;
      padding: 8px 10px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    #footer-left {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    #toggle-btn {
      cursor: pointer;
      padding: 4px 8px;
      background: #007BFF;
      color: #fff;
      border: none;
      border-radius: 4px;
    }
    #toggle-btn:hover {
      background: #0056b3;
    }
    .hidden {
      display: none;
    }
    label {
      display: block;
      margin-top: 8px;
      font-weight: bold;
    }
    input[type="text"], textarea {
      width: 100%;
      padding: 6px;
      box-sizing: border-box;
      margin-top: 4px;
    }
    select {
      padding: 5px;
      margin-top: 4px;
    }
    button {
      margin-top: 10px;
      cursor: pointer;
      padding: 8px 12px;
      border-radius: 4px;
      border: 1px solid #444;
      background: #eee;
    }
    .error { color: #dc3545; margin-top: 10px; }
    .success { color: #28a745; margin-top: 10px; }

    /* ---- MOBILE RESPONSIVE BREAKPOINT ---- */
    @media (max-width: 768px) {
      body {
        flex-direction: column; /* stack top-to-bottom */
        height: auto;
      }
      #left-panel, #right-panel {
        width: 100% !important;
        max-width: 100% !important;
        height: auto;
      }
      #left-panel.expanded, #left-panel.collapsed {
        width: 100% !important;
        max-width: 100% !important;
      }
      #toggle-btn {
        margin-bottom: 10px;
      }
      #panel-header h1 {
        font-size: 1rem;
      }
    }
  </style>
</head>
<body>
  <!-- Left Panel -->
  <div id="left-panel" class="expanded">
    <div id="panel-header">
<!--
      <img src="https://axiaba.com/assets/img/SOI.png" alt="Axialy Logo"/>
-->
      <a href="index.php">
        <img src="https://axiaba.com/assets/img/SOI.png" alt="Axialy Logo" style    ="cursor: pointer;"/>
      </a>

      <h1>Issues</h1>
    </div>
    <div id="issue-list-container">
      <h2>All Issues</h2>
      <div id="issue-list">Loading issues...</div>
    </div>
  </div>

  <!-- Right Panel -->
  <div id="right-panel">
    <div id="right-header">
      <div id="right-header-left">
        <img src="https://axiaba.com/assets/img/product_logo.png" alt="Axialy Logo"/>
        <strong>Issue Details</strong>
      </div>
      <div></div>
    </div>

    <div id="issue-detail-container">
      <div id="edit-form" class="hidden">
        <h3>Edit Issue</h3>
        <input type="hidden" id="edit-id" />

        <div style="margin-bottom:10px;">
          <strong>User Info:</strong>
          <div>ID: <span id="edit-user-id"></span></div>
          <div>Username: <span id="edit-user-name"></span></div>
          <div>Email: <span id="edit-user-email"></span></div>
        </div>

        <label for="edit-title">Title:</label>
        <input type="text" id="edit-title" />
        
        <label for="edit-desc">Description:</label>
        <textarea id="edit-desc" rows="5"></textarea>
        
        <label for="edit-status">Status:</label>
        <select id="edit-status">
          <option value="New">New</option>
          <option value="Reviewed">Reviewed</option>
          <option value="Open">Open</option>
          <option value="In Progress">In Progress</option>
          <option value="Resolved">Resolved</option>
          <option value="Closed">Closed</option>
        </select>

        <button onclick="updateIssue()">Save Changes</button>
        <button onclick="hideEditForm()">Cancel</button>

        <!-- Email to user section -->
        <div style="margin-top: 20px; border: 1px solid #ccc; padding: 10px;">
          <label for="personal-message">Personal Message to User:</label>
          <textarea id="personal-message" rows="3" placeholder="Type a brief message here..."></textarea>
          <button onclick="sendEmailToUser()">Send Email to User</button>
        </div>
        
        <div id="edit-error" class="error" style="display:none;"></div>
        <div id="edit-success" class="success" style="display:none;"></div>
      </div>
    </div>

    <div id="footer">
      <div id="footer-left">
        <button id="toggle-btn" onclick="toggleLeftPanel()">Collapse</button>
      </div>
      <div></div>
    </div>
  </div>

  <script>
  function toggleLeftPanel() {
    const leftPanel = document.getElementById('left-panel');
    const btn = document.getElementById('toggle-btn');
    if (leftPanel.classList.contains('expanded')) {
      leftPanel.classList.remove('expanded');
      leftPanel.classList.add('collapsed');
      btn.textContent = 'Expand';
    } else {
      leftPanel.classList.remove('collapsed');
      leftPanel.classList.add('expanded');
      btn.textContent = 'Collapse';
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    fetchIssues();
  });

  function fetchIssues() {
    fetch('issues_ajax_actions.php?action=list')
      .then(r => r.json())
      .then(data => {
        if (!Array.isArray(data)) {
          document.getElementById('issue-list').textContent = 'Error loading issues.';
          return;
        }
        renderIssueList(data);
      })
      .catch(err => {
        console.error(err);
        document.getElementById('issue-list').textContent = 'Error loading issues.';
      });
  }

  function renderIssueList(issues) {
    const container = document.getElementById('issue-list');
    container.innerHTML = '';
    issues.forEach(issue => {
      const div = document.createElement('div');
      div.className = 'issue-item';
      div.innerHTML = `
        <strong>#${issue.id} - ${escapeHtml(issue.issue_title)}</strong><br>
        Status: ${escapeHtml(issue.status)}<br>
        <small>Created At: ${escapeHtml(issue.created_at)}</small>
      `;
      div.onclick = () => {
        showEditForm(issue.id);
      };
      container.appendChild(div);
    });
  }

  function showEditForm(issueId) {
    fetch(`issues_ajax_actions.php?action=get&id=${issueId}`)
      .then(r => r.json())
      .then(data => {
        if (!data.id) {
          alert('Issue not found.');
          return;
        }
        document.getElementById('edit-id').value = data.id;
        // user info from left join
        document.getElementById('edit-user-id').textContent   = data.user_id_lookup || '';
        document.getElementById('edit-user-name').textContent = data.username || '';
        document.getElementById('edit-user-email').textContent= data.user_email || '';

        document.getElementById('edit-title').value = data.issue_title;
        document.getElementById('edit-desc').value  = data.issue_description;
        document.getElementById('edit-status').value= data.status;

        document.getElementById('edit-form').classList.remove('hidden');
      })
      .catch(err => {
        alert('Error retrieving issue: ' + err);
      });
  }

  function hideEditForm() {
    document.getElementById('edit-form').classList.add('hidden');
  }

/***************************************************************************/

  function updateIssue() {
    const id    = document.getElementById('edit-id').value;
    const title = document.getElementById('edit-title').value.trim();
    const desc  = document.getElementById('edit-desc').value.trim();
    const stat  = document.getElementById('edit-status').value;
  
    if (!id || !title || !desc) {
      alert('Title and description cannot be empty.');
      return;
    }
    const payload = {
      id,
      issue_title: title,
      issue_description: desc,
      status: stat
    };
    fetch('issues_ajax_actions.php?action=update', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(resp => {
      if (!resp.success) {
        document.getElementById('edit-error').textContent = resp.message || 'Error   updating issue.';
        document.getElementById('edit-error').style.display = 'block';
      } else {
        document.getElementById('edit-error').style.display = 'none';
        document.getElementById('edit-success').textContent = 'Issue updated!';
        document.getElementById('edit-success').style.display = 'block';
  
        // Refresh the left list so we see updated status there:
        fetchIssues();
        // DO NOT CALL hideEditForm() => the form stays open
      }
    })
    .catch(err => {
      document.getElementById('edit-error').textContent = err.toString();
      document.getElementById('edit-error').style.display = 'block';
    });
  }

/***************************************************************************/


  // ---- Send Email to user
  function sendEmailToUser() {
    const issueId = document.getElementById('edit-id').value;
    const personalMsg = document.getElementById('personal-message').value.trim();
    if (!issueId) {
      alert('No issue is loaded.');
      return;
    }
    if (!personalMsg) {
      alert('Please type a personal message before sending.');
      return;
    }
    const payload = {
      id: issueId,
      personal_message: personalMsg
    };
    fetch('issues_ajax_actions.php?action=sendEmail', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(resp => {
      if (!resp.success) {
        alert('Email sending failed: ' + resp.message);
      } else {
        alert('Email sent successfully!');
        // We can optionally clear the personal message field.
        document.getElementById('personal-message').value = '';
      }
    })
    .catch(err => {
      alert('Error sending email: ' + err);
    });
  }

  function escapeHtml(str) {
    if(!str) return '';
    return str.replace(/[<>&"']/g, function(m){
      switch(m){
        case '<': return '&lt;';
        case '>': return '&gt;';
        case '&': return '&amp;';
        case '"': return '&quot;';
        case '\'': return '&#39;';
      }
    });
  }
  </script>
</body>
</html>
