<?php

session_name('axialy_admin_session');
session_start();

require_once __DIR__ . '/includes/admin_auth.php';
requireAdminAuth();
require_once __DIR__ . '/includes/ui_db_connection.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'listDocs':
        listDocuments($pdoUI);
        break;

    case 'listVersions':
        listVersions($pdoUI);
        break;

    case 'createDoc':
        createDocumentAction($pdoUI);
        break;

    case 'createVersion':
        createVersionAction($pdoUI);
        break;

    case 'setActiveVersion':
        setActiveVersionAction($pdoUI);
        break;

    case 'generatePdf':
        generatePdfAction($pdoUI);
        break;

    case 'generateDocx':
        generateDocxAction($pdoUI);
        break;

    case 'uploadDocFile':
        uploadDocFile($pdoUI);
        break;

    case 'downloadDocFile':
        downloadDocFile($pdoUI);
        break;

    // NEW minimal addition:
    case 'getDoc':
        getDoc($pdoUI);
        break;
    case 'updateDoc':
        updateDoc($pdoUI);
        break;

    default:
        echo json_encode(['status'=>'error','message'=>'Unknown action']);
        break;
}

// --------------------------------------------------
// -------------- listDocuments() (Modified) --------------
// Exclude BLOB columns, but also retrieve axia_customer_docs
function listDocuments(PDO $pdoUI) {
    try {
        $sql = "
          SELECT
            id,
            doc_key,
            doc_name,
            axia_customer_docs,
            active_version_id,
            created_at,
            updated_at
          FROM documents
          ORDER BY id DESC
        ";
        $stmt = $pdoUI->query($sql);
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($docs);
    } catch (Exception $e) {
        echo json_encode(['error' => 'DB error', 'message' => $e->getMessage()]);
    }
}

