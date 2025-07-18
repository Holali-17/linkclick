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

    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les suggestions d'amis (utilisateurs qui ne sont pas déjà amis)
    $query = "SELECT 
                u.id,
                u.firstname,
                u.lastname,
                CONCAT(u.firstname, ' ', u.lastname) as name,
                u.avatar,
                u.bio,
                u.created_at
              FROM users u
              WHERE u.id != ?
              AND u.is_active = 1
              AND u.id NOT IN (
                  SELECT CASE 
                      WHEN requester_id = ? THEN addressee_id
                      ELSE requester_id
                  END
                  FROM friendships 
                  WHERE (requester_id = ? OR addressee_id = ?)
                  AND status IN ('accepted', 'pending')
              )
              ORDER BY u.created_at DESC
              LIMIT 20";

    $stmt = $db->prepare($query);
    $stmt->execute([$currentUserId, $currentUserId, $currentUserId, $currentUserId]);

    $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ajouter des informations supplémentaires
    foreach ($suggestions as &$suggestion) {
        $suggestion['status'] = 'Nouveau sur LinkClick';

        // Calculer les amis en commun (simulation basique)
        $mutualQuery = "SELECT COUNT(*) as mutual_count
                        FROM friendships f1
                        JOIN friendships f2 ON (
                            (f1.requester_id = f2.requester_id OR f1.requester_id = f2.addressee_id OR 
                             f1.addressee_id = f2.requester_id OR f1.addressee_id = f2.addressee_id)
                            AND f1.id != f2.id
                        )
                        WHERE ((f1.requester_id = ? OR f1.addressee_id = ?) AND f1.status = 'accepted')
                        AND ((f2.requester_id = ? OR f2.addressee_id = ?) AND f2.status = 'accepted')";

        $mutualStmt = $db->prepare($mutualQuery);
        $mutualStmt->execute([$currentUserId, $currentUserId, $suggestion['id'], $suggestion['id']]);

        $mutualCount = $mutualStmt->fetch(PDO::FETCH_ASSOC)['mutual_count'] ?? 0;
        $suggestion['mutual_friends'] = $mutualCount;

        if ($mutualCount > 0) {
            $suggestion['status'] = $mutualCount . ' ami(s) en commun';
        }
    }

    echo json_encode([
        'success' => true,
        'suggestions' => $suggestions,
        'total' => count($suggestions)
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
