<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// student_info.php
$page_title = 'Student Information';
require_once 'includes/header.php';
require_once 'includes/daemail.php'; // ensure sendDAEmail() is available

// Helper (if not already present)
if (!function_exists('post_str')) {
    function post_str($key, $default = '') {
        return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
    }
}
if (!function_exists('sanitize_input')) {
    function sanitize_input($v) {
        return isset($v) ? trim($v) : '';
    }
}

// Accept either internal id (id) or external student id (student_id) in GET
$student_lookup_internal_id = null;
$student_lookup_external_id = null;

if (isset($_GET['id']) && $_GET['id'] !== '') {
    // internal DB id passed (e.g., from disciplinary.php links)
    $student_lookup_internal_id = (int)$_GET['id'];
} elseif (isset($_GET['student_id']) && $_GET['student_id'] !== '') {
    // external student identifier (student_id column)
    $student_lookup_external_id = trim($_GET['student_id']);
} else {
    echo "<div class='alert alert-danger m-3'>No student selected.</div>";
    require_once 'includes/footer.php';
    exit;
}

// Messages to show at top
$message = '';
$message_type = 'info';

// ---------- HANDLE POST (add / edit / delete DA and edit student) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = post_str('action');

    /* -----------------------
       ADD DISCIPLINARY (from student page)
    ------------------------*/
    if ($action === 'add') {
        if (!has_any_role(['HOD','Admin','Teacher'])) {
            redirect('dashboard.php');
            exit;
        }

        $student_id  = (int)($_POST['student_id'] ?? 0);
        $action_type = post_str('action_type');
        // use 'reason' to match disciplinary.php
        $da_reason   = sanitize_input($_POST['reason'] ?? '');
        $action_date = post_str('action_date');
        $status      = post_str('status', 'Active');
        $imposed_by  = (int)($_SESSION['user_id'] ?? 0);

        // Server-side validation
        if ($student_id <= 0) {
            $message = 'Please select a valid student.';
            $message_type = 'danger';
        } elseif (empty($action_type)) {
            $message = 'Please select an action type.';
            $message_type = 'danger';
        } elseif (empty($da_reason)) {
            $message = 'Please enter a reason/description.';
            $message_type = 'danger';
        } elseif (empty($action_date)) {
            $message = 'Please enter an action date.';
            $message_type = 'danger';
        } else {
            $resolved_reason = null; // NULL for new records

            // Prepare insert
            $stmt = $conn->prepare("
                INSERT INTO disciplinary_actions 
                (student_id, action_type, da_reason, resolved_reason, action_date, imposed_by, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                $message = 'DB prepare failed: ' . $conn->error;
                $message_type = 'danger';
            } else {
                // types: i s s s s i s => "issssis" (7 params)
                $stmt->bind_param("issssis", $student_id, $action_type, $da_reason, $resolved_reason, $action_date, $imposed_by, $status);

                if ($stmt->execute()) {
                    $inserted_id = $stmt->insert_id;
                    $message = 'Disciplinary action recorded successfully';
                    $message_type = 'success';
                    
                    // Send email since this was successful
                    $student_email = $student_fname = $student_lname = null;

                    $stmt2 = $conn->prepare("
                        SELECT s.email, s.first_name, s.last_name
                        FROM students s
                        WHERE s.id = ?
                    ");
                    if ($stmt2) {
                        $stmt2->bind_param("i", $student_id);
                        if ($stmt2->execute()) {
                            $stmt2->bind_result($student_email, $student_fname, $student_lname);
                            $stmt2->fetch();
                        }
                        $stmt2->close();
                    }

                    if (!empty($student_email)) {
                        $ok = sendDAEmail(
                            $student_email,
                            trim($student_fname . ' ' . $student_lname),
                            $action_type,
                            $da_reason,
                            null,  // No resolved reason for ADD
                            $action_date,
                            'add'
                        );

                        $message .= $ok ? ' (email sent)' : ' (email not sent)';
                    }
                } else {
                    $message = 'Error recording disciplinary action: ' . $stmt->error;
                    $message_type = 'danger';
                }
                $stmt->close();
            }
        }
    }

    /* -----------------------
       EDIT DISCIPLINARY
    ------------------------*/
    elseif ($action === 'edit') {
        if (!has_any_role(['HOD','Admin'])) {
            redirect('dashboard.php');
            exit;
        }

        $id              = (int)($_POST['id'] ?? 0);
        $action_type     = post_str('action_type');
        $da_reason       = sanitize_input($_POST['reason'] ?? '');
        $resolved_reason = sanitize_input($_POST['resolved_reason'] ?? '');
        $action_date     = post_str('action_date');
        $status          = post_str('status', 'Active');

        if ($id <= 0) {
            $message = 'Invalid record ID.';
            $message_type = 'danger';
        } elseif (
            empty($action_type) ||
            empty($da_reason) ||
            empty($action_date) ||
            ($status === 'Resolved' && empty($resolved_reason))
        ) {
            $message = 'Please fill all required fields.';
            $message_type = 'danger';
        } else {
            $stmt = $conn->prepare("
                UPDATE disciplinary_actions
                SET action_type = ?, da_reason = ?, resolved_reason = ?, action_date = ?, status = ?
                WHERE id = ?
            ");
            if (!$stmt) {
                $message = 'DB prepare failed: ' . $conn->error;
                $message_type = 'danger';
            } else {
                // 5 strings + 1 integer => "sssssi"
                $stmt->bind_param("sssssi", $action_type, $da_reason, $resolved_reason, $action_date, $status, $id);

                if ($stmt->execute()) {
                    $message = 'Disciplinary action updated successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating disciplinary action: ' . $stmt->error;
                    $message_type = 'danger';
                }
                $stmt->close();
            }

            // Send email to student if update succeeded
            if ($message_type === 'success') {
                $student_email = $student_fname = $student_lname = null;
                $stmt2 = $conn->prepare("
                    SELECT s.email, s.first_name, s.last_name
                    FROM disciplinary_actions da
                    JOIN students s ON da.student_id = s.id
                    WHERE da.id = ?
                ");
                if ($stmt2) {
                    $stmt2->bind_param("i", $id);
                    if ($stmt2->execute()) {
                        $stmt2->bind_result($student_email, $student_fname, $student_lname);
                        $stmt2->fetch();
                    }
                    $stmt2->close();
                }

                if (!empty($student_email)) {
                    $ok = sendDAEmail(
                        $student_email,
                        trim($student_fname . ' ' . $student_lname),
                        $action_type,
                        $da_reason,
                        $resolved_reason,
                        $action_date,
                        'edit'
                    );

                    $message .= $ok ? ' (email sent)' : ' (email not sent)';
                }
            }
        }
    }

    /* -----------------------
       DELETE DISCIPLINARY
    ------------------------*/
    elseif ($action === 'delete') {
        if (!has_any_role(['HOD','Admin'])) {
            redirect('dashboard.php');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $message = 'Invalid ID for deletion.';
            $message_type = 'danger';
        } else {
            // fetch details for email
            $student_email = $student_fname = $student_lname = $action_type = $da_reason = $resolved_reason = $action_date = null;
            $stmt2 = $conn->prepare("
                SELECT s.email, s.first_name, s.last_name, da.action_type, da.da_reason, da.resolved_reason, da.action_date
                FROM disciplinary_actions da
                JOIN students s ON da.student_id = s.id
                WHERE da.id = ?
            ");
            if ($stmt2) {
                $stmt2->bind_param("i", $id);
                if ($stmt2->execute()) {
                    $stmt2->bind_result($student_email, $student_fname, $student_lname, $action_type, $da_reason, $resolved_reason, $action_date);
                    $stmt2->fetch();
                }
                $stmt2->close();
            }

            // delete
            $stmt = $conn->prepare("DELETE FROM disciplinary_actions WHERE id = ?");
            if (!$stmt) {
                $message = 'DB prepare failed: ' . $conn->error;
                $message_type = 'danger';
            } else {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $message = 'Disciplinary action deleted successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Error deleting disciplinary action: ' . $stmt->error;
                    $message_type = 'danger';
                }
                $stmt->close();
            }

            // optional: send email about deletion if needed (kept out here to match disciplinary.php behavior)
        }
    }


    /* -----------------------
       EDIT STUDENT (simple inline update)
    ------------------------*/
    elseif ($action === 'edit_student') {
        if (!has_any_role(['HOD','Admin'])) {
            $message = 'Access denied';
            $message_type = 'danger';
        } else {
            $sid = (int)($_POST['sid'] ?? 0);
            $first_name = sanitize_input($_POST['first_name'] ?? '');
            $last_name  = sanitize_input($_POST['last_name'] ?? '');
            $email      = sanitize_input($_POST['email'] ?? '');
            $phone      = sanitize_input($_POST['phone'] ?? '');
            $address    = sanitize_input($_POST['address'] ?? '');
            $parent_name  = sanitize_input($_POST['parent_name'] ?? '');
            $parent_phone = sanitize_input($_POST['parent_phone'] ?? '');
            $class_id   = (int)($_POST['class_id'] ?? 0);
            $status     = sanitize_input($_POST['status'] ?? 'Active');

            if ($sid <= 0 || empty($first_name) || empty($last_name)) {
                $message = 'Please provide student first and last name.';
                $message_type = 'danger';
            } else {
                $stmt = $conn->prepare("
                    UPDATE students
                    SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, parent_name = ?, parent_phone = ?, class_id = ?, status = ?
                    WHERE id = ?
                ");
                if ($stmt) {
                    $stmt->bind_param("sssssssisi", $first_name, $last_name, $email, $phone, $address, $parent_name, $parent_phone, $class_id, $status, $sid);
                    if ($stmt->execute()) {
                        $message = 'Student updated successfully';
                        $message_type = 'success';
                    } else {
                        $message = 'Student update failed: ' . $stmt->error;
                        $message_type = 'danger';
                    }
                    $stmt->close();
                } else {
                    $message = 'DB prepare failed: ' . $conn->error;
                    $message_type = 'danger';
                }
            }
        }
    }

    else {
        $message = 'Invalid action specified.';
        $message_type = 'danger';
    }

    // After any POST action, re-fetch student and disciplinary below so page shows latest
}

// ---------- FETCH STUDENT (fresh) ----------
$student = null;
if ($student_lookup_internal_id !== null) {
    $stmt = $conn->prepare("
        SELECT s.*, c.class_name, c.section, d.department_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN departments d ON c.department_id = d.id
        WHERE s.id = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("i", $student_lookup_internal_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();
    }
} elseif ($student_lookup_external_id !== null) {
    $stmt = $conn->prepare("
        SELECT s.*, c.class_name, c.section, d.department_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN departments d ON c.department_id = d.id
        WHERE s.student_id = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("s", $student_lookup_external_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();
    }
}

if (!$student) {
    echo "<div class='alert alert-warning m-3'>Student not found.</div>";
    require_once 'includes/footer.php';
    exit;
}

// Default values from current class (ACTIVE students)
$departmentName = $student['department_name'] ?? '';
$courseName = $student['class_name'] ?? '';

// ===== HANDLE GRADUATED STUDENT DISPLAY DATA =====
$is_graduated = ($student['status'] === 'Graduated');

// Batch (from admission_date)
$admissionYear = $student['admission_date']
    ? date('Y', strtotime($student['admission_date']))
    : '';

$graduationYear = $admissionYear ? $admissionYear + 2 : '';

// Defaults from active class
$courseName = $student['class_name'] ?? '';
$departmentName = $student['department_name'] ?? '';

if ($is_graduated && $admissionYear) {

    // Try alumni_profiles first (BEST)
    $stmt = $conn->prepare("
        SELECT ap.course, d.department_name
        FROM alumni_profiles ap
        LEFT JOIN classes c ON c.class_name = ap.course
        LEFT JOIN departments d ON d.id = c.department_id
        WHERE ap.student_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $student['id']);
    $stmt->execute();
    $alumni = $stmt->get_result()->fetch_assoc();

    if ($alumni) {
        $courseName = $alumni['course'];
        $departmentName = $alumni['department_name'];
    }

    // Fallback: derive from academic year
    if (!$courseName) {
        $stmt = $conn->prepare("
            SELECT c.class_name, d.department_name
            FROM classes c
            LEFT JOIN departments d ON d.id = c.department_id
            WHERE c.academic_year LIKE CONCAT(?, '%')
            ORDER BY c.id DESC
            LIMIT 1
        ");
        $stmt->bind_param("s", $admissionYear);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row) {
            $courseName = $row['class_name'];
            $departmentName = $row['department_name'];
        }
    }
}

// Final honest fallback
$courseName = $courseName ?: 'Unknown Course';
$departmentName = $departmentName ?: 'Unknown Department';

// Helper for profile picture
function getProfilePictureUrl($filename) {
    if (!$filename || $filename == "null" || $filename == "undefined") {
        return "uploads/profile_pictures/default.svg";
    }
    return htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
}

// ---------- FETCH DISCIPLINARY RECORDS ----------
$disciplinary_records = [];
$da_stmt = $conn->prepare("SELECT * FROM disciplinary_actions WHERE student_id = ? ORDER BY action_date DESC, created_at DESC");
if ($da_stmt) {
    $da_stmt->bind_param("i", $student['id']);
    $da_stmt->execute();
    $disc_result = $da_stmt->get_result();
    while ($row = $disc_result->fetch_assoc()) {
        $disciplinary_records[] = $row;
    }
    $da_stmt->close();
}

// Latest DA (for popup)
$latest_da = count($disciplinary_records) > 0 ? $disciplinary_records[0] : null;

// can edit student?
$can_edit_student = has_any_role(['HOD','Admin']);
?>

<link rel="stylesheet" href="assets/css/profilepic.css">

<div class="container mt-4">
  <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show">
      <?php echo htmlspecialchars($message); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="card shadow-lg mb-4">
    <div class="card-body">
      <div class="row g-4 align-items-start">

        <!-- Profile Picture -->
        <div class="col-12 col-md-3 text-center">
          <img src="<?php echo getProfilePictureUrl($student['profile_picture']); ?>"
     class="student-profile-pic img-fluid mb-3 shadow-sm"
     alt="Profile Picture"
     style="width:140px; height:160px; object-fit:cover; border-radius:0;">


          <h4 class="mb-1"><?php echo htmlspecialchars($student['first_name'] . " " . $student['last_name']); ?></h4>
          <p class="text-muted"><?php echo htmlspecialchars($student['student_id']); ?></p>
          <span class="badge bg-<?php echo $student['status'] == 'Active' ? 'success' : ($student['status'] == 'Inactive' ? 'warning' : 'info'); ?>">
              <?php echo htmlspecialchars($student['status']); ?>
          </span>
        </div>

        <!-- Student Info -->
        <div class="col-12 col-lg-6">
          <h3 class="mb-3">Student Information</h3>
          <div class="table-responsive">
            <table class="table table-bordered table-striped">
              <tbody>
                <tr><th>Full Name</th><td><?= htmlspecialchars($student['first_name'] . " " . $student['last_name']); ?></td></tr>
                <tr><th>Email</th><td><?= htmlspecialchars($student['email']); ?></td></tr>
                <tr><th>Phone</th><td><?= htmlspecialchars($student['phone']); ?></td></tr>
                <tr><th>Date of Birth</th><td><?= $student['date_of_birth'] ? date('M d, Y', strtotime($student['date_of_birth'])) : 'Not provided'; ?></td></tr>
                <tr><th>Gender</th><td><?= htmlspecialchars($student['gender']); ?></td></tr>
                <tr><th>Address</th><td><?= nl2br(htmlspecialchars($student['address'])); ?></td></tr>
<tr>
  <th>Class</th>
  <td>
    <?php if ($is_graduated): ?>
        <?= htmlspecialchars($courseName . " ({$admissionYear} - {$graduationYear})") ?>
    <?php else: ?>
        <?= htmlspecialchars($student['class_name'] . ' ' . $student['section']) ?>
    <?php endif; ?>
  </td>
</tr>

<tr>
  <th>Department</th>
  <td><?= htmlspecialchars($departmentName) ?></td>
</tr>

                <tr><th>Admission Date</th><td><?= $student['admission_date'] ? date('M d, Y', strtotime($student['admission_date'])) : 'Not provided'; ?></td></tr>
                <tr><th>Parent Name</th><td><?= htmlspecialchars($student['parent_name']); ?></td></tr>
                <tr><th>Parent Phone</th><td><?= htmlspecialchars($student['parent_phone']); ?></td></tr>
              </tbody>
            </table>
          </div>

          <!-- Disciplinary Records -->
          <div class="mt-4">
            <h5>Disciplinary Records
              <button class="btn btn-sm btn-outline-primary float-end" data-bs-toggle="modal" data-bs-target="#addDisciplinaryModal">
                <i class="fas fa-plus me-1"></i> Add
              </button>
            </h5>

            <?php if(count($disciplinary_records) > 0): ?>
            <div class="table-responsive">
              <table class="table table-bordered table-striped">
                <thead class="table-dark">
                  <tr>
                    <th>Date</th>
                    <th>Action Type</th>
                    <th>Reason</th>
                    <th>Resolved Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($disciplinary_records as $d): ?>
                  <tr>
                    <td><?= !empty($d['action_date']) ? date('M d, Y', strtotime($d['action_date'])) : ''; ?></td>
                    <td><?= htmlspecialchars($d['action_type']); ?></td>
                    <td><?= nl2br(htmlspecialchars($d['da_reason'] ?? '')); ?></td>
                    <td><?= nl2br(htmlspecialchars($d['resolved_reason'] ?? '')); ?></td>
                    <td><?= htmlspecialchars($d['status']); ?></td>
                    <td>
                      <?php if (has_any_role(['HOD','Admin'])): ?>
                        <button class="btn btn-sm btn-outline-primary" onclick='openEditDA(<?php echo json_encode($d, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'>
                          <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                      <?php else: ?>
                        <span class="text-muted">No Access</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php else: ?>
              <p class="text-muted">No disciplinary records found.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-12 col-lg-3">
          <div class="card border-0 shadow-sm bg-light h-100">
            <div class="card-body text-center">
              <h5 class="fw-bold mb-3 text-primary"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
              <div class="d-grid gap-2">
<div class="d-grid gap-2">

    <!-- ✅ NEW PRINT BUTTON -->
    <a class="btn btn-success" 
       href="student_profile_print.php?id=<?php echo $student['id']; ?>" 
       target="_blank">
        <i class="fas fa-print me-1"></i> Print Profile
    </a>

    <?php if($can_edit_student): ?>
      <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editStudentModal">
        <i class="fas fa-edit me-1"></i> Edit Student
      </button>
    <?php endif; ?>

    <a class="btn btn-outline-secondary" href="disciplinary.php">
      <i class="fas fa-list me-1"></i> Manage All DA
    </a>

    <button class="btn btn-secondary" onclick="history.back()">
      <i class="fas fa-arrow-left me-1"></i> Back
    </button>

</div>

            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- If latest DA exists and is Active, show a popup to alert -->
<?php if ($latest_da && (($latest_da['status'] ?? '') === 'Active')): ?>
<div class="modal fade" id="daPopupModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-danger shadow-lg">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Disciplinary Action Alert</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <h4><?php echo htmlspecialchars($latest_da['action_type']); ?></h4>
        <p><?php echo nl2br(htmlspecialchars($latest_da['da_reason'] ?? '')); ?></p>
        <span class="badge bg-<?php
            $st = $latest_da['status'] ?? '';
            echo ($st === 'Active') ? 'danger' : (($st === 'Resolved') ? 'success' : 'secondary');
        ?>">
            <?php echo htmlspecialchars($latest_da['status']); ?>
        </span>
      </div>
      <div class="modal-footer justify-content-center">
        <a href="student_da_record.php?student_id=<?php echo urlencode($student['student_id']); ?>" class="btn btn-danger">View Record</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var daModalEl = document.getElementById('daPopupModal');
  if (daModalEl) {
    var daModal = new bootstrap.Modal(daModalEl);
    daModal.show();
  }
});
</script>
<?php endif; ?>

<!-- Add Disciplinary Modal (aligned with disciplinary.php form) -->
<div class="modal fade" id="addDisciplinaryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Record Disciplinary Action</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" id="addDAForm" novalidate>
        <div class="modal-body">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="student_id" value="<?php echo (int)$student['id']; ?>">

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Action Type *</label>
              <select class="form-select" name="action_type" required>
                <option value="">Select Type</option>
                <option value="Warning">Warning</option>
                <option value="Suspension">Suspension</option>
                <option value="Expulsion">Expulsion</option>
                <option value="Detention">Detention</option>
              </select>
              <div class="invalid-feedback">Please choose an action type.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Action Date *</label>
              <input type="date" class="form-control" name="action_date" value="<?php echo date('Y-m-d'); ?>" required>
              <div class="invalid-feedback">Please pick an action date (not in future).</div>
            </div>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">Status *</label>
            <!-- Disabled status on add to match disciplinary.php behavior -->
            <select class="form-select" name="status" required disabled>
              <option value="Active" selected>Active</option>
            </select>
            <input type="hidden" name="status" value="Active">
            <small class="form-text text-muted">Status will be "Active" when added. You can resolve it later from edit.</small>
          </div>

          <div class="mb-3">
            <label class="form-label">Reason *</label>
            <textarea class="form-control" name="reason" rows="4" required placeholder="Describe the incident..."></textarea>
            <div class="invalid-feedback">Reason is required.</div>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Record Action</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Disciplinary Modal (aligned with disciplinary.php form) -->
<div class="modal fade" id="editDisciplinaryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Disciplinary Action</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" id="editDAForm" novalidate>
        <div class="modal-body">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="id" id="edit_da_id">

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Action Type *</label>
              <select class="form-select" name="action_type" id="edit_action_type" required>
                <option value="">Select Type</option>
                <option value="Warning">Warning</option>
                <option value="Suspension">Suspension</option>
                <option value="Expulsion">Expulsion</option>
                <option value="Detention">Detention</option>
              </select>
              <div class="invalid-feedback">Please choose an action type.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Action Date *</label>
              <input type="date" class="form-control" name="action_date" id="edit_action_date" required>
              <div class="invalid-feedback">Please pick an action date.</div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Status *</label>
            <select class="form-select" name="status" id="edit_status" required>
              <option value="Active">Active</option>
              <option value="Resolved">Resolved</option>
              <option value="Expired">Expired</option>
            </select>
            <div class="invalid-feedback">Select status.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Reason *</label>
            <textarea class="form-control" name="reason" id="edit_reason" rows="4" required></textarea>
            <div class="invalid-feedback">Reason is required.</div>
          </div>

          <div class="mb-3" id="edit_resolved_container" style="display:none;">
            <label class="form-label">Resolved Reason</label>
            <textarea class="form-control" name="resolved_reason" id="edit_resolved_reason" rows="3"></textarea>
            <div class="invalid-feedback">Resolved reason required when status is Resolved.</div>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Action</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Student Modal (basic full fields) -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" id="editStudentForm" novalidate>
        <div class="modal-body">
          <input type="hidden" name="action" value="edit_student">
          <input type="hidden" name="sid" value="<?php echo (int)$student['id']; ?>">

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">First Name *</label>
              <input class="form-control" name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
              <div class="invalid-feedback">First name required.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Last Name *</label>
              <input class="form-control" name="last_name" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
              <div class="invalid-feedback">Last name required.</div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Email</label>
              <input class="form-control" name="email" value="<?php echo htmlspecialchars($student['email']); ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Phone</label>
              <input class="form-control" name="phone" value="<?php echo htmlspecialchars($student['phone']); ?>">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Address</label>
            <textarea class="form-control" name="address"><?php echo htmlspecialchars($student['address']); ?></textarea>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Parent Name</label>
              <input class="form-control" name="parent_name" value="<?php echo htmlspecialchars($student['parent_name']); ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Parent Phone</label>
              <input class="form-control" name="parent_phone" value="<?php echo htmlspecialchars($student['parent_phone']); ?>">
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Class</label>
              <select class="form-select" name="class_id">
                <option value="0">-- Select Class --</option>
                <?php
                  $res = $conn->query("SELECT id, class_name, section FROM classes ORDER BY class_name, section");
                  while ($c = $res->fetch_assoc()):
                ?>
                <option value="<?php echo (int)$c['id']; ?>" <?php echo $c['id']==$student['class_id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($c['class_name'] . ' ' . $c['section']); ?>
                </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Status</label>
              <select class="form-select" name="status">
                <option value="Active" <?php echo $student['status']=='Active' ? 'selected' : ''; ?>>Active</option>
                <option value="Inactive" <?php echo $student['status']=='Inactive' ? 'selected' : ''; ?>>Inactive</option>
                <option value="Graduated" <?php echo $student['status']=='Graduated' ? 'selected' : ''; ?>>Alumni</option>
              </select>
            </div>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Student</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- JS: client-side helpers -->
<script>
/* Bootstrap validation */
(function () {
  'use strict';
  var forms = document.querySelectorAll('form[novalidate]');
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        // for editDAForm we need extra resolved_reason check when status=Resolved
        if (form.id === 'editDAForm') {
          var status = document.getElementById('edit_status').value;
          var resolvedCont = document.getElementById('edit_resolved_container');
          var resolvedField = document.getElementById('edit_resolved_reason');
          if (status === 'Resolved') {
            resolvedCont.style.display = 'block';
            resolvedField.setAttribute('required','required');
          } else {
            resolvedCont.style.display = 'none';
            resolvedField.removeAttribute('required');
          }
        }
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });
})();

/* Open edit modal and populate values */
function openEditDA(obj) {
  if (!obj) return;
  document.getElementById('edit_da_id').value = obj.id || '';
  document.getElementById('edit_action_type').value = obj.action_type || '';
  document.getElementById('edit_action_date').value = obj.action_date || '';
  document.getElementById('edit_status').value = obj.status || '';
  document.getElementById('edit_reason').value = obj.da_reason || obj.description || '';
  document.getElementById('edit_resolved_reason').value = obj.resolved_reason || '';

  // show/hide resolved container
  var editResolvedContainer = document.getElementById('edit_resolved_container');
  if (obj.status === 'Resolved') {
    editResolvedContainer.style.display = 'block';
    document.getElementById('edit_resolved_reason').setAttribute('required','required');
  } else {
    editResolvedContainer.style.display = 'none';
    document.getElementById('edit_resolved_reason').removeAttribute('required');
  }

  var modal = new bootstrap.Modal(document.getElementById('editDisciplinaryModal'));
  modal.show();
}

/* When edit_status changes, toggle resolved reason field */
document.addEventListener('DOMContentLoaded', function () {
  var editStatus = document.getElementById('edit_status');
  if (editStatus) {
    editStatus.addEventListener('change', function () {
      var cont = document.getElementById('edit_resolved_container');
      var fld = document.getElementById('edit_resolved_reason');
      if (this.value === 'Resolved') {
        cont.style.display = 'block';
        fld.setAttribute('required','required');
      } else {
        cont.style.display = 'none';
        fld.removeAttribute('required');
      }
    });
  }

  // simple date max enforcement for add and edit date inputs
  var today = new Date().toISOString().split('T')[0];
  var addDate = document.querySelector('input[name="action_date"]');
  if (addDate) addDate.setAttribute('max', today);
  var editDate = document.getElementById('edit_action_date');
  if (editDate) editDate.setAttribute('max', today);
});
</script>

<?php require_once 'includes/footer.php'; ?>