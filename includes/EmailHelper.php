<?php
/**
 * Email Helper Class
 * Handles all email sending through sndr.sh API
 * 
 * sndr.sh Documentation: https://sndr.sh
 * API Endpoint: https://api.sndr.sh/send
 */

class EmailHelper {
    
    private $api_key;
    private $sender_email;
    private $sender_name;
    private $api_endpoint = 'https://api.sndr.sh/v1/send';
    private $pdo;
    private $org_id;
    
    /**
     * Constructor
     * @param PDO $pdo - Database connection
     * @param int $org_id - Organization ID for fetching settings
     */
    public function __construct($pdo, $org_id = null) {
        $this->pdo = $pdo;
        $this->org_id = $org_id ?? (CURRENT_ORG_ID ?? null);
        
        // Fetch email settings from database
        $this->loadSettings();
    }
    
    /**
     * Load email settings from database
     */
    private function loadSettings() {
        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('sndr_api_key','sndr_sender_email','sndr_sender_name')");
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $this->api_key = $rows['sndr_api_key'] ?? null;
            $this->sender_email = $rows['sndr_sender_email'] ?? null;
            $this->sender_name = $rows['sndr_sender_name'] ?? 'HR Management System';
        } catch (Exception $e) {
            error_log('Error loading email settings: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if email is properly configured
     */
    public function isConfigured() {
        return !empty($this->api_key) && !empty($this->sender_email);
    }
    
    /**
     * Send email via sndr.sh API
     * 
     * @param string $to - Recipient email address
     * @param string $subject - Email subject
     * @param string $body_html - HTML email body
     * @param string $body_text - Plain text email body (optional)
     * @param array $attachments - Files to attach (optional)
     * @param array $reply_to - Reply-to address (optional)
     * 
     * @return array ['success' => bool, 'message' => string, 'response' => array]
     */
    public function send($to, $subject, $body_html, $body_text = null, $attachments = [], $reply_to = null) {

        // Check if organization has email enabled
        if ($this->org_id) {
            try {
                $stmt = $this->pdo->prepare("SELECT email_enabled FROM organizations WHERE id = ?");
                $stmt->execute([$this->org_id]);
                $row = $stmt->fetch();
                if ($row && empty($row['email_enabled'])) {
                    return [
                        'success' => false,
                        'message' => 'Email disabled for this organization',
                        'response' => null
                    ];
                }
            } catch (Exception $e) {
                error_log('Error checking org email_enabled: ' . $e->getMessage());
            }
        }

        // Validate configuration
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Email service not configured',
                'response' => null
            ];
        }
        
        // Validate email
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Invalid recipient email address',
                'response' => null
            ];
        }
        
        // Prepare request body
        $body_text = $body_text ?? strip_tags($body_html);
        
        $payload = [
            'to' => [$to],
            'from' => $this->sender_email,
            'subject' => $subject,
            'html' => $body_html,
            'text' => $body_text,
            'headers' => [
                'X-App' => 'HR-Management-System',
                'X-Org-ID' => $this->org_id
            ]
        ];
        
        // Add reply-to if provided
        if ($reply_to && filter_var($reply_to, FILTER_VALIDATE_EMAIL)) {
            $payload['reply_to'] = $reply_to;
        }
        
        // Add attachments if provided
        if (!empty($attachments)) {
            $payload['attachments'] = $attachments;
        }
        
        // Send via API
        return $this->sendViaAPI($payload);
    }
    
    /**
     * Send via sndr.sh API
     */
    private function sendViaAPI($payload) {
        try {
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->api_endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->api_key,
                    'User-Agent: HR-App/1.0'
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            
            curl_close($ch);
            
            // Log the request
            $this->logEmailRequest($payload, $http_code, $response);
            
            // Parse response
            $response_data = json_decode($response, true);
            
            if ($http_code === 200 || $http_code === 201 || $http_code === 202) {
                return [
                    'success' => true,
                    'message' => 'Email sent successfully',
                    'response' => $response_data
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send email: ' . ($response_data['message'] ?? $response),
                    'response' => $response_data
                ];
            }
            
        } catch (Exception $e) {
            error_log('Email sending error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'response' => null
            ];
        }
    }
    
    /**
     * Log email request for audit trail
     */
    private function logEmailRequest($payload, $http_code, $response) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO email_logs (organization_id, recipient, subject, status, response, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $status = ($http_code === 200 || $http_code === 201) ? 'sent' : 'failed';
            $recipients = is_array($payload['to']) ? implode(', ', $payload['to']) : $payload['to'];
            $stmt->execute([
                $this->org_id,
                $recipients,
                $payload['subject'],
                $status,
                substr($response, 0, 500) // Store first 500 chars of response
            ]);
        } catch (Exception $e) {
            error_log('Error logging email request: ' . $e->getMessage());
        }
    }
    
    /**
     * Send verification email
     */
    public function sendVerificationEmail($email, $verification_link, $org_name = 'HR System') {
        $subject = 'تأكيد بريدك الإلكتروني - Email Verification';
        
        $body_html = $this->getEmailTemplate('verification', [
            'email' => $email,
            'verification_link' => $verification_link,
            'org_name' => $org_name
        ]);
        
        return $this->send($email, $subject, $body_html);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($email, $reset_link, $username = '', $org_name = 'HR System') {
        $subject = 'إعادة تعيين كلمة المرور - Password Reset';
        
        $body_html = $this->getEmailTemplate('password_reset', [
            'email' => $email,
            'username' => $username,
            'reset_link' => $reset_link,
            'org_name' => $org_name,
            'expires_in' => '24 hours'
        ]);
        
        return $this->send($email, $subject, $body_html);
    }
    
    /**
     * Send 2FA code via email
     */
    public function send2FACode($email, $code, $username = '', $org_name = 'HR System') {
        $subject = 'رمز التحقق الثنائي - Your 2FA Code';
        
        $body_html = $this->getEmailTemplate('2fa_code', [
            'email' => $email,
            'username' => $username,
            'code' => $code,
            'org_name' => $org_name,
            'expires_in' => '10 minutes'
        ]);
        
        return $this->send($email, $subject, $body_html);
    }
    
    /**
     * Send notification email
     */
    public function sendNotification($email, $message_ar, $message_en, $action_type = 'notification', $org_name = 'HR System') {
        // Use appropriate subject based on action type
        $subject_map = [
            'leave_approved' => 'الموافقة على الإجازة - Leave Approved',
            'leave_rejected' => 'رفض الإجازة - Leave Rejected',
            'employee_approved' => 'موافقة على البيانات - Profile Approved',
            'registration_success' => 'نجاح التسجيل - Registration Successful',
            'leave_request_received' => 'تم استلام طلب الإجازة - Leave Request Received'
        ];
        
        $subject = $subject_map[$action_type] ?? 'إشعار جديد - New Notification';
        
        $body_html = $this->getEmailTemplate('notification', [
            'message_ar' => $message_ar,
            'message_en' => $message_en,
            'org_name' => $org_name,
            'action_type' => $action_type
        ]);
        
        return $this->send($email, $subject, $body_html);
    }
    
    /**
     * Send welcome email
     */
    public function sendWelcomeEmail($email, $username, $full_name = '', $org_name = 'HR System', $login_url = '') {
        $subject = 'مرحباً بك - Welcome to HR System';
        
        $body_html = $this->getEmailTemplate('welcome', [
            'email' => $email,
            'username' => $username,
            'full_name' => $full_name,
            'org_name' => $org_name,
            'login_url' => $login_url ?: BASE_URL . 'auth/login.php'
        ]);
        
        return $this->send($email, $subject, $body_html);
    }
    
    /**
     * Get email template with substitution
     */
    private function getEmailTemplate($template_type, $variables = []) {
        $templates = [
            'verification' => $this->getVerificationTemplate($variables),
            'password_reset' => $this->getPasswordResetTemplate($variables),
            '2fa_code' => $this->get2FATemplate($variables),
            'notification' => $this->getNotificationTemplate($variables),
            'welcome' => $this->getWelcomeTemplate($variables),
        ];
        
        return $templates[$template_type] ?? '<p>Email template not found</p>';
    }
    
    private function getVerificationTemplate($vars) {
        return "
        <div style='font-family: Arial, sans-serif; direction: rtl; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; text-align: center; color: white;'>
                <h1>تأكيد البريد الإلكتروني</h1>
                <p>Email Verification</p>
            </div>
            <div style='padding: 30px; background: #f8f9fa;'>
                <p>مرحباً،</p>
                <p>شكراً لتسجيلك في {$vars['org_name']}</p>
                <p>يرجى النقر على الرابط أدناه لتأكيد بريدك الإلكتروني:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$vars['verification_link']}' style='background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>تأكيد البريد الإلكتروني</a>
                </p>
                <p style='color: #666; font-size: 12px;'>
                    أو انسخ الرابط التالي في متصفحك:<br>
                    <code style='background: #e9ecef; padding: 5px; display: inline-block;'>{$vars['verification_link']}</code>
                </p>
                <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
                <p style='color: #999; font-size: 12px;'>
                    هذا البريد الإلكتروني تم إرساله إلى {$vars['email']}<br>
                    إذا لم تطلب تأكيد البريد، يرجى حذف هذا البريد.
                </p>
            </div>
        </div>";
    }
    
    private function getPasswordResetTemplate($vars) {
        return "
        <div style='font-family: Arial, sans-serif; direction: rtl; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 20px; text-align: center; color: white;'>
                <h1>إعادة تعيين كلمة المرور</h1>
                <p>Password Reset Request</p>
            </div>
            <div style='padding: 30px; background: #f8f9fa;'>
                <p>مرحباً {$vars['username']},</p>
                <p>تم تلقي طلب لإعادة تعيين كلمة مرورك</p>
                <p>يرجى النقر على الرابط أدناه لإنشاء كلمة مرور جديدة:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$vars['reset_link']}' style='background: #f5576c; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>إعادة تعيين كلمة المرور</a>
                </p>
                <p style='background: #fff3cd; padding: 10px; border-radius: 5px; color: #856404;'>
                    ⚠️ ينتهي صلاحية هذا الرابط خلال {$vars['expires_in']}
                </p>
                <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
                <p style='color: #999; font-size: 12px;'>
                    إذا لم تطلب إعادة تعيين كلمة المرور، يرجى تجاهل هذا البريد.
                </p>
            </div>
        </div>";
    }
    
    private function get2FATemplate($vars) {
        return "
        <div style='font-family: Arial, sans-serif; direction: rtl; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); padding: 20px; text-align: center; color: white;'>
                <h1>رمز التحقق الثنائي</h1>
                <p>Your 2FA Code</p>
            </div>
            <div style='padding: 30px; background: #f8f9fa;'>
                <p>مرحباً {$vars['username']},</p>
                <p>إليك رمز التحقق الثنائي:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <div style='font-size: 32px; font-weight: bold; letter-spacing: 5px; background: white; padding: 20px; border-radius: 5px; font-family: monospace; border: 2px solid #4facfe;'>
                        {$vars['code']}
                    </div>
                </div>
                <p style='background: #fff3cd; padding: 10px; border-radius: 5px; color: #856404;'>
                    ⏱️ ينتهي صلاحية هذا الرمز خلال {$vars['expires_in']}
                </p>
                <p style='color: #999; font-size: 12px;'>
                    لا تشارك هذا الرمز مع أي شخص.
                </p>
            </div>
        </div>";
    }
    
    private function getNotificationTemplate($vars) {
        return "
        <div style='font-family: Arial, sans-serif; direction: rtl; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; text-align: center; color: white;'>
                <h1>إشعار جديد</h1>
                <p>New Notification</p>
            </div>
            <div style='padding: 30px; background: #f8f9fa;'>
                <p>{$vars['message_ar']}</p>
                <p style='color: #666; font-size: 12px; margin-top: 30px;'>{$vars['message_en']}</p>
                <div style='text-align: center; margin-top: 30px;'>
                    <a href='" . BASE_URL . "' style='background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>الذهاب إلى النظام</a>
                </div>
            </div>
        </div>";
    }
    
    private function getWelcomeTemplate($vars) {
        return "
        <div style='font-family: Arial, sans-serif; direction: rtl; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); padding: 20px; text-align: center; color: white;'>
                <h1>مرحباً بك!</h1>
                <p>Welcome to {$vars['org_name']}</p>
            </div>
            <div style='padding: 30px; background: #f8f9fa;'>
                <p>مرحباً {$vars['full_name']},</p>
                <p>تم تفعيل حسابك بنجاح في {$vars['org_name']}</p>
                <p>بيانات حسابك:</p>
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr style='background: white;'>
                        <td style='padding: 10px; border: 1px solid #ddd;'>اسم المستخدم:</td>
                        <td style='padding: 10px; border: 1px solid #ddd;'>{$vars['username']}</td>
                    </tr>
                    <tr style='background: #f9f9f9;'>
                        <td style='padding: 10px; border: 1px solid #ddd;'>البريد الإلكتروني:</td>
                        <td style='padding: 10px; border: 1px solid #ddd;'>{$vars['email']}</td>
                    </tr>
                </table>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$vars['login_url']}' style='background: #38ef7d; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>تسجيل الدخول الآن</a>
                </p>
            </div>
        </div>";
    }
}

// Helper function for quick email sending
function sendEmail($to, $subject, $body_html, $body_text = null, $attachments = []) {
    try {
        global $pdo;
        $email_helper = new EmailHelper($pdo, CURRENT_ORG_ID);
        return $email_helper->send($to, $subject, $body_html, $body_text, $attachments);
    } catch (Exception $e) {
        error_log('Error sending email: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'response' => null
        ];
    }
}

?>
