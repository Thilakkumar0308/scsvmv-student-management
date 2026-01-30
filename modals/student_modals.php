<!-- ===========================
     ADD STUDENT MODAL
=========================== -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form id="addStudentForm" enctype="multipart/form-data">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Add Student</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">

            <div class="col-md-6">
              <label>Register Number</label>
              <input type="text" name="student_id" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label>First Name</label>
              <input type="text" name="first_name" id="add_first_name"
                class="form-control" required maxlength="40"
                pattern="[A-Za-z]{1,40}"
                oninput="this.value=this.value.replace(/[^A-Za-z]/g,'')">
            </div>

            <div class="col-md-6">
              <label>Last Name</label>
              <input type="text" name="last_name" id="add_last_name"
                class="form-control" required maxlength="40"
                pattern="[A-Za-z]{1,40}"
                oninput="this.value=this.value.replace(/[^A-Za-z]/g,'')">
            </div>

            <div class="col-md-6">
              <label>Email</label>
              <input type="email" name="email" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label>Phone Number</label>
              <div class="input-group">
                <select class="form-select" name="country_code" style="max-width:140px" required>
                  <option value="+91" selected>🇮🇳 +91 India</option>
                  <option value="+1">🇺🇸 +1 USA</option>
                  <option value="+44">🇬🇧 +44 UK</option>
                </select>
                <input type="tel" name="phone" id="add_phone"
                  class="form-control" required maxlength="10"
                  pattern="[0-9]{6,10}"
                  oninput="this.value=this.value.replace(/[^0-9]/g,'')">
              </div>
            </div>

            <div class="col-md-6">
              <label>Date of Birth</label>
              <input type="date" name="date_of_birth" id="add_dob"
                class="form-control" required>
            </div>

            <div class="col-md-6">
              <label>Gender</label>
              <select name="gender" class="form-select" required>
                <option value="">Select</option>
                <option>Male</option>
                <option>Female</option>
                <option>Other</option>
              </select>
            </div>

            <div class="col-md-6">
              <label>Class</label>
              <select name="class_id" class="form-select" required>
                <option value="">Select</option>
                <?php foreach ($classes as $c): ?>
                  <option value="<?= $c['id'] ?>">
                    <?= htmlspecialchars($c['class_name'].' '.$c['section']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label>Admission Date</label>
              <input type="date" name="admission_date"
                id="add_admission" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label>Parent Name</label>
              <input type="text" name="parent_name" id="add_parent_name"
                class="form-control" required maxlength="40"
                pattern="[A-Za-z ]{1,40}"
                oninput="this.value=this.value.replace(/[^A-Za-z ]/g,'')">
            </div>

            <div class="col-md-6">
              <label>Parent Phone</label>
              <div class="input-group">
                <select class="form-select" name="parent_country_code"
                  style="max-width:140px" required>
                  <option value="+91" selected>🇮🇳 +91 India</option>
                </select>
                <input type="tel" name="parent_phone"
                  class="form-control" required maxlength="10"
                  pattern="[0-9]{6,10}"
                  oninput="this.value=this.value.replace(/[^0-9]/g,'')">
              </div>
            </div>

            <div class="col-md-12">
              <label>Address</label>
              <textarea name="address" class="form-control" required></textarea>
            </div>

            <div class="col-md-6">
              <label>Profile Picture</label>
              <input type="file" name="profile_picture"
                id="add_profile_picture" class="form-control">
              <div id="add_profile_preview" class="mt-2 text-muted">No Photo</div>
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Add Student</button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- ===========================
     EDIT STUDENT MODAL
=========================== -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form id="editStudentForm" enctype="multipart/form-data">

      <input type="hidden" name="id" id="edit_id">

      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Edit Student</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">

            <div class="col-md-6">
              <label>Register Number</label>
              <input type="text" name="student_id" id="edit_student_id"
                class="form-control" required>
            </div>

            <div class="col-md-6">
              <label>First Name</label>
              <input type="text" name="first_name" id="edit_first_name"
                class="form-control" required maxlength="40"
                pattern="[A-Za-z]{1,40}">
            </div>

            <div class="col-md-6">
              <label>Last Name</label>
              <input type="text" name="last_name" id="edit_last_name"
                class="form-control" required maxlength="40"
                pattern="[A-Za-z]{1,40}">
            </div>

            <div class="col-md-6">
              <label>Email</label>
              <input type="email" name="email" id="edit_email"
                class="form-control" required>
            </div>

            <div class="col-md-6">
              <label>Phone Number</label>
              <div class="input-group">
                <select class="form-select" name="country_code"
                  style="max-width:140px" required>
                  <option value="+91">🇮🇳 +91 India</option>
                </select>
                <input type="tel" name="phone" id="edit_phone"
                  class="form-control" required maxlength="10"
                  pattern="[0-9]{6,10}"
                  oninput="this.value=this.value.replace(/[^0-9]/g,'')">
              </div>
            </div>

            <div class="col-md-6">
              <label>Date of Birth</label>
              <input type="date" name="date_of_birth"
                id="edit_date_of_birth" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label>Gender</label>
              <select name="gender" id="edit_gender"
                class="form-select" required>
                <option value="">Select</option>
                <option>Male</option>
                <option>Female</option>
                <option>Other</option>
              </select>
            </div>


            <div class="col-md-6">
              <label>Class</label>
              <select name="class_id" id="edit_class_id" 
              class="form-select" required>
                <option value="">Select</option>
                <?php foreach ($classes as $c): ?>
                  <option value="<?= $c['id'] ?>">
                    <?= htmlspecialchars($c['class_name'].' '.$c['section']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label>Admission Date</label>
              <input type="date" name="admission_date"
                id="edit_admission_date" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label>Parent Name</label>
              <input type="text" name="parent_name"
                id="edit_parent_name" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label>Parent Phone Number</label>
              <div class="input-group">
                <select class="form-select" name="country_code" style="max-width:140px" required>
                  <option value="+91" selected>🇮🇳 +91 India</option>
                  <option value="+1">🇺🇸 +1 USA</option>
                  <option value="+44">🇬🇧 +44 UK</option>
                </select>
                <input type="tel" name="parent_phone" id="edit_parent_phone"
                  class="form-control" required maxlength="10"
                  pattern="[0-9]{6,10}"
                  oninput="this.value=this.value.replace(/[^0-9]/g,'')">
              </div>
            </div>

            <div class="col-md-12">
              <label>Address</label>
              <textarea name="address" id="edit_address"
                class="form-control" required></textarea>
            </div>

            <div class="col-md-6">
              <label>Status</label>
              <select name="status" id="edit_status"
                class="form-select">
                <option>Active</option>
                <option>Inactive</option>
                <option>Graduated</option>
              </select>
            </div>

            <div class="col-md-6">
              <label>Profile Picture</label>
              <input type="file" name="profile_picture"
                id="edit_profile_picture" class="form-control">
              <div id="edit_profile_preview" class="mt-2 text-muted">No Photo</div>
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Update Student</button>
        </div>
      </div>
    </form>
  </div>
</div>
<!-- Student Save Loading Modal -->
<div class="modal fade" id="studentLoadingModal"
     data-bs-backdrop="static"
     data-bs-keyboard="false"
     tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bright-card text-center border-0 shadow-lg p-4">

      <div class="flying-mail mb-3">
        <i class="fas fa-paper-plane text-white fa-2x"></i>
      </div>

      <h5 class="fw-bold text-primary mb-1">Saving Student</h5>
      <p class="text-muted small mb-3">
        Please wait while the student details are being processed.
      </p>

      <div class="progress mx-4" style="height:6px;">
        <div class="progress-bar progress-bar-striped progress-bar-animated"
             style="width:80%"></div>
      </div>

      <div class="text-warning mt-3 small fw-semibold">
        <i class="fas fa-spinner fa-spin me-1"></i>Processing...
      </div>
    </div>
  </div>
</div>
