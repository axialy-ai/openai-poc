<?php

session_name('axialy_admin_session');
session_start();

require_once __DIR__ . '/includes/admin_auth.php';
requireAdminAuth();
require_once __DIR__ . '/includes/db_connection.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Axialy Admin - Documentation</title>
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
    #docs-list {
      flex: 1;
      overflow-y: auto;
      padding: 10px;
    }
    .doc-item {
      padding: 8px 4px;
      border-bottom: 1px solid #eee;
      cursor: pointer;
    }
    .doc-item:hover {
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
    #docs-detail-container {
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
      margin: 4px 0;
      padding: 6px 12px;
      cursor: pointer;
      border-radius: 4px;
      border: 1px solid #666;
      background: #eee;
    }

    .highlight { background: #d4edda; }
    .hidden { display: none; }
    label {
      display: block;
      margin-top: 8px;
    }
    input[type="text"], textarea {
      width: 100%;
      padding: 6px;
      box-sizing: border-box;
    }
    .buttons {
      margin-top: 10px;
    }
    #create-doc-form,
    #create-version-form,
    #upload-file-section,
    #edit-doc-form {
      border: 1px solid #ccc;
      padding: 10px;
      margin: 10px 0;
      background: #fff;
    }
    #create-doc-form h3,
    #create-version-form h3,
    #edit-doc-form h3 {
      margin-top: 0;
    }

    @media (max-width: 768px) {
      body {
        flex-direction: column;
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
      <a href="index.php">
        <img src="https://axiaba.com/assets/img/SOI.png" alt="Axialy Logo" style="cursor: pointer;"/>
      </a>
      <h1>Docs</h1>
    </div>
    <div id="docs-list">Loading documents...</div>
  </div>

  <!-- Right Panel -->
  <div id="right-panel">
    <div id="right-header">
      <div id="right-header-left">
        <img src="https://axiaba.com/assets/img/product_logo.png" alt="Axialy Logo"/>
        <strong>Documentation Management</strong>
      </div>
      <div></div>
    </div>

    <div id="docs-detail-container">
      <div class="buttons">
        <button type="button" onclick="showCreateDocForm()">Create New Document</button>
        <button type="button" onclick="showUploadSection()">
          Manage Doc-Level PDF/DOCX for Selected
        </button>
      </div>

      <!-- EDIT DOCUMENT FORM: doc_key, doc_name, axia_customer_docs -->
      <div id="edit-doc-form" class="hidden">
        <h3>Edit Document</h3>
        <input type="hidden" id="edit-doc-id" />

        <label>Doc Key:
          <input type="text" id="edit-doc-key"/>
        </label>

        <label>Doc Name:
          <input type="text" id="edit-doc-name"/>
        </label>

        <label>Customer Visible? (axia_customer_docs = 1 if checked)
          <input type="checkbox" id="edit-axia-customer-docs"/>
        </label>

        <button type="button" onclick="updateDocument()">Save Changes</button>
      </div>

      <!-- Versions container -->
      <div id="versions-container" class="hidden" style="margin-top:20px;">
        <h2>Versions for <span id="doc-name"></span></h2>
        <div id="versions-list">No document selected.</div>
        <div class="buttons">
          <button type="button" onclick="showCreateVersionForm()" id="btn-new-version" disabled>
            Create New Version
          </button>
        </div>
      </div>

      <!-- Create-Doc Form -->
      <div id="create-doc-form" class="hidden">
        <h3>Create Document</h3>
        <label>Doc Key: <input type="text" id="docKey"></label>
        <label>Doc Name: <input type="text" id="docName"></label>
        <button type="button" onclick="createDocument()">Save Document</button>
        <button type="button" onclick="hideCreateDocForm()">Cancel</button>
      </div>

      <!-- Create-Version Form -->
      <div id="create-version-form" class="hidden">
        <h3>New Version for <span id="form-doc-name"></span></h3>
        <label>Content Format:
          <select id="fileFormat">
            <option value="md">Markdown</option>
            <option value="html">HTML</option>
            <option value="json">JSON</option>
            <option value="xml">XML</option>
          </select>
        </label>
        <textarea id="fileContent" rows="8" cols="60" placeholder="Enter the doc content"></textarea>
        <br>
        <button type="button" onclick="createVersion()">Save Version</button>
        <button type="button" onclick="hideCreateVersionForm()">Cancel</button>
      </div>

      <!-- Doc-Level PDF/DOCX Upload Section -->
      <div id="upload-file-section" class="hidden">
        <h3>Doc-Level PDF/DOCX for: <span id="doc-level-file-docname"></span></h3>
        <p>Use these forms to upload or replace PDF/DOCX at the document level.</p>
        <form id="upload-pdf-form" enctype="multipart/form-data">
          <input type="hidden" name="action" value="uploadDocFile">
          <input type="hidden" name="file_type" value="pdf">
          <input type="hidden" name="documents_id" id="pdf-docid">
          <label>Select PDF file:
            <input type="file" name="uploaded_file" accept=".pdf" required>
          </label>
          <button type="submit">Upload PDF</button>
        </form>

        <form id="upload-docx-form" enctype="multipart/form-data" style="margin-top:10px;">
          <input type="hidden" name="action" value="uploadDocFile">
          <input type="hidden" name="file_type" value="docx">
          <input type="hidden" name="documents_id" id="docx-docid">
          <label>Select DOCX file:
            <input type="file" name="uploaded_file" accept=".docx" required>
          </label>
          <button type="submit">Upload DOCX</button>
        </form>
        <div id="download-links" style="margin-top:10px;"></div>
        <button type="button" style="margin-top:10px;" onclick="hideUploadSection()">Close</button>
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
    "use strict";
    let selectedDocId   = null;
    let selectedDocName = "";

    document.addEventListener("DOMContentLoaded", function(){
      fetchDocuments();
    });

    function toggleLeftPanel() {
      const leftPanel = document.getElementById('left-panel');
      const btn       = document.getElementById('toggle-btn');
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

    // ----- LIST DOCUMENTS -----
    function fetchDocuments() {
      fetch("doc_ajax_actions.php?action=listDocs")
        .then(r => r.json())
        .then(data => {
          const docsListDiv = document.getElementById("docs-list");
          if (!Array.isArray(data)) {
            docsListDiv.textContent = "Error loading documents.";
            return;
          }
          let html = "";
          data.forEach(doc => {
            html += `<div class="doc-item" onclick="selectDocument(${doc.id}, '${escapeHtml(doc.doc_name)}')">`;
            html += `[${escapeHtml(doc.doc_key)}] ${escapeHtml(doc.doc_name)}`;
            if (doc.active_version_id) {
              html += ` (Active Ver ID: ${doc.active_version_id})`;
            }
            html += '</div>';
          });
          docsListDiv.innerHTML = html;
        })
        .catch(err => {
          console.error("fetchDocuments error:", err);
          document.getElementById("docs-list").textContent = "Error loading docs.";
        });
    }

    // ----- SELECT A DOCUMENT -----
    function selectDocument(docId, docName) {
      selectedDocId   = docId;
      selectedDocName = docName;
      document.getElementById("doc-name").textContent = docName;
      // show versions area
      document.getElementById("versions-container").classList.remove("hidden");
      document.getElementById("btn-new-version").disabled = false;

      // load versions
      fetchVersions(docId);

      // We won't retrieve the doc again if you only want docKey/docName in the left panel.
      // But if you want to show the "Edit doc" form with axia_customer_docs, we have 2 options:
      // A) Re-list all docs array => find doc => fill form
      // B) Make a new "getDoc" call. For minimal code, let's do that:

      // We'll do a simple GET call:
      fetch(`doc_ajax_actions.php?action=getDoc&id=${docId}`)
        .then(r => r.json())
        .then(resp => {
          if (resp.error) {
            console.warn("No doc details returned, or error:", resp.message);
            // Maybe hide the edit form or do nothing
            return;
          }
          const doc = resp.doc;
          if (!doc) return;
          // Fill form
          document.getElementById("edit-doc-id").value  = doc.id;
          document.getElementById("edit-doc-key").value = doc.doc_key;
          document.getElementById("edit-doc-name").value= doc.doc_name;
          document.getElementById("edit-axia-customer-docs").checked = (doc.axia_customer_docs == 1);
          // Show form
          document.getElementById("edit-doc-form").classList.remove("hidden");
        })
        .catch(err => {
          console.error("Error retrieving doc details:", err);
        });
    }

    // ----- UPDATE THE SELECTED DOCUMENT -----
    function updateDocument() {
      const docIdEl    = document.getElementById("edit-doc-id");
      const docKeyEl   = document.getElementById("edit-doc-key");
      const docNameEl  = document.getElementById("edit-doc-name");
      const axiaCheck  = document.getElementById("edit-axia-customer-docs");

      if (!docIdEl.value) {
        alert("No document loaded in the Edit form.");
        return;
      }
      const docKey = docKeyEl.value.trim();
      const dName  = docNameEl.value.trim();
      const axiaVal= axiaCheck.checked ? 1 : 0;
      if (!docKey || !dName) {
        alert("Doc Key and Doc Name cannot be empty.");
        return;
      }
      const payload = {
        id: docIdEl.value,
        doc_key: docKey,
        doc_name: dName,
        axia_customer_docs: axiaVal
      };
      fetch("doc_ajax_actions.php?action=updateDoc", {
        method: "POST",
        headers: { "Content-Type":"application/json" },
        body: JSON.stringify(payload)
      })
      .then(r => r.json())
      .then(resp => {
        if (!resp.success) {
          alert("Error updating document: " + resp.message);
        } else {
          alert("Document updated successfully!");
          // Reload the doc list so we see updated doc_key/doc_name
          fetchDocuments();
        }
      })
      .catch(err => {
        alert("Error sending updateDoc request: " + err);
      });
    }

    // ----- LIST / CREATE VERSIONS -----
    function fetchVersions(docId) {
      fetch("doc_ajax_actions.php?action=listVersions&documents_id=" + docId)
        .then(r => r.json())
        .then(data => {
          const verList = document.getElementById("versions-list");
          if (!Array.isArray(data)) {
            verList.textContent = "Error loading versions.";
            return;
          }
          if (data.length === 0) {
            verList.textContent = "No versions yet.";
            return;
          }
          let html = "";
          data.forEach(ver => {
            const isActive = (ver.isActive == "1");
            html += `<div style="padding:5px; margin-bottom:4px; ${isActive ? 'background:#d4edda;' : ''}">`;
            html += `Version #${ver.version_number} `;
            html += `<button onclick="setActiveVersion(${ver.id})" ${isActive ? 'disabled' : ''}>Set Active</button>`;
            html += `<button onclick="generatePdf(${ver.id})" ${isActive ? '' : 'disabled'}>Generate PDF</button>`;
            html += `<button onclick="generateDocx(${ver.id})" ${isActive ? '' : 'disabled'}>Generate DOCX</button>`;
            html += `<br><small>Created: ${ver.created_at}</small>`;
            html += '</div>';
          });
          verList.innerHTML = html;
        })
        .catch(err => {
          console.error("fetchVersions error:", err);
          document.getElementById("versions-list").textContent = "Error listing versions.";
        });
    }

    function setActiveVersion(versionId) {
      if (!confirm("Set this version as active?")) return;
      fetch("doc_ajax_actions.php?action=setActiveVersion&version_id=" + versionId)
        .then(r => r.json())
        .then(resp => {
          if (resp.status === "success") {
            alert("Active version updated.");
            fetchVersions(selectedDocId);
            fetchDocuments();
          } else {
            alert("Error: " + resp.message);
          }
        })
        .catch(err => {
          alert("Error setting active version: " + err);
        });
    }

    function showCreateDocForm() {
      document.getElementById("create-doc-form").classList.remove("hidden");
    }
    function hideCreateDocForm() {
      document.getElementById("create-doc-form").classList.add("hidden");
    }
    function createDocument() {
      const key  = document.getElementById("docKey").value.trim();
      const name = document.getElementById("docName").value.trim();
      if (!key || !name) {
        alert("Please fill in both fields");
        return;
      }
      const payload = { doc_key: key, doc_name: name };
      fetch("doc_ajax_actions.php?action=createDoc", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      })
      .then(r => r.json())
      .then(resp => {
        if(resp.status === "success"){
          alert("Document created.");
          hideCreateDocForm();
          fetchDocuments();
        } else {
          alert("Error: " + resp.message);
        }
      })
      .catch(err => {
        alert("Error creating doc: " + err);
      });
    }

    function showCreateVersionForm() {
      if (!selectedDocId) {
        alert("No document selected.");
        return;
      }
      document.getElementById("form-doc-name").textContent = selectedDocName;
      document.getElementById("create-version-form").classList.remove("hidden");
    }
    function hideCreateVersionForm() {
      document.getElementById("create-version-form").classList.add("hidden");
    }
    function createVersion() {
      const format  = document.getElementById("fileFormat").value;
      const content = document.getElementById("fileContent").value;
      const payload = {
        documents_id: selectedDocId,
        file_content_format: format,
        file_content: content
      };
      fetch("doc_ajax_actions.php?action=createVersion", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      })
      .then(r => r.json())
      .then(resp => {
        if(resp.status === "success"){
          alert("Version created.");
          hideCreateVersionForm();
          fetchVersions(selectedDocId);
        } else {
          alert("Error: " + resp.message);
        }
      })
      .catch(err => {
        alert("Error: " + err);
      });
    }

    // ----- Generate PDF / DOCX -----
    function generatePdf(versionId) {
      if (!confirm("Generate PDF for this version?")) return;
      fetch("doc_ajax_actions.php?action=generatePdf&version_id=" + versionId)
        .then(r => r.json())
        .then(resp => {
          if(resp.status === "success"){
            alert("PDF generated successfully.");
            fetchVersions(selectedDocId);
          } else {
            alert("Error generating PDF: " + resp.message);
          }
        })
        .catch(err => {
          alert("Error: " + err);
        });
    }
    function generateDocx(versionId) {
      if (!confirm("Generate DOCX for this version?")) return;
      fetch("doc_ajax_actions.php?action=generateDocx&version_id=" + versionId)
        .then(r => r.json())
        .then(resp => {
          if(resp.status === "success"){
            alert("DOCX generated successfully.");
            fetchVersions(selectedDocId);
          } else {
            alert("Error generating DOCX: " + resp.message);
          }
        })
        .catch(err => {
          alert("Error: " + err);
        });
    }

    // ----- Upload / Download doc-level files -----
    function showUploadSection() {
      if (!selectedDocId) {
        alert("No document selected.");
        return;
      }
      document.getElementById("doc-level-file-docname").textContent = selectedDocName;
      document.getElementById("pdf-docid").value  = selectedDocId;
      document.getElementById("docx-docid").value = selectedDocId;
      document.getElementById("upload-file-section").classList.remove("hidden");
      loadDocLevelFiles(selectedDocId);
    }
    function hideUploadSection() {
      document.getElementById("upload-file-section").classList.add("hidden");
      document.getElementById("download-links").innerHTML = "";
    }
    function loadDocLevelFiles(docId) {
      const container = document.getElementById("download-links");
      container.innerHTML = '';
      const pdfUrl = `doc_ajax_actions.php?action=downloadDocFile&doc_id=${docId}&file_type=pdf`;
      const docxUrl= `doc_ajax_actions.php?action=downloadDocFile&doc_id=${docId}&file_type=docx`;

      checkFileExists(pdfUrl, function(exists){
        if(exists) {
          const link = document.createElement("a");
          link.href = pdfUrl;
          link.textContent = "Download Doc-Level PDF";
          link.target = "_blank";
          container.appendChild(link);
          container.appendChild(document.createElement("br"));
        }
      });
      checkFileExists(docxUrl, function(exists){
        if(exists) {
          const link = document.createElement("a");
          link.href = docxUrl;
          link.textContent = "Download Doc-Level DOCX";
          link.target = "_blank";
          container.appendChild(link);
          container.appendChild(document.createElement("br"));
        }
      });
    }
    function checkFileExists(url, callback) {
      fetch(url, { method: "HEAD" })
        .then(resp => {
          callback(resp.ok);
        })
        .catch(() => {
          callback(false);
        });
    }

    document.getElementById("upload-pdf-form").addEventListener("submit", function(e){
      e.preventDefault();
      const formData = new FormData(e.target);
      fetch("doc_ajax_actions.php", {
        method: "POST",
        body: formData
      })
      .then(r => r.json())
      .then(resp => {
        if(resp.status === "success"){
          alert("PDF uploaded successfully.");
          loadDocLevelFiles(selectedDocId);
        } else {
          alert("Error uploading PDF: " + resp.message);
        }
      })
      .catch(err => {
        alert("Error: " + err);
      });
    });
    document.getElementById("upload-docx-form").addEventListener("submit", function(e){
      e.preventDefault();
      const formData = new FormData(e.target);
      fetch("doc_ajax_actions.php", {
        method: "POST",
        body: formData
      })
      .then(r => r.json())
      .then(resp => {
        if(resp.status === "success"){
          alert("DOCX uploaded successfully.");
          loadDocLevelFiles(selectedDocId);
        } else {
          alert("Error uploading DOCX: " + resp.message);
        }
      })
      .catch(err => {
        alert("Error: " + err);
      });
    });

    function escapeHtml(text){
      if (!text) return "";
      return text.replace(/[<>&"']/g, function(m){
        switch(m){
          case '<': return "&lt;";
          case '>': return "&gt;";
          case '&': return "&amp;";
          case '"': return "&quot;";
          case "'": return "&#39;";
        }
      });
    }
  </script>
</body>
</html>
