<?php
require_once '../includes/config.php';

// إذا كان المستخدم مسجلاً بالفعل، يتم توجيهه للرئيسية
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = __('all_fields_required');
    } else {
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $manager_name = trim($_POST['manager_name'] ?? '');
    $manager_email = trim($_POST['manager_email'] ?? '');
    $manager_phone = trim($_POST['manager_phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($name_ar) || empty($name_en) || empty($slug) || empty($manager_name) || empty($manager_email) || empty($manager_phone)) {
        $error = __('all_fields_required');
    } elseif (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
        $error = __('slug_format_error');
    } else {
        try {
            // التحقق من أن Slug غير مستخدم مسبقاً في المؤسسات النشطة أو الطلبات القائمة
            $stmt_check1 = $pdo->prepare("SELECT COUNT(*) FROM organizations WHERE slug = ?");
            $stmt_check1->execute([$slug]);
            
            $stmt_check2 = $pdo->prepare("SELECT COUNT(*) FROM organization_requests WHERE slug = ? AND status = 'pending'");
            $stmt_check2->execute([$slug]);

            if ($stmt_check1->fetchColumn() > 0 || $stmt_check2->fetchColumn() > 0) {
                $error = __('slug_used');
            } else {
                $stmt = $pdo->prepare("INSERT INTO organization_requests (name_ar, name_en, slug, manager_name, manager_email, manager_phone, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                if ($stmt->execute([$name_ar, $name_en, $slug, $manager_name, $manager_email, $manager_phone, $notes])) {
                    $success = __('request_submitted');
                }
            }
        } catch (PDOException $e) {
            $error = __('request_error') . ': ' . $e->getMessage();
        }
    }
    }
}

$pageTitle = __('request_new_org_title');
include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center py-5">
        <div class="col-md-8">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="display-4 text-primary mb-2"><span class="emoji-icon">🏢➕</span></div>
                        <h1 class="h4 fw-bold"><?php echo __('request_new_org_title'); ?></h1>
                        <p class="text-muted small"><?php echo __('request_org_desc'); ?></p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger shadow-sm border-0"><span class="emoji-icon">⚠️</span> <?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success shadow-sm border-0 text-center py-4">
                            <h5 class="fw-bold mb-3"><span class="emoji-icon">✅</span> <?php echo $success; ?></h5>
                            <p class="mb-0 text-muted small"><?php echo __('follow_up_email'); ?></p>
                            <a href="login.php" class="btn btn-primary mt-4 px-5 shadow-sm"><?php echo __('login'); ?></a>
                        </div>
                    <?php else: ?>

                    <form action="request_org.php" method="POST" autocomplete="off">
                        <?php echo csrf_field(); ?>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <h6 class="fw-bold border-bottom pb-2 text-primary"><?php echo __('org_info'); ?></h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold"><?php echo __('org_name_ar'); ?></label>
                                <input type="text" name="name_ar" class="form-control bg-light" placeholder="<?php echo __('org_name_ar_example'); ?>" required value="<?php echo h($_POST['name_ar'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold"><?php echo __('org_name_en'); ?></label>
                                <input type="text" name="name_en" class="form-control bg-light" placeholder="<?php echo __('org_name_en_example'); ?>" required value="<?php echo h($_POST['name_en'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label small fw-bold"><?php echo __('org_slug'); ?></label>
                                <input type="text" name="slug" class="form-control bg-light" placeholder="<?php echo __('org_slug_example'); ?>" required pattern="^[a-z0-9\-]+$" value="<?php echo h($_POST['slug'] ?? ''); ?>">
                                <div class="form-text small opacity-75"><?php echo __('slug_tip'); ?></div>
                            </div>

                            <div class="col-md-12 mb-3 mt-3">
                                <h6 class="fw-bold border-bottom pb-2 text-success"><?php echo __('manager_info'); ?></h6>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label small fw-bold"><?php echo __('manager_full_name_label'); ?> *</label>
                                <input type="text" name="manager_name" class="form-control bg-light" required placeholder="<?php echo __('full_name_placeholder'); ?>" value="<?php echo h($_POST['manager_name'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label small fw-bold"><?php echo __('manager_email_label'); ?> *</label>
                                <input type="email" name="manager_email" class="form-control bg-light" required placeholder="<?php echo __('email_placeholder'); ?>" value="<?php echo h($_POST['manager_email'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label small fw-bold"><?php echo __('manager_phone_label'); ?> *</label>
                                <input type="text" name="manager_phone" class="form-control bg-light" required placeholder="<?php echo __('phone_placeholder'); ?>" value="<?php echo h($_POST['manager_phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label small fw-bold"><?php echo __('notes_label');?></label>
                                <textarea name="notes" class="form-control bg-light" rows="3" placeholder="<?php echo __('notes_placeholder'); ?>"><?php echo h($_POST['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm"><?php echo __('submit_request'); ?></button>
                        </div>
                        <div class="text-center mt-3">
                            <a href="login.php" class="small fw-bold text-decoration-none"><?php echo __('login'); ?></a>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
