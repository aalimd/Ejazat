# نظام إدارة الموظفين (HR App) - نسخة الاستضافة

تطبيق ويب متعدد الجهات (Multi-tenant) لإدارة الموظفين وطلبات الإجازات، مصمم للعمل على الاستضافات المشتركة (مثل Hostinger).

## المميزات
- واجهة عربية (RTL) مع دعم كامل للإنجليزية.
- تصميم متجاوب Bootstrap 5 مع وضع ليلي (Dark Mode).
- نظام صلاحيات (Super Admin, Admin, Manager, Employee).
- إدارة الموظفين (إضافة، استيراد، مراجعة، اعتماد).
- نظام طلبات الإجازات (طلب، اعتماد، تقارير، رصيد).
- لوحة تحكم Super Admin: إدارة الجهات، رموز الالتحاق، مراقبة صحة النظام، أدوات الصيانة.
- تطبيق ويب تقدمي (PWA) يعمل دون اتصال.

## متطلبات التشغيل
- PHP 8.0 أو أحدث (مع PDO و MySQL).
- MySQL أو MariaDB.

## الأمان المطبق
- PDO Prepared Statements لكل الاستعلامات (منع SQL Injection).
- كل المخرجات عبر `h()` (منع XSS).
- CSRF إلزامي لكل إجراءات POST مع توكين للجلسة.
- Rate Limiting لصفحات الدخول/التسجيل/طلب كلمة المرور (جدول `ip_rate_limits`).
- رسائل موحّدة لمنع تعداد المستخدمين (login/forgot/verify).
- مصادقة ثنائية (TOTP + رمز بريدي) مع قفل بعد محاولات فاشلة.
- إبطال الجلسات القديمة عند تغيير كلمة المرور عبر `users.auth_version`.
- حماية Host Header Poisoning عبر `TRUSTED_HOSTS`.
- جلسات صارمة: `httponly`, `samesite=Lax`, `secure` عند HTTPS.
- `.htaccess`: منع الوصول لملفات `.env` و`.sql` و`.git` وغيرها + تعطيل Indexes.

## طريقة التثبيت على Hostinger
1. أنشئ قاعدة بيانات MySQL جديدة من hPanel.
2. استورد `sql/database.sql` في قاعدة البيانات الجديدة.
3. ارفع المشروع إلى `public_html`.
4. أنشئ ملف `.env` في جذر المشروع (مثال):
   ```
   DB_HOST=localhost
   DB_NAME=your_db_name
   DB_USER=your_db_user
   DB_PASS=your_db_password
   DB_PORT=3306
   BASE_URL=/HR-App/
   APP_ENV=production
   ```
   (إن لم يوجد ملف `.env` سيُقرأ من `includes/config.php`)
5. اضبط `TRUSTED_HOSTS` في `includes/config.php` ليشمل دومينك.

## الترقية من إصدار أقدم
نفّذ ملفات المايجريشن بالترتيب من `database/migrations/` (الأحدث: `007_auth_version_and_rate_limits.sql`)، أو استخدم صفحة "مركز الترقية" في لوحة Super Admin.

## بيانات الدخول الافتراضية
- **Super Admin:** `superadmin` / `admin123`
- **Admin الجهة:** `khaled` / `admin123`

*ملاحظة: هذه كلمات مرور للاختبار المحلي فقط — غيّرها فور أول تسجيل دخول، ولا تستخدمها في الإنتاج.*

## بنية المشروع
- `assets/`: CSS/JS/صور (تنسيقات وتحسينات الثيم والوضع الليلي).
- `auth/`: دخول/تسجيل/استعادة كلمة المرور/2FA/الأمان.
- `employees/`: إدارة واعتماد الموظفين.
- `includes/`: إعدادات الأمان والدوال والهيدرات.
- `leaves/`: طلبات وإدارة وتقارير الإجازات.
- `superadmin/`: لوحة التحكم الرئيسية ومركز الترقية.
- `database/migrations/`: ملفات تحديث قاعدة البيانات.
- `sql/`: سكربت التثبيت الكامل.
