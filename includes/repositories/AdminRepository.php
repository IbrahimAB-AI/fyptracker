<?php
/**
 * FYPTracker — Admin Repository
 * includes/repositories/AdminRepository.php
 *
 * Handles all admin-specific database operations:
 *   - User management (list, create supervisor, toggle active, delete)
 *   - Supervisor assignment
 *   - System-wide report data
 *
 * Never duplicates ProjectRepository or SupervisorRepository methods.
 *
 * Principles:
 *  - backend-patterns: repository pattern, N+1 prevention
 *  - coding-standards: verb-noun methods, named constants, early returns
 */

class AdminRepository
{
    // ── Named constants ───────────────────────────────────────────────────
    public const ROLE_STUDENT    = 'student';
    public const ROLE_SUPERVISOR = 'supervisor';
    public const ROLE_ADMIN      = 'admin';

    public const USERS_PER_PAGE = 25;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ══════════════════════════════════════════════════════════════════════
    // USER MANAGEMENT
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Fetch paginated user list with extended profile data joined.
     * N+1 prevention: LEFT JOINs students + supervisors in one query.
     */
    public function findAllUsersWithProfiles(
        int    $page         = 1,
        string $filterRole   = '',
        string $searchQuery  = ''
    ): array {
        $offset     = ($page - 1) * self::USERS_PER_PAGE;
        $conditions = ['1=1'];
        $bindings   = [];

        if ($filterRole !== '') {
            $conditions[] = 'u.role = ?';
            $bindings[]   = $filterRole;
        }

        if ($searchQuery !== '') {
            $conditions[] = '(u.full_name LIKE ? OR u.email LIKE ?)';
            $like         = '%' . $searchQuery . '%';
            $bindings[]   = $like;
            $bindings[]   = $like;
        }

        $whereClause = implode(' AND ', $conditions);

        $stmt = $this->db->prepare(
            "SELECT
                u.user_id,
                u.full_name,
                u.email,
                u.role,
                u.is_active,
                u.created_at,
                st.matric_number,
                st.level,
                sv.staff_id,
                sv.title        AS supervisor_title,
                sv.specialisation
               FROM users u
               LEFT JOIN students    st ON st.student_id    = u.user_id
               LEFT JOIN supervisors sv ON sv.supervisor_id = u.user_id
              WHERE {$whereClause}
              ORDER BY u.created_at DESC
              LIMIT ? OFFSET ?"
        );

        $bindings[] = self::USERS_PER_PAGE;
        $bindings[] = $offset;

        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    /**
     * Count total users for pagination (same filters as findAllUsersWithProfiles).
     */
    public function countUsers(string $filterRole = '', string $searchQuery = ''): int
    {
        $conditions = ['1=1'];
        $bindings   = [];

        if ($filterRole !== '') {
            $conditions[] = 'role = ?';
            $bindings[]   = $filterRole;
        }

        if ($searchQuery !== '') {
            $conditions[] = '(full_name LIKE ? OR email LIKE ?)';
            $like         = '%' . $searchQuery . '%';
            $bindings[]   = $like;
            $bindings[]   = $like;
        }

        $whereClause = implode(' AND ', $conditions);
        $stmt        = $this->db->prepare("SELECT COUNT(*) FROM users WHERE {$whereClause}");
        $stmt->execute($bindings);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Find a single user by ID with their extended profile.
     */
    public function findUserById(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.*,
                    st.matric_number, st.level, st.programme,
                    sv.staff_id, sv.title AS supervisor_title,
                    sv.specialisation, sv.max_students
               FROM users u
               LEFT JOIN students    st ON st.student_id    = u.user_id
               LEFT JOIN supervisors sv ON sv.supervisor_id = u.user_id
              WHERE u.user_id = ?
              LIMIT 1'
        );
        $stmt->execute([$userId]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Create a supervisor account with extended profile.
     * Returns the new user_id.
     * Uses a transaction to keep users + supervisors atomic.
     */
    public function createSupervisorAccount(
        string $fullName,
        string $email,
        string $passwordHash,
        string $staffId,
        string $title,
        string $specialisation,
        int    $maxStudents
    ): int {
        $this->db->beginTransaction();

        try {
            $stmtUser = $this->db->prepare(
                "INSERT INTO users (full_name, email, password_hash, role)
                 VALUES (?, ?, ?, 'supervisor')"
            );
            $stmtUser->execute([$fullName, $email, $passwordHash]);
            $userId = (int) $this->db->lastInsertId();

            $stmtSupervisor = $this->db->prepare(
                'INSERT INTO supervisors
                    (supervisor_id, staff_id, title, specialisation, max_students)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmtSupervisor->execute([
                $userId,
                $staffId,
                $title,
                $specialisation,
                $maxStudents,
            ]);

            $this->db->commit();
            return $userId;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Toggle a user's is_active flag (suspend / reactivate).
     */
    public function toggleUserActiveStatus(int $userId, bool $isActive): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET is_active = ? WHERE user_id = ?'
        );
        $stmt->execute([(int) $isActive, $userId]);
    }

    /**
     * Soft-delete: deactivate user rather than hard delete to preserve FK integrity.
     */
    public function deactivateUser(int $userId): void
    {
        $this->toggleUserActiveStatus($userId, false);
    }

    // ══════════════════════════════════════════════════════════════════════
    // SUPERVISOR ASSIGNMENT
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Assign a supervisor to a project.
     * Also transitions project status to in_progress if it was approved.
     */
    public function assignSupervisorToProject(int $projectId, int $supervisorId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE projects
                SET supervisor_id = ?,
                    status = CASE
                        WHEN status = 'approved' THEN 'in_progress'
                        ELSE status
                    END
              WHERE project_id = ?"
        );
        $stmt->execute([$supervisorId, $projectId]);
    }

    /**
     * Find all students with no supervisor assigned yet.
     * N+1 prevention: single JOIN across projects + students + users.
     */
    public function findUnassignedStudents(): array
    {
        $stmt = $this->db->query(
            "SELECT
                u.user_id,
                u.full_name,
                u.email,
                st.matric_number,
                p.project_id,
                p.title     AS project_title,
                p.status    AS project_status,
                p.submission_date
               FROM users u
               JOIN students st ON st.student_id = u.user_id
               LEFT JOIN projects p ON p.student_id = u.user_id
              WHERE u.role      = 'student'
                AND u.is_active = 1
                AND (p.supervisor_id IS NULL OR p.project_id IS NULL)
              ORDER BY p.submission_date ASC, u.full_name ASC"
        );

        return $stmt->fetchAll();
    }

    /**
     * Find all assigned students with their supervisor for the assignment table.
     */
    public function findAssignedStudentsOverview(): array
    {
        $stmt = $this->db->query(
            "SELECT
                u.user_id,
                u.full_name    AS student_name,
                st.matric_number,
                p.project_id,
                p.title        AS project_title,
                p.status       AS project_status,
                sv.full_name   AS supervisor_name,
                sv.user_id     AS supervisor_id,
                COUNT(m.milestone_id)                        AS total_milestones,
                SUM(m.completion_status = 'completed')       AS completed_milestones
               FROM users u
               JOIN students st ON st.student_id = u.user_id
               JOIN projects p  ON p.student_id  = u.user_id
               JOIN users sv    ON sv.user_id     = p.supervisor_id
               LEFT JOIN milestones m ON m.project_id = p.project_id
              WHERE u.role = 'student'
                AND p.supervisor_id IS NOT NULL
              GROUP BY p.project_id
              ORDER BY sv.full_name ASC, u.full_name ASC"
        );

        return $stmt->fetchAll();
    }

    // ══════════════════════════════════════════════════════════════════════
    // SYSTEM STATS & REPORTS
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Get all system-wide counts in a single query block.
     * Avoids 7 separate COUNT queries (N+1 pattern).
     */
    public function fetchSystemStats(): array
    {
        $stats = [];

        // Single query with multiple aggregates
        $row = $this->db->query(
            "SELECT
                SUM(role = 'student')    AS total_students,
                SUM(role = 'supervisor') AS total_supervisors,
                SUM(role = 'admin')      AS total_admins,
                COUNT(*)                 AS total_users
               FROM users
              WHERE is_active = 1"
        )->fetch();

        $stats['total_students']    = (int) ($row['total_students']    ?? 0);
        $stats['total_supervisors'] = (int) ($row['total_supervisors'] ?? 0);
        $stats['total_admins']      = (int) ($row['total_admins']      ?? 0);
        $stats['total_users']       = (int) ($row['total_users']       ?? 0);

        // Project stats
        $projectRow = $this->db->query(
            "SELECT
                COUNT(*)                              AS total_projects,
                SUM(status = 'pending')               AS pending,
                SUM(status = 'approved')              AS approved,
                SUM(status = 'in_progress')           AS in_progress,
                SUM(status = 'completed')             AS completed,
                SUM(status = 'rejected')              AS rejected,
                SUM(supervisor_id IS NULL)            AS unassigned
               FROM projects"
        )->fetch();

        $stats['total_projects']    = (int) ($projectRow['total_projects'] ?? 0);
        $stats['pending_projects']  = (int) ($projectRow['pending']        ?? 0);
        $stats['approved_projects'] = (int) ($projectRow['approved']       ?? 0);
        $stats['active_projects']   = (int) ($projectRow['in_progress']    ?? 0);
        $stats['completed_projects']= (int) ($projectRow['completed']      ?? 0);
        $stats['rejected_projects'] = (int) ($projectRow['rejected']       ?? 0);
        $stats['unassigned_students']= (int) ($projectRow['unassigned']    ?? 0);

        // Milestone stats
        $msRow = $this->db->query(
            "SELECT
                COUNT(*)                              AS total_milestones,
                SUM(completion_status = 'completed')  AS completed_milestones
               FROM milestones"
        )->fetch();

        $stats['total_milestones']     = (int) ($msRow['total_milestones']     ?? 0);
        $stats['completed_milestones'] = (int) ($msRow['completed_milestones'] ?? 0);

        // Meeting stats
        $meetRow = $this->db->query(
            "SELECT
                COUNT(*)                     AS total_meetings,
                SUM(status = 'completed')    AS completed_meetings,
                SUM(status = 'scheduled'
                    AND scheduled_date >= NOW()) AS upcoming_meetings
               FROM meetings"
        )->fetch();

        $stats['total_meetings']    = (int) ($meetRow['total_meetings']    ?? 0);
        $stats['completed_meetings']= (int) ($meetRow['completed_meetings']?? 0);
        $stats['upcoming_meetings'] = (int) ($meetRow['upcoming_meetings'] ?? 0);

        return $stats;
    }

    /**
     * Fetch per-student report data for PDF/HTML export.
     * Full JOIN — loads everything needed for the report in one query.
     */
    public function fetchStudentReportData(?int $studentId = null): array
    {
        $whereClause = $studentId !== null ? 'AND u.user_id = ?' : '';
        $bindings    = $studentId !== null ? [$studentId] : [];

        $stmt = $this->db->prepare(
            "SELECT
                u.user_id,
                u.full_name           AS student_name,
                u.email               AS student_email,
                st.matric_number,
                sv.full_name          AS supervisor_name,
                p.project_id,
                p.title               AS project_title,
                p.status              AS project_status,
                p.submission_date,
                p.approval_date,
                p.completion_date,
                COUNT(m.milestone_id)                       AS total_milestones,
                SUM(m.completion_status = 'completed')      AS completed_milestones,
                SUM(m.completion_status = 'in_progress')    AS inprogress_milestones,
                COUNT(DISTINCT mt.meeting_id)               AS total_meetings,
                COUNT(DISTINCT f.feedback_id)               AS total_feedback
               FROM users u
               JOIN students st        ON st.student_id    = u.user_id
               LEFT JOIN projects p    ON p.student_id     = u.user_id
               LEFT JOIN users sv      ON sv.user_id       = p.supervisor_id
               LEFT JOIN milestones m  ON m.project_id     = p.project_id
               LEFT JOIN meetings mt   ON mt.project_id    = p.project_id
               LEFT JOIN feedback f    ON f.milestone_id   = m.milestone_id
              WHERE u.role = 'student'
                AND u.is_active = 1
                {$whereClause}
              GROUP BY u.user_id, p.project_id
              ORDER BY sv.full_name ASC, u.full_name ASC"
        );
        $stmt->execute($bindings);

        return $stmt->fetchAll();
    }

    /**
     * Fetch recent audit log entries for admin visibility.
     */
    public function fetchRecentAuditLogs(int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT al.*, u.full_name, u.role
               FROM audit_logs al
               LEFT JOIN users u ON u.user_id = al.user_id
              ORDER BY al.created_at DESC
              LIMIT ?'
        );
        $stmt->execute([$limit]);

        return $stmt->fetchAll();
    }
}
