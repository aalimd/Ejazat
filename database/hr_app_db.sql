-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for osx10.10 (x86_64)
--
-- Host: localhost    Database: hr_app_db
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action_ar` varchar(255) NOT NULL,
  `action_en` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
INSERT INTO `activity_log` VALUES (1,1,'إيقاف صلاحية الإجازة للموظف','Disabled Leave Permission for Employee','Employee: ahmed (ID: 2)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 17:04:01'),(2,1,'تفعيل صلاحية الإجازة للموظف','Enabled Leave Permission for Employee','Employee: ahmed (ID: 2)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 17:04:03'),(3,1,'⚙️ تحديث إعدادات النظام','⚙️ Update System Settings','General settings updated','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 17:08:08'),(4,1,'⚙️ تحديث إعدادات النظام','⚙️ Update System Settings','General settings updated','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 17:08:18'),(5,3,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 17:14:31'),(6,3,'✏️ تحديث الملف الشخصي','✏️ Update Profile','Employee updated their own profile','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 17:25:11'),(7,1,'✅ الموافقة على إجازة','✅ Approve Leave Request','Request ID: 2','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 17:25:55'),(8,1,'✏️ تعديل بيانات موظف','✏️ Edit Employee Info','Employee: AbdulRahman alzahrani (ID: 1)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 17:26:32'),(9,1,'✏️ تعديل بيانات موظف','✏️ Edit Employee Info','Employee: AbdulRahman alzahrani (ID: 1)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 17:26:41'),(10,1,'✏️ تعديل بيانات موظف','✏️ Edit Employee Info','Employee: AbdulRahman alzahrani (ID: 1)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 17:26:42'),(11,1,'✅ الموافقة على إجازة','✅ Approve Leave Request','Request ID: 3','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 17:28:23'),(12,3,'🚪 تسجيل الخروج','🚪 Logout',NULL,'::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 17:39:47'),(13,3,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 18:05:15'),(14,3,'🚪 تسجيل الخروج','🚪 Logout',NULL,'::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 18:14:30'),(15,NULL,'⚠️ محاولة دخول فاشلة','⚠️ Failed Login Attempt','Username: aaa','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 18:14:42'),(16,3,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 18:14:51'),(17,3,'🚪 تسجيل الخروج','🚪 Logout',NULL,'::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 18:19:02'),(18,3,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 18:19:22'),(19,3,'🚪 تسجيل الخروج','🚪 Logout',NULL,'::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 18:27:10'),(20,3,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 18:27:18'),(21,1,'✏️ تعديل نوع إجازة','✏️ Edit Leave Type','ID: 2, New Name: إجازة تعليمية / Educational Leave, Deduct: 1, Max: 30','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 18:29:12'),(22,1,'➕ إضافة نوع إجازة','➕ Add Leave Type','Name: إجازة عيد الفطر / Eid AlFeter Leave, Deduct: 1, Max: 7','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 18:30:57'),(23,1,'➕ إضافة نوع إجازة','➕ Add Leave Type','Name: إجازة عيد الأضحى / Eid AL Adhaha Leave, Deduct: 1, Max: 5','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 18:31:55'),(24,3,'🚪 تسجيل الخروج','🚪 Logout',NULL,'::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 19:18:18'),(25,1,'⚙️ تحديث إعدادات النظام','⚙️ Update System Settings','General settings updated','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 19:18:24'),(26,1,'⚙️ تحديث إعدادات النظام','⚙️ Update System Settings','General settings updated','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-17 19:18:54'),(27,1,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 05:31:08'),(28,1,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 05:32:29'),(29,1,'🔒 تفعيل التحقق الثنائي','🔒 Enable 2FA','User enabled Two-Factor Authentication','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 05:44:32'),(30,1,'🚪 تسجيل الخروج','🚪 Logout',NULL,'::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 05:44:50'),(31,NULL,'⚠️ فشل التحقق الثنائي','⚠️ Failed 2FA Attempt','Username: admin','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 05:45:10'),(32,1,'🔐 تسجيل الدخول (ثنائي)','🔐 Login (2FA)','User logged in successfully via 2FA','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 05:45:27'),(33,1,'🚪 تسجيل الخروج','🚪 Logout',NULL,'::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 06:01:14'),(34,1,'🔐 تسجيل الدخول (ثنائي)','🔐 Login (2FA)','User logged in successfully via 2FA','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 06:01:36'),(35,1,'🚪 تسجيل الخروج','🚪 Logout',NULL,'::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 06:03:00'),(36,5,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 06:03:32'),(37,5,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 06:19:31'),(38,5,'🚪 تسجيل الخروج','🚪 Logout',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 06:22:06'),(39,1,'🔓 إلغاء تفعيل التحقق الثنائي','🔓 Disable 2FA','User disabled Two-Factor Authentication','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 06:23:24'),(40,5,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 06:23:25'),(41,5,'🚪 تسجيل الخروج','🚪 Logout',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 06:25:33'),(42,5,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 07:31:04'),(43,5,'🚪 تسجيل الخروج','🚪 Logout',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 07:51:47'),(44,1,'🚪 تسجيل الخروج','🚪 Logout',NULL,'::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:12:41'),(45,5,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:12:54'),(46,1,'🚪 تسجيل الخروج','🚪 Logout',NULL,'::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:17:49'),(47,5,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:18:05'),(48,5,'🚪 تسجيل الخروج','🚪 Logout',NULL,'::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:18:13'),(49,NULL,'⚠️ محاولة دخول فاشلة','⚠️ Failed Login Attempt','Username: admin','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:18:21'),(50,NULL,'⚠️ محاولة دخول فاشلة','⚠️ Failed Login Attempt','Username: admin','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:18:33'),(51,NULL,'⚠️ محاولة دخول فاشلة','⚠️ Failed Login Attempt','Username: admin','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:19:03'),(52,1,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:19:13'),(53,1,'🚪 تسجيل الخروج','🚪 Logout',NULL,'::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:19:17'),(54,5,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:19:27'),(55,5,'🚪 تسجيل الخروج','🚪 Logout',NULL,'::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:24:22'),(56,5,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:24:32'),(57,5,'🏢 إضافة جهة عمل','🏢 Added Organization','Created organization: ED-Haram Hospital - Madina','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:26:57'),(58,5,'🚪 تسجيل الخروج','🚪 Logout',NULL,'::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:28:43'),(59,3,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:28:51'),(60,3,'🚪 تسجيل الخروج','🚪 Logout',NULL,'::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:29:54'),(61,1,'🔐 تسجيل الدخول','🔐 Login','User logged in successfully','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:30:08'),(62,1,'🚪 تسجيل الخروج','🚪 Logout',NULL,'::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-18 08:32:55');
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name_ar` varchar(100) DEFAULT NULL,
  `name_en` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `organization_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_org_dept_ar` (`organization_id`,`name_ar`),
  UNIQUE KEY `unique_org_dept_en` (`organization_id`,`name_en`),
  CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,'الإداريين','Admins','2026-05-17 15:25:01',1),(2,'التمريض','Nurses','2026-05-17 15:25:01',1),(3,'الخدمات المساندة','Others','2026-05-17 15:25:01',1),(4,'الأطباء','Doctors','2026-05-17 15:25:01',1),(5,'الموارد البشرية','Human Resources','2026-05-18 08:26:57',2),(6,'المحاسبة والمالية','Accounting & Finance','2026-05-18 08:26:57',2),(7,'تقنية المعلومات','Information Technology','2026-05-18 08:26:57',2);
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_leave_balances`
--

DROP TABLE IF EXISTS `employee_leave_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_leave_balances` (
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `balance` int(11) DEFAULT 0,
  PRIMARY KEY (`employee_id`,`leave_type_id`),
  KEY `leave_type_id` (`leave_type_id`),
  CONSTRAINT `employee_leave_balances_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_leave_balances_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_leave_balances`
--

LOCK TABLES `employee_leave_balances` WRITE;
/*!40000 ALTER TABLE `employee_leave_balances` DISABLE KEYS */;
/*!40000 ALTER TABLE `employee_leave_balances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `employee_id_number` varchar(20) NOT NULL,
  `system_id` varchar(20) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `initial_leave_balance` int(11) DEFAULT 0,
  `leave_balance_verified` tinyint(1) DEFAULT 0,
  `registration_code` varchar(20) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `decision_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `can_request_leave` tinyint(1) DEFAULT 1,
  `organization_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id_number` (`employee_id_number`),
  UNIQUE KEY `system_id` (`system_id`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_ibfk_3` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (1,3,'1000000000','HR20262816','AbdulRahman alzahrani','0000000000',4,'consultant EM',94,1,NULL,'2026-05-17','approved','','2026-05-17 18:37:41','2026-05-17 15:37:31',1,1),(2,4,'1010000000','HR20268508','ahmed','0000000001',4,'اخصائي',0,0,NULL,NULL,'approved','','2026-05-17 19:24:09','2026-05-17 16:22:53',1,1);
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `holidays`
--

