<?php
/**
 * REGISTRATION FORM SNIPPET
 * ─────────────────────────
 * Drop this block inside index.php where the register tab/section lives.
 * Requires fulafia_departments.php to be included at the top of index.php:
 *
 *   require_once __DIR__ . '/includes/fulafia_departments.php';
 *
 * The JS at the bottom cascades department options when the faculty changes.
 */
?>

<?php require_once __DIR__ . '/includes/fulafia_departments.php'; ?>

<form action="auth/register.php" method="POST" id="registerForm" novalidate>
    <?= csrfField() ?>

    <!-- Full Name -->
    <div class="form-group">
        <label for="reg_full_name">Full Name</label>
        <input
            type="text"
            id="reg_full_name"
            name="full_name"
            class="form-control"
            placeholder="e.g. Amina Bello"
            maxlength="150"
            required
        >
    </div>

    <!-- Email -->
    <div class="form-group">
        <label for="reg_email">Email Address</label>
        <input
            type="email"
            id="reg_email"
            name="email"
            class="form-control"
            placeholder="you@example.com"
            required
        >
    </div>

    <!-- Matric Number -->
    <div class="form-group">
        <label for="reg_matric">Matric Number</label>
        <input
            type="text"
            id="reg_matric"
            name="matric_number"
            class="form-control"
            placeholder="FUL/CS/2021/001"
            maxlength="25"
            required
        >
        <small class="form-hint">Format: FUL/XX/YYYY/NNN</small>
    </div>

    <!-- Faculty (cascading parent) -->
    <div class="form-group">
        <label for="reg_faculty">Faculty</label>
        <select id="reg_faculty" name="faculty" class="form-control" required>
            <option value="">— Select Faculty —</option>
            <?php foreach (array_keys(FULAFIA_FACULTIES) as $faculty): ?>
                <option value="<?= htmlspecialchars($faculty) ?>">
                    <?= htmlspecialchars($faculty) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Department (cascading child — populated by JS) -->
    <div class="form-group">
        <label for="reg_department">Department</label>
        <select id="reg_department" name="department" class="form-control" required disabled>
            <option value="">— Select Faculty first —</option>
        </select>
    </div>

    <!-- Level -->
    <div class="form-group">
        <label for="reg_level">Level</label>
        <select id="reg_level" name="level" class="form-control" required>
            <option value="">— Select Level —</option>
            <option value="100">100 Level</option>
            <option value="200">200 Level</option>
            <option value="300">300 Level</option>
            <option value="400">400 Level</option>
            <option value="500">500 Level</option>
            <option value="600">600 Level</option>
            <option value="700">700 Level</option>
        </select>
    </div>

    <!-- Password -->
    <div class="form-group">
        <label for="reg_password">Password</label>
        <input
            type="password"
            id="reg_password"
            name="password"
            class="form-control"
            minlength="8"
            placeholder="Minimum 8 characters"
            required
        >
    </div>

    <!-- Confirm Password -->
    <div class="form-group">
        <label for="reg_confirm">Confirm Password</label>
        <input
            type="password"
            id="reg_confirm"
            name="confirm_password"
            class="form-control"
            minlength="8"
            placeholder="Repeat your password"
            required
        >
    </div>

    <button type="submit" class="btn btn-primary btn-block">Create Account</button>
</form>


<!-- ── Cascading dropdown: Faculty → Department ─────────────────────────── -->
<script>
(function () {
    // Build the faculty→departments map from PHP
    const facultyMap = <?= json_encode(FULAFIA_FACULTIES, JSON_UNESCAPED_UNICODE) ?>;

    const facultySelect    = document.getElementById('reg_faculty');
    const departmentSelect = document.getElementById('reg_department');

    facultySelect.addEventListener('change', function () {
        const chosen = this.value;

        // Reset department dropdown
        departmentSelect.innerHTML = '';
        departmentSelect.disabled = true;

        if (!chosen || !facultyMap[chosen]) {
            departmentSelect.innerHTML = '<option value="">— Select Faculty first —</option>';
            return;
        }

        // Populate matching departments
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = '— Select Department —';
        departmentSelect.appendChild(placeholder);

        facultyMap[chosen].forEach(function (dept) {
            const opt = document.createElement('option');
            opt.value = dept;
            opt.textContent = dept;
            departmentSelect.appendChild(opt);
        });

        departmentSelect.disabled = false;
        departmentSelect.focus();
    });
})();
</script>
