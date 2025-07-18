<?php
require_once '../config/database.php';
require_once '../utils/jwt.php';
require_once '../utils/security.php';
// Définir les en-têtes
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
    // Authentification requise
    $userPayload = Security::requireAuth();
    
    // Vérifier les paramètres
    if (!isset($_GET['post_id'])) {
        throw new Exception('ID du post requis');
    }
    
    $postId = (int)$_GET['post_id'];
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 20;
    $offset = ($page - 1) * $limit;
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Vérifier que le post existe
    $checkQuery = "SELECT id FROM posts WHERE id = :post_id AND is_active = 1";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':post_id', $postId);
    $checkStmt->execute();
    
    if (!$checkStmt->fetch()) {
        throw new Exception('Post non trouvé');
    }
    
    // Récupérer les commentaires
    $query = "SELECT 
                c.id,
                c.content,
                c.created_at,
                u.id as user_id,
                CONCAT(u.firstname, ' ', u.lastname) as user_name,
                u.avatar as user_avatar
              FROM comments c
              JOIN users u ON c.user_id = u.id
              WHERE c.post_id = :post_id AND c.is_active = 1
              ORDER BY c.created_at ASC
              LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':post_id', $postId);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $comments = $stmt->fetchAll();
    
    // Compter le total
    $countQuery = "SELECT COUNT(*) as total FROM comments WHERE post_id = :post_id AND is_active = 1";
    $countStmt = $db->prepare($countQuery);
    $countStmt->bindParam(':post_id', $postId);
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];
    
    echo json_encode([
        'success' => true,
        'comments' => $comments,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_comments' => $total,
            'per_page' => $limit
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
