<?php
/**
 * FYPTracker — Project Repository
 * includes/repositories/ProjectRepository.php
 *
 * Centralises ALL database access for the projects domain.
 * Follows the Repository Pattern (backend-patterns skill).
 * No raw SQL in page controllers — everything goes through here.
 *
 * Principles applied:
 *  - backend-patterns: repository pattern, N+1 prevention via JOINs
 *  - coding-standards: verb-noun method names, descriptive variables,
 *                      early returns, no magic numbers
 */

class ProjectRepository
{
    // ── Named constants (coding-standards: no magic strings) ──────────────
    public const STATUS_PENDING     = 'pending';
    public const STATUS_APPROVED    = 'approved';
    public const STATUS_REJECTED    = 'rejected';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';

    public const MILESTONE_NOT_STARTED = 'not_started';
    public const MILESTONE_IN_PROGRESS = 'in_progress';
    public const MILESTONE_COMPLETED   = 'completed';

    public const MEETING_SCHEDULED   = 'scheduled';
    public const MEETING_COMPLETED   = 'completed';
    public const MEETING_CANCELLED   = 'cancelled';
    public const MEETING_RESCHEDULED = 'rescheduled';

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ══════════════════════════════════════════════════════════════════════
    // PROJECT QUERIES
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Find a student's project with supervisor name joined.
     * N+1 prevention: single JOIN instead of two queries.
     */
    public function findProjectByStudentId(int $studentId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*,
                    u.full_name AS supervisor_name,
                    u.email     AS supervisor_email
               FROM projects p
               LEFT JOIN users u ON u.user_id = p.supervisor_id
              WHERE p.student_id = ?
              LIMIT 1'
        );
        $stmt->execute([$studentId]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Find all projects for a supervisor with student info joined.
     * N+1 prevention: single JOIN query.
     */
    public function findProjectsBySupervisorId(int $supervisorId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*,
                    u.full_name    AS student_name,
                    st.matric_number
               FROM projects p
               JOIN users u    ON u.user_id    = p.student_id
               JOIN students st ON st.student_id = p.student_id
              WHERE p.supervisor_id = ?
              ORDER BY u.full_name ASC'
        );
        $stmt->execute([$supervisorId]);

        return $stmt->fetchAll();
    }

