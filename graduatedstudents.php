<?php
$page_title = 'Graduated Students';
require_once 'includes/header.php';
require_role('Admin');

/*
|--------------------------------------------------------------------------
| STEP 1: FETCH COURSES (FROM classes TABLE)
|--------------------------------------------------------------------------
*/
$courses = [];
$res = $conn->query("
    SELECT DISTINCT class_name 
    FROM classes
    ORDER BY class_name
");
while ($row = $res->fetch_assoc()) {
    $courses[] = $row['class_name'];
}

/*
|--------------------------------------------------------------------------
| STEP 2: READ FILTERS
|--------------------------------------------------------------------------
*/
$course = $_GET['course'] ?? '';
$batch  = $_GET['batch'] ?? '';

/*
|--------------------------------------------------------------------------
| STEP 3: FETCH BATCHES FOR SELECTED COURSE
|--------------------------------------------------------------------------
*/
$batches = [];
if ($course) {
    $stmt = $conn->prepare("
        SELECT DISTINCT academic_year
        FROM classes
        WHERE class_name = ?
        ORDER BY academic_year DESC
    ");
    $stmt->bind_param("s", $course);
    $stmt->execute();
    $batches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/*
|--------------------------------------------------------------------------
| STEP 4: FETCH GRADUATED STUDENTS (CORRECT LOGIC)
|--------------------------------------------------------------------------
| IMPORTANT:
| Graduated students have class_id = NULL
| So we FILTER BY admission_date YEAR
|--------------------------------------------------------------------------
*/
$students = [];
if ($course && $batch) {

    // Example batch: 2024-2026 → admission year = 2024
    [$admissionYear, $gradYear] = array_map('intval', explode('-', $batch));

    $stmt = $conn->prepare("
        SELECT student_id, first_name, last_name, email, phone
        FROM students
        WHERE status = 'Graduated'
          AND admission_date IS NOT NULL
          AND YEAR(admission_date) = ?
        ORDER BY CAST(student_id AS UNSIGNED)
    ");

    $stmt->bind_param("i", $admissionYear);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="container mt-4">

    <h4 class="mb-4">
        <i class="fas fa-user-graduate me-2"></i>Graduated Students
    </h4>

    <!-- FILTERS -->
    <form method="GET" class="row g-3 mb-4">

        <!-- COURSE -->
        <div class="col-md-4">
            <label class="form-label">Course</label>
            <select name="course" class="form-select" onchange="this.form.submit()">
                <option value="">-- Select Course --</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $course === $c ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- BATCH -->
        <?php if ($course): ?>
        <div class="col-md-4">
            <label class="form-label">Batch</label>
            <select name="batch" class="form-select" onchange="this.form.submit()">
                <option value="">-- Select Batch --</option>
                <?php foreach ($batches as $b): ?>
                    <option value="<?= $b['academic_year'] ?>" <?= $batch === $b['academic_year'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($course . ' (' . $b['academic_year'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

    </form>

    <!-- STUDENT TABLE -->
    <?php if ($students): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <?= htmlspecialchars($course . ' (' . $batch . ') - Graduated Students') ?>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
<thead class="table-dark">
    <tr>
        <th>Register No</th>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Status</th>
        <th>Profile</th>
    </tr>
</thead>

                <tbody>
                    <?php foreach ($students as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['student_id']) ?></td>
                        <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                        <td><?= htmlspecialchars($s['email']) ?></td>
                        <td><?= htmlspecialchars($s['phone']) ?></td>
                        <td>
    <span class="badge bg-secondary">Graduated</span>
</td>

<td class="text-center">
    <a href="alumni_profile.php?student_id=<?= urlencode($s['student_id']) ?>"
       class="btn btn-sm btn-outline-primary"
       title="View Alumni Profile">
        <i class="fas fa-user-graduate"></i>
    </a>
</td>


                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($course && $batch): ?>
        <div class="alert alert-info text-center">
            No graduated students found for this batch.
        </div>
    <?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
