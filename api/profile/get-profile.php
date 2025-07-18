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
    // Activer les erreurs PDO pour un meilleur débogage
    $database = new Database();
    $db = $database->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Authentification requise
    $userPayload = Security::requireAuth();
    $currentUserId = $userPayload['user_id'];

    // Vérifier que l'ID utilisateur est valide
    if (!is_numeric($currentUserId)) {
        throw new Exception('ID utilisateur invalide');
    }

    // Récupérer les informations du profil
    $query = "SELECT 
                u.id,
                u.firstname,
                u.lastname,
                u.email,
                u.avatar,
                u.bio,
                u.created_at,
                u.updated_at,
                (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND is_active = 1) as posts_count,
                (SELECT COUNT(*) FROM friendships 
                 WHERE (requester_id = u.id OR addressee_id = u.id) 
                 AND status = 'accepted') as friends_count,
                (SELECT COUNT(*) FROM post_reactions pr 
                 JOIN posts p ON pr.post_id = p.id 
                 WHERE p.user_id = u.id AND pr.type = 'like') as likes_received
              FROM users u
              WHERE u.id = :user_id AND u.is_active = 1";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
    $stmt->execute();

    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        throw new Exception('Profil non trouvé');
    }

    // Récupérer les posts récents de l'utilisateur
    $postsQuery = "SELECT 
                     p.id,
                     p.content,
                     p.image,
                     p.likes_count,
                     p.dislikes_count,
                     p.comments_count,
                     p.created_at
                   FROM posts p
                   WHERE p.user_id = :user_id AND p.is_active = 1
                   ORDER BY p.created_at DESC
                   LIMIT 5";

    $postsStmt = $db->prepare($postsQuery);
    $postsStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
    $postsStmt->execute();

    $profile['recent_posts'] = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer quelques amis récents (requête reformulée)
    $friendsQuery = "SELECT 
        u.id,
        u.firstname,
        u.lastname,
        u.avatar
    FROM friendships f
    JOIN users u ON (
        (f.requester_id = :user_id AND u.id = f.addressee_id) OR 
        (f.addressee_id = :user_id_2 AND u.id = f.requester_id)
    )
    WHERE f.status = 'accepted'
    AND u.is_active = 1
    ORDER BY f.updated_at DESC
    LIMIT 6";

    $friendsStmt = $db->prepare($friendsQuery);
    $friendsStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
    $friendsStmt->bindParam(':user_id_2', $currentUserId, PDO::PARAM_INT);


    $profile['recent_friends'] = $friendsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Réponse JSON
    echo json_encode([
        'success' => true,
        'profile' => $profile
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>