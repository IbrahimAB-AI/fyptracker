<?php
/**
 * FYPTracker — File Upload Service
 * includes/services/FileUploadService.php
 *
 * Centralises ALL file upload validation and storage logic.
 * No scattered file handling in page controllers.
 *
 * Principles applied:
 *  - coding-standards: DRY, single responsibility, named constants,
 *                      descriptive names, comprehensive error handling
 *  - backend-patterns: service layer, centralized validation
 */

class FileUploadService
{
    // ── Named constants (coding-standards: no magic numbers/strings) ──────
    private const ALLOWED_MIME_TYPES = [
        'application/pdf'                                                  => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/msword'                                               => 'doc',
    ];

    private const ALLOWED_EXTENSIONS  = ['pdf', 'docx', 'doc'];
    private const MAX_FILE_SIZE_BYTES  = 10 * 1024 * 1024; // 10 MB
    private const MAX_FILE_SIZE_LABEL  = '10MB';

    /** Subdirectory under uploads/ for each context */
    private const UPLOAD_DIRECTORIES = [
        'proposal'   => 'proposals',
        'milestone'  => 'milestones',
        'chapter'    => 'chapters',
    ];

    private string $uploadsBasePath;

    public function __construct(string $uploadsBasePath)
    {
        $this->uploadsBasePath = rtrim($uploadsBasePath, '/');
    }

    // ── Public API ────────────────────────────────────────────────────────

    /**
     * Validate and store an uploaded file.
     *
     * @param array  $fileData   $_FILES['field'] entry
     * @param string $context    'proposal' | 'milestone' | 'chapter'
     * @param string $prefix     Filename prefix (e.g. 'student_5')
     *
     * @return array{
     *   success: bool,
     *   path: string|null,
     *   error: string|null
     * }
     */
    public function handleUpload(
        array  $fileData,
        string $context,
        string $prefix
    ): array {
        // Early return: no file uploaded
        if ($this->isEmptyUpload($fileData)) {
            return $this->buildResult(true, null, null);
        }

        $validationError = $this->validateUpload($fileData);
        if ($validationError !== null) {
            return $this->buildResult(false, null, $validationError);
        }

        $savedPath = $this->saveFile($fileData, $context, $prefix);
        if ($savedPath === null) {
            return $this->buildResult(false, null, 'Failed to save file. Please try again.');
        }

        return $this->buildResult(true, $savedPath, null);
    }

    /**
     * Returns whether a file was actually submitted (not an empty input).
     */
    public function isEmptyUpload(array $fileData): bool
    {
        return empty($fileData['tmp_name']) || $fileData['error'] === UPLOAD_ERR_NO_FILE;
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Run all validation checks. Returns error message or null if valid.
     * Early returns per coding-standards: no deep nesting.
     */
    private function validateUpload(array $fileData): ?string
    {
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            return $this->describeUploadError($fileData['error']);
        }

        if ($fileData['size'] > self::MAX_FILE_SIZE_BYTES) {
            return sprintf(
                'File is too large. Maximum allowed size is %s.',
                self::MAX_FILE_SIZE_LABEL
            );
        }

        if (!$this->hasAllowedExtension($fileData['name'])) {
            return sprintf(
                'Invalid file type. Only %s files are accepted.',
                implode(', ', array_map('strtoupper', self::ALLOWED_EXTENSIONS))
            );
        }

        if (!$this->hasAllowedMimeType($fileData['tmp_name'])) {
            return 'File content does not match the expected type. Please upload a valid PDF or Word document.';
        }

        return null;
    }

    /**
     * Save the validated file to disk.
     * Returns relative path (from project root) or null on failure.
     */
    private function saveFile(array $fileData, string $context, string $prefix): ?string
    {
        $subDirectory = self::UPLOAD_DIRECTORIES[$context] ?? 'misc';
        $targetDir    = $this->uploadsBasePath . '/' . $subDirectory;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            error_log("FileUploadService: Failed to create directory {$targetDir}");
            return null;
        }

        $extension   = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        $uniqueName  = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destination = $targetDir . '/' . $uniqueName;

        if (!move_uploaded_file($fileData['tmp_name'], $destination)) {
            error_log("FileUploadService: move_uploaded_file failed to {$destination}");
            return null;
        }

        // Return path relative to project root for DB storage
        return 'uploads/' . $subDirectory . '/' . $uniqueName;
    }

    private function hasAllowedExtension(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, self::ALLOWED_EXTENSIONS, true);
    }

    private function hasAllowedMimeType(string $tmpPath): bool
    {
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpPath);

        return array_key_exists($mimeType, self::ALLOWED_MIME_TYPES);
    }

    /**
     * Human-readable upload error (coding-standards: no magic numbers).
     */
    private function describeUploadError(int $errorCode): string
    {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload size limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form upload size limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error: missing temp directory.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A server extension blocked the upload.',
        ];

        return $errorMessages[$errorCode]
            ?? 'An unknown upload error occurred. Please try again.';
    }

    private function buildResult(bool $success, ?string $path, ?string $error): array
    {
        return [
            'success' => $success,
            'path'    => $path,
            'error'   => $error,
        ];
    }
}
