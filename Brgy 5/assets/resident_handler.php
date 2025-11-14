<?php
// resident_handler.php

// Enable error reporting (for debugging JSON issues)
ini_set('display_errors', 0); // Turn off HTML errors
ini_set('log_errors', 1);     // Log errors
error_reporting(E_ALL);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

header('Content-Type: application/json');

// Ensure PDO exists
if (!isset($pdo)) {
    echo json_encode(['success' => false, 'message' => 'Database connection not established']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_POST['action'] ?? '';

// --------------------- get_families ---------------------
if ($action === 'get_families') {
    try {
        $stmt = $pdo->query("SELECT * FROM families ORDER BY created_at DESC");
        $families = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'families' => $families]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// --------------------- create_family_and_resident ---------------------
if ($action === 'create_family_and_resident') {
    $first_name = $_POST['first_name'] ?? '';
    $last_name  = $_POST['last_name'] ?? '';
    $gender     = $_POST['gender'] ?? '';
    
    if (empty($first_name) || empty($last_name) || empty($gender)) {
        echo json_encode(['success' => false, 'message' => 'All fields required']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Create family
        $family_code = 'FAM' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $head_name = $first_name . ' ' . $last_name;

        $stmt = $pdo->prepare("INSERT INTO families (family_code, head_of_family, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$family_code, $head_name, $_SESSION['username']]);
        $family_id = $pdo->lastInsertId();

        // Create resident as head
        $stmt = $pdo->prepare("INSERT INTO residents (first_name, last_name, gender, family_id, relationship_to_head, registered_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$first_name, $last_name, $gender, $family_id, 'Head of Family', $_SESSION['username']]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Family and resident created', 'family_code' => $family_code]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
        exit;
    }
}

// --------------------- add_to_existing_family ---------------------
if ($action === 'add_to_existing_family') {
    $first_name        = $_POST['first_name'] ?? '';
    $last_name         = $_POST['last_name'] ?? '';
    $gender            = $_POST['gender'] ?? '';
    $relationship      = $_POST['relationship'] ?? '';
    $family_identifier = trim($_POST['family_identifier'] ?? ''); // Trim extra spaces

    if (empty($first_name) || empty($last_name) || empty($gender) || empty($relationship) || empty($family_identifier)) {
        echo json_encode(['success' => false, 'message' => 'All fields required']);
        exit;
    }

    try {
        // Lookup family by code (exact, case-insensitive) or head name (partial, case-insensitive)
        $stmt = $pdo->prepare("
            SELECT * FROM families 
            WHERE UPPER(family_code) = UPPER(?) 
               OR UPPER(head_of_family) LIKE UPPER(?)
            LIMIT 1
        ");
        $stmt->execute([$family_identifier, "%$family_identifier%"]);
        $family = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$family) {
            echo json_encode(['success' => false, 'message' => 'Family not found. Please check your input.']);
            exit;
        }

        $family_id = $family['family_id']; // FIXED: use correct column name

        // Insert resident
        $stmt = $pdo->prepare("
            INSERT INTO residents 
            (first_name, last_name, gender, family_id, relationship_to_head, registered_by) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$first_name, $last_name, $gender, $family_id, $relationship, $_SESSION['username']]);

        echo json_encode([
            'success' => true,
            'message' => 'Resident registered',
            'family_code' => $family['family_code']
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
        exit;
    }
}


// --------------------- get_residents ---------------------
if ($action === 'get_residents') {
    try {
        $stmt = $pdo->query("
            SELECT r.*, f.family_code 
            FROM residents r 
            JOIN families f ON r.family_id = f.id 
            ORDER BY r.registered_at DESC
        ");
        $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'residents' => $residents]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
?>