    /**
     * Find a single project by its primary key.
     */
    public function findProjectById(int $projectId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*,
                    su.full_name AS student_name,
                    sv.full_name AS supervisor_name
               FROM projects p
               JOIN users su ON su.user_id = p.student_id
               LEFT JOIN users sv ON sv.user_id = p.supervisor_id
              WHERE p.project_id = ?
              LIMIT 1'
        );
        $stmt->execute([$projectId]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Find all projects (admin view) — full join for reporting.
     */
    public function findAllProjectsWithDetails(): array
    {
        $stmt = $this->db->query(
            'SELECT p.*,
                    su.full_name    AS student_name,
                    st.matric_number,
                    sv.full_name    AS supervisor_name,
                    COUNT(m.milestone_id)                       AS total_milestones,
                    SUM(m.completion_status = \'completed\')   AS completed_milestones
               FROM projects p
               JOIN users su     ON su.user_id    = p.student_id
               JOIN students st  ON st.student_id = p.student_id
               LEFT JOIN users sv     ON sv.user_id    = p.supervisor_id
               LEFT JOIN milestones m ON m.project_id  = p.project_id
              GROUP BY p.project_id
              ORDER BY p.created_at DESC'
        );

        return $stmt->fetchAll();
    }

    /**
     * Create a new project proposal.
     * Returns the new project_id.
     */
    public function createProject(
        int    $studentId,
        string $title,
        string $description,
        ?string $chapterFilePath = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO projects
                (student_id, title, description, chapter_file, status, submission_date)
             VALUES (?, ?, ?, ?, ?, CURDATE())'
        );
        $stmt->execute([
            $studentId,
            $title,
            $description,
            $chapterFilePath,
            self::STATUS_PENDING,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a project's proposal details (for resubmission after rejection).
     */
    public function updateProjectProposal(
        int    $projectId,
        string $title,
        string $description,
        ?string $chapterFilePath = null
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE projects
                SET title           = ?,
                    description     = ?,
                    chapter_file    = COALESCE(?, chapter_file),
                    status          = ?,
                    rejection_reason = NULL,
                    submission_date  = CURDATE()
              WHERE project_id = ?'
        );
        $stmt->execute([
            $title,
            $description,
            $chapterFilePath,
            self::STATUS_PENDING,
            $projectId,
        ]);
    }

    /**
     * Update a project's status (approve / reject / complete).
     */
    public function updateProjectStatus(
        int    $projectId,
        string $status,
        ?string $rejectionReason = null
    ): void {
        $approvalDate    = in_array($status, [self::STATUS_APPROVED, self::STATUS_IN_PROGRESS], true)
            ? date('Y-m-d') : null;
        $completionDate  = ($status === self::STATUS_COMPLETED) ? date('Y-m-d') : null;

        $stmt = $this->db->prepare(
            'UPDATE projects
                SET status           = ?,
                    rejection_reason = ?,
                    approval_date    = COALESCE(?, approval_date),
                    completion_date  = COALESCE(?, completion_date)
              WHERE project_id = ?'
        );
        $stmt->execute([
            $status,
            $rejectionReason,
            $approvalDate,
            $completionDate,
            $projectId,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // MILESTONE QUERIES
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Find all milestones for a project, ordered by due date.
     */
    public function findMilestonesByProjectId(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*,
                    u.full_name AS created_by_name
               FROM milestones m
               JOIN users u ON u.user_id = m.created_by
              WHERE m.project_id = ?
              ORDER BY m.due_date ASC'
        );
        $stmt->execute([$projectId]);

        return $stmt->fetchAll();
    }

    /**
     * Find a single milestone with its feedback (N+1 prevention).
     * Returns milestone + all feedback rows joined.
     */
    public function findMilestoneWithFeedback(int $milestoneId): ?array
    {
        // Milestone
        $stmtMilestone = $this->db->prepare(
            'SELECT m.*, u.full_name AS created_by_name
               FROM milestones m
               JOIN users u ON u.user_id = m.created_by
              WHERE m.milestone_id = ?
              LIMIT 1'
        );
        $stmtMilestone->execute([$milestoneId]);
        $milestone = $stmtMilestone->fetch();

        if (!$milestone) {
            return null;
        }

        // Feedback for this milestone (all rows at once — no loop)
        $stmtFeedback = $this->db->prepare(
            'SELECT f.*, u.full_name AS supervisor_name
               FROM feedback f
               JOIN users u ON u.user_id = f.supervisor_id
              WHERE f.milestone_id = ?
              ORDER BY f.created_at DESC'
        );
        $stmtFeedback->execute([$milestoneId]);
        $milestone['feedback'] = $stmtFeedback->fetchAll();

        return $milestone;
    }

    /**
     * Create a new milestone.
     */
    public function createMilestone(
        int    $projectId,
        string $title,
        string $description,
        string $dueDate,
        int    $createdByUserId
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO milestones
                (project_id, title, description, due_date, completion_status, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $projectId,
            $title,
            $description,
            $dueDate,
            self::MILESTONE_NOT_STARTED,
            $createdByUserId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a milestone's completion status.
     */
    public function updateMilestoneStatus(
        int    $milestoneId,
        string $completionStatus,
        ?string $submissionFilePath = null
    ): void {
        $completedAt = ($completionStatus === self::MILESTONE_COMPLETED)
            ? date('Y-m-d H:i:s') : null;

        $stmt = $this->db->prepare(
            'UPDATE milestones
                SET completion_status = ?,
                    submission_file   = COALESCE(?, submission_file),
                    completed_at      = COALESCE(?, completed_at)
              WHERE milestone_id = ?'
        );
        $stmt->execute([
            $completionStatus,
            $submissionFilePath,
            $completedAt,
            $milestoneId,
        ]);
    }

    /**
     * Count milestone completion stats for a project.
     * Returns associative array: ['total', 'completed', 'in_progress', 'not_started']
     */
    public function countMilestoneStatsByProjectId(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                COUNT(*)                                    AS total,
                SUM(completion_status = \'completed\')     AS completed,
                SUM(completion_status = \'in_progress\')   AS in_progress,
                SUM(completion_status = \'not_started\')   AS not_started
               FROM milestones
              WHERE project_id = ?'
        );
        $stmt->execute([$projectId]);
        $row = $stmt->fetch();

        // Ensure integers (N+1 prevention: aggregated in single query)
        return [
            'total'       => (int) ($row['total']       ?? 0),
            'completed'   => (int) ($row['completed']   ?? 0),
            'in_progress' => (int) ($row['in_progress'] ?? 0),
            'not_started' => (int) ($row['not_started'] ?? 0),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // MEETING QUERIES
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Find all meetings for a project, ordered by date desc.
     * N+1 prevention: requester name joined.
     */
    public function findMeetingsByProjectId(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT mt.*, u.full_name AS requested_by_name
               FROM meetings mt
               JOIN users u ON u.user_id = mt.requested_by
              WHERE mt.project_id = ?
              ORDER BY mt.scheduled_date DESC'
        );
        $stmt->execute([$projectId]);

        return $stmt->fetchAll();
    }

    /**
     * Find a single meeting by ID.
     */
    public function findMeetingById(int $meetingId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT mt.*, u.full_name AS requested_by_name
               FROM meetings mt
               JOIN users u ON u.user_id = mt.requested_by
              WHERE mt.meeting_id = ?
              LIMIT 1'
        );
        $stmt->execute([$meetingId]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Find upcoming meetings for a project (status=scheduled, future dates).
     */
    public function findUpcomingMeetingsByProjectId(int $projectId, int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT mt.*, u.full_name AS requested_by_name
               FROM meetings mt
               JOIN users u ON u.user_id = mt.requested_by
              WHERE mt.project_id = ?
                AND mt.status     = ?
                AND mt.scheduled_date >= NOW()
              ORDER BY mt.scheduled_date ASC
              LIMIT ?'
        );
        $stmt->execute([$projectId, self::MEETING_SCHEDULED, $limit]);

        return $stmt->fetchAll();
    }

    /**
     * Create a new meeting request.
     */
    public function createMeeting(
        int    $projectId,
        string $scheduledDate,
        string $venue,
        string $agenda,
        int    $requestedByUserId
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO meetings
                (project_id, scheduled_date, venue, agenda, status, requested_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $projectId,
            $scheduledDate,
            $venue,
            $agenda,
            self::MEETING_SCHEDULED,
            $requestedByUserId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update meeting status (complete / cancel).
     */
    public function updateMeetingStatus(
        int    $meetingId,
        string $status,
        ?string $minutes = null
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE meetings
                SET status  = ?,
                    minutes = COALESCE(?, minutes)
              WHERE meeting_id = ?'
        );
        $stmt->execute([$status, $minutes, $meetingId]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // FEEDBACK QUERIES
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Find all feedback for a project's milestones.
     * N+1 prevention: single JOIN across milestones + feedback + users.
     */
    public function findFeedbackByProjectId(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT f.*,
                    m.title      AS milestone_title,
                    u.full_name  AS supervisor_name
               FROM feedback f
               JOIN milestones m ON m.milestone_id = f.milestone_id
               JOIN users u      ON u.user_id       = f.supervisor_id
              WHERE m.project_id = ?
              ORDER BY f.created_at DESC'
        );
        $stmt->execute([$projectId]);

        return $stmt->fetchAll();
    }

    /**
     * Create feedback for a milestone.
     */
    public function createFeedback(
        int    $milestoneId,
        int    $supervisorId,
        string $comment,
        ?int   $rating = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO feedback (milestone_id, supervisor_id, comment, rating)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$milestoneId, $supervisorId, $comment, $rating]);

        return (int) $this->db->lastInsertId();
    }
}
