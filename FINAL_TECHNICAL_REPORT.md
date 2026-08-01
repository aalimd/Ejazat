# التقرير الفني النهائي — تدقيق منطق الأعمال وأخطاء PHP

**النطاق:** مراجعة بحثية كاملة لمنطق الأعمال وأخطاء PHP 8.x في تطبيق HR متعدد الجهات (المجلد الجذر `/Applications/XAMPP/xamppfiles/htdocs/HR-App`).
**المنهجية:** قراءة ملفات العمل والنواة (`includes/config.php`, `includes/EmailHelper.php`, صفحات auth/admin/leaves/employees/superadmin) والتحقق من السلوك عبر grep على الأنماط (blocked_email, status, is_active, max_login_attempts, min_password_length, requires_invitation_code, sndr_...).
**البيئة:** PHP 8.4.19، `php -l` نظيف على جميع الملفات.
**ملاحظة:** هذا التقرير لا يكرر ما وثّقته التقارير السابقة (`SECURITY_AUDIT_REPORT.md`, `SYSTEM_AUDIT_REPORT.md`, `COMPREHENSIVE_FIXES_REPORT.md`, `AUDIT_SUMMARY.md`)؛ جميع النتائج أدناه جديدة.

---

## أولاً: نتائج حرجة (Critical)

### C1. تعليق المنظمة (Suspension) لا يفعّل شيئاً — إجراء شكلي بالكامل
- **الكتابة فقط:** `admin/organizations.php:242,400-406` يحدّث `organizations.status` إلى `'suspended'`/`'active'`.
- **لا توجد أي قراءة مفعّلة له:** `organizations.status` يُقرأ في ملف واحد فقط وهو `superadmin/dashboard.php:10` (عدّ للعرض الإحصائي).
- **التحقق من الأدلة:**
  - `auth/login.php:28`: `SELECT id, username, password, role, two_factor_enabled, organization_id FROM users WHERE username = ?` — لا join على `organizations` إطلاقاً.
  - `includes/config.php:698` (checkAuth): يقرأ `role` فقط من جدول users — لا فحص لحالة المنظمة أو `is_active`.
  - `auth/register.php:40,48`: الفحص الوحيد هو `is_public = 1 AND is_active = 1` — وليس `status = 'active'`.
- **الأثر:** مستخدمو المنظمة المعلّقة (بما فيهم الأدمن) يدخلون ويعملون بشكل طبيعي. تعليق المنظمة من لوحة التحكم لا يمنع أي شيء.
- **الحل المقترح:** إضافة `JOIN organizations ON ... AND organizations.status = 'active'` في استعلام تسجيل الدخول، وفحص حالة المنظمة في `checkAuth()` (أو عند بناء الجلسة)، ورفض الجلسات عند تغيير الحالة، مع رسالة واضحة.

### C2. ميزة "حظر الإيميلات" في لوحة التحكم غير مفعّلة (كود ميت)
- **الكتابة فقط:** `admin/registration_control.php:81` يخزّن `blocked_email_<email> = '1'` في جدول settings للمنظمة 1، و`registration_control.php:89` يمسحها — لكن **لا يوجد أي `read` في التطبيق** (grep مؤكد: تطابقان فقط، كلاهما كتابة).
- **الأثر:** الإيميل المحظور يستطيع التسجيل بحرية من `auth/register.php`؛ خاصية منع التسجيل للأشخاص المحظورين لا تعمل أصلاً.
- **الحل المقترح:** قبل إنشاء الحساب في `auth/register.php` (قرب سطر 134) إضافة فحص `SELECT setting_value FROM settings WHERE organization_id = ? AND setting_key = CONCAT('blocked_email_', ?)` ومنع التسجيل برسالة واضحة.

---

## ثانياً: نتائج عالية (High)

### H1. إعداد "طلب رمز الالتحاق" (requires_invitation_code) غير مفروض — يمكن تجاوزه بعنوان URL مباشر
- `admin/registration_control.php:50` يحفظ `requires_invitation_code` لكل منظمة.
- `auth/register.php:21-24`: التحقق من الرمز **اختياري** — يُنفَّذ فقط إذا أُدخل الرمز.
- `auth/register.php:36-59`: عند غياب الرمز، يسمح باختيار أي منظمة عامة عبر `GET ?org_id=` أو POST بشرط `is_public = 1 AND is_active = 1` **دون أي فحص لـ `requires_invitation_code`**، وفي السطر 57-59 يختار أول منظمة عامة افتراضياً.
- مسار POST (سطور 62-93) يفحص `allow_registration` فقط — لا يوجد فحص `requires_invitation_code` إطلاقاً.
- **الأثر:** أي شخص يفتح `auth/register.php?org_id=<X>` لمنظمة عامة مطلوب لها رمز التحاق يستطيع إنشاء حساب دون رمز.
- **الحل المقترح:** إضافة شرط في المسارين (الاختيار والـ POST): إن كانت `requires_invitation_code = 1` فلابد من `code_used = true`.

---

## ثالثاً: نتائج متوسطة (Medium)

