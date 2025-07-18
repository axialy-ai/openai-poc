<?php
// /includes/focus_org_session.php

function getFocusOrganization($pdo, $userId) {
    try {
        // Get current focus organization from user_focus_organizations table
        $stmt = $pdo->prepare("
            SELECT focus_org_id
            FROM user_focus_organizations
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT 1
        ");
        
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return 'default' if no focus org is set, otherwise return the focus_org_id
        return $result && $result['focus_org_id'] ? $result['focus_org_id'] : 'default';
    } catch (Exception $e) {
        error_log("Error getting focus organization: " . $e->getMessage());
        return 'default'; // Return default on error
    }
}

function setFocusOrganization($pdo, $userId, $focusOrgId) {
    $pdo->beginTransaction();
    try {
        // Delete existing focus org
        $stmt = $pdo->prepare("DELETE FROM user_focus_organizations WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);

        if ($focusOrgId !== 'default') {
            // Insert new focus org
            $stmt = $pdo->prepare("
                INSERT INTO user_focus_organizations (user_id, focus_org_id, created_at)
                VALUES (:user_id, :focus_org_id, NOW())
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':focus_org_id' => $focusOrgId
            ]);
        }
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error setting focus organization: " . $e->getMessage());
        return false;
    }
}
?>