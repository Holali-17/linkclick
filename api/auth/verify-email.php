<?php
require_once '../config/database.php';
require_once '../utils/security.php';


// Configuration des en-têtes CORS


// Gérer la requête OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    error_log("Requête OPTIONS reçue");
    http_response_code(200);
    exit();
}
try {
    // Vérifier la méthode
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Méthode non autorisée');
    }
    
    if (empty($_GET['token'])) {
        throw new Exception('Token de vérification requis');
    }
    
    $token = Security::sanitizeInput($_GET['token']);
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Vérifier le token
    $query = "SELECT id, firstname, lastname, email 
              FROM users 
              WHERE verification_token = :token 
              AND email_verified = 0 
              AND is_active = 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('Token invalide ou email déjà vérifié');
    }
    
    // Marquer l'email comme vérifié
    $updateQuery = "UPDATE users 
                    SET email_verified = 1, 
                        verification_token = NULL,
                        updated_at = NOW()
                    WHERE id = :user_id";
    
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindParam(':user_id', $user['id']);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Erreur lors de la vérification');
    }
    
    // Log de sécurité
    Security::logSecurityEvent('email_verified', [
        'user_id' => $user['id'],
        'email' => $user['email']
    ]);
    
    // Rediriger vers la page de connexion avec un message de succès
    header('Location: ../../index.html?verified=1');
    exit;
    
} catch (Exception $e) {
    // Rediriger vers la page d'erreur
    header('Location: ../../index.html?error=' . urlencode($e->getMessage()));
    exit;
}
?>
