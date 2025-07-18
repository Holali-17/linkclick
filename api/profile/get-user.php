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
    
    // Vérifier les paramètres
    if (!isset($_GET['user_id'])) {
        throw new Exception('ID utilisateur requis');
    }
    
    $targetUserId = (int)$_GET['user_id'];
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Récupérer les informations de l'utilisateur
    $query = "SELECT 
                u.id,
                u.firstname,
                u.lastname,
                u.avatar,
                u.bio,
                u.created_at,
                (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND is_active = 1) as posts_count,
                (SELECT COUNT(*) FROM friendships 
                 WHERE (requester_id = u.id OR addressee_id = u.id) 
                 AND status = 'accepted') as friends_count
              FROM users u
              WHERE u.id = :user_id AND u.is_active = 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $targetUserId, PDO::PARAM_INT);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Utilisateur non trouvé');
    }
    
    // Vérifier la relation d'amitié avec des paramètres distincts
    $friendshipQuery = "SELECT status FROM friendships 
                        WHERE (requester_id = :user1 AND addressee_id = :user2)
                        OR (requester_id = :user3 AND addressee_id = :user4)";
    
    $friendshipStmt = $db->prepare($friendshipQuery);
    $friendshipStmt->bindParam(':user1', $currentUserId, PDO::PARAM_INT);
    $friendshipStmt->bindParam(':user2', $targetUserId, PDO::PARAM_INT);
    $friendshipStmt->bindParam(':user3', $targetUserId, PDO::PARAM_INT);
    $friendshipStmt->bindParam(':user4', $currentUserId, PDO::PARAM_INT);
    $friendshipStmt->execute();
    
    $friendship = $friendshipStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($friendship) {
        $user['friendship_status'] = $friendship['status'];
    } else {
        $user['friendship_status'] = 'none';
    }
    
    // Si c'est un ami ou soi-même, récupérer plus d'informations
    if ($targetUserId === $currentUserId || ($friendship && $friendship['status'] === 'accepted')) {
        // Récupérer les posts récents
        $postsQuery = "SELECT 
                         p.id,
                         p.content,
                         p.image,
                         p.likes_count,
                         p.dislikes_count,
                         p.comments_count,
                         p.created_at
                       FROM posts p
                       WHERE p.user_id = :user_id AND p.is_active = 1
                       ORDER BY p.created_at DESC
                       LIMIT 10";
        
        $postsStmt = $db->prepare($postsQuery);
        $postsStmt->bindParam(':user_id', $targetUserId, PDO::PARAM_INT);
        $postsStmt->execute();
        
        $user['posts'] = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
