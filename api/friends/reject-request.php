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
    
    // Rejeter la demande (supprimer l'enregistrement)
    $deleteQuery = "DELETE FROM friendships 
                    WHERE requester_id = :requester_id 
                    AND addressee_id = :addressee_id";
    
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->bindParam(':requester_id', $requesterId);
    $deleteStmt->bindParam(':addressee_id', $currentUserId);
    $deleteStmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Demande d\'amitié refusée'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
