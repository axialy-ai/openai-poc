<?php
session_name('axialy_admin_session');
session_start();

require_once __DIR__ . '/includes/admin_auth.php';
requireAdminAuth();
require_once __DIR__ . '/includes/ui_db_connection.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Axialy Admin - Data Viewer</title>
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
    #table-list-panel {
      flex: 1;
      overflow-y: auto;
      padding: 10px;
      background: #fff;
    }
    .table-item {
      padding: 8px 4px;
      border-bottom: 1px solid #eee;
      cursor: pointer;
    }
    .table-item:hover {
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
    #data-header-controls {
      display: flex;
      align-items: flex-end;
      gap: 12px;
    }
    #data-header-controls h2 {
      margin: 0;
      font-size: 1.2rem;
    }
    #filter-controls {
      display: flex;
      align-items: center;
      gap: 4px;
    }
    #data-container {
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
    table {
      border-collapse: collapse;
      min-width: 600px;
      background: #fff;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 6px 8px;
      text-align: left;
    }
    .pagination button {
      margin-right: 5px;
      cursor: pointer;
      padding: 4px 8px;
    }
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
      </a>      <h1>DB Viewer</h1>
    </div>
    <div id="table-list-panel">
      Loading...
    </div>
  </div>

  <!-- Right Panel -->
  <div id="right-panel">
    <div id="right-header">
      <!-- Left side: brand presence -->
      <div id="right-header-left">
        <img src="https://axiaba.com/assets/img/product_logo.png" alt="Axialy Logo"/>
        <strong>Data Viewer</strong>
      </div>
      <!-- Right side: filter area -->
      <div id="data-header-controls">
        <div id="filter-controls">
          <label for="filter-column">Column:</label>
          <select id="filter-column" style="min-width:120px;"></select>
          <label for="filter-operator">Op:</label>
          <select id="filter-operator" style="min-width:80px;">
            <option value="eq">=</option>
            <option value="neq"><></option>
            <option value="gt">></option>
            <option value="lt"><</option>
            <option value="gte">>=</option>
            <option value="lte"><=</option>
            <option value="like">LIKE</option>
            <option value="nlike">NOT LIKE</option>
          </select>
          <label for="filter-value">Value:</label>
          <input type="text" id="filter-value" placeholder="Enter text" />
          <button type="button" onclick="applyFilter()">Apply</button>
        </div>
      </div>
    </div>

    <div id="data-container">
      <div id="data-status">Select a table on the left</div>
      <div id="table-data"></div>
    </div>

    <div id="footer">
      <div id="footer-left">
        <button id="toggle-btn" onclick="toggleLeftPanel()">Collapse</button>
        Rows per page:
        <select id="page-size-selector" onchange="changePageSize()">
          <option value="25">25</option>
          <option value="50" selected>50</option>
          <option value="100">100</option>
          <option value="250">250</option>
        </select>
      </div>
      <div class="pagination" id="pagination-controls"></div>
    </div>
  </div>

  <script>
    let currentTable = '';
    let currentPage  = 1;
    let pageSize     = 50;
    let hasNextPage  = false;
    let filterColumn   = '';
    let filterOperator = 'eq';
    let filterValue    = '';

    document.addEventListener('DOMContentLoaded', function() {
      fetchTableList();
    });

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

    function fetchTableList() {
      fetch('db_viewer_ajax.php?action=list_tables')
        .then(res => res.json())
        .then(data => {
          if (!Array.isArray(data)) {
            document.getElementById('table-list-panel').textContent = 'Error retrieving table list.';
            return;
          }
          renderTableList(data);
        })
        .catch(err => {
          console.error(err);
          document.getElementById('table-list-panel').textContent = 'Error retrieving table list.';
        });
    }

    function renderTableList(tables) {
      const container = document.getElementById('table-list-panel');
      container.innerHTML = '';
      tables.forEach(tbl => {
        const div = document.createElement('div');
        div.className = 'table-item';
        div.textContent = tbl.table_name + ' (' + tbl.row_count + ')';
        div.onclick = () => {
          currentTable = tbl.table_name;
          currentPage  = 1;
          filterColumn = '';
          filterOperator = 'eq';
          filterValue = '';
          loadTableData();
        };
        container.appendChild(div);
      });
    }

    function loadTableData() {
      if (!currentTable) return;
      document.getElementById('data-status').textContent =
        `Loading data for "${currentTable}", page ${currentPage}...`;
      document.getElementById('table-data').innerHTML = '';

      const params = new URLSearchParams({
        action: 'table_data_indef',
        table: currentTable,
        page: currentPage,
        page_size: pageSize,
        filter_col: filterColumn,
        filter_op: filterOperator,
        filter_val: filterValue
      });

      fetch('db_viewer_ajax.php?' + params.toString())
        .then(res => res.json())
        .then(resp => {
          if (resp.error) {
            document.getElementById('data-status').textContent = 'Error: ' + resp.message;
            return;
          }
          hasNextPage = resp.has_next;
          renderTableData(resp.rows);
          renderPaginationControls(resp.rows.length);
          setupFilterDropdown(resp.column_names);
        })
        .catch(err => {
          document.getElementById('data-status').textContent = 'Error loading data: ' + err;
        });
    }

    function renderTableData(rows) {
      const tableDataEl = document.getElementById('table-data');
      tableDataEl.innerHTML = '';
      if (!rows || rows.length === 0) {
        document.getElementById('data-status').textContent =
          `No data found for table "${currentTable}" (page ${currentPage}).`;
        return;
      }
      document.getElementById('data-status').textContent =
        `Showing table "${currentTable}" (page ${currentPage}).`;

      const colNames = Object.keys(rows[0]);
      if (!filterColumn && colNames.length > 0) {
        filterColumn = colNames[0];
      }

      const tableEl = document.createElement('table');
      const thead = document.createElement('thead');
      const trHead = document.createElement('tr');
      colNames.forEach(col => {
        const th = document.createElement('th');
        th.textContent = col;
        trHead.appendChild(th);
      });
      thead.appendChild(trHead);
      tableEl.appendChild(thead);

      const tbody = document.createElement('tbody');
      rows.forEach(r => {
        const tr = document.createElement('tr');
        colNames.forEach(col => {
          const td = document.createElement('td');
          td.textContent = (r[col] === null ? 'NULL' : r[col]);
          tr.appendChild(td);
        });
        tbody.appendChild(tr);
      });
      tableEl.appendChild(tbody);

      tableDataEl.appendChild(tableEl);
    }

    function renderPaginationControls(numRows) {
      const pagDiv = document.getElementById('pagination-controls');
      pagDiv.innerHTML = '';

      if (currentPage > 1) {
        const prevBtn = document.createElement('button');
        prevBtn.textContent = '<< Prev';
        prevBtn.onclick = () => {
          currentPage--;
          loadTableData();
        };
        pagDiv.appendChild(prevBtn);
      }

      const labelSpan = document.createElement('span');
      labelSpan.style.margin = '0 10px';
      labelSpan.textContent = `Page ${currentPage}, rows: ${numRows}`;
      pagDiv.appendChild(labelSpan);

      if (hasNextPage) {
        const nextBtn = document.createElement('button');
        nextBtn.textContent = 'Next >>';
        nextBtn.onclick = () => {
          currentPage++;
          loadTableData();
        };
        pagDiv.appendChild(nextBtn);
      }
    }

    function setupFilterDropdown(colNames) {
      if (!Array.isArray(colNames) || colNames.length === 0) return;
      const colSelect = document.getElementById('filter-column');
      colSelect.innerHTML = '';
      colNames.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c;
        opt.textContent = c;
        colSelect.appendChild(opt);
      });
      colSelect.value = filterColumn || colNames[0];
      document.getElementById('filter-operator').value = filterOperator;
      document.getElementById('filter-value').value = filterValue;
    }

    function applyFilter() {
      const colSelect = document.getElementById('filter-column');
      const opSelect  = document.getElementById('filter-operator');
      const valInput  = document.getElementById('filter-value');
      filterColumn   = colSelect.value || '';
      filterOperator = opSelect.value;
      filterValue    = valInput.value.trim();
      currentPage = 1;
      loadTableData();
    }

    function changePageSize() {
      const selector = document.getElementById('page-size-selector');
      pageSize = parseInt(selector.value, 10);
      currentPage = 1;
      if (currentTable) {
        loadTableData();
      }
    }
  </script>
</body>
</html>