DROP TABLE IF EXISTS `holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_id` int(11) NOT NULL,
  `name_ar` varchar(150) NOT NULL,
  `name_en` varchar(150) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `holidays_ibfk_1` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `holidays`
--

LOCK TABLES `holidays` WRITE;
/*!40000 ALTER TABLE `holidays` DISABLE KEYS */;
/*!40000 ALTER TABLE `holidays` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_requests`
--

DROP TABLE IF EXISTS `leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `action_at` datetime DEFAULT NULL,
  `request_code` varchar(20) DEFAULT NULL,
  `manager_note` text DEFAULT NULL,
  `attachment_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `leave_type_id` (`leave_type_id`),
  CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_requests`
--

LOCK TABLES `leave_requests` WRITE;
/*!40000 ALTER TABLE `leave_requests` DISABLE KEYS */;
INSERT INTO `leave_requests` VALUES (1,1,1,'2026-06-01','2026-06-30','','rejected',NULL,NULL,'جميع الاجازات معلقه من قبل ادارة التجمع الصحي',NULL,'2026-05-17 15:40:44'),(2,1,1,'2026-05-26','2026-05-28','','approved',NULL,'LV-201508-1FCC','',NULL,'2026-05-17 17:15:08'),(3,1,2,'2026-05-17','2026-05-17','','approved',NULL,'LV-202802-8E82','',NULL,'2026-05-17 17:28:02'),(4,1,6,'2026-05-26','2026-05-30','','pending',NULL,'LV-221147-ABBC',NULL,'https://res.cloudinary.com/dbvx6lbko/image/upload/v1779045064/haram_photo_ft11io.jpg','2026-05-17 19:11:47'),(5,1,1,'2026-10-19','2026-10-27','','pending',NULL,'LV-112945-38FF',NULL,'https://res.cloudinary.com/dbvx6lbko/image/upload/v1779092982/kamcmakkah_kisoub.png','2026-05-18 08:29:45');
/*!40000 ALTER TABLE `leave_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_types`
--

DROP TABLE IF EXISTS `leave_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name_ar` varchar(100) DEFAULT NULL,
  `name_en` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deduct_from_balance` tinyint(1) DEFAULT 1,
  `max_days_per_year` int(11) DEFAULT 30,
  `organization_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_org_leave_ar` (`organization_id`,`name_ar`),
  UNIQUE KEY `unique_org_leave_en` (`organization_id`,`name_en`),
  CONSTRAINT `leave_types_ibfk_1` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_types`
--

LOCK TABLES `leave_types` WRITE;
/*!40000 ALTER TABLE `leave_types` DISABLE KEYS */;
INSERT INTO `leave_types` VALUES (1,'إجازة سنوية','Annual Leave','2026-05-17 15:25:01',1,30,1),(2,'إجازة تعليمية','Educational Leave','2026-05-17 15:25:01',1,30,1),(3,'إجازة اضطرارية','Emergency Leave','2026-05-17 15:25:01',1,30,1),(4,'إجازة بدون راتب','NO Salary Leave','2026-05-17 15:25:01',1,30,1),(5,'إجازة عيد الفطر','Eid AlFeter Leave','2026-05-17 18:30:57',1,7,1),(6,'إجازة عيد الأضحى','Eid AL Adhaha Leave','2026-05-17 18:31:55',1,5,1),(7,'إجازة سنوية','Annual Leave','2026-05-18 08:26:57',1,30,2),(8,'إجازة مرضية','Sick Leave','2026-05-18 08:26:57',1,15,2),(9,'إجازة اضطرارية','Emergency Leave','2026-05-18 08:26:57',1,5,2);
/*!40000 ALTER TABLE `leave_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `message_ar` text NOT NULL,
  `message_en` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,3,'✅ Your leave request has been approved','✅ Your leave request has been approved',1,'2026-05-17 17:25:55'),(2,3,'✅ Your leave request has been approved','✅ Your leave request has been approved',1,'2026-05-17 17:28:23');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organizations`
--

DROP TABLE IF EXISTS `organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name_ar` varchar(150) NOT NULL,
  `name_en` varchar(150) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `status` enum('active','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_ar` (`name_ar`),
  UNIQUE KEY `name_en` (`name_en`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organizations`
--

LOCK TABLES `organizations` WRITE;
/*!40000 ALTER TABLE `organizations` DISABLE KEYS */;
INSERT INTO `organizations` VALUES (1,'الجهة الافتراضية','Default Organization','default','active','2026-05-18 05:54:04'),(2,'طوارئ مستشفى الحرم-المدينة','ED-Haram Hospital - Madina','edharammadina','active','2026-05-18 08:26:57');
/*!40000 ALTER TABLE `organizations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `organization_id` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`organization_id`,`setting_key`),
  CONSTRAINT `settings_ibfk_1` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('allow_leave_requests','1','2026-05-17 16:56:27',1),('allow_past_leaves','0','2026-05-17 19:15:25',1),('allow_registration','1','2026-05-17 19:18:54',1),('font_family_ar','Cairo','2026-05-17 16:56:27',1),('font_family_en','Poppins','2026-05-17 17:08:18',1),('footer_text_ar','جميع الحقوق محفوظة','2026-05-17 16:56:27',1),('footer_text_en','All Rights Reserved','2026-05-17 16:56:27',1),('prevent_overlapping_leaves','1','2026-05-17 19:15:25',1),('primary_color','#0d6efd','2026-05-17 16:56:27',1),('site_name_ar','🏢 نظام إدارة الموظفين','2026-05-17 16:56:27',1),('site_name_en','🏢 HR Management System','2026-05-17 16:56:27',1),('allow_leave_requests','1','2026-05-18 08:26:57',2),('allow_past_leaves','1','2026-05-18 08:26:57',2),('allow_registration','1','2026-05-18 08:26:57',2),('font_family_ar','Cairo','2026-05-18 08:26:57',2),('font_family_en','Inter','2026-05-18 08:26:57',2),('footer_text_ar','جميع الحقوق محفوظة - طوارئ مستشفى الحرم-المدينة','2026-05-18 08:26:57',2),('footer_text_en','All Rights Reserved - ED-Haram Hospital - Madina','2026-05-18 08:26:57',2),('prevent_overlapping_leaves','1','2026-05-18 08:26:57',2),('primary_color','#0d6efd','2026-05-18 08:26:57',2),('site_name_ar','🏢 طوارئ مستشفى الحرم-المدينة','2026-05-18 08:26:57',2),('site_name_en','🏢 ED-Haram Hospital - Madina','2026-05-18 08:26:57',2);
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('super_admin','admin','manager','employee') NOT NULL DEFAULT 'employee',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `organization_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `organization_id` (`organization_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$12$zlKWSuwH4Nu63gHpea6H.uGGWZkqOf2VjmjekG.pxLT2q.JuSU6YG','admin@example.com','admin','2026-05-17 15:25:01',NULL,0,1),(3,'aaa','$2y$10$hwUDpLVVF2dimu35VYGsEO0NG3JwGa3pKjOFnW1hPHulCmaEqqgLG','apps@aamd.sa','employee','2026-05-17 15:37:31',NULL,0,1),(4,'zzz','$2y$10$xa6zlRFuweSrSNfNcJAvYOmfmace6OMi2Phya/YEtuwbSrPkBG2EC','ia3m@live.com','employee','2026-05-17 16:22:53',NULL,0,1),(5,'superadmin','$2y$12$zlKWSuwH4Nu63gHpea6H.uGGWZkqOf2VjmjekG.pxLT2q.JuSU6YG','super@example.com','super_admin','2026-05-18 05:58:05',NULL,0,NULL),(6,'khalid','$2y$10$6dqMhsJ/LAUrvqdw8LV7yOvI9xvhLSjJ016cg/tYpK43OCzQgLdd6','khalid@aa.cc','admin','2026-05-18 08:26:57',NULL,0,2);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-18 11:51:22
