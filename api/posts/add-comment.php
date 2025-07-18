<?php
require_once '../config/database.php';
require_once '../utils/jwt.php';
require_once '../utils/security.php';
// Définir les en-têtes
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
    
    // Vérifier la méthode
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }
    
    // Récupérer les données
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['post_id']) || !isset($input['content'])) {
        throw new Exception('ID du post et contenu requis');
    }
    
    $postId = (int)$input['post_id'];
    $content = Security::validateInput($input['content'], 'text', ['max_length' => 500]);
    
    if (empty(trim($content))) {
        throw new Exception('Le commentaire ne peut pas être vide');
    }
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Vérifier que le post existe
    $checkQuery = "SELECT id, user_id FROM posts WHERE id = :post_id AND is_active = 1";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':post_id', $postId);
    $checkStmt->execute();
    
    $post = $checkStmt->fetch();
    if (!$post) {
        throw new Exception('Post non trouvé');
    }
    
    // Insérer le commentaire
    $insertQuery = "INSERT INTO comments (user_id, post_id, content, created_at) 
                    VALUES (:user_id, :post_id, :content, NOW())";
    
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindParam(':user_id', $currentUserId);
    $insertStmt->bindParam(':post_id', $postId);
    $insertStmt->bindParam(':content', $content);
    $insertStmt->execute();
    
    $commentId = $db->lastInsertId();
    
    // Récupérer le commentaire créé avec les infos utilisateur
    $selectQuery = "SELECT 
                      c.id,
                      c.content,
                      c.created_at,
                      u.id as user_id,
                      CONCAT(u.firstname, ' ', u.lastname) as user_name,
                      u.avatar as user_avatar
                    FROM comments c
                    JOIN users u ON c.user_id = u.id
                    WHERE c.id = :comment_id";
    
    $selectStmt = $db->prepare($selectQuery);
    $selectStmt->bindParam(':comment_id', $commentId);
    $selectStmt->execute();
    
    $comment = $selectStmt->fetch();
    
    // Créer une notification pour le propriétaire du post (si ce n'est pas lui qui commente)
    if ($post['user_id'] != $currentUserId) {
        $notificationQuery = "INSERT INTO notifications (user_id, type, title, content, related_id, created_at)
                              VALUES (:user_id, 'post_comment', :title, :content, :related_id, NOW())";
        
        $notificationStmt = $db->prepare($notificationQuery);
        $notificationStmt->bindParam(':user_id', $post['user_id']);
        $notificationStmt->bindValue(':title', 'Nouveau commentaire');
        $notificationStmt->bindValue(':content', 'Quelqu\'un a commenté votre publication');
        $notificationStmt->bindParam(':related_id', $postId);
        $notificationStmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Commentaire ajouté avec succès',
        'comment' => $comment
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
