<?php
require_once '../config/database.php';
require_once '../utils/security.php';

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
    // Vérifier la méthode
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }
    
    // Récupérer les données
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['token']) || empty($input['password'])) {
        throw new Exception('Token et nouveau mot de passe requis');
    }
    
    $token = Security::sanitizeInput($input['token']);
    $password = $input['password'];
    
    // Valider le mot de passe
    Security::validateInput($password, 'password');
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Vérifier le token
    $query = "SELECT id, firstname, lastname, email 
              FROM users 
              WHERE reset_token = :token 
              AND reset_token_expires > NOW() 
              AND is_active = 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('Token invalide ou expiré');
    }
    
    // Hasher le nouveau mot de passe
    $hashedPassword = Security::hashPassword($password);
    
    // Mettre à jour le mot de passe et supprimer le token
    $updateQuery = "UPDATE users 
                    SET password = :password, 
                        reset_token = NULL, 
                        reset_token_expires = NULL,
                        updated_at = NOW()
                    WHERE id = :user_id";
    
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':password', $hashedPassword);
    $updateStmt->bindParam(':user_id', $user['id']);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Erreur lors de la mise à jour du mot de passe');
    }
    
    // Supprimer toutes les sessions actives de cet utilisateur
    $deleteSessionsQuery = "DELETE FROM user_sessions WHERE user_id = :user_id";
    $deleteSessionsStmt = $db->prepare($deleteSessionsQuery);
    $deleteSessionsStmt->bindParam(':user_id', $user['id']);
    $deleteSessionsStmt->execute();
    
    // Log de sécurité
    Security::logSecurityEvent('password_reset_completed', [
        'user_id' => $user['id'],
        'email' => $user['email']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
