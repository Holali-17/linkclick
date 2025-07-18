<?php
require_once '../config/database.php';
require_once '../utils/jwt.php';
require_once '../utils/security.php';
require_once '../utils/upload.php';
// Configuration des en-têtes CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://linkclick.netlify.app');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');
error_log("En-têtes CORS configurés");

// Gérer la requête OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    error_log("Requête OPTIONS reçue");
    http_response_code(200);
    exit();
}
try {
    $userPayload = Security::requireAuth();
    $currentUserId = $userPayload['user_id'];
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['user_id'])) {
        throw new Exception('ID utilisateur requis');
    }
    
    $otherUserId = (int)$input['user_id'];
    $content = isset($input['content']) ? Security::validateInput($input['content'], 'text', ['max_length' => 1000]) : '';
    
    if (empty($content) && empty($_FILES['image'])) {
        throw new Exception('Contenu ou image requis');
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Vérifier utilisateur cible
    $checkUserQuery = "SELECT id FROM users WHERE id = :user_id AND is_active = 1";
    $checkUserStmt = $db->prepare($checkUserQuery);
    $checkUserStmt->bindParam(':user_id', $otherUserId, PDO::PARAM_INT);
    $checkUserStmt->execute();
    
    if (!$checkUserStmt->fetch()) {
        throw new Exception('Utilisateur non trouvé');
    }
    
    // Récupérer/créer conversation (avec placeholders uniques)
    $conversationQuery = "SELECT id FROM conversations 
                          WHERE (user1_id = :user1a AND user2_id = :user2a)
                          OR (user1_id = :user1b AND user2_id = :user2b)";
    
    $conversationStmt = $db->prepare($conversationQuery);
    $conversationStmt->bindParam(':user1a', $currentUserId, PDO::PARAM_INT);
    $conversationStmt->bindParam(':user2a', $otherUserId, PDO::PARAM_INT);
    $conversationStmt->bindParam(':user1b', $otherUserId, PDO::PARAM_INT);
    $conversationStmt->bindParam(':user2b', $currentUserId, PDO::PARAM_INT);
    $conversationStmt->execute();
    
    $conversation = $conversationStmt->fetch();
    
    if (!$conversation) {
        $createConvQuery = "INSERT INTO conversations (user1_id, user2_id, created_at) 
                            VALUES (:user1, :user2, NOW())";
        
        $createConvStmt = $db->prepare($createConvQuery);
        $user1 = min($currentUserId, $otherUserId);
        $user2 = max($currentUserId, $otherUserId);
        $createConvStmt->bindParam(':user1', $user1, PDO::PARAM_INT);
        $createConvStmt->bindParam(':user2', $user2, PDO::PARAM_INT);
        $createConvStmt->execute();
        
        $conversationId = $db->lastInsertId();
    } else {
        $conversationId = $conversation['id'];
    }
    
    $db->beginTransaction();
    
    try {
        $insertQuery = "INSERT INTO messages (conversation_id, sender_id, content, created_at) 
                        VALUES (:conversation_id, :sender_id, :content, NOW())";
        
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bindParam(':conversation_id', $conversationId, PDO::PARAM_INT);
        $insertStmt->bindParam(':sender_id', $currentUserId, PDO::PARAM_INT);
        $insertStmt->bindParam(':content', $content, PDO::PARAM_STR);
        $insertStmt->execute();
        
        $messageId = $db->lastInsertId();
        
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadManager = new UploadManager();
            $imagePath = $uploadManager->uploadMessageImage($_FILES['image'], $messageId);
            
            $updateQuery = "UPDATE messages SET image = :image WHERE id = :message_id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':image', $imagePath, PDO::PARAM_STR);
            $updateStmt->bindParam(':message_id', $messageId, PDO::PARAM_INT);
            $updateStmt->execute();
        }
        
        $selectQuery = "SELECT 
                          m.id,
                          m.content,
                          m.image,
                          m.sender_id,
                          m.is_read,
                          m.created_at,
                          u.firstname,
                          u.lastname,
                          u.avatar
                        FROM messages m
                        JOIN users u ON m.sender_id = u.id
                        WHERE m.id = :message_id";
        
        $selectStmt = $db->prepare($selectQuery);
        $selectStmt->bindParam(':message_id', $messageId, PDO::PARAM_INT);
        $selectStmt->execute();
        
        $message = $selectStmt->fetch(PDO::FETCH_ASSOC);
        $message['is_mine'] = true;
        $message['sender_name'] = $message['firstname'] . ' ' . $message['lastname'];
        
        $notificationQuery = "INSERT INTO notifications (user_id, type, title, content, related_id, created_at)
                              VALUES (:user_id, 'message', :title, :content, :related_id, NOW())";
        
        $notificationStmt = $db->prepare($notificationQuery);
        $notificationStmt->bindParam(':user_id', $otherUserId, PDO::PARAM_INT);
        $notificationStmt->bindValue(':title', 'Nouveau message');
        $notificationStmt->bindValue(':content', 'Vous avez reçu un nouveau message');
        $notificationStmt->bindParam(':related_id', $currentUserId, PDO::PARAM_INT);
        $notificationStmt->execute();
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Message envoyé avec succès',
            'data' => $message
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
