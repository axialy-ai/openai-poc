<?php
// /process_new_focus_area.php
//
// Creates a brand-new focus area in the given package, plus an initial
// focus-area version (version_number=0), and inserts the user-provided
// records into analysis_package_focus_area_records.
//
// The front-end payload looks like:
// {
//   "package_id": 2,
//   "package_name": "Demo Package",
//   "focus_area_name": "My New Focus Area",
//   "focus_area_properties": ["Field1","Field2",...],
//   "focus_area_records": [
//     { "Field1": "val1", "Field2": "val2" },
//     { "Field1": "val3", "Field2": "val4" }
//   ]
// }
require_once __DIR__ . '/includes/db_connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST requests are allowed']);
    exit;
}

// Read raw JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Extract the required fields
$pkgId      = isset($data['package_id']) ? (int)$data['package_id'] : 0;
$faName     = isset($data['focus_area_name']) ? trim($data['focus_area_name']) : '';
$faPropsRaw = (!empty($data['focus_area_properties']) && is_array($data['focus_area_properties']))
               ? $data['focus_area_properties']
               : [];
$faRecords  = (!empty($data['focus_area_records']) && is_array($data['focus_area_records']))
               ? $data['focus_area_records']
               : [];

// Basic validations
if ($pkgId <= 0 || $faName === '' || empty($faRecords)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields or no records provided']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1) Confirm the package exists and is not deleted
    $sqlPkg = "
        SELECT COUNT(*)
        FROM analysis_package_headers
        WHERE id = :pid
          AND is_deleted = 0
    ";
    $stmtPkg = $pdo->prepare($sqlPkg);
    $stmtPkg->execute([':pid' => $pkgId]);
    $pkgCount = (int)$stmtPkg->fetchColumn();
    if ($pkgCount < 1) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Package not found or deleted']);
        exit;
    }

    // 2) Create the new focus area row
    //    We'll store the property-name array as JSON in "focus_area_abstract"
    $faPropsJson = json_encode($faPropsRaw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $sqlFa = "
        INSERT INTO analysis_package_focus_areas
        (analysis_package_headers_id, focus_area_name, focus_area_abstract, is_deleted, created_at)
        VALUES
        (:pkg, :fan, :fabstract, 0, NOW())
    ";
    $stmtFa = $pdo->prepare($sqlFa);
    $stmtFa->execute([
        ':pkg'       => $pkgId,
        ':fan'       => $faName,
        ':fabstract' => $faPropsJson
    ]);
    $newFaId = (int)$pdo->lastInsertId();

    // 3) Create the first version with version_number=0
    //    NOTE the fix: We now also set analysis_package_headers_id = $pkgId
    $summary = "Initial version from collectedData";
    $sqlFav = "
        INSERT INTO analysis_package_focus_area_versions
        (analysis_package_headers_id,
         analysis_package_focus_areas_id,
         focus_area_version_number,
         focus_area_revision_summary,
         created_at
        )
        VALUES
        (:pkgId, :faid, 0, :rev, NOW())
    ";
    $stmtFav = $pdo->prepare($sqlFav);
    $stmtFav->execute([
        ':pkgId' => $pkgId,   // <-- ensures analysis_package_headers_id is not NULL
        ':faid'  => $newFaId,
        ':rev'   => $summary
    ]);
    $favId = (int)$pdo->lastInsertId();

    // 4) Update the new focus area so that its current version points to $favId
    $sqlUpdFa = "
        UPDATE analysis_package_focus_areas
        SET current_analysis_package_focus_area_versions_id = :fav
        WHERE id = :faid
    ";
    $stmtUpdFa = $pdo->prepare($sqlUpdFa);
    $stmtUpdFa->execute([
        ':fav'  => $favId,
        ':faid' => $newFaId
    ]);

    // 5) Insert the user-provided records
    //    We store each array/object of the payload in "properties" as JSON
    $sqlRec = "
        INSERT INTO analysis_package_focus_area_records
        (
          analysis_package_headers_id,
          analysis_package_focus_areas_id,
          analysis_package_focus_area_versions_id,
          input_text_summaries_id,
          grid_index,
          display_order,
          properties,
          is_deleted,
          created_at
        )
        VALUES
        (
          :pkgId,
          :faId,
          :favId,
          NULL,
          :gdx,
          :dispOrd,
          :props,
          0,
          NOW()
        )
    ";
    $stmtRec = $pdo->prepare($sqlRec);

    $gridIndex = 0;
    foreach ($faRecords as $recordObj) {
        $propsJson = json_encode($recordObj, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $displayOrd = $gridIndex + 1;
        $stmtRec->execute([
            ':pkgId'   => $pkgId,
            ':faId'    => $newFaId,
            ':favId'   => $favId,
            ':gdx'     => $gridIndex,
            ':dispOrd' => $displayOrd,
            ':props'   => $propsJson
        ]);
        $gridIndex++;
    }

    $pdo->commit();

    echo json_encode([
        'status'        => 'success',
        'message'       => 'New focus area created successfully.',
        'focus_area_id' => $newFaId
    ]);

} catch (Exception $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $ex->getMessage()]);
}
