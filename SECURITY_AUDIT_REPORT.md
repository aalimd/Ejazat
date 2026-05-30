# HR-App Security Audit Report
**Date:** May 25, 2026  
**Status:** ⚠️ CRITICAL ISSUES FOUND - Requires Immediate Attention

---

## Executive Summary
The HR-App contains **several critical security vulnerabilities** that must be addressed before production deployment. The most severe issues include hardcoded database credentials, missing CSRF protection, and debug mode enabled in production settings.

---

## 🔴 CRITICAL ISSUES (Must Fix Immediately)

### 1. **Hardcoded Database Credentials in Production**
**File:** `includes/config.php` (Lines 22-27)  
**Severity:** CRITICAL  
**Issue:**
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u331306605_ejazat');
define('DB_USER', 'u331306605_ejazatuser');
define('DB_PASS', 'Az@99668');  // ❌ EXPOSED!
```

**Risks:**
- Database credentials are hardcoded and visible in version control
- If code is leaked, attackers have direct database access
- Credentials exposed to anyone with server access

**Fix:**
Use environment variables or a `.env` file (not in version control):
```php
define('DB_HOST', getenv('DB_HOST'));
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
```

---

### 2. **Missing CSRF Token Protection**
**Files:** ALL form submissions (employees/add.php, admin/departments.php, admin/users.php, leaves/manage.php, etc.)  
**Severity:** CRITICAL  
**Issue:** No CSRF tokens in any POST forms

**Risks:**
- Users can be tricked into submitting forms via CSRF attacks
- Attackers can approve/reject leave requests on behalf of admins
- Unauthorized user creations possible

**Fix:** Implement CSRF token protection:
```php
// In form
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

// In config.php - add CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// In POST handlers
if (!hash_equals($_POST['csrf_token'] ?? '', $_SESSION['csrf_token'])) {
    die('CSRF token mismatch');
}
```

---

### 3. **Debug Mode Enabled in Production**
**File:** `includes/config.php` (Lines 10-12)  
**Severity:** CRITICAL  
**Issue:**
```php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
```

**Risks:**
- Stack traces expose system architecture and file paths
- Database error messages reveal table/column names
- Potential source code disclosure

**Fix:**
```php
// Production environment
if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    // Production - disable error display
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
    // Log errors to file instead
    ini_set('log_errors', 1);
    ini_set('error_log', '/path/to/logs/error.log');
}
```

---

### 4. **Test File in Production**
**File:** `test_role.php`  
**Severity:** CRITICAL  
**Issue:** Contains testing code that bypasses authentication:
```php
session_start();
$_SESSION['user_id'] = 5;
$_SESSION['role'] = 'super_admin';
```

**Risks:**
- Anyone can access this file to become a super_admin
- Complete system compromise possible

**Fix:** Delete this file immediately from production. Use proper testing frameworks instead.

---

## 🟠 HIGH SEVERITY ISSUES

### 5. **Missing Password Strength Validation**
**Files:** auth/register.php, auth/login.php, employees/add.php, admin/users.php  
**Severity:** HIGH  
**Issue:** Passwords accepted with no validation
```php
$password = $_POST['password'] ?? '';
// Directly hashed without validation
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
```

**Risks:**
- Users can set weak passwords (single character, all numbers, etc.)
- Dictionary attacks easier
- Compliance violations (most standards require min 8 chars, uppercase, lowercase, etc.)

**Fix:**
```php
function validatePassword($password) {
    if (strlen($password) < 12) {
        return "Password must be at least 12 characters";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must contain uppercase letter";
    }
    if (!preg_match('/[a-z]/', $password)) {
        return "Password must contain lowercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        return "Password must contain number";
    }
    if (!preg_match('/[!@#$%^&*]/', $password)) {
        return "Password must contain special character";
    }
    return null;
}

$error = validatePassword($_POST['password']);
if ($error) {
    die($error);
}
```

---

### 6. **No Login Rate Limiting - Brute Force Vulnerability**
**File:** auth/login.php  
**Severity:** HIGH  
**Issue:** No protection against repeated login attempts

**Risks:**
- Attackers can brute force passwords
- No delay between attempts
- No account lockout mechanism

**Fix:**
```php
// In config.php
function checkLoginAttempts($username) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT failed_attempts, last_attempt FROM login_attempts WHERE username = ? AND last_attempt > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$username]);
    $attempt = $stmt->fetch();
    
    if ($attempt && $attempt['failed_attempts'] >= 5) {
        return "Account locked. Try again in 15 minutes.";
    }
    return null;
}

function recordFailedLogin($username) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO login_attempts (username, failed_attempts, last_attempt) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE failed_attempts = failed_attempts + 1, last_attempt = NOW()");
    $stmt->execute([$username]);
}

