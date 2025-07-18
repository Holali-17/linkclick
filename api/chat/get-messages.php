<?php
require_once '../config/database.php';
require_once '../utils/jwt.php';
require_once '../utils/security.php';
// Configuration des en-têtes CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
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
    // Authentification requise
    $userPayload = Security::requireAuth();
    $currentUserId = $userPayload['user_id'];
    
    // Vérifier les paramètres
    if (!isset($_GET['user_id'])) {
        throw new Exception('ID utilisateur requis');
    }
    
    $otherUserId = (int)$_GET['user_id'];
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 30;
    $offset = ($page - 1) * $limit;
    
    // Sécuriser limit et offset
    $limit = filter_var($limit, FILTER_VALIDATE_INT, ["options" => ["default" => 30, "min_range" => 1, "max_range" => 50]]);
    $offset = max(0, $offset);
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Vérifier que l'autre utilisateur existe
    $checkUserQuery = "SELECT id FROM users WHERE id = :user_id AND is_active = 1";
    $checkUserStmt = $db->prepare($checkUserQuery);
    $checkUserStmt->bindParam(':user_id', $otherUserId, PDO::PARAM_INT);
    $checkUserStmt->execute();
    
    if (!$checkUserStmt->fetch()) {
        throw new Exception('Utilisateur non trouvé');
    }
    
    // Récupérer ou créer la conversation avec placeholders uniques
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
        // Créer une nouvelle conversation
        $createConvQuery = "INSERT INTO conversations (user1_id, user2_id, created_at) 
                            VALUES (:user1, :user2, NOW())";
        
        $createConvStmt = $db->prepare($createConvQuery);
        $user1 = min($currentUserId, $otherUserId);
        $user2 = max($currentUserId, $otherUserId);
        $createConvStmt->bindParam(':user1', $user1, PDO::PARAM_INT);
        $createConvStmt->bindParam(':user2', $user2, PDO::PARAM_INT);
        $createConvStmt->execute();
        
        $conversationId = $db->lastInsertId();
        $messages = [];
    } else {
        $conversationId = $conversation['id'];
        
        // Récupérer les messages
        $limit = (int)$limit;
        $offset = (int)$offset;

        $messagesQuery = "SELECT 
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
                        WHERE m.conversation_id = :conversation_id
                        ORDER BY m.created_at DESC
                        LIMIT $limit OFFSET $offset";

        $messagesStmt = $db->prepare($messagesQuery);
        $messagesStmt->bindParam(':conversation_id', $conversationId, PDO::PARAM_INT);
        $messagesStmt->execute();

        $messages = array_reverse($messagesStmt->fetchAll(PDO::FETCH_ASSOC));
        
        // Marquer les messages comme lus
        $markReadQuery = "UPDATE messages 
                          SET is_read = 1 
                          WHERE conversation_id = :conversation_id 
                          AND sender_id = :sender_id 
                          AND is_read = 0";
        
        $markReadStmt = $db->prepare($markReadQuery);
        $markReadStmt->bindParam(':conversation_id', $conversationId, PDO::PARAM_INT);
        $markReadStmt->bindParam(':sender_id', $otherUserId, PDO::PARAM_INT);
        $markReadStmt->execute();
    }
    
    // Enrichir les messages
    foreach ($messages as &$message) {
        $message['is_mine'] = $message['sender_id'] == $currentUserId;
        $message['sender_name'] = $message['firstname'] . ' ' . $message['lastname'];
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'conversation_id' => $conversationId
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
