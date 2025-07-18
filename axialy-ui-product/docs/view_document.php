<?php
// /docs/view_document.php

require_once __DIR__ . '/../includes/db_connection.php';

// 1) Grab the doc key from the query string, e.g. ?key=user_guide
$docKey = $_GET['key'] ?? 'user_guide';

// 2) Fetch the corresponding document record (doc-level)
$stmt = $pdo->prepare("SELECT * FROM documents WHERE doc_key = ? LIMIT 1");
$stmt->execute([$docKey]);
$docRow = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$docRow) {
    die("Document not found: $docKey");
}

// -- If the user is requesting doc-level download: ?docDownload=pdf or ?docDownload=docx
if (isset($_GET['docDownload'])) {
    $dlType = $_GET['docDownload'];
    if ($dlType === 'pdf') {
        // doc-level PDF
        if (!empty($docRow['file_pdf_data'])) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="AxiaBA-User-Document.pdf"');
            echo $docRow['file_pdf_data'];
            exit;
        } else {
            die("No doc-level PDF available for '$docKey'.");
        }
    } elseif ($dlType === 'docx') {
        // doc-level DOCX
        if (!empty($docRow['file_docx_data'])) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="AxiaBA-User-Document.docx"');
            echo $docRow['file_docx_data'];
            exit;
        } else {
            die("No doc-level DOCX available for '$docKey'.");
        }
    }
}

// 3) Check for an active version (for version-level logic)
if (!$docRow['active_version_id']) {
    die("No active version for '$docKey'.");
}

// 4) Fetch that active version row
$stmt2 = $pdo->prepare("SELECT * FROM document_versions WHERE id = ?");
$stmt2->execute([$docRow['active_version_id']]);
$verRow = $stmt2->fetch(PDO::FETCH_ASSOC);
if (!$verRow) {
    die("Active version record not found for $docKey.");
}

// 5) Handle version-level file downloads: ?download=pdf|docx
if (isset($_GET['download'])) {
    $downloadType = $_GET['download'];
    if ($downloadType === 'pdf') {
        // Version-level PDF
        if (!empty($verRow['file_pdf_data'])) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="AxiaBA-User-Guide.pdf"');
            echo $verRow['file_pdf_data'];
            exit;
        } else {
            // Removed the "No PDF available" action
            exit;
        }
    } elseif ($downloadType === 'docx') {
        // Version-level DOCX
        if (!empty($verRow['file_docx_data'])) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="AxiaBA-User-Guide.docx"');
            echo $verRow['file_docx_data'];
            exit;
        } else {
            // Removed the "No DOCX available" action
            exit;
        }
    }
}

// 6) Decide how to display the file content in the browser
$fileFormat  = $verRow['file_content_format'] ?? 'md';
$fileContent = $verRow['file_content']        ?? '';

/**
 * A simple self-contained Markdown parser.
 * Removes the minimal version. This is the only definition you need.
 */
function convertMarkdownToHtml($md) {
    // 1) Split input into lines
    $lines = preg_split("/\r\n|\n|\r/", $md);

    $html   = '';
    $inList = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // -- Detect if this line starts a list item ("- "):
        if (preg_match('/^\-\s+/', $trimmed)) {
            // Start <ul> if not already inside one
            if (!$inList) {
                $html .= "<ul>\n";
                $inList = true;
            }
            // Remove '- ' from the line, apply inline formats
            $itemText = preg_replace('/^\-\s+/', '', $trimmed);
            $itemText = applyInlineMdStyles($itemText);
            $html .= "<li>{$itemText}</li>\n";
            continue;
        } else {
            // If we WERE in a list, close it
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
        }

        // -- Check for headings in order of ####, ###, ##, #
        if (preg_match('/^#{4}\s+(.*)/', $trimmed, $matches)) {
            $headingText = applyInlineMdStyles($matches[1]);
            $html .= "<h4>{$headingText}</h4>\n";
            continue;
        }
        if (preg_match('/^#{3}\s+(.*)/', $trimmed, $matches)) {
            $headingText = applyInlineMdStyles($matches[1]);
            $html .= "<h3>{$headingText}</h3>\n";
            continue;
        }
        if (preg_match('/^#{2}\s+(.*)/', $trimmed, $matches)) {
            $headingText = applyInlineMdStyles($matches[1]);
            $html .= "<h2>{$headingText}</h2>\n";
            continue;
        }
        if (preg_match('/^#\s+(.*)/', $trimmed, $matches)) {
            $headingText = applyInlineMdStyles($matches[1]);
            $html .= "<h1>{$headingText}</h1>\n";
            continue;
        }

        // -- Otherwise, treat as a paragraph
        $html .= '<p>' . applyInlineMdStyles($trimmed) . "</p>\n";
    }

    // If the last lines were a list, close the list
    if ($inList) {
        $html .= "</ul>\n";
    }

    return $html;
}

