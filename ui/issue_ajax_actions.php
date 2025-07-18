<?php
// /issue_ajax_actions.php

require_once __DIR__ . '/includes/auth.php';
requireAuth();
header('Content-Type: application/json');

// DB connection
require_once __DIR__ . '/includes/db_connection.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'listTickets':
        // Just call listTickets, which is defined OUTSIDE the switch:
        listTickets($pdo, /*includeClosedParam=*/true);
        break;
    
    case 'getTicketDetails':
        getTicketDetails($pdo);
        break;
    
    case 'createTicket':
        createTicket($pdo);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}

/**
 * Return all issues for the current user.
 * Optionally filter out closed tickets if `$_GET['includeClosed']` is not set.
 */
function listTickets(PDO $pdo, $dummyParam = false) {
    $userId = $_SESSION['user_id'] ?? 0;
    if (!$userId) {
        echo json_encode([
            'success' => false,
            'message' => 'Not authenticated'
        ]);
        return;
    }

    // Check if includeClosed=1 in the query string
    $includeClosed = isset($_GET['includeClosed']) && $_GET['includeClosed'] == '1';

    try {
        if ($includeClosed) {
            $sql = "SELECT id, issue_title, status, created_at
                    FROM issues
                    WHERE user_id = :uid
                    ORDER BY created_at DESC";
        } else {
            $sql = "SELECT id, issue_title, status, created_at
                    FROM issues
                    WHERE user_id = :uid
                      AND status != 'Closed'
                    ORDER BY created_at DESC";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'tickets' => $tickets
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Return detailed info for one ticket (by ID) if it belongs to the current user.
 */
function getTicketDetails(PDO $pdo) {
    $userId = $_SESSION['user_id'] ?? 0;
    $ticketId = $_GET['ticketId'] ?? $_POST['ticketId'] ?? null;

    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        return;
    }
    if (!$ticketId) {
        echo json_encode(['success' => false, 'message' => 'Missing ticketId']);
        return;
    }

    try {
        $sql = "SELECT id, issue_title, issue_description, status, created_at, updated_at
                FROM issues
                WHERE user_id = :uid
                AND id = :tid
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uid' => $userId,
            ':tid' => $ticketId
        ]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            echo json_encode(['success' => false, 'message' => 'Ticket not found']);
            return;
        }

        echo json_encode([
            'success' => true,
            'ticket' => $ticket
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Create a new support ticket in the `issues` table.
 * (Previously called createIssue)
 */
function createTicket(PDO $pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['ticket_title']) || empty($input['ticket_description'])) {
        echo json_encode(['success' => false, 'message' => 'Title and description are required.']);
        return;
    }

    $title = trim($input['ticket_title']);
    $desc  = trim($input['ticket_description']);
    $userId = $_SESSION['user_id'] ?? 0;
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        return;
    }

    try {
        $sql = "INSERT INTO issues (user_id, issue_title, issue_description, status, created_at)
                VALUES (:uid, :t, :d, 'Open', NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uid' => $userId,
            ':t'   => $title,
            ':d'   => $desc
        ]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
