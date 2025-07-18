<?php
// Gestion des JWT tokens pour LinkClick

class JWT {
    private static $secret;
    private static $algorithm = 'HS256';
    
    public static function init() {
        self::$secret = JWT_SECRET;
    }
    
    public static function encode($payload) {
        self::init();
        
        $header = json_encode(['typ' => 'JWT', 'alg' => self::$algorithm]);
        $payload = json_encode($payload);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, self::$secret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    public static function decode($jwt) {
        self::init();
        
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new Exception('Token invalide');
        }
        
        list($base64Header, $base64Payload, $base64Signature) = $parts;
        
        $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Header)), true);
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Payload)), true);
        
        if (!$header || !$payload) {
            throw new Exception('Token invalide');
        }
        
        // Vérifier la signature
        $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Signature));
        $expectedSignature = hash_hmac('sha256', $base64Header . "." . $base64Payload, self::$secret, true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            throw new Exception('Signature invalide');
        }
        
        // Vérifier l'expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new Exception('Token expiré');
        }
        
        return $payload;
    }
    
    public static function createToken($userId, $email, $role = 'user') {
        $payload = [
            'user_id' => $userId,
            'email' => $email,
            'role' => $role,
            'iat' => time(),
            'exp' => time() + JWT_EXPIRATION
        ];
        
        return self::encode($payload);
    }
    
    public static function validateToken($token) {
        try {
            return self::decode($token);
        } catch (Exception $e) {
            return false;
        }
    }
    
    public static function getUserFromToken($token) {
        $payload = self::validateToken($token);
        return $payload ? $payload : null;
    }
}
?>
