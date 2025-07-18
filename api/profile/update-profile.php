<?php
require_once '../config/database.php';
require_once '../utils/jwt.php';
require_once '../utils/security.php';
require_once '../utils/upload.php';
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
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Récupérer les données actuelles
    $currentQuery = "SELECT firstname, lastname, bio, avatar FROM users WHERE id = :user_id";
    $currentStmt = $db->prepare($currentQuery);
    $currentStmt->bindParam(':user_id', $currentUserId);
    $currentStmt->execute();
    $currentData = $currentStmt->fetch();
    
    if (!$currentData) {
        throw new Exception('Utilisateur non trouvé');
    }
    
    // Préparer les données à mettre à jour
    $updateFields = [];
    $updateValues = [];
    
    // Prénom
    if (isset($_POST['firstname']) && !empty($_POST['firstname'])) {
        $firstname = Security::validateInput($_POST['firstname'], 'name');
        $updateFields[] = "firstname = :firstname";
        $updateValues[':firstname'] = $firstname;
    }
    
    // Nom
    if (isset($_POST['lastname']) && !empty($_POST['lastname'])) {
        $lastname = Security::validateInput($_POST['lastname'], 'name');
        $updateFields[] = "lastname = :lastname";
        $updateValues[':lastname'] = $lastname;
    }
    
    // Bio
    if (isset($_POST['bio'])) {
        $bio = Security::validateInput($_POST['bio'], 'text', ['max_length' => 500]);
        $updateFields[] = "bio = :bio";
        $updateValues[':bio'] = $bio;
    }
    
    // Gestion de l'avatar
    $newAvatar = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadManager = new UploadManager();
        $newAvatar = $uploadManager->uploadProfileImage($_FILES['avatar'], $currentUserId);
        
        // Supprimer l'ancien avatar s'il existe
        if ($currentData['avatar']) {
            $uploadManager->deleteFile($currentData['avatar']);
        }
        
        $updateFields[] = "avatar = :avatar";
        $updateValues[':avatar'] = $newAvatar;
    }
    
    // Vérifier s'il y a des champs à mettre à jour
    if (empty($updateFields)) {
        throw new Exception('Aucune donnée à mettre à jour');
    }
    
    // Construire et exécuter la requête de mise à jour
    $updateQuery = "UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = :user_id";
    $updateValues[':user_id'] = $currentUserId;
    
    $updateStmt = $db->prepare($updateQuery);
    
    foreach ($updateValues as $key => $value) {
        $updateStmt->bindValue($key, $value);
    }
    
    if (!$updateStmt->execute()) {
        throw new Exception('Erreur lors de la mise à jour du profil');
    }
    
    // Récupérer les données mises à jour
    $updatedQuery = "SELECT id, firstname, lastname, email, avatar, bio, updated_at FROM users WHERE id = :user_id";
    $updatedStmt = $db->prepare($updatedQuery);
    $updatedStmt->bindParam(':user_id', $currentUserId);
    $updatedStmt->execute();
    
    $updatedProfile = $updatedStmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'Profil mis à jour avec succès',
        'profile' => $updatedProfile
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
