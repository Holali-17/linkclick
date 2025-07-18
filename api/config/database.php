<?php
// Configuration de la base de données LinkClick

class Database {
    private $host = "nue.domcloud.co";
    private $db_name = "linkclick_db";
    private $username = "linkclick";
    private $password = "f_O-Bc+K7z58y6mMX1";
    private $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                )
            );
        } catch(PDOException $exception) {
            error_log("Erreur de connexion: " . $exception->getMessage());
            throw new Exception("Erreur de connexion à la base de données");
        }

        return $this->conn;
    }

    public function closeConnection() {
        $this->conn = null;
    }
}

// Configuration générale
define('JWT_SECRET', 'linkclick_secret_key_2024');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRATION', 86400); // 24 heures

// Configuration email
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('FROM_EMAIL', 'noreply@linkclick.com');
define('FROM_NAME', 'LinkClick');

// Configuration upload
define('UPLOAD_PATH', '../assets/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Créer le dossier d'upload s'il n'existe pas
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
    mkdir(UPLOAD_PATH . 'profiles/', 0755, true);
    mkdir(UPLOAD_PATH . 'posts/', 0755, true);
    mkdir(UPLOAD_PATH . 'messages/', 0755, true);
}
define('API', 'localhost:8001');

?>
