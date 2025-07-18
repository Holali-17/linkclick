<?php
require_once '../config/database.php';
require_once '../utils/security.php';
require_once '../utils/email.php';

try {
    // Vérifier la méthode
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }
    
    // Récupérer les données
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['email'])) {
        throw new Exception('Email requis');
    }
    
    $email = Security::validateInput($input['email'], 'email');
    
    // Rate limiting
    $clientIP = Security::getClientIP();
    if (!Security::rateLimitCheck('forgot_password_' . $clientIP, 3, 3600)) {
        throw new Exception('Trop de tentatives. Réessayez dans 1 heure.');
    }
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Rechercher l'utilisateur
    $query = "SELECT id, firstname, lastname, email FROM users WHERE email = :email AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    // Toujours retourner un succès pour éviter l'énumération d'emails
    if ($user) {
        // Générer le token de réinitialisation
        $resetToken = Security::generateToken();
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 heure
        
        // Sauvegarder le token
        $updateQuery = "UPDATE users 
                        SET reset_token = :reset_token, reset_token_expires = :expires_at 
                        WHERE id = :user_id";
        
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':reset_token', $resetToken);
        $updateStmt->bindParam(':expires_at', $expiresAt);
        $updateStmt->bindParam(':user_id', $user['id']);
        $updateStmt->execute();
        
        // Envoyer l'email
        try {
            $emailManager = new EmailManager();
            $emailManager->sendPasswordResetEmail($email, $user['firstname'], $resetToken);
        } catch (Exception $e) {
            error_log('Erreur envoi email reset: ' . $e->getMessage());
        }
        
        // Log de sécurité
        Security::logSecurityEvent('password_reset_requested', [
            'user_id' => $user['id'],
            'email' => $email
        ]);
    }
    
    // Réponse générique
    echo json_encode([
        'success' => true,
        'message' => 'Si cet email existe dans notre système, vous recevrez un lien de réinitialisation.'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
