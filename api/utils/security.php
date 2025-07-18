<?php
// Fonctions de sécurité pour LinkClick

class Security {
    
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validatePassword($password) {
        // Au moins 8 caractères, une majuscule, une minuscule, un chiffre
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password);
    }
    
    public static function validateName($name) {
        return preg_match('/^[a-zA-ZÀ-ÿ\s\-\']{2,50}$/', $name);
    }
    
    public static function rateLimitCheck($identifier, $maxAttempts = 5, $timeWindow = 300) {
        $cacheFile = sys_get_temp_dir() . '/linkclick_ratelimit_' . md5($identifier);
        
        $attempts = [];
        if (file_exists($cacheFile)) {
            $attempts = json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        
        // Nettoyer les anciennes tentatives
        $currentTime = time();
        $attempts = array_filter($attempts, function($timestamp) use ($currentTime, $timeWindow) {
            return ($currentTime - $timestamp) < $timeWindow;
        });
        
        // Vérifier si la limite est atteinte
        if (count($attempts) >= $maxAttempts) {
            return false;
        }
        
        // Ajouter la tentative actuelle
        $attempts[] = $currentTime;
        file_put_contents($cacheFile, json_encode($attempts));
        
        return true;
    }
    
    public static function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    public static function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = self::generateToken();
        $_SESSION['csrf_token'] = $token;
        
        return $token;
    }
    
    public static function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function logSecurityEvent($event, $details = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => self::getClientIP(),
            'user_agent' => self::getUserAgent(),
            'details' => $details
        ];
        
        $logFile = '../logs/security.log';
        $logDir = dirname($logFile);
        
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    public static function checkSQLInjection($input) {
        $patterns = [
            '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION|SCRIPT)\b)/i',
            '/(\b(OR|AND)\s+\d+\s*=\s*\d+)/i',
            '/(\'|\"|;|--|\*|\/\*|\*\/)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    public static function checkXSS($input) {
        $patterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    public static function validateInput($input, $type = 'string', $options = []) {
        // Nettoyer l'input
        $input = self::sanitizeInput($input);
        
        // Vérifier les injections
        if (self::checkSQLInjection($input) || self::checkXSS($input)) {
            throw new Exception('Input potentiellement malveillant détecté');
        }
        
        // Validation selon le type
        switch ($type) {
            case 'email':
                if (!self::validateEmail($input)) {
                    throw new Exception('Email invalide');
                }
                break;
                
            case 'password':
                if (!self::validatePassword($input)) {
                    throw new Exception('Mot de passe invalide (min 8 caractères, 1 majuscule, 1 minuscule, 1 chiffre)');
                }
                break;
                
            case 'name':
                if (!self::validateName($input)) {
                    throw new Exception('Nom invalide');
                }
                break;
                
            case 'text':
                $maxLength = $options['max_length'] ?? 1000;
                if (strlen($input) > $maxLength) {
                    throw new Exception('Texte trop long (max ' . $maxLength . ' caractères)');
                }
                break;
                
            case 'int':
                if (!filter_var($input, FILTER_VALIDATE_INT)) {
                    throw new Exception('Nombre entier invalide');
                }
                break;
        }
        
        return $input;
    }
    
    public static function requireAuth() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token d\'authentification requis']);
            exit;
        }
        
        $token = $matches[1];
        $payload = JWT::validateToken($token);
        
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token invalide ou expiré']);
            exit;
        }
        
        return $payload;
    }
    
    public static function requireRole($requiredRole, $userPayload) {
        $roleHierarchy = ['user' => 1, 'moderator' => 2, 'admin' => 3];
        
        $userRoleLevel = $roleHierarchy[$userPayload['role']] ?? 0;
        $requiredRoleLevel = $roleHierarchy[$requiredRole] ?? 999;
        
        if ($userRoleLevel < $requiredRoleLevel) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Permissions insuffisantes']);
            exit;
        }
        
        return true;
    }
}
?>
