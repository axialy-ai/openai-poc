<?php
//
// 1) Admin session
session_name('axialy_admin_session');
session_start();

// 2) Must be logged in as admin
require_once __DIR__ . '/includes/admin_auth.php';
requireAdminAuth();

// 3) Connect to the currently selected UI DB
require_once __DIR__ . '/includes/ui_db_connection.php';
// $pdoUI is the PDO connection to the UI DB

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list_tables':
        listTables($pdoUI);
        break;

    case 'table_data_indef':
        tableDataIndef($pdoUI);
        break;

    default:
        echo json_encode([
            'error'   => true,
            'message' => 'Unknown action'
        ]);
        break;
}

// ----------------------------------------------------------------------

function listTables(PDO $pdo) {
    try {
        // Show all table names
        $result = $pdo->query("SHOW TABLES");
        $allTableNames = $result->fetchAll(PDO::FETCH_COLUMN);

        $tables = [];
        // We'll do a quick row count for display, as before
        foreach ($allTableNames as $tableName) {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM `{$tableName}`");
            $rowCount = (int)$countStmt->fetchColumn();
            $tables[] = [
                'table_name' => $tableName,
                'row_count'  => $rowCount
            ];
        }

        echo json_encode($tables);

    } catch (Exception $ex) {
        echo json_encode([
            'error'   => true,
            'message' => 'DB error: ' . $ex->getMessage()
        ]);
    }
}

/**
 * tableDataIndef + optional filter operator:
 * We do indefinite paging (no SELECT COUNT(*)),
 * returning up to pageSize+1 rows to see if there's a next page.
 *
 * NEW LOGIC: Exclude BLOB columns so our JSON doesn’t break.
 */
function tableDataIndef(PDO $pdo) {
    $tableName = $_GET['table'] ?? '';
    // simple sanitize
    $tableName = preg_replace('/[^A-Za-z0-9_\-]/', '', $tableName);
    if (!$tableName) {
        echo json_encode([
            'error'   => true,
            'message' => 'No table name provided'
        ]);
        return;
    }

    // page & page_size
    $page = (int)($_GET['page'] ?? 1);
    if ($page < 1) $page = 1;

    $pageSize = (int)($_GET['page_size'] ?? 50);
    if ($pageSize < 1) $pageSize = 50;

    $offset = ($page - 1) * $pageSize;

    // Filter column, operator, value
    $filterCol = $_GET['filter_col'] ?? '';
    $filterOp  = $_GET['filter_op']  ?? 'eq'; // default
    $filterVal = $_GET['filter_val'] ?? '';

    try {
        // Step 1: get actual columns + data types from information_schema
        $colsStmt = $pdo->prepare("
            SELECT COLUMN_NAME, DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = :tbl
            ORDER BY ORDINAL_POSITION
        ");
        $colsStmt->execute([':tbl' => $tableName]);
        $allColumnsRaw = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$allColumnsRaw) {
            echo json_encode([
                'error' => true,
                'message' => 'No columns found for table ' . $tableName
            ]);
            return;
        }

        // Separate the columns into:
        //  - 'selectable' columns (non-BLOB) => we'll select them directly
        //  - 'blob' columns => we skip them or create placeholder
        $selectableCols = [];
        $blobCols       = [];
        $blobTypes = ['blob','longblob','mediumblob','tinyblob','binary','varbinary'];
        foreach ($allColumnsRaw as $colInfo) {
            $colName = $colInfo['COLUMN_NAME'];
            $colType = strtolower($colInfo['DATA_TYPE']);

            if (in_array($colType, $blobTypes)) {
                $blobCols[] = $colName;
            } else {
                $selectableCols[] = $colName;
            }
        }

        if (empty($selectableCols)) {
            // If literally all columns are BLOB (unlikely), we can’t do much
            echo json_encode([
                'error' => true,
                'message' => 'All columns in this table are binary/BLOB. Cannot display.'
            ]);
            return;
        }

        // Step 2: Whitelist operators
        $allowedOps = [
            'eq'   => '=',
            'neq'  => '<>',
            'gt'   => '>',
            'lt'   => '<',
            'gte'  => '>=',
            'lte'  => '<=',
            'like' => 'LIKE',
            'nlike'=> 'NOT LIKE'
        ];
        $sqlOp = $allowedOps[$filterOp] ?? '='; // fallback to '=' if not recognized

        // Step 3: Build WHERE clause if valid column & filterVal not empty
        // Also ensure filterCol is in $selectableCols or $blobCols
        $allColNames = array_merge($selectableCols, $blobCols);
        $where  = '';
        $params = [];
        if ($filterCol && in_array($filterCol, $allColNames) && $filterVal !== '') {
            // If operator is LIKE or NOT LIKE, we wrap value in %...%
            if ($sqlOp === 'LIKE' || $sqlOp === 'NOT LIKE') {
                $where = "WHERE `{$filterCol}` $sqlOp :flt";
                $params[':flt'] = '%' . $filterVal . '%';
            } else {
                $where = "WHERE `{$filterCol}` $sqlOp :flt";
                $params[':flt'] = $filterVal;
            }
        }

        // Step 4: Build the SELECT statement with only the non-BLOB columns
        // Example: SELECT `col1`,`col2`,`col3` FROM `tablename` ...
        // If you want to show some placeholder for BLOB columns, you can add
        //   LENGTH(`file_pdf_data`) as `file_pdf_data`
        // or just skip them. In this example, let's skip them entirely.
        $selectedColList = '`' . implode('`,`', $selectableCols) . '`';
        $sql = "SELECT {$selectedColList} FROM `{$tableName}` $where LIMIT :offs, :lim";

        $stmt = $pdo->prepare($sql);

        // Bind offset & limit
        $stmt->bindValue(':offs', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':lim',  $pageSize + 1, PDO::PARAM_INT);

        // Bind filter param if needed
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }

        $stmt->execute();
        $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Step 5: detect "has_next" if we got > pageSize rows
        $hasNext = false;
        if (count($allRows) > $pageSize) {
            $hasNext = true;
            array_pop($allRows); // remove the extra row
        }

        // Our column_names = $selectableCols (the actual columns we returned).
        // If you want to list BLOB columns too, you can add them at the end with placeholders, but
        // that may break the existing UI logic that expects the data to match the column_names.
        $columnNames = $selectableCols;

        // Return results
        echo json_encode([
            'error'        => false,
            'has_next'     => $hasNext,
            'rows'         => $allRows,
            'column_names' => $columnNames
        ]);

    } catch (Exception $ex) {
        echo json_encode([
            'error'   => true,
            'message' => 'DB error: ' . $ex->getMessage()
        ]);
    }
}
