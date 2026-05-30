# Organization Privacy & Invitation Codes System

## Overview

The HR-App now supports organization privacy control through invitation codes. This allows organizations to control who can join through the registration page:

- **Public Organizations** (🌍): Visible in registration dropdown, no code required
- **Private Organizations** (🔐): Hidden from dropdown, requires unique invitation code

---

## Quick Start

### For Super Admin: Enable Organization Privacy

1. **Access Admin Panel** → **🔐 Organization Codes Management**
2. **Toggle Organization Status**:
   - Click **🌍 Make Public** or **🔐 Make Private**
   - Confirm the change
3. **Share Code with Employees** (for private orgs):
   - Copy the code (📋 button)
   - Share with employees who should join
4. **Regenerate Code** (if compromised):
   - Click **🔄 New Code**
   - Old code becomes inactive immediately
   - New code can be shared

### For New Employees: Register with Code

1. **Open Registration Page** (`/auth/register.php`)
2. **See Two Options**:
   - **Option A**: Select from public organizations in dropdown
   - **Option B**: Enter invitation code if organization is private
3. **Enter Code** (if applicable):
   - Paste the code provided by your organization
   - Click **Check Code**
4. **Complete Registration** with rest of form

---

## Technical Details

### Database Schema

#### organization_invitation_codes Table
```sql
CREATE TABLE organization_invitation_codes (
  id INT PRIMARY KEY AUTO_INCREMENT,
  organization_id INT NOT NULL,
  code VARCHAR(20) UNIQUE NOT NULL,           -- Unique 12-char code
  is_active TINYINT(1) DEFAULT 1,            -- Current code only
  previous_code VARCHAR(20),                 -- Audit trail
  code_regenerated_at TIMESTAMP,             -- When regenerated
  regenerated_by_user_id INT,                -- Super Admin who regenerated
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### organization_code_attempts Table
```sql
CREATE TABLE organization_code_attempts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  organization_id INT,
  code_entered VARCHAR(20),
  ip_address VARCHAR(45) NOT NULL,
  success TINYINT(1) DEFAULT 0,
  attempt_type ENUM('registration','verification'),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### organizations Table (Modified)
```sql
-- New columns added:
ALTER TABLE organizations ADD COLUMN (
  is_public TINYINT(1) DEFAULT 1,           -- 1=show in public list
  requires_invitation_code TINYINT(1) DEFAULT 0  -- Enforce code requirement
);
```

### Code Format

**Pattern**: `ABC123DEF456` (12 characters)
- 3 random letters A-Z
- 3 random numbers 0-9
- 3 random letters A-Z
- 3 random numbers 0-9

**Examples**:
- `XYZ789ABC123`
- `DEF456GHI789`
- `JKL012MNO345`

**Why this format?**
- Easy to remember and communicate verbally
- Hard to guess (52,200,625 possible combinations for 12-char alphanumeric)
- No special characters (no confusion with L/1, O/0)
- Uppercase only (prevents keyboard confusion)

### Security Features

#### Rate Limiting
- **Max 5 failed attempts per IP per hour**
- Blocks further attempts for 1 hour after limit exceeded
- Prevents brute force attacks on codes

#### Audit Trail
All code attempts logged with:
- IP address
- Code entered
- Success/failure
- Timestamp
- Organization ID

Query audit trail:
```sql
SELECT * FROM organization_code_attempts 
ORDER BY created_at DESC 
LIMIT 100;
```

#### Code Validity
- Only **active** codes work
- When code is regenerated, old code becomes inactive
- Multiple old codes can exist in `previous_code` for audit purposes
- No automatic code expiration

### Helper Functions

All functions in `includes/config.php`:

#### `generateRandomInvitationCode()`
```php
$code = generateRandomInvitationCode();
// Returns: "ABC123DEF456"
```

#### `getOrganizationByCode($code)`
```php
$org = getOrganizationByCode("ABC123DEF456");
// Returns: ['id' => 1, 'name_ar' => '...', 'name_en' => '...', ...]
```

