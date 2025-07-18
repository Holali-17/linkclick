<?php
// Gestion des uploads pour LinkClick

class UploadManager {
    private $uploadPath;
    private $maxFileSize;
    private $allowedTypes;
    
    public function __construct() {
        $this->uploadPath = UPLOAD_PATH;
        $this->maxFileSize = MAX_FILE_SIZE;
        $this->allowedTypes = ALLOWED_IMAGE_TYPES;
    }
    
    public function uploadImage($file, $folder = 'general', $prefix = '') {
        try {
            // Vérifications de base
            if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
                throw new Exception('Aucun fichier sélectionné');
            }
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erreur lors de l\'upload: ' . $this->getUploadError($file['error']));
            }
            
            // Vérifier la taille
            if ($file['size'] > $this->maxFileSize) {
                throw new Exception('Fichier trop volumineux. Taille maximale: ' . $this->formatBytes($this->maxFileSize));
            }
            
            // Vérifier le type MIME
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $this->allowedTypes)) {
                throw new Exception('Type de fichier non autorisé. Types acceptés: ' . implode(', ', $this->allowedTypes));
            }
            
            // Créer le dossier de destination s'il n'existe pas
            $destinationFolder = $this->uploadPath . $folder . '/';
            if (!file_exists($destinationFolder)) {
                mkdir($destinationFolder, 0755, true);
            }
            
            // Générer un nom de fichier unique
            $extension = $this->getExtensionFromMime($mimeType);
            $filename = $prefix . uniqid() . '_' . time() . '.' . $extension;
            $destinationPath = $destinationFolder . $filename;
            
            // Déplacer le fichier
            if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
                throw new Exception('Erreur lors de la sauvegarde du fichier');
            }
            
            // Redimensionner l'image si nécessaire
            $this->resizeImage($destinationPath, $mimeType);
            
            // Retourner le chemin relatif
            return 'assets/uploads/' . $folder . '/' . $filename;
            
        } catch (Exception $e) {
            error_log('Erreur upload: ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function uploadProfileImage($file, $userId) {
        return $this->uploadImage($file, 'profiles', 'profile_' . $userId . '_');
    }
    
    public function uploadPostImage($file, $postId) {
        return $this->uploadImage($file, 'posts', 'post_' . $postId . '_');
    }
    
    public function uploadMessageImage($file, $messageId) {
        return $this->uploadImage($file, 'messages', 'msg_' . $messageId . '_');
    }
    
    public function deleteFile($filePath) {
        $fullPath = '../' . $filePath;
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return true;
    }
    
    private function resizeImage($imagePath, $mimeType, $maxWidth = 1200, $maxHeight = 800) {
        // Créer une ressource image selon le type
        switch ($mimeType) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($imagePath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($imagePath);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($imagePath);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($imagePath);
                break;
            default:
                return; // Type non supporté pour le redimensionnement
        }
        
        if (!$source) return;
        
        // Obtenir les dimensions actuelles
        $width = imagesx($source);
        $height = imagesy($source);
        
        // Calculer les nouvelles dimensions
        if ($width <= $maxWidth && $height <= $maxHeight) {
            imagedestroy($source);
            return; // Pas besoin de redimensionner
        }
        
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = intval($width * $ratio);
        $newHeight = intval($height * $ratio);
        
        // Créer la nouvelle image
        $destination = imagecreatetruecolor($newWidth, $newHeight);
        
        // Préserver la transparence pour PNG et GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
            $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
            imagefilledrectangle($destination, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Redimensionner
        imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Sauvegarder selon le type
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($destination, $imagePath, 85);
                break;
            case 'image/png':
                imagepng($destination, $imagePath, 8);
                break;
            case 'image/gif':
                imagegif($destination, $imagePath);
                break;
            case 'image/webp':
                imagewebp($destination, $imagePath, 85);
                break;
        }
        
        // Libérer la mémoire
        imagedestroy($source);
        imagedestroy($destination);
    }
    
    private function getExtensionFromMime($mimeType) {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        
        return $extensions[$mimeType] ?? 'jpg';
    }
    
    private function getUploadError($errorCode) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée',
            UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale du formulaire',
            UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement téléchargé',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été téléchargé',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier sur le disque',
            UPLOAD_ERR_EXTENSION => 'Upload arrêté par une extension PHP'
        ];
        
        return $errors[$errorCode] ?? 'Erreur inconnue';
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    public function createThumbnail($imagePath, $width = 150, $height = 150) {
        $pathInfo = pathinfo($imagePath);
        $thumbnailPath = $pathInfo['dirname'] . '/thumb_' . $pathInfo['basename'];
        
        // Déterminer le type MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, '../' . $imagePath);
        finfo_close($finfo);
        
        // Créer la source
        switch ($mimeType) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg('../' . $imagePath);
                break;
            case 'image/png':
                $source = imagecreatefrompng('../' . $imagePath);
                break;
            case 'image/gif':
                $source = imagecreatefromgif('../' . $imagePath);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp('../' . $imagePath);
                break;
            default:
                return $imagePath; // Retourner l'original si type non supporté
        }
        
        if (!$source) return $imagePath;
        
        $originalWidth = imagesx($source);
        $originalHeight = imagesy($source);
        
        // Créer le thumbnail carré
        $thumbnail = imagecreatetruecolor($width, $height);
        
        // Préserver la transparence
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $width, $height, $transparent);
        }
        
        // Calculer les dimensions pour un crop centré
        $ratio = max($width / $originalWidth, $height / $originalHeight);
        $newWidth = intval($originalWidth * $ratio);
        $newHeight = intval($originalHeight * $ratio);
        
        $x = intval(($width - $newWidth) / 2);
        $y = intval(($height - $newHeight) / 2);
        
        imagecopyresampled($thumbnail, $source, $x, $y, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        
        // Sauvegarder
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($thumbnail, '../' . $thumbnailPath, 85);
                break;
            case 'image/png':
                imagepng($thumbnail, '../' . $thumbnailPath, 8);
                break;
            case 'image/gif':
                imagegif($thumbnail, '../' . $thumbnailPath);
                break;
            case 'image/webp':
                imagewebp($thumbnail, '../' . $thumbnailPath, 85);
                break;
        }
        
        imagedestroy($source);
        imagedestroy($thumbnail);
        
        return $thumbnailPath;
    }
}
?>
