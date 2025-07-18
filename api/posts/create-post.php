<?php
require_once '../config/database.php';
require_once '../utils/jwt.php';
require_once '../utils/security.php';
require_once '../utils/upload.php';
// Définir les en-têtes
// Configuration des en-têtes CORS


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
    
    // Récupérer le contenu
    $content = isset($_POST['content']) ? Security::validateInput($_POST['content'], 'text', ['max_length' => 2000]) : '';
    
    if (empty($content) && empty($_FILES['image'])) {
        throw new Exception('Le contenu ou une image est requis');
    }
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Commencer une transaction
    $db->beginTransaction();
    
    try {
        // Insérer le post
        $insertQuery = "INSERT INTO posts (user_id, content, created_at) VALUES (:user_id, :content, NOW())";
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bindParam(':user_id', $currentUserId);
        $insertStmt->bindParam(':content', $content);
        $insertStmt->execute();
        
        $postId = $db->lastInsertId();
        
        // Gérer l'upload d'image si présente
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadManager = new UploadManager();
            $imagePath = $uploadManager->uploadPostImage($_FILES['image'], $postId);
            
            // Mettre à jour le post avec l'image
            $updateQuery = "UPDATE posts SET image = :image WHERE id = :post_id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':image', $imagePath);
            $updateStmt->bindParam(':post_id', $postId);
            $updateStmt->execute();
        }
        
        // Récupérer le post créé avec les infos utilisateur
        $selectQuery = "SELECT 
                          p.id,
                          p.content,
                          p.image,
                          p.likes_count,
                          p.dislikes_count,
                          p.comments_count,
                          p.created_at,
                          u.id as user_id,
                          CONCAT(u.firstname, ' ', u.lastname) as user_name,
                          u.avatar as user_avatar
                        FROM posts p
                        JOIN users u ON p.user_id = u.id
                        WHERE p.id = :post_id";
        
        $selectStmt = $db->prepare($selectQuery);
        $selectStmt->bindParam(':post_id', $postId);
        $selectStmt->execute();
        
        $post = $selectStmt->fetch();
        $post['user_liked'] = false;
        $post['user_disliked'] = false;
        $post['comments'] = [];
        
        // Valider la transaction
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Post créé avec succès',
            'post' => $post
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
