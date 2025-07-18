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
    if (!isset($_GET['q']) || strlen($_GET['q']) < 2) {
        throw new Exception('Requête de recherche trop courte (minimum 2 caractères)');
    }
    
    $query = Security::sanitizeInput($_GET['q']);
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 20) : 10;
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Rechercher les utilisateurs
    $searchQuery = "SELECT 
                      u.id,
                      u.firstname,
                      u.lastname,
                      CONCAT(u.firstname, ' ', u.lastname) as full_name,
                      u.avatar,
                      u.bio,
                      f.status as friendship_status
                    FROM users u
                    LEFT JOIN friendships f ON (
                        (f.requester_id = :user_id AND f.addressee_id = u.id)
                        OR (f.requester_id = u.id AND f.addressee_id = :user_id)
                    )
                    WHERE u.id != :user_id
                    AND u.is_active = 1
                    AND (
                        u.firstname LIKE :query
                        OR u.lastname LIKE :query
                        OR CONCAT(u.firstname, ' ', u.lastname) LIKE :query
                    )
                    ORDER BY 
                        CASE 
                            WHEN u.firstname LIKE :exact_query OR u.lastname LIKE :exact_query THEN 1
                            WHEN CONCAT(u.firstname, ' ', u.lastname) LIKE :exact_query THEN 2
                            ELSE 3
                        END,
                        u.firstname, u.lastname
                    LIMIT :limit";
    
    $stmt = $db->prepare($searchQuery);
    $stmt->bindParam(':user_id', $currentUserId);
    $stmt->bindValue(':query', '%' . $query . '%');
    $stmt->bindValue(':exact_query', $query . '%');
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $users = $stmt->fetchAll();
    
    // Enrichir les résultats
    foreach ($users as &$user) {
        $user['friendship_status'] = $user['friendship_status'] ?? 'none';
        
        // Calculer les amis en commun
        $mutualQuery = "SELECT COUNT(*) as mutual_count
                        FROM friendships f1
                        JOIN friendships f2 ON (
                            (f1.requester_id = f2.requester_id OR f1.requester_id = f2.addressee_id OR 
                             f1.addressee_id = f2.requester_id OR f1.addressee_id = f2.addressee_id)
                            AND f1.id != f2.id
                        )
                        WHERE ((f1.requester_id = :user_id OR f1.addressee_id = :user_id) AND f1.status = 'accepted')
                        AND ((f2.requester_id = :target_id OR f2.addressee_id = :target_id) AND f2.status = 'accepted')";
        
        $mutualStmt = $db->prepare($mutualQuery);
        $mutualStmt->bindParam(':user_id', $currentUserId);
        $mutualStmt->bindParam(':target_id', $user['id']);
        $mutualStmt->execute();
        
        $user['mutual_friends'] = $mutualStmt->fetch()['mutual_count'] ?? 0;
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'query' => $query,
        'total' => count($users)
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
