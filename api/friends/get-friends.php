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
    // Activer les erreurs PDO pour un meilleur débogage
    $database = new Database();
    $db = $database->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Authentification requise
    $userPayload = Security::requireAuth();
    $currentUserId = $userPayload['user_id'];

    // Vérifier que l'ID utilisateur est valide
    if (!is_numeric($currentUserId)) {
        throw new Exception("ID utilisateur invalide");
    }

    // Requête SQL corrigée avec deux paramètres anonymes (?)
    $query = "SELECT 
                u.id,
                u.firstname,
                u.lastname,
                CONCAT(u.firstname, ' ', u.lastname) as name,
                u.avatar,
                u.bio,
                f.created_at as friends_since
              FROM friendships f
              JOIN users u ON (
                  (f.requester_id = ? AND u.id = f.addressee_id) OR 
                  (f.addressee_id = ? AND u.id = f.requester_id)
              )
              WHERE f.status = 'accepted'
              AND u.is_active = 1
              ORDER BY u.firstname, u.lastname";

    $stmt = $db->prepare($query);
    $stmt->execute([$currentUserId, $currentUserId]);

    $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ajouter le statut en ligne (simulation)
    foreach ($friends as &$friend) {
        $friend['status'] = 'Membre LinkClick';
        $friend['is_online'] = rand(0, 1) === 1;
    }

    // Réponse JSON
    echo json_encode([
        'success' => true,
        'friends' => $friends,
        'total' => count($friends)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
