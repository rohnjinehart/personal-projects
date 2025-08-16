<?php
require_once 'session.php';
requireLogin();

// Database connection
$servername = "[redacted]";
$username = "[redacted]";
$password = "[redacted]";
$dbname = "[redacted]";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get or create conversation between two users
function getConversation($userId1, $userId2, $conn) {
    $sql = "SELECT c.id 
            FROM conversations c
            JOIN conversation_participants cp1 ON c.id = cp1.conversation_id
            JOIN conversation_participants cp2 ON c.id = cp2.conversation_id
            WHERE cp1.user_id = ? AND cp2.user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId1, $userId2);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['id'];
    }
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['id'];
    } else {
        // Create new conversation
        $conn->begin_transaction();
        
        try {
            $conn->query("INSERT INTO conversations VALUES ()");
            $conversationId = $conn->insert_id;
            
            $stmt = $conn->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $conversationId, $userId1);
            $stmt->execute();
            
            $stmt->bind_param("ii", $conversationId, $userId2);
            $stmt->execute();
            
            $conn->commit();
            return $conversationId;
        } catch (Exception $e) {
            $conn->rollback();
            return false;
        }
    }
}

// Get messages for a conversation
function getMessages($conversationId, $conn) {
    $sql = "SELECT m.*, u.username as sender_name 
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $conversationId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    return $messages;
}

// Send a message
function sendMessage($conversationId, $senderId, $content, $conn) {
    $sql = "INSERT INTO messages (conversation_id, sender_id, content) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $conversationId, $senderId, $content);
    
    if ($stmt->execute()) {
        // Update conversation timestamp
        $conn->query("UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = $conversationId");
        return true;
    }
    
    return false;
}

// Get user conversations
function getUserConversations($userId, $conn) {
    $sql = "SELECT c.id, c.updated_at, 
                   u.id as other_user_id, u.username as other_user_name,
                   (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
            FROM conversations c
            JOIN conversation_participants cp ON c.id = cp.conversation_id
            JOIN users u ON cp.user_id = u.id
            WHERE c.id IN (SELECT conversation_id FROM conversation_participants WHERE user_id = ?)
              AND u.id != ?
            ORDER BY c.updated_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        $conversations[] = $row;
    }
    
    return $conversations;
}

// Mark messages as read
function markMessagesAsRead($conversationId, $userId, $conn) {
    $sql = "UPDATE messages SET is_read = TRUE 
            WHERE conversation_id = ? AND sender_id != ? AND is_read = FALSE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $conversationId, $userId);
    return $stmt->execute();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';
        $response = [];
        
        switch ($action) {
            case 'get_conversations':
                $response['conversations'] = getUserConversations($_SESSION['user_id'], $conn);
                break;
                
            case 'get_messages':
                $conversationId = $_POST['conversation_id'];
                $response['messages'] = getMessages($conversationId, $conn);
                markMessagesAsRead($conversationId, $_SESSION['user_id'], $conn);
                break;
                
            case 'send_message':
                $conversationId = $_POST['conversation_id'];
                $content = trim($_POST['content']);
                
                if (empty($content)) {
                    throw new Exception("Message cannot be empty");
                }
                
                if (sendMessage($conversationId, $_SESSION['user_id'], $content, $conn)) {
                    $response['success'] = true;
                    $response['messages'] = getMessages($conversationId, $conn);
                } else {
                    throw new Exception("Failed to send message");
                }
                break;
                
            case 'start_conversation':
                $otherUserId = $_POST['user_id'];
                $conversationId = getConversation($_SESSION['user_id'], $otherUserId, $conn);
                $response['conversation_id'] = $conversationId;
                $response['messages'] = getMessages($conversationId, $conn);
                break;
                
            default:
                throw new Exception("Invalid action");
        }
        
        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    exit;
}

$conn->close();
?>