<?php
// Désactiver l'affichage des erreurs en production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

include_once '../config/database.php'; // Inclure la configuration API --- IGNORE ---
/// Configuration des en-têtes CORS
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

require_once '../config/database.php';
require_once '../utils/jwt.php';
require_once '../utils/security.php';

try {
    // Authentification requise
    $userPayload = Security::requireAuth();
    $currentUserId = $userPayload['user_id'];
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Récupérer les conversations avec le dernier message
    $query = "SELECT 
                c.id as conversation_id,
                CASE 
                    WHEN c.user1_id = :user_id1 THEN c.user2_id
                    ELSE c.user1_id
                END as user_id,
                CASE 
                    WHEN c.user1_id = :user_id2 THEN CONCAT(u2.firstname, ' ', u2.lastname)
                    ELSE CONCAT(u1.firstname, ' ', u1.lastname)
                END as user_name,
                CASE 
                    WHEN c.user1_id = :user_id3 THEN u2.avatar
                    ELSE u1.avatar
                END as user_avatar,
                m.content as last_message,
                m.created_at as last_message_time,
                m.sender_id as last_sender_id,
                (SELECT COUNT(*) FROM messages 
                 WHERE conversation_id = c.id 
                 AND sender_id != :user_id4 
                 AND is_read = 0) as unread_count
              FROM conversations c
              JOIN users u1 ON c.user1_id = u1.id
              JOIN users u2 ON c.user2_id = u2.id
              LEFT JOIN messages m ON c.last_message_id = m.id
              WHERE (c.user1_id = :user_id5 OR c.user2_id = :user_id6)
              AND u1.is_active = 1 AND u2.is_active = 1
              ORDER BY c.updated_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id1', $currentUserId, PDO::PARAM_INT);
    $stmt->bindParam(':user_id2', $currentUserId, PDO::PARAM_INT);
    $stmt->bindParam(':user_id3', $currentUserId, PDO::PARAM_INT);
    $stmt->bindParam(':user_id4', $currentUserId, PDO::PARAM_INT);
    $stmt->bindParam(':user_id5', $currentUserId, PDO::PARAM_INT);
    $stmt->bindParam(':user_id6', $currentUserId, PDO::PARAM_INT);
    $stmt->execute();
    
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les données
    foreach ($conversations as &$conversation) {
        $conversation['is_last_message_mine'] = $conversation['last_sender_id'] == $currentUserId;
        
        if ($conversation['last_message']) {
            // Tronquer le message s'il est trop long
            if (strlen($conversation['last_message']) > 50) {
                $conversation['last_message'] = substr($conversation['last_message'], 0, 50) . '...';
            }
        } else {
            $conversation['last_message'] = 'Aucun message';
        }
        
        // Nettoyer les données sensibles
        unset($conversation['last_sender_id']);
    }
    
    echo json_encode([
        'success' => true,
        'conversations' => $conversations
    ]);
    exit;
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>