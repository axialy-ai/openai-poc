<?php
// /fetch_analysis_packages.php
require_once 'includes/db_connection.php';
require_once 'includes/focus_org_session.php';

$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Get current focus organization
    $focusOrg     = getFocusOrganization($pdo, $_SESSION['user_id']);
    $defaultOrgId = $_SESSION['default_organization_id'];
    
    // Base SQL - always filter by default organization first
    $whereClause = "WHERE aph.default_organization_id = :defaultOrgId";
    $params = [':defaultOrgId' => $defaultOrgId];
    
    // Add focus organization filter
    if ($focusOrg === 'default') {
        $whereClause .= " AND aph.custom_organization_id IS NULL";
    } else {
        $whereClause .= " AND aph.custom_organization_id = :customOrgId";
        $params[':customOrgId'] = $focusOrg;
    }

    // Add search conditions if search term exists
    if ($searchTerm !== '') {
        $whereClause .= " AND (aph.package_name LIKE :searchTerm OR aph.id LIKE :searchTerm)";
        $params[':searchTerm'] = '%' . $searchTerm . '%';
    }

    /*
     Instead of referencing a deprecated package-level version table,
     we retrieve the maximum focus-area version across all focus areas
     for each package. This yields a "focus_area_version_number" as a
     convenient metric for UI displays.
    */
    $sql = "
        SELECT
            aph.id,
            aph.package_name,
            aph.short_summary,
            COALESCE(MAX(afav.focus_area_version_number), 0) AS focus_area_version_number
        FROM analysis_package_headers aph
        LEFT JOIN analysis_package_focus_areas afa
            ON afa.analysis_package_headers_id = aph.id
        LEFT JOIN analysis_package_focus_area_versions afav
            ON afav.analysis_package_focus_areas_id = afa.id
        $whereClause
        GROUP BY aph.id
        ORDER BY aph.package_name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($packages);

} catch (PDOException $e) {
    error_log('Database query error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database query error']);
}
