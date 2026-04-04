<?php
/**
 * FYPTracker — Supervisor Repository
 * includes/repositories/SupervisorRepository.php
 *
 * Handles queries specific to the supervisor role:
 *   - Fetching their profile and assigned student overview
 *   - Workload and capacity queries (used by admin too)
 *   - Pending proposal counts
 *
 * ProjectRepository handles all project/milestone/meeting/feedback
 * CRUD — this class never duplicates those methods.
 *
 * Principles:
 *  - backend-patterns: repository pattern, N+1 prevention via JOINs
 *  - coding-standards: verb-noun names, named constants, descriptive vars
 */

class SupervisorRepository
{
    // ── Named constants ───────────────────────────────────────────────────
    public const DEFAULT_MAX_STUDENTS = 5;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ══════════════════════════════════════════════════════════════════════
    // PROFILE
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Find a supervisor's extended profile joined with their user record.
     * N+1 prevention: single JOIN.
     */
    public function findProfileByUserId(int $supervisorUserId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.user_id, u.full_name, u.email,
                    s.staff_id, s.title, s.specialisation, s.max_students
               FROM users u
               JOIN supervisors s ON s.supervisor_id = u.user_id
              WHERE u.user_id = ?
              LIMIT 1'
        );
        $stmt->execute([$supervisorUserId]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    // ══════════════════════════════════════════════════════════════════════
    // STUDENT OVERVIEW (for supervisor dashboard & milestone list)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Fetch all assigned students with project info and milestone progress.
     * Single query — aggregates in SQL to avoid N+1.
     */
    public function findAssignedStudentsWithProgress(int $supervisorUserId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                u.user_id,
                u.full_name                                      AS student_name,
                u.email                                          AS student_email,
                st.matric_number,
                p.project_id,
                p.title                                          AS project_title,
                p.status                                         AS project_status,
                p.submission_date,
                COUNT(m.milestone_id)                            AS total_milestones,
                SUM(m.completion_status = 'completed')           AS completed_milestones,
                SUM(m.completion_status = 'in_progress')         AS inprogress_milestones,
                SUM(m.completion_status = 'not_started')         AS pending_milestones
               FROM projects p
               JOIN users u      ON u.user_id     = p.student_id
               JOIN students st  ON st.student_id = p.student_id
               LEFT JOIN milestones m ON m.project_id = p.project_id
              WHERE p.supervisor_id = ?
              GROUP BY p.project_id
              ORDER BY u.full_name ASC"
        );
        $stmt->execute([$supervisorUserId]);

        return $stmt->fetchAll();
    }

    /**
     * Count pending proposals awaiting this supervisor's review.
     */
    public function countPendingProposals(int $supervisorUserId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM projects
              WHERE supervisor_id = ? AND status = 'pending'"
        );
        $stmt->execute([$supervisorUserId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Count upcoming meetings for this supervisor across all students.
     */
    public function countUpcomingMeetings(int $supervisorUserId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
               FROM meetings mt
               JOIN projects p ON p.project_id = mt.project_id
              WHERE p.supervisor_id = ?
                AND mt.status = 'scheduled'
                AND mt.scheduled_date >= NOW()"
        );
        $stmt->execute([$supervisorUserId]);

        return (int) $stmt->fetchColumn();
    }

    // ══════════════════════════════════════════════════════════════════════
    // MEETINGS — supervisor-scoped
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Find all meetings across all of a supervisor's projects.
     * N+1 prevention: student name joined in single query.
     */
    public function findAllMeetingsBySupervisorId(int $supervisorUserId): array
    {
        $stmt = $this->db->prepare(
            'SELECT mt.*,
                    u.full_name  AS student_name,
                    p.title      AS project_title,
                    p.project_id
               FROM meetings mt
               JOIN projects p ON p.project_id  = mt.project_id
               JOIN users u    ON u.user_id      = p.student_id
              WHERE p.supervisor_id = ?
              ORDER BY mt.scheduled_date DESC'
        );
        $stmt->execute([$supervisorUserId]);

        return $stmt->fetchAll();
    }

    /**
     * Find upcoming meetings for all of a supervisor's students.
     */
    public function findUpcomingMeetingsBySupervisorId(int $supervisorUserId, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT mt.*,
                    u.full_name AS student_name,
                    p.title     AS project_title
               FROM meetings mt
               JOIN projects p ON p.project_id = mt.project_id
               JOIN users u    ON u.user_id     = p.student_id
              WHERE p.supervisor_id = ?
                AND mt.status       = 'scheduled'
                AND mt.scheduled_date >= NOW()
              ORDER BY mt.scheduled_date ASC
              LIMIT ?"
        );
        $stmt->execute([$supervisorUserId, $limit]);

        return $stmt->fetchAll();
    }

    // ══════════════════════════════════════════════════════════════════════
    // ADMIN — all supervisors workload (used by admin assign page)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Fetch all supervisors with their current workload.
     * Used by admin assign_supervisors page.
     */
    public function findAllSupervisorsWithWorkload(): array
    {
        $stmt = $this->db->query(
            "SELECT
                u.user_id,
                u.full_name,
                u.email,
                s.staff_id,
                s.title,
                s.specialisation,
                s.max_students,
                COUNT(p.project_id)                       AS assigned_students,
                s.max_students - COUNT(p.project_id)      AS available_slots,
                SUM(p.status = 'pending')                 AS pending_reviews,
                SUM(p.status = 'in_progress')             AS active_projects,
                SUM(p.status = 'completed')               AS completed_projects
               FROM users u
               JOIN supervisors s ON s.supervisor_id = u.user_id
               LEFT JOIN projects p ON p.supervisor_id = u.user_id
              WHERE u.role = 'supervisor'
                AND u.is_active = 1
              GROUP BY u.user_id
              ORDER BY available_slots DESC, u.full_name ASC"
        );

        return $stmt->fetchAll();
    }
}
