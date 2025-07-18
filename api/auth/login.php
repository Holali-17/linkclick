<?php
// Désactiver l'affichage des erreurs en production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
include_once '../config/database.php'; // Inclure la configuration API --- IGNORE ---
// Définir les en-têtes
// Configuration des en-têtes CORS


// Gérer la requête OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    error_log("Requête OPTIONS reçue");
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../utils/jwt.php';
require_once '../utils/security.php';

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
    if (empty($input['email']) || empty($input['password'])) {
        throw new Exception('Email et mot de passe requis');
    }
    
    $email = Security::validateInput($input['email'], 'email');
    $password = $input['password'];
    
    // Rate limiting
    $clientIP = Security::getClientIP();
    if (!Security::rateLimitCheck('login_' . $clientIP, 5, 300)) {
        throw new Exception('Trop de tentatives de connexion. Réessayez dans 5 minutes.');
    }
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Rechercher l'utilisateur
    $query = "SELECT id, firstname, lastname, email, password, avatar, role, is_active, email_verified 
              FROM users 
              WHERE email = :email";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    if (!$user || !Security::verifyPassword($password, $user['password'])) {
        Security::logSecurityEvent('failed_login', ['email' => $email]);
        throw new Exception('Email ou mot de passe incorrect');
    }
    
    // Vérifier si le compte est actif
    if (!$user['is_active']) {
        throw new Exception('Compte désactivé. Contactez l\'administrateur.');
    }
    
    // Vérifier si l'email est vérifié
    if (!$user['email_verified']) {
        throw new Exception('Email non vérifié. Vérifiez votre boîte mail.');
    }
    
    // Générer le token JWT
    $token = JWT::createToken($user['id'], $user['email'], $user['role']);
    
    // Enregistrer la session
    $sessionQuery = "INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at) 
                     VALUES (:user_id, :token, :ip_address, :user_agent, :expires_at)";
    
    $sessionStmt = $db->prepare($sessionQuery);
    $sessionStmt->bindParam(':user_id', $user['id']);
    $sessionStmt->bindParam(':token', $token);
    $sessionStmt->bindParam(':ip_address', $clientIP);
    $sessionStmt->bindParam(':user_agent', Security::getUserAgent());
    $expiresAt = date('Y-m-d H:i:s', time() + JWT_EXPIRATION); // Stocker dans une variable
    $sessionStmt->bindValue(':expires_at', $expiresAt);
    $sessionStmt->execute();
    
    // Log de sécurité
    Security::logSecurityEvent('successful_login', ['user_id' => $user['id'], 'email' => $email]);
    
    // Préparer les données utilisateur (sans le mot de passe)
    $userData = [
        'id' => $user['id'],
        'firstname' => $user['firstname'],
        'lastname' => $user['lastname'],
        'email' => $user['email'],
        'avatar' => $user['avatar'],
        'role' => $user['role']
    ];
    
    // Réponse de succès
    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie',
        'token' => $token,
        'user' => $userData
    ]);
    exit; // S'assurer qu'aucune autre sortie ne suit
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit; // S'assurer qu'aucune autre sortie ne suit
}