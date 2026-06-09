<?php
require_once '../includes/config.php';
checkAuth(['admin', 'manager']);

$pageTitle = __('leave_calendar');
include '../includes/header.php';

$org_id = CURRENT_ORG_ID;

// Fetch all approved leave requests
$stmt = $pdo->prepare("SELECT lr.start_date, lr.end_date, e.full_name, lt.name_ar as type_ar, lt.name_en as type_en, lr.status
                       FROM leave_requests lr
                       JOIN employees e ON lr.employee_id = e.id
                       JOIN leave_types lt ON lr.leave_type_id = lt.id
                       WHERE lr.organization_id = ? AND lr.status IN ('approved', 'pending')
                       ORDER BY lr.start_date ASC");
$stmt->execute([$org_id]);
$leaves = $stmt->fetchAll();

$events = [];
foreach ($leaves as $leave) {
    $color = $leave['status'] == 'approved' ? '#198754' : '#ffc107';
    $events[] = [
        'title' => $leave['full_name'] . ' - ' . get_name(['name_ar' => $leave['type_ar'], 'name_en' => $leave['type_en']]),
        'start' => $leave['start_date'],
        'end' => date('Y-m-d', strtotime($leave['end_date'] . ' +1 day')),
        'color' => $color,
        'textColor' => '#fff',
    ];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><?php echo __('leave_calendar'); ?></h1>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div id="calendar"></div>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: '<?php echo __('lang_code') == 'ar' ? 'ar' : 'en'; ?>',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,dayGridWeek'
        },
        buttonText: {
            today: '<?php echo __('today'); ?>',
            month: '<?php echo __('month'); ?>',
            week: '<?php echo __('week'); ?>'
        },
        events: <?php echo json_encode($events); ?>,
        height: 'auto',
        firstDay: 1
    });
    calendar.render();
});
</script>

<?php include '../includes/footer.php'; ?>