### M1. إعداد `max_login_attempts` غير مفعّل (قيم ثابتة في الكود)
- `admin/system_settings.php:56` يحفظ الإعداد في `system_settings` — لكن `includes/config.php:502-503` يستخدم ثوابت `$max_attempts = 5; $lockout_minutes = 15;` ولا يقرأ الإعداد (الاستخدام الوحيد للقراءة هو عرض تحذيري في `admin/site_health.php:294`).
- **الأثر:** تغيير "الحد الأقصى لمحاولات الدخول" من الإعدادات لا يؤثر على القفل الفعلي.
- **الحل:** قراءة القيمتين من `getSetting()` في `checkLoginAttempts`.

### M2. إعداد `min_password_length` غير مفعّل
- `admin/system_settings.php:50` يحفظه، و`admin/site_health.php:271` يقرؤه للعرض فقط.
- فرض الطول يتم بقيمة ثابتة 8 في `auth/register.php:118` و`auth/reset_password.php:39` و`admin/users.php` (لا فحص أصلاً عند إضافة مستخدم — انظر L4).
- **الحل:** قراءة `min_password_length` من الإعداد في كل نقاط تعيين كلمة المرور، وضبط `admin/users.php` ليطبّق الحد الأدنى أيضاً.

### M3. صلاحية المدير (manager) على إدارة الأقسام وأنواع الإجازات — نطاق واسع
- `admin/departments.php:3` و`admin/leave_types.php:3`: `checkAuth(['admin','manager'])` — المدير قادر على إنشاء/تعديل/حذف أقسام وأنواع إجازات تخص كامل المنظمة، بما فيها تغيير أنواع الأرصدة التي يعتمد عليها كل الموظفين (خاصة أن الحذف يُفسد رصيد `leave_balances`).
- **الحل:** قصر كتابة الأقسام وأنواع الإجازات على `admin` (المدير للقراءة فقط)، أو توثيق النية بوضوح.

### M4. رفع المرفقات عبر Cloudinary بـ unsigned preset وبدون أي تحقق سيرفر
- `leaves/my_requests.php:418`: `uploadPreset: 'ml_default'` مع `cloudName: 'dbvxlb6ko'` مكتوبان صراحةً في الجافاسكريبت — وidget غير موقّع (unsigned) وبدون `acceptedFiles` أو `maxFileSize`، أي أن أي شخص يعرف الـ preset يستطيع رفع ملفات ضخمة/عشوائية لحساب المنظمة (إساءة استهلاك التخزين والتكلفة).
- `leaves/my_requests.php:52`: `attachment_url` يُؤخذ من العميل **دون أي تحقق سيرفر** ويُخزَّن كما هو (لا تحقق من النطاق ولا من الحجم ولا من MIME)، ويُعرض لاحقاً للمديرين كرابط قابل للنقر (`my_requests.php:283`, `leaves/manage.php:255`). العرض محمي بـ `htmlspecialchars` (لا XSS مباشر) لكن المحتوى/الوجهة غير موثوقة.
- **الحل:** توقيع الرفعات (signed preset) مع قيود الأنواع والأحجام، وفرض تحقق سيرفر من نطاق الرابط المخزن (قائمة بيضاء cloudinary.com وغيرها) عند حفظ الطلب.

### M5. غياب CSRF في صفحة الأمان (تفعيل/إلغاء 2FA)
- `auth/security.php:22-70`: معالجة POST الخاصة بـ `enable_2fa` و`disable_2fa` **بدون استدعاء `verify_csrf()`** — على عكس بقية الصفحات.
- **الأثر:** مخفّف عملياً لأن إلغاء 2FA يتطلب معرفة رمز TOTP الحالي، لكنه يخالف النمط الموحد ويُضعف الطبقة عند تقصير رمز.
- **الحل:** إضافة `verify_csrf()` قبل معالجة العمليتين.

### M6. لون السمة `primary_color` متجاهل في واجهة السوبر أدمن
- `includes/superadmin_header.php:7`: `$primary_hex = '#0d6efd';` قيمة ثابتة — إعداد اللون من `admin/system_settings.php` لا يُطبَّق على صفحات السوبر أدمن (`superadmin/*`, `admin/system_settings.php`, `admin/organizations.php`).

### M7. لا توجد صفحة تغيير كلمة مرور للمستخدم المسجّل
- grep مؤكد: لا يوجد أي مرجع `change_password` أو `new_password` خارج `auth/reset_password.php` (إعادة تعيين عبر البريد فقط). صفحة `auth/security.php` تعنى بـ 2FA فقط.
- **الأثر:** مستخدم لا يمكنه تغيير كلمة مروره إلا عبر forgot-password — قصور وظيفي، ويحفّز مشاركة كلمات مرور ثابتة.
- **الحل:** صفحة تغيير كلمة مرور تتطلب كلمة المرور الحالية + تحقق CSRF.

---

## رابعاً: نتائج منخفضة / ملاحظات (Low)

