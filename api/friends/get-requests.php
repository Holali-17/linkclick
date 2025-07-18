<?php
require_once '../config/database.php';
require_once '../utils/jwt.php';
require_once '../utils/security.php';
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
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Récupérer les demandes d'amitié reçues
    $query = "SELECT 
                u.id,
                u.firstname,
                u.lastname,
                CONCAT(u.firstname, ' ', u.lastname) as name,
                u.avatar,
                u.bio,
                f.created_at as request_date
              FROM friendships f
              JOIN users u ON u.id = f.requester_id
              WHERE f.addressee_id = :user_id
              AND f.status = 'pending'
              AND u.is_active = 1
              ORDER BY f.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $currentUserId);
    $stmt->execute();
    
    $requests = $stmt->fetchAll();
    
    // Ajouter des informations supplémentaires
    foreach ($requests as &$request) {
        $request['status'] = 'Demande d\'ami en attente';
    }
    
    echo json_encode([
        'success' => true,
        'requests' => $requests,
        'total' => count($requests)
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
