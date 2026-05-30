# Email Configuration Guide - sndr.sh Integration

## Overview

The HR-App now supports professional email sending via [sndr.sh](https://sndr.sh), a reliable email delivery service. This guide walks you through configuring and using email features.

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Database Setup](#database-setup)
3. [Configuration Steps](#configuration-steps)
4. [Email Features](#email-features)
5. [Testing](#testing)
6. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Requirements
- sndr.sh account with valid API key
- Verified sender email domain
- HR-App installed and running
- PHP cURL extension enabled
- MySQL/MariaDB database access

### Get sndr.sh API Key
1. Visit [sndr.sh](https://sndr.sh)
2. Sign up for an account
3. Verify your domain
4. Generate an API key from your dashboard
5. Note the API key and verified sender email address

---

## Database Setup

### Step 1: Run Email Migration SQL

Execute the email setup SQL file to create required tables:

```bash
# Using MySQL command line
mysql -u root -p hr_app_db < /path/to/HR-App/database/email_setup.sql

# Or copy and paste the SQL from: database/email_setup.sql into your MySQL client
```

### Tables Created

- `email_logs` - Audit trail of all sent emails
- `email_verifications` - Email verification tokens and status
- `password_resets` - Password reset token management
- `login_attempts` - Rate limiting for failed login attempts

---

## Configuration Steps

### Step 1: Access Admin Settings

1. Log in as **Admin** user
2. Navigate to **⚙️ Settings** (Admin panel)
3. Scroll to **Email Settings** section

### Step 2: Configure Email Credentials

Fill in the following fields:

| Field | Value | Example |
|-------|-------|---------|
| **Sndr.sh API Key** | Your sndr.sh API key | `sk_test_abc123xyz...` |
| **Sender Email** | Verified domain email | `noreply@yourdomain.com` |
| **Sender Name** | Display name for emails | `HR Management System` |

### Step 3: Enable Email Features

Toggle the following settings:

- ✅ **Email Notifications** - Enable email alerts for users
- ✅ **Require Email Verification** - Users must verify email on registration

### Step 4: Save Settings

Click **💾 Save Settings** to store configuration.

---

## Email Features

### 1. Email Verification on Registration

**When:** User registers for an account  
**What:** Verification email sent with confirmation link  
**Duration:** Link valid for 24 hours  
**User Action:** Click link or copy/paste token to verify

```
Flow:
User Registration → Email Verification Sent → User Clicks Link → Email Verified → Can Login
```

#### Resend Verification Email
Users can resend verification email at: `/auth/verify_email.php`

---

### 2. Password Reset

**When:** User clicks "Forgot Password" or requests password reset  
**What:** Reset email sent with secure token link  
**Duration:** Link valid for 1 hour  
**User Action:** Click link and set new password

```
Flow:
Forgot Password → Email Sent → Click Link → New Password Form → Password Updated
```

#### Access Password Reset
- **Forgot Password:** `/auth/forgot_password.php`
- **Reset Password:** `/auth/reset_password.php?token=...` (auto-sent in email)

---

### 3. Two-Factor Authentication (2FA) Email Code

**When:** User logs in with 2FA enabled  
**What:** 6-digit verification code sent to email  
**Duration:** Code valid for 10 minutes  
**User Action:** Enter code in 2FA form

```
Flow:
Login → 2FA Enabled → Email Code Sent → User Enters Code → Session Created
```

---

### 4. Welcome Email

**When:** User registration (if email verification disabled)  
**What:** Welcome message with login instructions  
**When Sent:** Immediately after successful registration

---

### 5. Notification Emails

**When:** Various system events (leave approved, employee approved, etc.)  
**What:** Configured notification messages  
**Control:** Toggle in Settings → Email Notifications

---

## Testing

### Test Email Configuration

#### Method 1: Manual Test
```php
// Create test file: test_email.php
<?php
require_once 'includes/config.php';

$email_helper = new EmailHelper($pdo, 1); // org_id = 1

$result = $email_helper->send(
    'test@example.com',
    'Test Email',
    '<p>This is a test email from HR-App</p>'
);

echo json_encode($result);
?>
```

#### Method 2: Test via Registration
1. Navigate to `/auth/register.php`
2. Fill out registration form
3. Submit
4. Check your email for verification link
5. Click link to verify

#### Method 3: Test via Forgot Password
1. Navigate to `/auth/forgot_password.php`
2. Enter registered email
3. Check your email for reset link
4. Click link and reset password

### Check Email Logs
1. Access database
2. Query `email_logs` table:

```sql
SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 20;
```

---

## Environment Variables (Optional Security)

For enhanced security, store API key as environment variable instead of database:

### .env File (Create in root)
```
SNDR_API_KEY=sk_test_abc123xyz...
SNDR_SENDER_EMAIL=noreply@yourdomain.com
SNDR_SENDER_NAME=HR Management System
```

### Update config.php

Edit `includes/EmailHelper.php`:

```php
// Line ~20, in loadSettings()
$this->api_key = getenv('SNDR_API_KEY') ?: ($row['setting_value'] ?? null);
```

---

## Troubleshooting

### Issue: Emails Not Being Sent

**Check 1: Is Email Configured?**
```php
$email_helper = new EmailHelper($pdo, CURRENT_ORG_ID);
if (!$email_helper->isConfigured()) {
    die("Email not configured");
}
```

**Check 2: API Key Valid?**
- Log in to [sndr.sh](https://sndr.sh)
- Verify API key hasn't expired
- Check quota usage

**Check 3: Sender Email Verified?**
- Ensure sender email is verified in sndr.sh
- Check domain DNS records

**Check 4: PHP cURL Enabled?**
```php
<?php echo extension_loaded('curl') ? 'YES' : 'NO'; ?>
```

### Issue: Email Goes to Spam

**Solutions:**
1. Add DKIM records to domain DNS
2. Add SPF record: `v=spf1 include:sndr.sh ~all`
3. Enable DMARC: `v=DMARC1; p=none;`
4. Use branded sender email (not generic)

### Issue: Token Expired Error

**Verification Tokens:**
- Valid for 24 hours
- Send user to `/auth/verify_email.php` to resend

**Password Reset Tokens:**
- Valid for 1 hour
- Send user to `/auth/forgot_password.php` to request new reset

### Issue: Email Logs Show "failed" Status

Check the `response` column in `email_logs` table for error details:

```sql
SELECT * FROM email_logs WHERE status = 'failed' ORDER BY created_at DESC;
```

Common errors:
- `unauthorized` - Invalid API key
- `invalid_email` - Recipient email is invalid
- `rate_limited` - Too many requests (sndr.sh quota)

---

## API Reference

### EmailHelper Class Methods

#### `send($to, $subject, $body_html, $body_text, $attachments, $reply_to)`
Send custom email

```php
$email_helper = new EmailHelper($pdo, CURRENT_ORG_ID);
$result = $email_helper->send(
    'user@example.com',
    'Subject',
    '<p>HTML Body</p>',
    'Text Body',
    [],
    'reply@example.com'
);
```

#### `sendVerificationEmail($email, $verification_link, $org_name)`
Send email verification link

#### `sendPasswordResetEmail($email, $reset_link, $username, $org_name)`
Send password reset link

#### `send2FACode($email, $code, $username, $org_name)`
Send 2FA verification code

#### `sendNotification($email, $message_ar, $message_en, $action_type, $org_name)`
Send notification email

#### `sendWelcomeEmail($email, $username, $full_name, $org_name, $login_url)`
Send welcome email

---

## Security Considerations

### Best Practices

1. **API Key Security**
   - Never commit API key to git
   - Use environment variables in production
   - Rotate keys regularly

2. **Rate Limiting**
   - Email has rate limiting per user
   - See `login_attempts` table

3. **Token Expiration**
   - Verification: 24 hours
   - Password Reset: 1 hour
   - Enforce strict expiration

4. **Email Verification**
   - Required on registration (recommended)
   - Prevents fake email addresses
   - Improves deliverability

5. **Audit Logging**
   - All emails logged in `email_logs`
   - Check for abuse patterns
   - Monitor failed deliveries

---

## Configuration Examples

### Setup for Organization #1

```sql
-- Set sndr credentials for organization 1
REPLACE INTO settings (organization_id, setting_key, setting_value) VALUES
(1, 'sndr_api_key', 'sk_test_abc123xyz...'),
(1, 'sndr_sender_email', 'noreply@company1.com'),
(1, 'sndr_sender_name', 'Company 1 HR System'),
(1, 'email_notifications_enabled', '1'),
(1, 'email_verification_required', '1');
```

### Enable Emails Globally

```sql
-- Enable for all organizations
UPDATE settings SET setting_value = '1' WHERE setting_key IN ('email_notifications_enabled', 'email_verification_required');
```

---

## Support

For sndr.sh API issues:
- Documentation: https://sndr.sh/docs
- Status: https://status.sndr.sh
- Support: contact@sndr.sh

For HR-App email issues:
- Check application logs
- Review email_logs table
- Verify database tables exist

---

**Last Updated:** May 25, 2026  
**Version:** 1.0