#### `validateInvitationCode($code)`
```php
$result = validateInvitationCode($_POST['invitation_code']);
if ($result['success']) {
    $org_id = $result['org_id'];
    $org_name = $result['org_name'];
} else {
    echo $result['message']; // Error message
}
// Returns: ['success' => bool, 'org_id' => int, 'org_name' => string, 'message' => string]
```

#### `checkCodeRateLimit($ip_address)`
```php
$limit = checkCodeRateLimit($_SERVER['REMOTE_ADDR']);
if (!$limit['allowed']) {
    echo $limit['message'];  // "Too many failed attempts..."
}
// Returns: ['allowed' => bool, 'message' => string, 'attempts_remaining' => int]
```

#### `logCodeAttempt($org_id, $code, $ip, $success)`
```php
logCodeAttempt(1, "ABC123DEF456", "192.168.1.1", true);
// Logs attempt to organization_code_attempts table
```

#### `regenerateOrganizationCode($org_id, $user_id)`
```php
// Only works if $user_id is Super Admin
$result = regenerateOrganizationCode(1, $_SESSION['user_id']);
if ($result['success']) {
    echo "New code: " . $result['new_code'];
    echo "Old code: " . $result['old_code'];
}
// Returns: ['success' => bool, 'new_code' => string, 'old_code' => string, 'message' => string]
```

#### `getOrganizationCode($org_id)`
```php
$code_info = getOrganizationCode(1);
// Returns: ['code' => 'ABC123DEF456', 'is_active' => 1, 'code_regenerated_at' => '...', ...]
```

---

## Usage Examples

### Example 1: Make Organization Private

```
Super Admin Dashboard:
├── 🔐 Organization Codes Management
├── Find "Acme Corporation"
├── Click "🔐 Make Private"
└── Code generated: ABC123DEF456
   Share with Acme employees
```

When employees try to register:
```
Registration page:
├── Option 1: "Select from list" → (Acme not shown)
├── Option 2: Enter code → ABC123DEF456
└── ✅ Success → Registration continues
```

### Example 2: Regenerate Compromised Code

If code is shared publicly or needs rotation:

```
Super Admin Dashboard:
├── 🔐 Organization Codes Management
├── Find "Acme Corporation"
├── Click "🔄 New Code"
├── Confirm
└── New code: XYZ789ABC123
   Old code: ABC123DEF456 (now inactive)
   
All attempts with ABC123DEF456 now fail ❌
```

### Example 3: Audit Failed Login Attempts

```sql
-- Check for brute force attempts
SELECT 
  ip_address,
  COUNT(*) as attempts,
  MAX(created_at) as last_attempt
FROM organization_code_attempts
WHERE success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY ip_address
ORDER BY attempts DESC;

-- Result: Identify suspicious IPs
```

---

## Setup Instructions

### Step 1: Run Database Migration

```bash
# Execute both SQL files
mysql -u root -p hr_app_db < /path/to/org_privacy_setup.sql
mysql -u root -p hr_app_db < /path/to/email_setup.sql
```

Or in PhpMyAdmin:
1. Open SQL tab
2. Copy contents of `org_privacy_setup.sql`
3. Paste and execute
4. Repeat for `email_setup.sql`

### Step 2: Verify Database Changes

```sql
-- Check new columns exist
DESCRIBE organizations;
-- Should show: is_public, requires_invitation_code

-- Check new tables exist
SHOW TABLES LIKE '%invitation%';
SHOW TABLES LIKE '%code_attempt%';
```

### Step 3: Access Organization Codes Admin

1. Log in as **Super Admin**
2. Navigate to **⚙️ Settings** (Admin Panel)
3. Look for **🔐 Organization Codes Management** option
4. Or direct URL: `/admin/organization_codes.php`

### Step 4: Configure Organizations

For each organization, decide:
- **Public** (🌍): Show in registration list?
- **Private** (🔐): Require invitation code?

