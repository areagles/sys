<?php
// review_api.php - (V3.0) - Now with designer replies

header('Content-Type: application/json; charset=utf-8');
require 'config.php';

$response = ['status' => 'error', 'message' => 'Invalid Action'];
$conn->set_charset("utf8mb4");

$action = $_REQUEST['action'] ?? ''; 

// --- ACTION 1: Get all comments for a specific proof ---
if ($action == 'get_comments' && isset($_GET['proof_id'])) {
    $proof_id = intval($_GET['proof_id']);
    $comments = [];

    // ✨ NOW SELECTING THE REPLY COLUMNS AS WELL
    $stmt = $conn->prepare("SELECT id, pos_x, pos_y, comment_text, status, author, designer_reply, replied_at, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as created_at FROM proof_comments WHERE proof_id = ? ORDER BY created_at ASC");
    $stmt->bind_param("i", $proof_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $row['comment_text'] = htmlspecialchars($row['comment_text']);
        $row['designer_reply'] = htmlspecialchars($row['designer_reply'] ?? ''); // Ensure it's not null
        $comments[] = $row;
    }

    $stmt->close();
    $response = ['status' => 'success', 'comments' => $comments];
}

// --- ACTION 2: Add a new comment (No changes) ---
// ...

// --- ACTION 3: Update a comment's status (No changes) ---
// ...

// --- ✨ ACTION 4: NEW - Add or update a designer's reply ---
if ($action == 'add_designer_reply' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['comment_id'], $input['reply_text'])) {
        $comment_id = intval($input['comment_id']);
        $reply_text = trim($input['reply_text']);

        $stmt = $conn->prepare("UPDATE proof_comments SET designer_reply = ?, replied_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $reply_text, $comment_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response = ['status' => 'success', 'message' => 'Designer reply saved.'];
            } else {
                $response = ['status' => 'error', 'message' => 'Comment not found or reply is the same.'];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Database error: ' . $stmt->error];
        }
        $stmt->close();
    } else {
        $response = ['status' => 'error', 'message' => 'Incomplete data for designer reply.'];
    }
}

$conn->close();
echo json_encode($response);
exit;
?>
