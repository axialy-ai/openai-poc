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
  <title>Axialy Admin - Promo Codes</title>
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
    #promo-list {
      flex: 1;
      overflow-y: auto;
      padding: 10px;
    }
    .promo-item {
      padding: 8px 4px;
      border-bottom: 1px solid #eee;
      cursor: pointer;
    }
    .promo-item:hover {
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
    #promo-detail-container {
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
    button {
      margin: 5px 0;
      padding: 6px 12px;
      cursor: pointer;
      border-radius: 4px;
      border: 1px solid #666;
      background: #eee;
    }
    .hidden { display: none; }
    #create-form, #edit-form {
      border: 1px solid #ccc;
      padding: 10px;
      margin: 10px 0;
      background: #fff;
    }
    label { display: block; margin-top: 8px; font-weight: bold; }
    input[type="text"], textarea, select, input[type="number"] {
      width: 100%;
      padding: 6px;
      box-sizing: border-box;
      margin-top: 4px;
    }
    .error { color: #dc3545; margin-top: 10px; }
    .success { color: #28a745; margin-top: 10px; }
    /* Add this to the bottom of each page's <style> */
    /* Adjust 768px if you prefer a different breakpoint */
    
    @media (max-width: 768px) {
      body {
        flex-direction: column; /* Instead of row */
        height: auto;           /* Let panels expand naturally */
      }
    
      #left-panel, #right-panel {
        width: 100% !important;
        max-width: 100% !important;
        height: auto; /* Allow full height as needed */
      }
    
      /* If using 'expanded'/'collapsed' classes, override them for mobile: */
      #left-panel.expanded, #left-panel.collapsed {
        width: 100% !important;
        max-width: 100% !important;
      }
    
      /* Possibly hide the toggle button or rename it for mobile, etc. */
      /* Example: place toggle button at the top if you prefer. */
      #toggle-btn {
        margin-bottom: 10px;
      }
    
      /* You can also adjust fonts, padding, etc., if desired. */
      #panel-header h1,
      #right-header-left strong {
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
      </a>      <h1>Promo Codes</h1>
    </div>
    <div id="promo-list">Loading...</div>
  </div>

  <!-- Right Panel -->
  <div id="right-panel">
    <div id="right-header">
      <div id="right-header-left">
        <img src="https://axiaba.com/assets/img/product_logo.png" alt="Axialy Logo"/>
        <strong>Promo Codes Management</strong>
      </div>
      <div></div>
    </div>
    <div id="promo-detail-container">
      <button onclick="showCreateForm()">Create New Promo Code</button>

      <div id="create-form" class="hidden">
        <h3>Create Promo Code</h3>
        <label>Code:
          <input type="text" id="create-code" placeholder="E.g. FREE2025" />
        </label>
        <label>Description (optional):
          <textarea id="create-desc" rows="3"></textarea>
        </label>
        <label>Type:
          <select id="create-type">
            <option value="unlimited">Unlimited</option>
            <option value="limited">Limited</option>
          </select>
        </label>
        <label>Limited Days (if limited):
          <input type="number" id="create-days" value="7" />
        </label>
        <label>Statement Required?
          <input type="checkbox" id="create-statement-required" />
        </label>
        <label>Statement Text (optional):
          <textarea id="create-statement" rows="3"></textarea>
        </label>
        <label>Start Date (YYYY-MM-DD HH:MM:SS):
          <input type="text" id="create-start" placeholder="2025-04-01 00:00:00" />
        </label>
        <label>End Date (YYYY-MM-DD HH:MM:SS):
          <input type="text" id="create-end" placeholder="2025-04-30 23:59:59" />
        </label>
        <label>Usage Limit (optional):
          <input type="number" id="create-limit" placeholder="e.g. 100" />
        </label>
        <button onclick="submitCreate()">Save</button>
        <button onclick="hideCreateForm()">Cancel</button>
        <div id="create-error" class="error" style="display:none;"></div>
        <div id="create-success" class="success" style="display:none;"></div>
      </div>

      <div id="edit-form" class="hidden">
        <h3>Edit Promo Code</h3>
        <input type="hidden" id="edit-id" />
        <label>Code:
          <input type="text" id="edit-code" />
        </label>
        <label>Description:
          <textarea id="edit-desc" rows="3"></textarea>
        </label>
        <label>Type:
          <select id="edit-type">
            <option value="unlimited">Unlimited</option>
            <option value="limited">Limited</option>
          </select>
        </label>
        <label>Limited Days:
          <input type="number" id="edit-days" />
        </label>
        <label>Statement Required?
          <input type="checkbox" id="edit-statement-required" />
        </label>
        <label>Statement Text:
          <textarea id="edit-statement" rows="3"></textarea>
        </label>
        <label>Start Date:
          <input type="text" id="edit-start" />
        </label>
        <label>End Date:
          <input type="text" id="edit-end" />
        </label>
        <label>Usage Limit:
          <input type="number" id="edit-limit" />
        </label>
        <label>Active?
          <input type="checkbox" id="edit-active" checked />
        </label>
        <button onclick="submitEdit()">Save Changes</button>
        <button onclick="hideEditForm()">Cancel</button>
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
    fetchPromoCodes();
  });

  function fetchPromoCodes() {
    fetch('promo_codes_ajax_actions.php?action=list')
      .then(res => res.json())
      .then(data => {
        if (!Array.isArray(data)) {
          document.getElementById('promo-list').textContent = 'Error loading promo codes.';
          return;
        }
        renderPromoList(data);
      })
      .catch(err => {
        console.error(err);
        document.getElementById('promo-list').textContent = 'Error loading promo codes.';
      });
  }

  function renderPromoList(codes) {
    let html = '';
    for (let pc of codes) {
      html += `
        <div class="promo-item" onclick="showEditForm(${pc.id})">
          <strong>${escapeHtml(pc.code)}</strong>
          - Type: ${pc.code_type}
          - Active: ${pc.active == 1 ? 'Yes' : 'No'}
          - Used: ${pc.usage_count}
        </div>
      `;
    }
    document.getElementById('promo-list').innerHTML = html;
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

  function showCreateForm() {
    document.getElementById('create-form').classList.remove('hidden');
  }
  function hideCreateForm() {
    document.getElementById('create-form').classList.add('hidden');
  }
  function submitCreate() {
    const code = document.getElementById('create-code').value.trim();
    if (!code) {
      showCreateError('Code cannot be empty.');
      return;
    }
    const desc = document.getElementById('create-desc').value.trim();
    const ctype= document.getElementById('create-type').value;
    const days = parseInt(document.getElementById('create-days').value) || 0;
    const sreq = document.getElementById('create-statement-required').checked ? 1 : 0;
    const stm  = document.getElementById('create-statement').value.trim();
    const sd   = document.getElementById('create-start').value.trim();
    const ed   = document.getElementById('create-end').value.trim();
    const ul   = document.getElementById('create-limit').value.trim();

    const payload = {
      code, description: desc, code_type: ctype,
      limited_days: days, statement_required: sreq,
      statement: stm, start_date: sd, end_date: ed,
      usage_limit: ul
    };
    fetch('promo_codes_ajax_actions.php?action=create', {
      method: 'POST',
      headers: { 'Content-Type':'application/json' },
      body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(resp => {
      if(!resp.success) {
        showCreateError(resp.message);
      } else {
        document.getElementById('create-success').textContent = 'Promo code created!';
        document.getElementById('create-success').style.display = 'block';
        setTimeout(() => {
          hideCreateForm();
          fetchPromoCodes();
        }, 1000);
      }
    })
    .catch(err => {
      showCreateError(err.toString());
    });
  }
  function showCreateError(msg) {
    document.getElementById('create-error').textContent = msg;
    document.getElementById('create-error').style.display = 'block';
  }

  function showEditForm(id) {
    fetch(`promo_codes_ajax_actions.php?action=get&id=${id}`)
      .then(r => r.json())
      .then(pc => {
        if (!pc.id) {
          alert('Promo code not found.');
          return;
        }
        document.getElementById('edit-id').value = pc.id;
        document.getElementById('edit-code').value = pc.code;
        document.getElementById('edit-desc').value = pc.description || '';
        document.getElementById('edit-type').value = pc.code_type;
        document.getElementById('edit-days').value = pc.limited_days || 0;
        document.getElementById('edit-statement-required').checked = (pc.statement_required == 1);
        document.getElementById('edit-statement').value = pc.statement || '';
        document.getElementById('edit-start').value = pc.start_date || '';
        document.getElementById('edit-end').value = pc.end_date || '';
        document.getElementById('edit-limit').value = pc.usage_limit || '';
        document.getElementById('edit-active').checked = (pc.active == 1);

        document.getElementById('edit-form').classList.remove('hidden');
      })
      .catch(err => {
        alert('Error retrieving promo code: ' + err);
      });
  }
  function hideEditForm() {
    document.getElementById('edit-form').classList.add('hidden');
  }
  function submitEdit() {
    const id   = document.getElementById('edit-id').value;
    const code = document.getElementById('edit-code').value.trim();
    if (!code) {
      document.getElementById('edit-error').textContent = 'Code cannot be empty.';
      document.getElementById('edit-error').style.display='block';
      return;
    }
    const desc = document.getElementById('edit-desc').value.trim();
    const ctype= document.getElementById('edit-type').value;
    const days = parseInt(document.getElementById('edit-days').value) || 0;
    const sreq = document.getElementById('edit-statement-required').checked ? 1 : 0;
    const stm  = document.getElementById('edit-statement').value.trim();
    const sd   = document.getElementById('edit-start').value.trim();
    const ed   = document.getElementById('edit-end').value.trim();
    const ul   = document.getElementById('edit-limit').value.trim();
    const act  = document.getElementById('edit-active').checked ? 1 : 0;

    const payload = {
      id, code, description: desc, code_type: ctype,
      limited_days: days, statement_required: sreq,
      statement: stm, start_date: sd, end_date: ed,
      usage_limit: ul, active: act
    };
    fetch('promo_codes_ajax_actions.php?action=update', {
      method: 'POST',
      headers: { 'Content-Type':'application/json' },
      body: JSON.stringify(payload)
    })
    .then(r=>r.json())
    .then(resp=>{
      if(!resp.success) {
        document.getElementById('edit-error').textContent = resp.message;
        document.getElementById('edit-error').style.display='block';
      } else {
        document.getElementById('edit-success').textContent = 'Promo code updated.';
        document.getElementById('edit-success').style.display='block';
        setTimeout(()=>{
          hideEditForm();
          fetchPromoCodes();
        },1000);
      }
    })
    .catch(err=>{
      document.getElementById('edit-error').textContent = err.toString();
      document.getElementById('edit-error').style.display='block';
    });
  }
  </script>
</body>
</html>