### L1. أخطاء عامة في التحقق من المُدخلات
- `includes/config.php:115` — استعلام جلسة يقرأ users بدون تحقق إضافي؛ `auth/forgot_password.php:40` و`auth/verify_email.php:41` يعتمدان على `users.email` ويمكن التمييز بين الحسابات (user enumeration جزئي عبر اختلاف الاستجابة). (تم التطرق جزئياً في التقارير السابقة — يُدرج هنا للاكتمال بوصف الحالة الراهنة).

### L2. فجوة البنية البيانية: `sql/database.sql` مقابل `database/clean_db.sql`
- `sql/database.sql` يبني **20 جدولاً** (يشمل `organization_requests`, `email_logs`, `email_verifications`, `password_resets`, `login_attempts`, `organization_invitation_codes`, `organization_code_attempts`, `system_settings`, `schema_migrations`) بينما `database/clean_db.sql` يبني **12 جدولاً** فقط (يصل إلى `settings`).
- `database/migrations/` يحتوي ملفاً واحداً فقط (`006_add_cancelled_status.sql`) بينما `includes/MigrationService.php:100-134` يتوقع سلسلة 001-00N — أي تثبيت جديد عبر clean_db + migrations سيترك بنية ناقصة.

### L3. `mark_notifications_read.php` بدون CSRF وبدون تقييد للطريقة
- `mark_notifications_read.php:6-17`: أي طلب GET من مستخدم مسجّل يصفّر جميع إشعاراته (يتطلب `isLoggedIn()` فقط). الأثر منخفض (إزعاج)، لكن يخالف النمط.

### L4. عدم اتساق صلاحيات إنشاء المستخدمين
- `admin/users.php:3`: `checkAuth('admin')` — super_admin يمر عبر `hasRole` (المكوّنة للوصول الشامل في config.php). لكن:
  - عند إضافة مستخدم بدور super_admin: `admin/users.php:27` يحفظ `org_id = null` ثم الاستعلام بالعرض `admin/users.php:44` يقرأ بـ `WHERE organization_id = CURRENT_ORG_ID` — الحساب الجديد لن يظهر في قائمة المستخدمين للعرض/الإدارة.
  - لا فحص `min_password_length` عند إضافة مستخدم (ينسجم مع M2).

### L5. عدم توحيد بوابات التحقق بين صفحات السوبر أدمن
- `admin/users.php:3` و`admin/activity_log.php:4` يستخدمان `checkAuth('admin')`، بينما `admin/organizations.php:5` و`admin/system_settings.php:4` يستخدمان `checkAuth('super_admin')`، و`superadmin/dashboard.php:3` يستخدم `checkSuperAdmin()` — كلها تعمل وظيفياً (بفضل `hasRole` الشامل) لكن الأسلوب غير موحد وصعبة الصيانة.

### L6. متغيرات ملحوظة
- `offline.php:4` — تحليل `sscanf` على قيمة hex غير صالحة (سلوك غير محسوم عند التحليل الفاشل).

---

## خامساً: ملخص وتوصيات حسب الأولوية

| # | الملف:السطر | الخطورة | الخلاصة |
|---|-------------|---------|---------|
| C1 | `admin/organizations.php:242` + `auth/login.php:28` | حرجة | تعليق المنظمة لا يمنع الدخول — الصق `status='active'` في مسار الدخول/الجلسة |
| C2 | `admin/registration_control.php:81` | حرجة | حظر الإيميلات غير مقروء في أي مكان — أضف الفحص في `auth/register.php` |
| H1 | `auth/register.php:36-59` | عالية | تجاوز رمز الالتحاق بفتح `?org_id=` مباشرة لمنظمة عامة |
| M1 | `includes/config.php:502-503` | متوسطة | `max_login_attempts` محفوظ وغير مفعّل |
| M2 | `auth/register.php:118` | متوسطة | `min_password_length` محفوظ وغير مفعّل (ثابت 8) |
| M3 | `admin/departments.php:3`, `admin/leave_types.php:3` | متوسطة | المدير يحرّر بيانات شاملة للمنظمة |
| M4 | `leaves/my_requests.php:418,52` | متوسطة | unsigned upload preset + تخزين روابط غير موثوقة من العميل |
| M5 | `auth/security.php:22-70` | متوسطة | غياب CSRF في تفعيل/إلغاء 2FA |
| M6 | `includes/superadmin_header.php:7` | متوسطة | لون السمة ثابت في واجهة السوبر أدمن |
| M7 | `auth/` (غير موجود) | متوسطة | لا توجد صفحة تغيير كلمة مرور للمسجّلين |
| L2–L6 | كما هو مذكور | منخفضة | فجوة البنية/الترحيلات، CSRF بسيط، عدم التوحيد |

**أولوية التنفيذ المقترحة:** C1 → C2 → H1 → M4 → M1/M2 → M5 → بقية النقاط.

**ملاحظة ختامية:** جميع النتائج أعلاه جديدة ومؤكدة بالأدلة (أسطر دقيقة + grep)، وتجنّبت تكرار ما وثّقته التقارير الأربعة السابقة في الجذر. لم يتم تعديل أي ملف أثناء هذه المراجعة (بحثية بحتة).