// In login.php
$error = checkLoginAttempts($username);
if (!$error && !password_verify($password, $user['password'])) {
    recordFailedLogin($username);
    $error = 'Invalid credentials';
}
```

---

### 7. **No Email Verification on Registration**
**File:** auth/register.php  
**Severity:** HIGH  
**Issue:** Users can register with any email address without verification

**Risks:**
- Fake/invalid email addresses in system
- Notifications sent to non-existent addresses
- No way to verify user identity

**Fix:** Add email verification:
```php
// Generate verification token
$verification_token = bin2hex(random_bytes(32));
$stmt = $pdo->prepare("INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
$stmt->execute([$user_id, $verification_token]);

// Send verification email
// Create verify_email.php to validate token
```

---

### 8. **No Password Reset Functionality**
**Severity:** HIGH  
**Issue:** No way for users to reset forgotten passwords

**Risks:**
- Users locked out of their accounts
- No recovery mechanism
- Support burden increases

**Fix:** Implement password reset:
1. Create `auth/forgot_password.php`
2. Send password reset email with token
3. Create `auth/reset_password.php` to validate token and set new password

---

### 9. **No Session Timeout**
**File:** includes/config.php  
**Severity:** HIGH  
**Issue:** Sessions have no idle timeout

**Risks:**
- Unattended sessions remain active indefinitely
- If user leaves computer, others can access their account
- Compliance violations

**Fix:**
```php
// Add to config.php after session_start()
$timeout = 30 * 60; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_destroy();
    header("Location: " . BASE_URL . "auth/login.php?expired=1");
    exit();
}
$_SESSION['last_activity'] = time();
```

---

## 🟡 MEDIUM SEVERITY ISSUES

### 10. **Insufficient Input Validation on File URLs**
**File:** leaves/my_requests.php (Line ~80)  
**Severity:** MEDIUM  
**Issue:** `attachment_url` field accepts any URL without validation
```php
$attachment_url = trim($_POST['attachment_url'] ?? '');
// No validation - directly stored in database
$stmt->execute([..., $attachment_url, ...]);
```

**Risks:**
- Malicious URLs (phishing, malware)
- No validation that URL is actually accessible
- Broken links in reports

**Fix:**
```php
function validateURL($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return "Invalid URL format";
    }
    $parsed = parse_url($url);
    // Only allow http/https
    if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
        return "Only HTTP/HTTPS URLs allowed";
    }
    // Whitelist allowed domains
    $allowed_domains = ['cloudinary.com', 'imgur.com', 'drive.google.com'];
    $host = $parsed['host'] ?? '';
    $is_allowed = false;
    foreach ($allowed_domains as $domain) {
        if (strpos($host, $domain) !== false) {
            $is_allowed = true;
            break;
        }
    }
    if (!$is_allowed) {
        return "URL domain not allowed";
    }
    return null;
}
```

---

### 11. **Potential XSS in Dynamic Content**
**Files:** Multiple files use `h()` function inconsistently  
**Severity:** MEDIUM  
**Issue:** While `h()` function exists, not all user input is escaped:
```php
// In includes/header.php - uses variable in CSS
$primary_hex = getSetting('primary_color', '#0d6efd');
// Not escaped in CSS context
```

**Risks:**
- CSS injection possible
- JavaScript injection in HTML attributes

**Fix:** Create context-specific escaping functions:
```php
function escapeCSS($string) {
    return preg_replace('/[^a-f0-9#]/i', '', $string);
}

function escapeJS($string) {
    return json_encode($string);
}

