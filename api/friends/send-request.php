<?php
require_once '../config/database.php';
require_once '../utils/jwt.php';
require_once '../utils/security.php';
require_once '../utils/email.php';
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
    
    if (!$input || !isset($input['user_id'])) {
        throw new Exception('ID utilisateur requis');
    }
    
    $targetUserId = (int)$input['user_id'];
    
    if ($targetUserId === $currentUserId) {
        throw new Exception('Vous ne pouvez pas vous ajouter vous-même');
    }
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Vérifier que l'utilisateur cible existe
    $checkUserQuery = "SELECT id, firstname, lastname, email FROM users WHERE id = :user_id AND is_active = 1";
    $checkUserStmt = $db->prepare($checkUserQuery);
    $checkUserStmt->bindParam(':user_id', $targetUserId, PDO::PARAM_INT);
    $checkUserStmt->execute();
    
    $targetUser = $checkUserStmt->fetch(PDO::FETCH_ASSOC);
    if (!$targetUser) {
        throw new Exception('Utilisateur non trouvé');
    }
    
    // Vérifier s'il n'y a pas déjà une relation avec paramètres distincts
    $checkFriendshipQuery = "SELECT status FROM friendships 
                             WHERE (requester_id = :user1 AND addressee_id = :user2)
                             OR (requester_id = :user3 AND addressee_id = :user4)";
    
    $checkFriendshipStmt = $db->prepare($checkFriendshipQuery);
    $checkFriendshipStmt->bindParam(':user1', $currentUserId, PDO::PARAM_INT);
    $checkFriendshipStmt->bindParam(':user2', $targetUserId, PDO::PARAM_INT);
    $checkFriendshipStmt->bindParam(':user3', $targetUserId, PDO::PARAM_INT);
    $checkFriendshipStmt->bindParam(':user4', $currentUserId, PDO::PARAM_INT);
    $checkFriendshipStmt->execute();
    
    $existingFriendship = $checkFriendshipStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingFriendship) {
        switch ($existingFriendship['status']) {
            case 'accepted':
                throw new Exception('Vous êtes déjà amis');
            case 'pending':
                throw new Exception('Demande d\'amitié déjà envoyée');
            case 'blocked':
                throw new Exception('Impossible d\'envoyer une demande d\'amitié');
        }
    }
    
    // Créer la demande d'amitié
    $insertQuery = "INSERT INTO friendships (requester_id, addressee_id, status, created_at) 
                    VALUES (:requester_id, :addressee_id, 'pending', NOW())";
    
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindParam(':requester_id', $currentUserId, PDO::PARAM_INT);
    $insertStmt->bindParam(':addressee_id', $targetUserId, PDO::PARAM_INT);
    $insertStmt->execute();
    
    // Récupérer les infos du demandeur pour la notification
    $requesterQuery = "SELECT firstname, lastname FROM users WHERE id = :user_id";
    $requesterStmt = $db->prepare($requesterQuery);
    $requesterStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
    $requesterStmt->execute();
    $requester = $requesterStmt->fetch(PDO::FETCH_ASSOC);
    
    // Créer une notification
    $notificationQuery = "INSERT INTO notifications (user_id, type, title, content, related_id, created_at)
                          VALUES (:user_id, 'friend_request', :title, :content, :related_id, NOW())";
    
    $notificationStmt = $db->prepare($notificationQuery);
    $notificationStmt->bindParam(':user_id', $targetUserId, PDO::PARAM_INT);
    $notificationStmt->bindValue(':title', 'Nouvelle demande d\'ami');
    $notificationStmt->bindValue(':content', $requester['firstname'] . ' ' . $requester['lastname'] . ' souhaite devenir votre ami');
    $notificationStmt->bindParam(':related_id', $currentUserId, PDO::PARAM_INT);
    $notificationStmt->execute();
    
    // Envoyer un email de notification
    try {
        $emailManager = new EmailManager();
        $emailManager->sendFriendRequestEmail(
            $targetUser['email'], 
            $targetUser['firstname'], 
            $requester['firstname'] . ' ' . $requester['lastname']
        );
    } catch (Exception $e) {
        // Log l'erreur mais ne pas faire échouer la demande
        error_log('Erreur envoi email demande ami: ' . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Demande d\'amitié envoyée avec succès'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
