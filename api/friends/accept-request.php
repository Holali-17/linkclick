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
    
    if (!$input || !isset($input['user_id'])) {
        throw new Exception('ID utilisateur requis');
    }
    
    $requesterId = (int)$input['user_id'];
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Vérifier que la demande existe et est en attente
    $checkQuery = "SELECT id FROM friendships 
                   WHERE requester_id = :requester_id 
                   AND addressee_id = :addressee_id 
                   AND status = 'pending'";
    
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':requester_id', $requesterId);
    $checkStmt->bindParam(':addressee_id', $currentUserId);
    $checkStmt->execute();
    
    if (!$checkStmt->fetch()) {
        throw new Exception('Demande d\'amitié non trouvée');
    }
    
    // Accepter la demande
    $updateQuery = "UPDATE friendships 
                    SET status = 'accepted', updated_at = NOW() 
                    WHERE requester_id = :requester_id 
                    AND addressee_id = :addressee_id";
    
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':requester_id', $requesterId);
    $updateStmt->bindParam(':addressee_id', $currentUserId);
    $updateStmt->execute();
    
    // Récupérer les infos de l'utilisateur actuel pour la notification
    $currentUserQuery = "SELECT firstname, lastname FROM users WHERE id = :user_id";
    $currentUserStmt = $db->prepare($currentUserQuery);
    $currentUserStmt->bindParam(':user_id', $currentUserId);
    $currentUserStmt->execute();
    $currentUser = $currentUserStmt->fetch();
    
    // Créer une notification pour le demandeur
    $notificationQuery = "INSERT INTO notifications (user_id, type, title, content, related_id, created_at)
                          VALUES (:user_id, 'friend_accepted', :title, :content, :related_id, NOW())";
    
    $notificationStmt = $db->prepare($notificationQuery);
    $notificationStmt->bindParam(':user_id', $requesterId);
    $notificationStmt->bindValue(':title', 'Demande d\'ami acceptée');
    $notificationStmt->bindValue(':content', $currentUser['firstname'] . ' ' . $currentUser['lastname'] . ' a accepté votre demande d\'ami');
    $notificationStmt->bindParam(':related_id', $currentUserId);
    $notificationStmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Demande d\'amitié acceptée'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
