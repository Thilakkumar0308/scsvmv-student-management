// students.js
document.addEventListener('DOMContentLoaded', function () {

    // ===============================
    // Student Loading Modal
    // ===============================
    let studentLoadingModal;
    const loadingEl = document.getElementById('studentLoadingModal');

    if (loadingEl) {
        studentLoadingModal = new bootstrap.Modal(loadingEl, {
            backdrop: 'static',
            keyboard: false
        });
    }

    function showStudentLoader() {
        if (studentLoadingModal) studentLoadingModal.show();
    }

    function hideStudentLoader() {
        if (studentLoadingModal) studentLoadingModal.hide();
    }

    // ===============================
    // DataTable
    // ===============================
    if (window.jQuery && $.fn.DataTable) {
        $('#studentsTable').DataTable();
    }

    // ===============================
    // Alerts
    // ===============================
    function showAlert(message, type = 'success') {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.querySelector('.container')?.prepend(alert);
        setTimeout(() => alert.remove(), 5000);
    }

    // ===============================
    // Populate Edit Modal
    // ===============================
    function populateEditModal(student) {
        document.getElementById('edit_id').value = student.id || '';
        document.getElementById('edit_student_id').value = student.student_id || '';
        document.getElementById('edit_first_name').value = student.first_name || '';
        document.getElementById('edit_last_name').value = student.last_name || '';
        document.getElementById('edit_email').value = student.email || '';
        document.getElementById('edit_phone').value = student.phone || '';
        document.getElementById('edit_date_of_birth').value = student.date_of_birth || '';
        document.getElementById('edit_gender').value = student.gender || '';
        document.getElementById('edit_address').value = student.address || '';
        document.getElementById('edit_class_id').value = student.class_id || '';
        document.getElementById('edit_parent_name').value = student.parent_name || '';
        document.getElementById('edit_parent_phone').value = student.parent_phone || '';
        document.getElementById('edit_admission_date').value = student.admission_date || '';
        document.getElementById('edit_status').value = student.status || 'Active';

        const preview = document.getElementById('edit_profile_preview');
        preview.innerHTML = student.profile_picture
            ? `<img src="${student.profile_picture}" style="width:100px;height:100px;object-fit:cover;border-radius:50%;">`
            : '<div class="text-muted">No Photo</div>';

        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    // ===============================
    // Edit / Delete Buttons
    // ===============================
    document.addEventListener('click', function (e) {

        // EDIT
        if (e.target.closest('.btn-edit')) {
            const btn = e.target.closest('.btn-edit');
            const student = JSON.parse(btn.dataset.student || '{}');
            populateEditModal(student);
            return;
        }

        // DELETE
        if (e.target.closest('.btn-delete')) {
            const btn = e.target.closest('.btn-delete');
            const id = btn.dataset.id;

            if (!id || !confirm('Are you sure you want to delete this student?')) return;

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);

            fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.text())
                .then(res => {
                    const doc = new DOMParser().parseFromString(res, 'text/html');
                    const alertEl = doc.querySelector('.alert');
                    if (alertEl) {
                        showAlert(
                            alertEl.innerHTML,
                            alertEl.classList.contains('alert-danger') ? 'danger' : 'success'
                        );
                    }
                    setTimeout(() => location.reload(), 1000);
                })
                .catch(() => showAlert('Error deleting student', 'danger'));

            return;
        }
    });

    

    // ===============================
    // ADD Student
    // ===============================
    const addForm = document.getElementById('addStudentForm');
    if (addForm) {
        addForm.addEventListener('submit', function (e) {
            e.preventDefault();

            if (!validateDifferentPhones(addForm)) return;

            showStudentLoader();

            const formData = new FormData(addForm);
            formData.append('action', 'add');

            fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.text())
                .then(res => {
                    hideStudentLoader();

                    const doc = new DOMParser().parseFromString(res, 'text/html');
                    const alertEl = doc.querySelector('.alert');
                    if (alertEl) {
                        showAlert(
                            alertEl.innerHTML,
                            alertEl.classList.contains('alert-danger') ? 'danger' : 'success'
                        );
                    }

                    addForm.reset();
                    bootstrap.Modal.getInstance(document.getElementById('addModal')).hide();
                    setTimeout(() => location.reload(), 1000);
                })
                .catch(() => {
                    hideStudentLoader();
                    showAlert('Error adding student', 'danger');
                });
        });
    }

    // ===============================
    // EDIT Student
    // ===============================
    const editForm = document.getElementById('editStudentForm');
    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();

            if (!validateDifferentPhones(editForm)) return;

            showStudentLoader();

            const formData = new FormData(editForm);
            formData.append('action', 'edit');

            fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.text())
                .then(res => {
                    hideStudentLoader();

                    const doc = new DOMParser().parseFromString(res, 'text/html');
                    const alertEl = doc.querySelector('.alert');
                    if (alertEl) {
                        showAlert(
                            alertEl.innerHTML,
                            alertEl.classList.contains('alert-danger') ? 'danger' : 'success'
                        );
                    }

                    bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                    setTimeout(() => location.reload(), 1000);
                })
                .catch(() => {
                    hideStudentLoader();
                    showAlert('Error updating student', 'danger');
                });
        });
    }

    // ===============================
    // DOB & Admission Date Limits
    // ===============================
    const today = new Date();
    const todayISO = today.toISOString().split('T')[0];

    const dobMin = new Date(today.getFullYear() - 45, today.getMonth(), today.getDate()).toISOString().split('T')[0];
    const dobMax = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate()).toISOString().split('T')[0];

    document.getElementById('add_dob')?.setAttribute('min', dobMin);
    document.getElementById('add_dob')?.setAttribute('max', dobMax);
    document.getElementById('edit_date_of_birth')?.setAttribute('min', dobMin);
    document.getElementById('edit_date_of_birth')?.setAttribute('max', dobMax);

    document.getElementById('add_admission')?.setAttribute('min', '1999-01-01');
    document.getElementById('add_admission')?.setAttribute('max', todayISO);
    document.getElementById('edit_admission_date')?.setAttribute('min', '1999-01-01');
    document.getElementById('edit_admission_date')?.setAttribute('max', todayISO);

    // ===============================
    // ✅ MISSING PART (RESTORED)
    // Student Row / Card Redirect
    // ===============================
    document.querySelectorAll('.student-row').forEach(row => {
        row.style.cursor = 'pointer';
        row.addEventListener('click', function (e) {
            if (e.target.closest('button, a, i')) return;
            const studentId = this.dataset.studentId;
            if (studentId) {
                window.location.href = 'student_info.php?student_id=' + encodeURIComponent(studentId);
            }
        });
    });

    document.querySelectorAll('.student-card').forEach(card => {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function (e) {
            if (e.target.closest('button, a, i')) return;
            const studentId = this.dataset.studentId;
            if (studentId) {
                window.location.href = 'student_info.php?student_id=' + encodeURIComponent(studentId);
            }
        });
    });
});

// ===============================
// Phone Validation
// ===============================
function validateDifferentPhones(form) {
    const studentCode = form.querySelector('[name="country_code"]')?.value || '';
    const parentCode = form.querySelector('[name="parent_country_code"]')?.value || '';
    const studentPhone = form.querySelector('[name="phone"]');
    const parentPhone = form.querySelector('[name="parent_phone"]');

    if (!studentPhone || !parentPhone) return true;

    parentPhone.setCustomValidity('');

    if (
        studentPhone.value &&
        parentPhone.value &&
        studentCode + studentPhone.value === parentCode + parentPhone.value
    ) {
        parentPhone.setCustomValidity('Student and Parent phone numbers must be different');
        parentPhone.reportValidity();
        return false;
    }
    return true;
}
