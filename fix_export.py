import re

# Patch manage.php
filepath = "/Applications/XAMPP/xamppfiles/htdocs/HR-App/leaves/manage.php"

with open(filepath, 'r', encoding='utf-8') as f:
    content = f.read()

csv_export_manage = """
// Excel (CSV) Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Leave_Requests_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    // Add UTF-8 BOM for proper Arabic rendering in Excel
    fputs($output, "\\xEF\\xBB\\xBF");
    
    // Headers
    fputcsv($output, ['الموظف', 'الرقم الوظيفي', 'نوع الإجازة', 'من تاريخ', 'إلى تاريخ', 'تاريخ التقديم', 'الحالة', 'تاريخ القرار', 'السبب']);
    
    // Data
    foreach ($requests as $req) {
        $status_text = $req['status'] == 'approved' ? 'مقبول' : ($req['status'] == 'rejected' ? 'مرفوض' : 'معلق');
        fputcsv($output, [
            $req['full_name'],
            $req['employee_id_number'],
            $req['type_ar'],
            $req['start_date'],
            $req['end_date'],
            date('Y-m-d', strtotime($req['created_at'])),
            $status_text,
            $req['action_at'] ? date('Y-m-d', strtotime($req['action_at'])) : '-',
            $req['reason']
        ]);
    }
    fclose($output);
    exit;
}
"""

content = re.sub(
    r"// Excel Export.*?exit;\s*}",
    csv_export_manage,
    content,
    flags=re.DOTALL
)

with open(filepath, 'w', encoding='utf-8') as f:
    f.write(content)


# Patch list.php
filepath = "/Applications/XAMPP/xamppfiles/htdocs/HR-App/employees/list.php"

with open(filepath, 'r', encoding='utf-8') as f:
    content = f.read()

csv_export_list = """
// Excel (CSV) Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Employees_List_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    // Add UTF-8 BOM for proper Arabic rendering in Excel
    fputs($output, "\\xEF\\xBB\\xBF");
    
    // Headers
    fputcsv($output, ['المعرف الخاص', 'الرقم الوظيفي', 'الاسم الكامل', 'القسم', 'المسمى الوظيفي', 'تاريخ التوظيف', 'حالة الحساب']);
    
    // Data
    foreach ($employees as $emp) {
        $status_text = $emp['status'] == 'approved' ? 'مفعل' : ($emp['status'] == 'rejected' ? 'مرفوض' : 'معلق');
        fputcsv($output, [
            $emp['system_id'],
            $emp['employee_id_number'],
            $emp['full_name'],
            $emp['dept_ar'],
            $emp['job_title'],
            $emp['hire_date'],
            $status_text
        ]);
    }
    fclose($output);
    exit;
}
"""

content = re.sub(
    r"// Excel Export.*?exit;\s*}",
    csv_export_list,
    content,
    flags=re.DOTALL
)

with open(filepath, 'w', encoding='utf-8') as f:
    f.write(content)

print("Export logic updated to standard CSV")
