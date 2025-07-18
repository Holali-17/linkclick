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
    $currentUserId = $userPayload['user_id'];
    
    // Vérifier la méthode
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }
    
    // Récupérer les données
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['post_id']) || !isset($input['type'])) {
        throw new Exception('ID du post et type de réaction requis');
    }
    
    $postId = (int)$input['post_id'];
    $type = $input['type']; // 'like' ou 'dislike'
    
    if (!in_array($type, ['like', 'dislike'])) {
        throw new Exception('Type de réaction invalide');
    }
    
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
    
    // Vérifier la réaction existante
    $existingQuery = "SELECT type FROM post_reactions WHERE user_id = :user_id AND post_id = :post_id";
    $existingStmt = $db->prepare($existingQuery);
    $existingStmt->bindParam(':user_id', $currentUserId);
    $existingStmt->bindParam(':post_id', $postId);
    $existingStmt->execute();
    
    $existingReaction = $existingStmt->fetch();
    
    if ($existingReaction) {
        if ($existingReaction['type'] === $type) {
            // Supprimer la réaction (toggle off)
            $deleteQuery = "DELETE FROM post_reactions WHERE user_id = :user_id AND post_id = :post_id";
            $deleteStmt = $db->prepare($deleteQuery);
            $deleteStmt->bindParam(':user_id', $currentUserId);
            $deleteStmt->bindParam(':post_id', $postId);
            $deleteStmt->execute();
            
            $action = 'removed';
        } else {
            // Changer le type de réaction
            $updateQuery = "UPDATE post_reactions SET type = :type WHERE user_id = :user_id AND post_id = :post_id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':type', $type);
            $updateStmt->bindParam(':user_id', $currentUserId);
            $updateStmt->bindParam(':post_id', $postId);
            $updateStmt->execute();
            
            $action = 'changed';
        }
    } else {
        // Ajouter une nouvelle réaction
        $insertQuery = "INSERT INTO post_reactions (user_id, post_id, type) VALUES (:user_id, :post_id, :type)";
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bindParam(':user_id', $currentUserId);
        $insertStmt->bindParam(':post_id', $postId);
        $insertStmt->bindParam(':type', $type);
        $insertStmt->execute();
        
        $action = 'added';
    }
    
    // Récupérer les nouveaux compteurs
    $countsQuery = "SELECT likes_count, dislikes_count FROM posts WHERE id = :post_id";
    $countsStmt = $db->prepare($countsQuery);
    $countsStmt->bindParam(':post_id', $postId);
    $countsStmt->execute();
    $counts = $countsStmt->fetch();
    
    // Récupérer la réaction actuelle de l'utilisateur
    $userReactionQuery = "SELECT type FROM post_reactions WHERE user_id = :user_id AND post_id = :post_id";
    $userReactionStmt = $db->prepare($userReactionQuery);
    $userReactionStmt->bindParam(':user_id', $currentUserId);
    $userReactionStmt->bindParam(':post_id', $postId);
    $userReactionStmt->execute();
    $userReaction = $userReactionStmt->fetch();
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'likes_count' => $counts['likes_count'],
        'dislikes_count' => $counts['dislikes_count'],
        'user_liked' => $userReaction && $userReaction['type'] === 'like',
        'user_disliked' => $userReaction && $userReaction['type'] === 'dislike'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
