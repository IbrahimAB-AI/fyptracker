<?php
/**
 * FYPTracker — Validation Service
 * includes/services/ValidationService.php
 *
 * Centralises all form validation logic.
 * Follows coding-standards: DRY, descriptive names, no magic numbers,
 * early returns, comprehensive error messages.
 */

class ValidationService
{
    // ── Named constants ───────────────────────────────────────────────────
    private const MIN_TITLE_LENGTH       = 10;
    private const MAX_TITLE_LENGTH       = 255;
    private const MIN_DESCRIPTION_LENGTH = 50;
    private const MAX_DESCRIPTION_LENGTH = 5000;
    private const MIN_AGENDA_LENGTH      = 10;
    private const MAX_AGENDA_LENGTH      = 1000;
    private const MIN_COMMENT_LENGTH     = 10;
    private const MAX_COMMENT_LENGTH     = 3000;
    private const MIN_VENUE_LENGTH       = 3;
    private const MAX_VENUE_LENGTH       = 255;
    private const MIN_RATING             = 1;
    private const MAX_RATING             = 5;

    private array $errors = [];

    // ── Public API ────────────────────────────────────────────────────────

    /**
     * Validate proposal submission form.
     * Returns true if valid, false otherwise. Errors via getErrors().
     */
    public function validateProposalForm(string $title, string $description): bool
    {
        $this->errors = [];

        $this->checkStringLength('Project title', $title, self::MIN_TITLE_LENGTH, self::MAX_TITLE_LENGTH);
        $this->checkStringLength('Project description', $description, self::MIN_DESCRIPTION_LENGTH, self::MAX_DESCRIPTION_LENGTH);

        return $this->hasNoErrors();
    }

    /**
     * Validate meeting request form.
     */
    public function validateMeetingForm(
        string $scheduledDate,
        string $venue,
        string $agenda
    ): bool {
        $this->errors = [];

        $this->checkFutureDateTime('Meeting date', $scheduledDate);
        $this->checkStringLength('Venue', $venue, self::MIN_VENUE_LENGTH, self::MAX_VENUE_LENGTH);
        $this->checkStringLength('Agenda', $agenda, self::MIN_AGENDA_LENGTH, self::MAX_AGENDA_LENGTH);

        return $this->hasNoErrors();
    }

    /**
     * Validate milestone creation form (supervisor).
     */
    public function validateMilestoneForm(
        string $title,
        string $description,
        string $dueDate
    ): bool {
        $this->errors = [];

        $this->checkStringLength('Milestone title', $title, self::MIN_TITLE_LENGTH, self::MAX_TITLE_LENGTH);
        $this->checkStringLength('Description', $description, self::MIN_AGENDA_LENGTH, self::MAX_DESCRIPTION_LENGTH);
        $this->checkFutureDate('Due date', $dueDate);

        return $this->hasNoErrors();
    }

    /**
     * Validate feedback form (supervisor).
     */
    public function validateFeedbackForm(string $comment, ?string $rating): bool
    {
        $this->errors = [];

        $this->checkStringLength('Feedback comment', $comment, self::MIN_COMMENT_LENGTH, self::MAX_COMMENT_LENGTH);

        if ($rating !== null && $rating !== '') {
            $ratingInt = (int) $rating;
            if ($ratingInt < self::MIN_RATING || $ratingInt > self::MAX_RATING) {
                $this->errors[] = sprintf(
                    'Rating must be between %d and %d.',
                    self::MIN_RATING,
                    self::MAX_RATING
                );
            }
        }

        return $this->hasNoErrors();
    }

    /**
     * Validate milestone status update (student marking complete).
     */
    public function validateMilestoneStatusUpdate(string $status): bool
    {
        $this->errors = [];

        $allowedStatuses = [
            ProjectRepository::MILESTONE_NOT_STARTED,
            ProjectRepository::MILESTONE_IN_PROGRESS,
            ProjectRepository::MILESTONE_COMPLETED,
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            $this->errors[] = 'Invalid milestone status provided.';
        }

        return $this->hasNoErrors();
    }

    /**
     * Returns all accumulated validation errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Returns the first error as a single string (for simple flash messages).
     */
    public function getFirstError(): string
    {
        return $this->errors[0] ?? '';
    }

    // ── Private rule helpers ──────────────────────────────────────────────

    private function checkStringLength(
        string $fieldName,
        string $value,
        int    $minLength,
        int    $maxLength
    ): void {
        $trimmed = trim($value);
        $length  = mb_strlen($trimmed);

        if ($length < $minLength) {
            $this->errors[] = sprintf(
                '%s must be at least %d characters (currently %d).',
                $fieldName,
                $minLength,
                $length
            );
            return;
        }

        if ($length > $maxLength) {
            $this->errors[] = sprintf(
                '%s must not exceed %d characters (currently %d).',
                $fieldName,
                $maxLength,
                $length
            );
        }
    }

    private function checkFutureDateTime(string $fieldName, string $dateTimeString): void
    {
        if (empty(trim($dateTimeString))) {
            $this->errors[] = "{$fieldName} is required.";
            return;
        }

        $timestamp = strtotime($dateTimeString);

        if ($timestamp === false) {
            $this->errors[] = "{$fieldName} is not a valid date and time.";
            return;
        }

        if ($timestamp <= time()) {
            $this->errors[] = "{$fieldName} must be in the future.";
        }
    }

    private function checkFutureDate(string $fieldName, string $dateString): void
    {
        if (empty(trim($dateString))) {
            $this->errors[] = "{$fieldName} is required.";
            return;
        }

        $timestamp = strtotime($dateString);

        if ($timestamp === false) {
            $this->errors[] = "{$fieldName} is not a valid date.";
            return;
        }

        if ($timestamp < strtotime('today')) {
            $this->errors[] = "{$fieldName} must be today or in the future.";
        }
    }

    private function hasNoErrors(): bool
    {
        return empty($this->errors);
    }
}