// --------------------------------------------------
function listVersions(PDO $pdoUI) {
    $documents_id = (int)($_GET['documents_id'] ?? 0);
    try {
        $sql = "
          SELECT
            dv.id,
            dv.documents_id,
            dv.version_number,
            dv.file_content_format,
            dv.created_at,
            dv.updated_at,
            (dv.id = d.active_version_id) AS isActive
          FROM document_versions dv
          JOIN documents d ON dv.documents_id = d.id
          WHERE dv.documents_id = :doc
          ORDER BY dv.version_number DESC
        ";
        $stmt = $pdoUI->prepare($sql);
        $stmt->execute([':doc' => $documents_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($rows);
    } catch (Exception $e) {
        echo json_encode(['error'=>'DB error','message'=>$e->getMessage()]);
    }
}

// --------------------------------------------------
function createDocumentAction(PDO $pdoUI) {
    $postData = json_decode(file_get_contents('php://input'), true);
    $docKey   = trim($postData['doc_key']  ?? '');
    $docName  = trim($postData['doc_name'] ?? '');

    if (!$docKey || !$docName) {
        echo json_encode(['status'=>'error','message'=>'Missing doc_key or doc_name']);
        return;
    }
    try {
        $stmt = $pdoUI->prepare("
          INSERT INTO documents (doc_key, doc_name, created_at)
          VALUES (:k, :n, NOW())
        ");
        $stmt->execute([':k' => $docKey, ':n' => $docName]);

        echo json_encode(['status'=>'success']);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
}

// --------------------------------------------------
function createVersionAction(PDO $pdoUI) {
    $postData = json_decode(file_get_contents('php://input'), true);
    $documents_id = (int)($postData['documents_id'] ?? 0);
    $fileFormat   = $postData['file_content_format'] ?? 'md';
    $fileContent  = $postData['file_content'] ?? '';

    if (!$documents_id) {
        echo json_encode(['status'=>'error','message'=>'No documents_id provided']);
        return;
    }
    try {
        $stmt = $pdoUI->prepare("SELECT MAX(version_number) FROM document_versions WHERE documents_id = :d");
        $stmt->execute([':d' => $documents_id]);
        $maxVer = (int)$stmt->fetchColumn();
        $newVer = $maxVer + 1;

        $stmtIns = $pdoUI->prepare("
          INSERT INTO document_versions (
            documents_id,
            version_number,
            file_content,
            file_content_format,
            created_at
          ) VALUES (
            :did, :ver, :cont, :fmt, NOW()
          )
        ");
        $stmtIns->execute([
          ':did'  => $documents_id,
          ':ver'  => $newVer,
          ':cont' => $fileContent,
          ':fmt'  => $fileFormat
        ]);

        echo json_encode(['status'=>'success']);
    } catch(Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
}

// --------------------------------------------------
function setActiveVersionAction(PDO $pdoUI) {
    $version_id = (int)($_GET['version_id'] ?? 0);
    if (!$version_id) {
        echo json_encode(['status'=>'error','message'=>'No version_id']);
        return;
    }
    try {
        // 1) doc id from version
        $stmt = $pdoUI->prepare("SELECT documents_id FROM document_versions WHERE id=:vid");
        $stmt->execute([':vid' => $version_id]);
        $docId = $stmt->fetchColumn();
        if (!$docId) {
            echo json_encode(['status'=>'error','message'=>'Version not found.']);
            return;
        }
        // 2) Clear old active version's PDF/DOCX
        $stmt2 = $pdoUI->prepare("SELECT active_version_id FROM documents WHERE id=:doc LIMIT 1");
        $stmt2->execute([':doc' => $docId]);
        $oldActiveVerId = $stmt2->fetchColumn();

        if ($oldActiveVerId && (int)$oldActiveVerId !== $version_id) {
            $stmtClr = $pdoUI->prepare("
              UPDATE document_versions
                 SET file_pdf_data=NULL,
                     file_docx_data=NULL
               WHERE id=:aid
            ");
            $stmtClr->execute([':aid' => $oldActiveVerId]);
        }
        // 3) set new active version
        $stmt3 = $pdoUI->prepare("
          UPDATE documents
             SET active_version_id=:vid,
                 updated_at=NOW()
           WHERE id=:doc
        ");
        $stmt3->execute([
          ':vid' => $version_id,
          ':doc' => $docId
        ]);

        echo json_encode(['status'=>'success']);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
}

// --------------------------------------------------
function generatePdfAction(PDO $pdoUI) {
    $version_id = (int)($_GET['version_id'] ?? 0);

    try {
        $stmt = $pdoUI->prepare("
          SELECT dv.*,
                 (dv.id = d.active_version_id) AS isActive
            FROM document_versions dv
            JOIN documents d ON dv.documents_id = d.id
           WHERE dv.id=:vid
        ");
        $stmt->execute([':vid' => $version_id]);
        $verData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$verData) {
            echo json_encode(['status'=>'error','message'=>'Version not found.']);
            return;
        }
        if (!(int)$verData['isActive']) {
            echo json_encode(['status'=>'error','message'=>'Cannot generate PDF for a non-active version.']);
            return;
        }
        // placeholder PDF data
        $dummyPdfData = 'PDF-BINARY-DATA('.substr(md5(time()),0,8).')';

        $stmtU = $pdoUI->prepare("
          UPDATE document_versions
             SET file_pdf_data=:pdfdata,
                 updated_at=NOW()
           WHERE id=:vid
        ");
        $stmtU->execute([
          ':pdfdata' => $dummyPdfData,
          ':vid'     => $version_id
        ]);

        echo json_encode(['status'=>'success']);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
}

// --------------------------------------------------
function generateDocxAction(PDO $pdoUI) {
    $version_id = (int)($_GET['version_id'] ?? 0);

    try {
        $stmt = $pdoUI->prepare("
          SELECT dv.*,
                 (dv.id = d.active_version_id) AS isActive
            FROM document_versions dv
            JOIN documents d ON dv.documents_id = d.id
           WHERE dv.id=:vid
        ");
        $stmt->execute([':vid' => $version_id]);
        $verData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$verData) {
            echo json_encode(['status'=>'error','message'=>'Version not found.']);
            return;
        }
        if (!(int)$verData['isActive']) {
            echo json_encode(['status'=>'error','message'=>'Cannot generate DOCX for a non-active version.']);
            return;
        }
        // placeholder docx data
        $dummyDocxData = 'DOCX-BINARY-DATA('.substr(md5(time()),0,8).')';

        $stmtU = $pdoUI->prepare("
          UPDATE document_versions
             SET file_docx_data=:docxdata,
                 updated_at=NOW()
           WHERE id=:vid
        ");
        $stmtU->execute([
          ':docxdata' => $dummyDocxData,
          ':vid'      => $version_id
        ]);

        echo json_encode(['status'=>'success']);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
}

// --------------------------------------------------
// Upload doc-level PDF or DOCX
function uploadDocFile(PDO $pdoUI) {
    if (!isset($_FILES['uploaded_file']) || $_FILES['uploaded_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode([
            'status'=>'error',
            'message'=>'No file uploaded or upload error.'
        ]);
        return;
    }

    $docId    = (int)($_POST['documents_id'] ?? 0);
    $fileType = $_POST['file_type'] ?? '';

    if (!$docId || !in_array($fileType, ['pdf','docx'])) {
        echo json_encode(['status'=>'error','message'=>'Invalid documents_id or file_type']);
        return;
    }

    $tmpPath  = $_FILES['uploaded_file']['tmp_name'];
    $fileData = @file_get_contents($tmpPath);
    if (!$fileData) {
        echo json_encode(['status'=>'error','message'=>'Cannot read uploaded file data.']);
        return;
    }

    // Decide which column to store in
    $columnName = ($fileType === 'pdf') ? 'file_pdf_data' : 'file_docx_data';

    try {
        // verify doc record
        $stmtChk = $pdoUI->prepare("SELECT COUNT(*) FROM documents WHERE id=?");
        $stmtChk->execute([$docId]);
        $exists = $stmtChk->fetchColumn();
        if (!$exists) {
            echo json_encode(['status'=>'error','message'=>'Document ID not found']);
            return;
        }

        // update doc-level file data
        $sql = "
          UPDATE documents
             SET $columnName = :blobdata,
                 updated_at = NOW()
           WHERE id = :did
        ";
        $stmtUp = $pdoUI->prepare($sql);
        $stmtUp->execute([
            ':blobdata' => $fileData,
            ':did'      => $docId
        ]);

        echo json_encode(['status'=>'success','message'=>'File uploaded successfully.']);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
}

// --------------------------------------------------
// Download doc-level PDF or DOCX
function downloadDocFile(PDO $pdoUI) {
    header_remove('Content-Type');

    $docId    = (int)($_GET['doc_id'] ?? 0);
    $fileType = $_GET['file_type'] ?? '';

    if (!$docId || !in_array($fileType, ['pdf','docx'])) {
        header('HTTP/1.1 400 Bad Request');
        echo "Invalid doc_id or file_type.";
        exit;
    }

    $columnName = ($fileType === 'pdf') ? 'file_pdf_data' : 'file_docx_data';

    try {
        $stmt = $pdoUI->prepare("SELECT $columnName FROM documents WHERE id=:did");
        $stmt->execute([':did' => $docId]);
        $fileData = $stmt->fetchColumn();

        if (!$fileData) {
            header('HTTP/1.1 404 Not Found');
            echo "No $fileType file available at the document level.";
            exit;
        }
        if ($fileType === 'pdf') {
            header('Content-Type: application/pdf');
            $filename = "document_{$docId}.pdf";
        } else {
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            $filename = "document_{$docId}.docx";
        }
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: ' . strlen($fileData));
        echo $fileData;
        exit;
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo "Error retrieving file: " . $e->getMessage();
        exit;
    }
}

// --------------------------------------------------
// (NEW) getDoc action, if you want to fetch doc_key, doc_name, axia_customer_docs individually
function getDoc(PDO $pdo) {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        echo json_encode(['error'=>true,'message'=>'No id provided.']);
        return;
    }
    try {
        $stmt = $pdo->prepare("
          SELECT id, doc_key, doc_name, axia_customer_docs
          FROM documents
          WHERE id=:id
          LIMIT 1
        ");
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['error'=>true,'message'=>'Document not found']);
            return;
        }
        echo json_encode(['error'=>false,'doc'=>$row]);
    } catch (Exception $ex) {
        echo json_encode(['error'=>true,'message'=>$ex->getMessage()]);
    }
}

// --------------------------------------------------
// (NEW) updateDoc so Admin can edit doc_key/doc_name/axia_customer_docs
function updateDoc(PDO $pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['id'])) {
        echo json_encode(['success'=>false,'message'=>'No ID provided']);
        return;
    }
    $id   = (int)$data['id'];
    $key  = trim($data['doc_key']  ?? '');
    $name = trim($data['doc_name'] ?? '');
    $axia = (int)($data['axia_customer_docs'] ?? 0);

    if (!$key || !$name) {
        echo json_encode(['success'=>false,'message'=>'doc_key and doc_name cannot be empty']);
        return;
    }
    try {
        $stmt = $pdo->prepare("
          UPDATE documents
             SET doc_key          = :k,
                 doc_name         = :n,
                 axia_customer_docs = :a,
                 updated_at       = NOW()
           WHERE id=:id
           LIMIT 1
        ");
        $stmt->execute([
            ':k' => $key,
            ':n' => $name,
            ':a' => $axia,
            ':id'=> $id
        ]);
        echo json_encode(['success'=>true]);
    } catch (Exception $ex) {
        echo json_encode(['success'=>false,'message'=>$ex->getMessage()]);
    }
}
