<?php
require_once '../config/database.php';
require_once '../utils/security.php';
require_once '../utils/email.php';

// Configuration des en-têtes CORS


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
    
    if (!$input) {
        throw new Exception('Données invalides');
    }
    
    // Valider les champs requis
    $requiredFields = ['firstname', 'lastname', 'email', 'password', 'confirm_password'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception('Tous les champs sont requis');
        }
    }
    
    // Valider les données
    $firstname = Security::validateInput($input['firstname'], 'name');
    $lastname = Security::validateInput($input['lastname'], 'name');
    $email = Security::validateInput($input['email'], 'email');
    $password = $input['password'];
    $confirmPassword = $input['confirm_password'];
    
    // Vérifier que les mots de passe correspondent
    if ($password !== $confirmPassword) {
        throw new Exception('Les mots de passe ne correspondent pas');
    }
    
    // Valider le mot de passe
    Security::validateInput($password, 'password');
    
    // Rate limiting
    $clientIP = Security::getClientIP();
    if (!Security::rateLimitCheck('register_' . $clientIP, 3, 3600)) {
        throw new Exception('Trop de tentatives d\'inscription. Réessayez dans 1 heure.');
    }
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Vérifier si l'email existe déjà
    $checkQuery = "SELECT id FROM users WHERE email = :email";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':email', $email);
    $checkStmt->execute();
    
    if ($checkStmt->fetch()) {
        throw new Exception('Un compte avec cet email existe déjà');
    }
    
    // Hasher le mot de passe
    $hashedPassword = Security::hashPassword($password);
    
    // Générer le token de vérification
    $verificationToken = Security::generateToken();
    
    // Insérer l'utilisateur
    $insertQuery = "INSERT INTO users (firstname, lastname, email, password, verification_token, created_at) 
                    VALUES (:firstname, :lastname, :email, :password, :verification_token, NOW())";
    
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindParam(':firstname', $firstname);
    $insertStmt->bindParam(':lastname', $lastname);
    $insertStmt->bindParam(':email', $email);
    $insertStmt->bindParam(':password', $hashedPassword);
    $insertStmt->bindParam(':verification_token', $verificationToken);
    
    if (!$insertStmt->execute()) {
        throw new Exception('Erreur lors de la création du compte');
    }
    
    $userId = $db->lastInsertId();
    
    // Envoyer l'email de bienvenue
    try {
        $emailManager = new EmailManager();
        $emailManager->sendWelcomeEmail($email, $firstname, $verificationToken);
    } catch (Exception $e) {
        // Log l'erreur mais ne pas faire échouer l'inscription
        error_log('Erreur envoi email: ' . $e->getMessage());
    }
    
    // Log de sécurité
    Security::logSecurityEvent('user_registration', [
        'user_id' => $userId,
        'email' => $email,
        'name' => $firstname . ' ' . $lastname
    ]);
    
    // Réponse de succès
    echo json_encode([
        'success' => true,
        'message' => 'Compte créé avec succès ! Vérifiez votre email pour activer votre compte.',
        'user_id' => $userId
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
