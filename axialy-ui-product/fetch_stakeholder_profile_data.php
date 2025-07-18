<?php
// fetch_stakeholder_profile_data.php

header('Content-Type: application/json');

require_once '../includes/db_connection.php';

// Get the email from the query parameter
$stakeholderEmail = isset($_GET['email']) ? $_GET['email'] : '';

try {
    if (empty($stakeholderEmail)) {
        throw new Exception('Stakeholder email is required.');
    }

    // Fetch stakeholder profile data
    $sql = "SELECT 
                stakeholder_email,
                stakeholder_handle,
                stakeholder_first_name,
                stakeholder_last_name,
                stakeholder_company,
                stakeholder_role
            FROM stakeholder_feedback_headers
            WHERE stakeholder_email = :stakeholderEmail
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['stakeholderEmail' => $stakeholderEmail]);
    $stakeholderProfile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stakeholderProfile) {
        echo json_encode([]);
        exit;
    }

    // Fetch additional details if necessary

    echo json_encode($stakeholderProfile);

} catch (Exception $e) {
    error_log('Error fetching stakeholder profile data: ' . $e->getMessage());
    echo json_encode(['error' => 'Error fetching stakeholder profile data.']);
}
