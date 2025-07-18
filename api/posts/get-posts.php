<?php
// Désactiver l'affichage des erreurs en production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
include_once '../config/database.php'; // Inclure la configuration API --- IGNORE ---
// Définir les en-têtes
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
    
    // Paramètres de pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 20;
    $offset = ($page - 1) * $limit;

    // Valider les paramètres de pagination
    $limit = (int)$limit;
    $offset = (int)$offset;
    
    // Requête pour récupérer les posts avec les informations utilisateur et les réactions
    $query = "SELECT 
                p.id,
                p.content,
                p.image,
                p.likes_count,
                p.dislikes_count,
                p.comments_count,
                p.created_at,
                u.id as user_id,
                u.firstname,
                u.lastname,
                CONCAT(u.firstname, ' ', u.lastname) as user_name,
                u.avatar as user_avatar,
                pr.type as user_reaction
              FROM posts p
              JOIN users u ON p.user_id = u.id
              LEFT JOIN post_reactions pr ON p.id = pr.post_id AND pr.user_id = :current_user_id1
              WHERE p.is_active = 1
              AND (
                  p.user_id = :current_user_id2
                  OR p.user_id IN (
                      SELECT CASE 
                          WHEN requester_id = :current_user_id3 THEN addressee_id
                          ELSE requester_id
                      END
                      FROM friendships 
                      WHERE (requester_id = :current_user_id4 OR addressee_id = :current_user_id5)
                      AND status = 'accepted'
                  )
              )
              ORDER BY p.created_at DESC
              LIMIT $limit OFFSET $offset"; // LIMIT et OFFSET insérés directement
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':current_user_id1', $currentUserId, PDO::PARAM_INT);
    $stmt->bindParam(':current_user_id2', $currentUserId, PDO::PARAM_INT);
    $stmt->bindParam(':current_user_id3', $currentUserId, PDO::PARAM_INT);
    $stmt->bindParam(':current_user_id4', $currentUserId, PDO::PARAM_INT);
    $stmt->bindParam(':current_user_id5', $currentUserId, PDO::PARAM_INT);
    $stmt->execute();
    
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Enrichir chaque post avec les commentaires récents
    foreach ($posts as &$post) {
        // Ajouter les flags de réaction
        $post['user_liked'] = $post['user_reaction'] === 'like';
        $post['user_disliked'] = $post['user_reaction'] === 'dislike';
        
        // Récupérer les 3 derniers commentaires
        $commentsQuery = "SELECT 
                            c.id,
                            c.content,
                            c.created_at,
                            u.id as user_id,
                            CONCAT(u.firstname, ' ', u.lastname) as user_name,
                            u.avatar as user_avatar
                          FROM comments c
                          JOIN users u ON c.user_id = u.id
                          WHERE c.post_id = :post_id AND c.is_active = 1
                          ORDER BY c.created_at DESC
                          LIMIT 3";
        
        $commentsStmt = $db->prepare($commentsQuery);
        $commentsStmt->bindParam(':post_id', $post['id'], PDO::PARAM_INT);
        $commentsStmt->execute();
        
        $post['comments'] = array_reverse($commentsStmt->fetchAll(PDO::FETCH_ASSOC));
        
        // Nettoyer les données sensibles
        unset($post['user_reaction']);
    }
    
    // Compter le total pour la pagination
    $countQuery = "SELECT COUNT(*) as total
                   FROM posts p
                   WHERE p.is_active = 1
                   AND (
                       p.user_id = :current_user_id1
                       OR p.user_id IN (
                           SELECT CASE 
                               WHEN requester_id = :current_user_id2 THEN addressee_id
                               ELSE requester_id
                           END
                           FROM friendships 
                           WHERE (requester_id = :current_user_id3 OR addressee_id = :current_user_id4)
                           AND status = 'accepted'
                       )
                   )";
    
    $countStmt = $db->prepare($countQuery);
    $countStmt->bindParam(':current_user_id1', $currentUserId, PDO::PARAM_INT);
    $countStmt->bindParam(':current_user_id2', $currentUserId, PDO::PARAM_INT);
    $countStmt->bindParam(':current_user_id3', $currentUserId, PDO::PARAM_INT);
    $countStmt->bindParam(':current_user_id4', $currentUserId, PDO::PARAM_INT);
    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'posts' => $posts,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_posts' => $total,
            'per_page' => $limit
        ]
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