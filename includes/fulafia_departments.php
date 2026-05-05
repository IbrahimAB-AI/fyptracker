<?php
/**
 * FULafia — Faculty & Department Data
 * includes/fulafia_departments.php
 *
 * Centralised list of all FULafia faculties and their departments.
 * Used by registration forms and validation logic.
 */

define('FULAFIA_FACULTIES', [

    // ── College of Medicine ───────────────────────────────────────────────────
    'Faculty of Basic Medical Sciences' => [
        'Anatomy',
        'Physiology',
        'Medical Biochemistry',
    ],
    'Faculty of Clinical Sciences' => [
        'Medicine and Surgery (MBBS)',
    ],
    'Faculty of Allied Health Sciences' => [
        'Nursing Science',
        'Medical Laboratory Science',
        'Radiography',
    ],

    // ── Science & Technology ─────────────────────────────────────────────────
    'Faculty of Physical Sciences' => [
        'Chemistry',
        'Physics',
        'Mathematics',
        'Geology',
        'Statistics',
    ],
    'Faculty of Biological Sciences' => [
        'Microbiology',
        'Biochemistry',
        'Plant Science & Biotechnology',
        'Zoology',
        'Science Laboratory Technology',
    ],
    'Faculty of Computing' => [
        'Computer Science',
        'Cyber Security',
        'Information Technology',
        'Software Engineering',
        'Information Systems',
    ],

    // ── Humanities & Social Sciences ─────────────────────────────────────────
    'Faculty of Arts' => [
        'English & Literary Studies',
        'History & International Studies',
        'Theatre & Media Arts',
        'French',
        'Arabic Studies',
        'Islamic Studies',
        'Christian Religious Studies',
        'Philosophy',
        'Visual & Creative Arts',
        'Hausa and Nigerian Languages',
    ],
    'Faculty of Social Sciences' => [
        'Economics',
        'Political Science',
        'Sociology',
        'Psychology',
        'Mass Communication',
        'Geography',
        'Criminology & Security Studies',
        'Social Work',
        'Library & Information Science',
    ],

    // ── Professional & Applied Sciences ──────────────────────────────────────
    'Faculty of Agriculture' => [
        'Agriculture',
        'Agric-Economics & Extension',
        'Agronomy',
        'Animal Science',
        'Fisheries & Aquaculture',
        'Forestry & Wildlife Management',
    ],
    'Faculty of Education' => [
        'Science Education',
        'Business Education',
        'Special Needs & Rehabilitation',
        'Vocational & Technical Education',
        'Educational Foundations',
        'Arts & Social Science Education',
    ],
    'Faculty of Management Sciences' => [
        'Accounting',
        'Business Administration',
        'Entrepreneurship Studies',
        'Taxation',
        'Banking & Finance',
        'Public Administration',
    ],
    'Faculty of Environmental Design' => [
        'Architecture',
        'Building',
        'Industrial Design',
        'Quantity Surveying',
        'Urban & Regional Planning',
        'Glass & Silicate Technology',
    ],

    // ── Recent NUC Approvals (commencing 2025/2026) ───────────────────────────
    'Faculty of Law' => [
        'Bachelor of Laws (LL.B)',
    ],
    'Faculty of Pharmaceutical Sciences' => [
        'Doctor of Pharmacy (Pharm.D.)',
    ],
    'Faculty of Veterinary Medicine' => [
        'Doctor of Veterinary Medicine (D.V.M)',
    ],
]);

/**
 * Returns a flat list of all department names (for quick validation).
 */
function getAllDepartments(): array {
    $all = [];
    foreach (FULAFIA_FACULTIES as $depts) {
        foreach ($depts as $dept) {
            $all[] = $dept;
        }
    }
    return $all;
}

/**
 * Returns the faculty name that owns a given department, or null if not found.
 */
function getFacultyForDepartment(string $department): ?string {
    foreach (FULAFIA_FACULTIES as $faculty => $depts) {
        if (in_array($department, $depts, true)) {
            return $faculty;
        }
    }
    return null;
}
