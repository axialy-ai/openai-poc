<?php
// /user-documentation.php

// 1) Require user session + subscription checks
require_once __DIR__ . '/includes/auth.php';
requireAuth(); // If user is not logged in, this redirects to login.php or subscription page

// 2) Get DB connection
require_once __DIR__ . '/includes/db_connection.php';

// 3) Fetch "customer-facing" documents for the left panel
try {
    $stmt = $pdo->prepare("
        SELECT doc_key, doc_name
        FROM documents
        WHERE axia_customer_docs = 1
        ORDER BY doc_name ASC
    ");
    $stmt->execute();
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die('Error fetching user documentation list: ' . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AxiaBA User Documentation</title>
    <!-- Optional: Link any global styles or bootstrap as needed -->
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"> -->

    <style>
      /* Basic resets */
      * {
        box-sizing: border-box;
      }
      body, html {
        margin: 0; padding: 0;
        font-family: "Segoe UI", Tahoma, sans-serif;
        height: 100%; /* let the containers fill full page */
      }

      /* Page Header with AxiaBA Logo + Title */
      .documentation-header {
        display: flex;
        align-items: center;
        padding: 8px 16px;
        background-color: #f8f9fa;
        border-bottom: 1px solid #ddd;
      }
      .documentation-header img {
        height: 44px;
        margin-right: 12px;
      }
      .documentation-header h1 {
        font-size: 1.3rem;
        color: #333;
        margin: 0;
        font-weight: 600;
      }

      /* Main container: left doc list + right doc viewer */
      .doc-page-container {
        display: flex;
        width: 100%;
        height: calc(100vh - 64px); /* subtract header height */
      }

      .left-panel {
        width: 250px;
        background-color: #f8f9fa;
        border-right: 1px solid #ddd;
        overflow-y: auto;
      }
      .left-panel h2 {
        margin: 0;
        padding: 12px 16px;
        font-size: 1.1rem;
        color: #444;
        border-bottom: 1px solid #ccc;
        background-color: #e9ecef;
      }
      .doc-list {
        list-style: none;
        margin: 0; 
        padding: 0;
      }
      .doc-list li a {
        display: block;
        padding: 10px 16px;
        color: #007bff;
        text-decoration: none;
        border-bottom: 1px solid #eee;
        font-size: 0.95rem;
      }
      .doc-list li a:hover {
        background-color: #e2e6ea;
        text-decoration: underline;
      }

      .right-panel {
        flex: 1; /* fill remaining space */
        position: relative;
        overflow: hidden;
      }
      /* If you're using an iframe to load doc content: */
      .doc-viewer-frame {
        width: 100%;
        height: 100%;
        border: none;
      }

      /* Default message container if no doc is selected yet */
      .doc-placeholder {
        padding: 20px;
        font-size: 1rem;
        color: #555;
      }
    </style>
</head>
<body>

<!-- Top Header: product logo + "AxiaBA User Documentation" -->
<div class="documentation-header">
  <img 
    src="https://axialy.ai/assets/img/product_logo.png" 
    alt="AxiaBA Logo"
  >
  <h1>AxiaBA User Documentation</h1>
</div>

<!-- Main content area: left doc list + right doc viewer -->
<div class="doc-page-container">
  <!-- Left side: list of documents -->
  <div class="left-panel">
    <h2>User Documents</h2>
    <ul class="doc-list">
      <?php if (!empty($docs)): ?>
        <?php foreach ($docs as $doc): ?>
          <li>
            <a 
              href="docs/view_document.php?key=<?php echo urlencode($doc['doc_key']); ?>" 
              target="docViewer"
            >
              <?php echo htmlspecialchars($doc['doc_name']); ?>
            </a>
          </li>
        <?php endforeach; ?>
      <?php else: ?>
        <li style="padding:10px;">
          No customer-facing docs available.
        </li>
      <?php endif; ?>
    </ul>
  </div>

  <!-- Right side: the doc viewer panel -->
  <div class="right-panel">
    <!-- By default, if user hasn't clicked a doc yet, show a placeholder message. 
         When user clicks a doc link, it loads into the <iframe name="docViewer">.
    -->
    <iframe 
      name="docViewer" 
      class="doc-viewer-frame"
      src="/content/user-documentation.html"
    >
    </iframe>

    <!-- If you want a "default content" rather than a blank, 
         you can set the 'src' to some page, e.g. "docs/welcome.html",
         or show a fallback <div> behind the iframe. -->
    <!-- e.g.:
    <iframe 
      name="docViewer" 
      class="doc-viewer-frame"
      src="/docs/view_document.php?key=some_default_doc"
    >
    </iframe>
    -->
  </div>
</div>

</body>
</html>