function escapeHTML($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Use context-appropriate function
$primary_hex = escapeCSS(getSetting('primary_color', '#0d6efd'));
```

---

### 12. **Insufficient Activity Logging**
**File:** includes/config.php (`logActivity()` function)  
**Severity:** MEDIUM  
**Issue:** Not all security-critical operations are logged:
- Failed password changes
- Permission changes
- Data exports
- Sensitive field modifications

**Risks:**
- Audit trail incomplete
- Compliance violations
- Can't trace who made unauthorized changes

**Fix:** Add logging for:
- All data modifications (add/edit/delete)
- Permission changes
- Failed operations
- Sensitive field access

---

### 13. **Missing Two-Factor Authentication Enforcement**
**File:** includes/config.php  
**Severity:** MEDIUM  
**Issue:** 2FA is optional, not enforced for admin accounts

**Risks:**
- Admin accounts vulnerable to password compromise
- Weak passwords still possible
- Compliance violations

**Fix:**
```php
// Require 2FA for admin/super_admin
if (in_array($_SESSION['role'], ['admin', 'super_admin']) && !$_SESSION['2fa_verified']) {
    header("Location: " . BASE_URL . "auth/verify_2fa.php?required=1");
    exit();
}
```

---

### 14. **Missing Data Export Security**
**Files:** employees/list.php, leaves/manage.php  
**Severity:** MEDIUM  
**Issue:** Data export to CSV has no rate limiting or export logging
```php
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Direct export without logging
```

**Risks:**
- Data exfiltration possible
- No audit trail of bulk exports
- Compliance violations

**Fix:** Add export logging and rate limiting

---

### 15. **Weak Slug Validation for Organizations**
**File:** admin/organizations.php (Line 16)  
**Severity:** MEDIUM  
**Issue:** Slug validation insufficient:
```php
if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
```

**Risks:**
- Possible to create duplicate slugs with different case
- SQL injection through slug (though parameterized)

**Fix:**
```php
if (!preg_match('/^[a-z0-9\-]+$/', $slug) || strlen($slug) < 3 || strlen($slug) > 50) {
    $error = 'Invalid slug';
}
// Check for duplicates
$stmt = $pdo->prepare("SELECT id FROM organizations WHERE LOWER(slug) = LOWER(?)");
$stmt->execute([$slug]);
if ($stmt->fetch()) {
    $error = 'Slug already exists';
}
```

---

## 🔵 LOW SEVERITY ISSUES

### 16. **Cloudinary Upload Configuration**
**File:** leaves/my_requests.php (Line 346)  
**Severity:** LOW  
**Issue:** Uses `ml_default` upload preset
```javascript
uploadPreset: 'ml_default',
```

**Risks:**
- Default preset may have lax validation
- Need to verify file type restrictions

**Fix:** Create a custom upload preset with:
- File type restrictions (PDF, images only)
- File size limits
- Folder organization

---

### 17. **Missing Rate Limiting on API Operations**
**Severity:** LOW  
**Issue:** No rate limiting on general operations (form submissions, data queries)

**Fix:** Implement rate limiting for:
- Form submissions (1 per second per user)
- Export operations (1 per minute per user)
- Password changes (1 per day per user)

---

### 18. **Missing Content Security Policy (CSP)**
**Severity:** LOW  
**Issue:** No CSP headers to prevent injection attacks

**Fix:** Add to header.php:
```php
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net upload-widget.cloudinary.com; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com; font-src fonts.gstatic.com");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
```

---

## 📋 MISSING FUNCTIONALITY

### 19. **No Account Deactivation/Deletion**
Users cannot delete their accounts or request data deletion (GDPR compliance issue)

**Fix:** Add account deactivation and data deletion options

---

### 20. **No API Key Management**
No way to manage API keys for programmatic access

**Fix:** Implement API key generation and revocation

---

### 21. **No Audit Trail for Database Changes**
No history of who changed what data when

**Fix:** Implement trigger-based audit logging for all tables

---

## 🔧 QUICK FIX PRIORITY LIST

### Immediate (Within 24 hours):
1. ✅ Delete `test_role.php`
2. ✅ Move database credentials to `.env` file
3. ✅ Disable debug mode in production
4. ✅ Add CSRF token protection to all forms
5. ✅ Implement password strength validation
6. ✅ Add login rate limiting

### Short-term (Within 1 week):
7. ✅ Add email verification
8. ✅ Add session timeout
9. ✅ Implement password reset functionality
10. ✅ Add CSP and security headers
11. ✅ Enforce 2FA for admins

### Medium-term (Within 1 month):
12. ✅ Add comprehensive audit logging
13. ✅ Add data export logging
14. ✅ Implement rate limiting
15. ✅ Add context-specific input validation

---

## 📊 VULNERABILITY SUMMARY

| Severity | Count | Status |
|----------|-------|--------|
| 🔴 Critical | 4 | ⚠️ NEEDS FIX |
| 🟠 High | 5 | ⚠️ NEEDS FIX |
| 🟡 Medium | 8 | ⚠️ NEEDS FIX |
| 🔵 Low | 4 | ℹ️ SHOULD FIX |

**Total Issues: 21**

---

## ✅ POSITIVE FINDINGS

The following good security practices are already implemented:
1. ✅ Using PDO prepared statements (SQL injection prevention)
2. ✅ Password hashing with `password_hash()` (not MD5/SHA1)
3. ✅ `h()` function for XSS protection in most places
4. ✅ Organization-based multi-tenancy isolation
5. ✅ Activity logging implemented
6. ✅ Two-factor authentication (TOTP) available
7. ✅ Proper authentication checks (`checkAuth()` function)
8. ✅ Role-based access control
9. ✅ Email field validation for basic format

---

## 📚 RECOMMENDED READING

- OWASP Top 10: https://owasp.org/www-project-top-ten/
- OWASP PHP Security: https://cheatsheetseries.owasp.org/cheatsheets/PHP_Security_Cheat_Sheet.html
- CWE/SANS Top 25: https://cwe.mitre.org/top25/

---

**Report Generated:** May 25, 2026  
**Next Review:** After critical issues fixed
