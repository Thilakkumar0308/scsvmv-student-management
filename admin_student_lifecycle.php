<?php
$page_title = 'Student Lifecycle Automation';
require_once 'includes/header.php';
require_role('Admin');

/* ----------------------------------------
   Fetch classes
---------------------------------------- */
$classes = [];
$res = $conn->query("
    SELECT id, class_name, academic_year 
    FROM classes 
    ORDER BY class_name
");
while ($row = $res->fetch_assoc()) {
    $classes[] = $row;
}

/* ----------------------------------------
   Selected class
---------------------------------------- */
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

/* ----------------------------------------
   Fetch students
---------------------------------------- */
$students = [];
if ($class_id > 0) {
    $stmt = $conn->prepare("
        SELECT id, student_id, first_name, last_name, status
        FROM students
        WHERE class_id = ?
          AND status = 'Active'
        ORDER BY student_id
    ");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="container mt-4">

    <h4 class="mb-3">
        <i class="fas fa-sync-alt me-2"></i>Student Lifecycle Automation
    </h4>

    <!-- Success / Error messages -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'promoted'): ?>
            <div class="alert alert-success">Students promoted successfully.</div>
        <?php elseif ($_GET['msg'] === 'graduated'): ?>
            <div class="alert alert-success">Students graduated successfully.</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">Please select at least one student.</div>
    <?php endif; ?>

    <!-- Class Filter -->
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-6">
            <label class="form-label">Select Class</label>
            <select name="class_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Select Class --</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $class_id === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['class_name'] . ' (' . $c['academic_year'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($class_id > 0 && count($students) > 0): ?>

    <!-- Student List -->
    <form method="POST" action="admin_student_lifecycle_action.php">
        <input type="hidden" name="class_id" value="<?= $class_id ?>">

        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <input type="checkbox" id="selectAll" onclick="toggleAll(this)">
                <label for="selectAll" class="ms-2">Select All Students</label>
            </div>

            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th></th>
                            <th>Register No</th>
                            <th>Name</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                        <tr>
                            <td>
                                <input type="checkbox"
                                       name="student_ids[]"
                                       value="<?= (int)$s['id'] ?>"
                                       class="student-checkbox">
                            </td>
                            <td><?= htmlspecialchars($s['student_id']) ?></td>
                            <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                            <td>
                                <span class="badge bg-success"><?= htmlspecialchars($s['status']) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-footer text-end">
                <button type="submit"
                        name="action"
                        value="promote"
                        class="btn btn-warning me-2"
                        onclick="return confirmAction()">
                    Promote
                </button>

                <button type="submit"
                        name="action"
                        value="graduate"
                        class="btn btn-danger"
                        onclick="return confirmAction()">
                    Graduate
                </button>
            </div>
        </div>
    </form>

    <?php elseif ($class_id > 0): ?>
        <p class="text-muted">No active students in this class.</p>
    <?php endif; ?>

</div>

<script>
function toggleAll(master) {
    document.querySelectorAll('.student-checkbox').forEach(cb => {
        cb.checked = master.checked;
    });
}

function confirmAction() {
    const checked = document.querySelectorAll('.student-checkbox:checked').length;
    if (checked === 0) {
        alert('Please select at least one student.');
        return false;
    }
    return confirm('Are you sure you want to continue?');
}
</script>

<?php require_once 'includes/footer.php'; ?>