---

## Troubleshooting

### Issue: "Code not found" Error

**Cause**: Code doesn't exist or is inactive

**Solutions**:
1. Verify code with Super Admin
2. Check if organization is active (is_active = 1)
3. Confirm code hasn't been regenerated
4. Try regenerating new code

### Issue: "Too many failed attempts"

**Cause**: More than 5 failed attempts from same IP in 1 hour

**Solutions**:
1. Wait 1 hour for rate limit to clear
2. Change IP address
3. Check code spelling carefully
4. Contact Super Admin if code is correct

### Issue: Organization doesn't appear in dropdown

**Cause 1**: Organization is private (is_public = 0)
- **Solution**: Ask for invitation code or contact admin

**Cause 2**: Organization is inactive (is_active = 0)
- **Solution**: Contact super admin

**Cause 3**: User's language preference
- **Solution**: Switch language and try again

### Issue: Can't find Organization Codes page

**Direct URL**: `http://yourdomain/admin/organization_codes.php`

**Permissions**: Must be **Super Admin** role

---

## Best Practices

### 1. Code Management

✅ **DO:**
- Share codes securely (email, secure message)
- Regenerate codes regularly (e.g., quarterly)
- Keep audit logs for compliance

❌ **DON'T:**
- Share codes in public channels
- Use same code for multiple organizations
- Forget to deactivate compromised codes

### 2. Organization Setup

✅ **DO:**
- Start with public organizations
- Make organizations private only if needed
- Review and document each organization's privacy setting

❌ **DON'T:**
- Frequently toggle public/private (confuses employees)
- Require codes for all organizations (defeats purpose)
- Share registration links without mentioning codes

### 3. Security

✅ **DO:**
- Monitor failed code attempts
- Block suspicious IPs if needed
- Rotate codes regularly

❌ **DON'T:**
- Disable rate limiting
- Share Super Admin dashboard access
- Ignore audit logs

---

## FAQ

**Q: Can employees change organizations after registering?**
A: No, organization is set during registration and tied to their account.

**Q: What if an employee forgets their code?**
A: They should contact their organization's HR admin who can get the current code from Super Admin.

**Q: Can organization admins see/regenerate codes?**
A: No, only Super Admin can access and regenerate codes. This prevents accidents.

**Q: How long are codes valid?**
A: Codes are valid indefinitely until regenerated or organization becomes public.

**Q: Can the same code be used for multiple organizations?**
A: No, each organization has exactly one active code at a time.

**Q: What happens to employees who registered with old code after regeneration?**
A: They keep their account. Only NEW registrations are blocked. Old registrations remain valid.

---

## Analytics & Reporting

### Registration by Code

```sql
-- Track which organizations get registrations
SELECT 
  o.name_ar as organization,
  COUNT(u.id) as total_registrations,
  COUNT(CASE WHEN o.is_public = 1 THEN 1 END) as from_public_list,
  COUNT(CASE WHEN o.is_public = 0 THEN 1 END) as from_code
FROM users u
JOIN organizations o ON u.organization_id = o.id
WHERE u.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY o.id
ORDER BY total_registrations DESC;
```

### Failed Code Attempts

```sql
-- Identify problematic codes or IPs
SELECT 
  o.name_ar as organization,
  COUNT(*) as failed_attempts,
  GROUP_CONCAT(DISTINCT ip_address) as source_ips
FROM organization_code_attempts a
JOIN organizations o ON a.organization_id = o.id
WHERE a.success = 0 AND a.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY a.organization_id
ORDER BY failed_attempts DESC;
```

---

## Support

For issues with organization codes:
1. Check Super Admin dashboard
2. Review audit logs in `organization_code_attempts`
3. Verify database tables were created (`org_privacy_setup.sql`)
4. Contact development team with error message and IP

---

**Last Updated:** May 25, 2026  
**Version:** 1.0  
**Feature Status:** ✅ Production Ready
