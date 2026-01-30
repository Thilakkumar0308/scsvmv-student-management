<?php
$page_title = 'Alumni Profile';
require_once 'includes/header.php';
require_role('Admin');

/* -------------------------------------------------
   VALIDATE INPUT
------------------------------------------------- */
$student_id = $_GET['student_id'] ?? '';
if (!$student_id) {
    echo "<div class='alert alert-danger m-3'>Invalid student.</div>";
    require_once 'includes/footer.php';
    exit;
}

/* -------------------------------------------------
   FETCH GRADUATED STUDENT
------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT *
    FROM students
    WHERE student_id = ?
      AND status = 'Graduated'
    LIMIT 1
");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    echo "<div class='alert alert-warning m-3'>Alumni not found.</div>";
    require_once 'includes/footer.php';
    exit;
}

/* -------------------------------------------------
   DERIVE COURSE + BATCH (CRITICAL FIX)
------------------------------------------------- */
$course = 'N/A';
$batch  = 'N/A';

if (!empty($student['admission_date'])) {

    $admissionYear = (int)date('Y', strtotime($student['admission_date']));
    $graduationYear = $admissionYear + 2; // default 2-year program
    $batch = $admissionYear . ' - ' . $graduationYear;

    // derive course from classes table
    $stmt = $conn->prepare("
        SELECT class_name
        FROM classes
        WHERE academic_year LIKE CONCAT(?, '%')
        ORDER BY id ASC
        LIMIT 1
    ");
    $stmt->bind_param("i", $admissionYear);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $course = $row['class_name'];
    }
}

/* -------------------------------------------------
   FETCH ALUMNI PROFILE (OPTIONAL)
------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT *
    FROM alumni_profiles
    WHERE student_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $student['id']);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

/* -------------------------------------------------
   HELPERS
------------------------------------------------- */
function profilePic($pic) {
    if (!$pic || $pic === 'null' || $pic === 'undefined') {
        return 'uploads/profile_pictures/default.svg';
    }
    return htmlspecialchars($pic);
}
?>

<div class="container mt-4">

    <h4 class="mb-4">
        <i class="fas fa-user-graduate me-2"></i> Alumni Profile
    </h4>

    <div class="card shadow-lg">
        <div class="card-body">

            <div class="row g-4">

                <!-- PROFILE -->
                <div class="col-md-3 text-center">
                    <img src="<?= profilePic($student['profile_picture']) ?>"
                         class="img-fluid shadow-sm mb-3"
                         style="width:150px;height:180px;object-fit:cover;border-radius:6px;">

                    <h5 class="mb-1">
                        <?= htmlspecialchars($student['first_name'].' '.$student['last_name']) ?>
                    </h5>

                    <p class="text-muted mb-1">
                        <?= htmlspecialchars($student['student_id']) ?>
                    </p>

                    <span class="badge bg-secondary">
                        <i class="fas fa-user-graduate me-1"></i> Alumni
                    </span>
                </div>

                <!-- ACADEMIC INFO -->
                <div class="col-md-5">
                    <h5 class="mb-3">Academic Information</h5>
                    <table class="table table-bordered table-striped">
                        <tbody>
                            <tr>
                                <th>Course</th>
                                <td><?= htmlspecialchars($course) ?></td>
                            </tr>
                            <tr>
                                <th>Batch</th>
                                <td><?= htmlspecialchars($batch) ?></td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td><?= htmlspecialchars($student['email']) ?></td>
                            </tr>
                            <tr>
                                <th>Phone</th>
                                <td><?= htmlspecialchars($student['phone']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- PROFESSIONAL INFO -->
                <div class="col-md-4">
                    <h5 class="mb-3">Professional Details</h5>
                    <table class="table table-bordered table-striped">
                        <tbody>
                            <tr>
                                <th>Company</th>
                                <td><?= htmlspecialchars($profile['current_company'] ?? '-') ?></td>
                            </tr>
                            <tr>
                                <th>Designation</th>
                                <td><?= htmlspecialchars($profile['designation'] ?? '-') ?></td>
                            </tr>
                            <tr>
                                <th>Industry</th>
                                <td><?= htmlspecialchars($profile['industry'] ?? '-') ?></td>
                            </tr>
                            <tr>
                                <th>Location</th>
                                <td><?= htmlspecialchars($profile['location'] ?? '-') ?></td>
                            </tr>
                            <tr>
                                <th>Higher Studies</th>
                                <td><?= htmlspecialchars($profile['higher_studies'] ?? '-') ?></td>
                            </tr>
                            <tr>
                                <th>LinkedIn</th>
                                <td>
                                    <?php if (!empty($profile['linkedin_url'])): ?>
                                        <a href="<?= htmlspecialchars($profile['linkedin_url']) ?>" target="_blank">
                                            <i class="fab fa-linkedin"></i> View
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>

            <hr>

            <!-- ACTIONS -->
            <div class="d-flex justify-content-end gap-2">
                <a href="edit_alumni_profile.php?student_id=<?= urlencode($student['student_id']) ?>"
                   class="btn btn-warning">
                    <i class="fas fa-edit me-1"></i> Edit Alumni Profile
                </a>

                <button class="btn btn-secondary" onclick="history.back()">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </button>
            </div>

        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
