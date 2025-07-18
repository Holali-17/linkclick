<?php
// Gestion des emails pour LinkClick

class EmailManager {
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        $this->smtp_host = SMTP_HOST;
        $this->smtp_port = SMTP_PORT;
        $this->smtp_username = SMTP_USERNAME;
        $this->smtp_password = SMTP_PASSWORD;
        $this->from_email = FROM_EMAIL;
        $this->from_name = FROM_NAME;
    }
    
    public function sendEmail($to, $subject, $htmlBody, $textBody = '') {
        // Headers
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->from_name . ' <' . $this->from_email . '>',
            'Reply-To: ' . $this->from_email,
            'X-Mailer: LinkClick Mailer'
        ];
        
        // Utiliser mail() pour la simplicité (en production, utiliser PHPMailer)
        return mail($to, $subject, $htmlBody, implode("\r\n", $headers));
    }
    
    public function sendWelcomeEmail($email, $firstname, $verificationToken) {
        $subject = "Bienvenue sur LinkClick !";
        $verificationUrl = "http://localhost/linkclick/api/auth/verify-email.php?token=" . $verificationToken;
        
        $htmlBody = $this->getWelcomeTemplate($firstname, $verificationUrl);
        
        return $this->sendEmail($email, $subject, $htmlBody);
    }
    
    public function sendPasswordResetEmail($email, $firstname, $resetToken) {
        $subject = "Réinitialisation de votre mot de passe LinkClick";
        $resetUrl = "http://localhost/linkclick/reset-password.html?token=" . $resetToken;
        
        $htmlBody = $this->getPasswordResetTemplate($firstname, $resetUrl);
        
        return $this->sendEmail($email, $subject, $htmlBody);
    }
    
    public function sendFriendRequestEmail($email, $firstname, $requesterName) {
        $subject = "Nouvelle demande d'ami sur LinkClick";
        $linkclickUrl = "http://localhost/linkclick/";
        
        $htmlBody = $this->getFriendRequestTemplate($firstname, $requesterName, $linkclickUrl);
        
        return $this->sendEmail($email, $subject, $htmlBody);
    }
    
    private function getWelcomeTemplate($firstname, $verificationUrl) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Bienvenue sur LinkClick</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #1877f2, #42b883); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: white; padding: 30px; border: 1px solid #ddd; }
                .button { display: inline-block; background: #1877f2; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; border-radius: 0 0 10px 10px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🔗 Bienvenue sur LinkClick !</h1>
                    <p>Votre nouveau réseau social vous attend</p>
                </div>
                <div class="content">
                    <h2>Bonjour ' . htmlspecialchars($firstname) . ' !</h2>
                    <p>Nous sommes ravis de vous accueillir dans la communauté LinkClick. Votre compte a été créé avec succès !</p>
                    
                    <p>Pour commencer à utiliser votre compte, veuillez confirmer votre adresse email en cliquant sur le bouton ci-dessous :</p>
                    
                    <div style="text-align: center;">
                        <a href="' . $verificationUrl . '" class="button">Confirmer mon email</a>
                    </div>
                    
                    <p>Une fois votre email confirmé, vous pourrez :</p>
                    <ul>
                        <li>📝 Publier vos premiers posts</li>
                        <li>👥 Ajouter des amis</li>
                        <li>💬 Discuter en temps réel</li>
                        <li>❤️ Interagir avec la communauté</li>
                    </ul>
                    
                    <p>Si vous n\'avez pas créé ce compte, vous pouvez ignorer cet email.</p>
                </div>
                <div class="footer">
                    <p>© 2024 LinkClick - Votre réseau social de confiance</p>
                    <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    private function getPasswordResetTemplate($firstname, $resetUrl) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Réinitialisation mot de passe - LinkClick</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: white; padding: 30px; border: 1px solid #ddd; }
                .button { display: inline-block; background: #e74c3c; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; border-radius: 0 0 10px 10px; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🔒 Réinitialisation de mot de passe</h1>
                    <p>LinkClick - Sécurité de votre compte</p>
                </div>
                <div class="content">
                    <h2>Bonjour ' . htmlspecialchars($firstname) . ',</h2>
                    <p>Vous avez demandé la réinitialisation de votre mot de passe LinkClick.</p>
                    
                    <p>Pour créer un nouveau mot de passe, cliquez sur le bouton ci-dessous :</p>
                    
                    <div style="text-align: center;">
                        <a href="' . $resetUrl . '" class="button">Réinitialiser mon mot de passe</a>
                    </div>
                    
                    <div class="warning">
                        <strong>⚠️ Important :</strong>
                        <ul>
                            <li>Ce lien est valide pendant 1 heure seulement</li>
                            <li>Si vous n\'avez pas demandé cette réinitialisation, ignorez cet email</li>
                            <li>Votre mot de passe actuel reste inchangé tant que vous ne créez pas un nouveau</li>
                        </ul>
                    </div>
                    
                    <p>Pour votre sécurité, assurez-vous de choisir un mot de passe fort contenant :</p>
                    <ul>
                        <li>Au moins 8 caractères</li>
                        <li>Des lettres majuscules et minuscules</li>
                        <li>Des chiffres</li>
                        <li>Des caractères spéciaux</li>
                    </ul>
                </div>
                <div class="footer">
                    <p>© 2024 LinkClick - Votre réseau social de confiance</p>
                    <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    private function getFriendRequestTemplate($firstname, $requesterName, $linkclickUrl) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Nouvelle demande d\'ami - LinkClick</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #42b883, #369870); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: white; padding: 30px; border: 1px solid #ddd; }
                .button { display: inline-block; background: #42b883; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; border-radius: 0 0 10px 10px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>👥 Nouvelle demande d\'ami !</h1>
                    <p>Quelqu\'un souhaite vous ajouter sur LinkClick</p>
                </div>
                <div class="content">
                    <h2>Bonjour ' . htmlspecialchars($firstname) . ' !</h2>
                    <p><strong>' . htmlspecialchars($requesterName) . '</strong> souhaite devenir votre ami sur LinkClick.</p>
                    
                    <p>Connectez-vous à votre compte pour accepter ou refuser cette demande d\'amitié.</p>
                    
                    <div style="text-align: center;">
                        <a href="' . $linkclickUrl . '" class="button">Voir la demande</a>
                    </div>
                    
                    <p>Une fois connectés, vous pourrez :</p>
                    <ul>
                        <li>💬 Discuter ensemble</li>
                        <li>👀 Voir vos publications respectives</li>
                        <li>❤️ Interagir avec vos contenus</li>
                        <li>🔔 Recevoir des notifications</li>
                    </ul>
                </div>
                <div class="footer">
                    <p>© 2024 LinkClick - Votre réseau social de confiance</p>
                    <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                </div>
            </div>
        </body>
        </html>';
    }
}
?>
