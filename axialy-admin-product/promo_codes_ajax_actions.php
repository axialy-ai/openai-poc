<?php

// 1) Set session name & start session
//    So that requireAdminAuth() won't rename the session and cause warnings:
session_name('axialy_admin_session');
session_start();

// 2) Enforce admin session
require_once __DIR__ . '/includes/admin_auth.php';
requireAdminAuth();

require_once __DIR__ . '/includes/db_connection.php';

// We’ll return JSON
header('Content-Type: application/json');

// Determine action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch($action) {
    case 'list':
        listPromoCodes($pdo);
        break;
    case 'get':
        getPromoCode($pdo);
        break;
    case 'create':
        createPromoCode($pdo);
        break;
    case 'update':
        updatePromoCode($pdo);
        break;
    default:
        echo json_encode(['success'=>false, 'message'=>'Unknown action']);
        break;
}

// --- Implementation ---

function listPromoCodes(PDO $pdo) {
    // Return a simple list of all codes (excluding statement BLOB if needed)
    $sql = "SELECT * FROM promo_codes ORDER BY id DESC";
    $stmt= $pdo->query($sql);
    $rows= $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
}

function getPromoCode(PDO $pdo) {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success'=>false,'message'=>'No ID given']);
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE id=?");
    $stmt->execute([$id]);
    $pc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pc) {
        echo json_encode(['success'=>false,'message'=>'Promo code not found.']);
        return;
    }
    echo json_encode($pc);
}

function createPromoCode(PDO $pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty($data['code'])) {
        echo json_encode(['success'=>false,'message'=>'No code provided.']);
        return;
    }
    $code = trim($data['code']);
    $desc = $data['description'] ?? '';
    $ctype= $data['code_type'] ?? 'unlimited';
    $days = (int)($data['limited_days'] ?? 0);
    $sreq = (int)($data['statement_required'] ?? 0);
    $stmtx= $data['statement'] ?? '';
    $sdat = !empty($data['start_date']) ? $data['start_date'] : null;
    $edat = !empty($data['end_date']) ? $data['end_date'] : null;
    $ulim = $data['usage_limit'] !== '' ? (int)$data['usage_limit'] : null;

    try {
        $sql = "
          INSERT INTO promo_codes
            (code, description, code_type, limited_days,
             statement_required, statement, start_date, end_date,
             usage_limit, active, created_at)
          VALUES
            (:c, :d, :ct, :ld, :sr, :stm, :sd, :ed, :ulim, 1, NOW())
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':c' => $code,
            ':d' => $desc,
            ':ct'=> $ctype,
            ':ld'=> $days,
            ':sr'=> $sreq,
            ':stm'=> $stmtx,
            ':sd' => $sdat,
            ':ed' => $edat,
            ':ulim'=> $ulim
        ]);
        echo json_encode(['success'=>true]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
}

function updatePromoCode(PDO $pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty($data['id'])) {
        echo json_encode(['success'=>false,'message'=>'No ID provided.']);
        return;
    }
    $id   = (int)$data['id'];
    $code = trim($data['code'] ?? '');
    if (!$code) {
        echo json_encode(['success'=>false,'message'=>'Code cannot be empty.']);
        return;
    }
    $desc = $data['description'] ?? '';
    $ctype= $data['code_type'] ?? 'unlimited';
    $days = (int)($data['limited_days'] ?? 0);
    $sreq = (int)($data['statement_required'] ?? 0);
    $stmtx= $data['statement'] ?? '';
    $sdat = !empty($data['start_date']) ? $data['start_date'] : null;
    $edat = !empty($data['end_date']) ? $data['end_date'] : null;
    $ulim = ($data['usage_limit'] !== '') ? (int)$data['usage_limit'] : null;
    $act  = isset($data['active']) ? (int)$data['active'] : 1;

    try {
        $sql = "
          UPDATE promo_codes
             SET code=:c,
                 description=:d,
                 code_type=:ct,
                 limited_days=:ld,
                 statement_required=:sr,
                 statement=:stm,
                 start_date=:sd,
                 end_date=:ed,
                 usage_limit=:ulim,
                 active=:act,
                 updated_at=NOW()
           WHERE id=:id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':c'   => $code,
            ':d'   => $desc,
            ':ct'  => $ctype,
            ':ld'  => $days,
            ':sr'  => $sreq,
            ':stm' => $stmtx,
            ':sd'  => $sdat,
            ':ed'  => $edat,
            ':ulim'=> $ulim,
            ':act' => $act,
            ':id'  => $id
        ]);
        echo json_encode(['success'=>true]);
    } catch (Exception $ex) {
        echo json_encode(['success'=>false,'message'=>$ex->getMessage()]);
    }
}