/**
 * applyInlineMdStyles
 * A naive approach to handle ***bold+italic***, **bold**, and *italic*.
 */
function applyInlineMdStyles($text) {
    // 1) Convert special HTML chars (except for our placeholders)
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // 2) Bold+italic: ***text***
    $text = preg_replace('/\*\*\*(.*?)\*\*\*/', '<strong><em>$1</em></strong>', $text);

    // 3) Bold: **text**
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);

    // 4) Italic: *text*
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);

    return $text;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Viewing Document: <?php echo htmlspecialchars($docKey); ?></title>
    <style>
      body { margin: 20px; font-family: sans-serif; }
      .top-right { float: right; margin-left: 10px; }
      .download-link { font-size: 14px; color: #0366d6; text-decoration: none; }
      .download-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<!-- Top-right corner: doc-level download links if they exist -->
<div style="float:right;">
  <?php if(!empty($docRow['file_pdf_data'])): ?>
    <a class="download-link top-right"
       href="?key=<?php echo urlencode($docKey); ?>&docDownload=pdf">
      Download PDF
    </a>
  <?php endif; ?>
  <?php if(!empty($docRow['file_docx_data'])): ?>
    <a class="download-link top-right"
       href="?key=<?php echo urlencode($docKey); ?>&docDownload=docx">
      Download DOCX
    </a>
  <?php endif; ?>
</div>

<h1><?php echo htmlspecialchars($docRow['doc_name']); ?></h1>

<div>
<?php
switch ($fileFormat) {
    case 'md':
        // Show naive Markdown conversion
        echo convertMarkdownToHtml($fileContent);
        break;

    case 'html':
        // We trust the HTML is safe to render
        echo $fileContent;
        break;

    case 'json':
    case 'xml':
        // Show raw text in <pre> tags
        echo '<pre>' . htmlspecialchars($fileContent, ENT_QUOTES, 'UTF-8') . '</pre>';
        break;

    default:
        // Fallback: plain text with line breaks
        echo nl2br(htmlspecialchars($fileContent, ENT_QUOTES, 'UTF-8'));
        break;
}
?>
</div>

<hr>
<!-- Version-level download links (existing behavior)
<h2>Active Version Downloads</h2> -->

<!-- Show the Download PDF link if file_pdf_data is present at the version level -->
<?php if (!empty($verRow['file_pdf_data'])): ?>
    <p>
        <a href="?key=<?php echo urlencode($docKey); ?>&download=pdf"
           class="download-link"
        >
            Download PDF (Version-Level)
        </a>
    </p>
<?php endif; ?>

<!-- Show the Download DOCX link if file_docx_data is present at the version level -->
<?php if (!empty($verRow['file_docx_data'])): ?>
    <p>
        <a href="?key=<?php echo urlencode($docKey); ?>&download=docx"
           class="download-link"
        >
            Download DOCX (Version-Level)
        </a>
    </p>
<?php endif; ?>

</body>
</html>
