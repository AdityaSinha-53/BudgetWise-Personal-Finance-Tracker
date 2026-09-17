<?php
// ============================================================
//  uploads.php  -  Receipt Attachment Upload Helpers
// ============================================================
//  Loaded only by pages that handle uploads — transaction add,
//  edit, and delete. Not included by db.php to keep the
//  default page payload small.
//
//  Three layers of security:
//      1. MIME type whitelist (images only)
//      2. Size limit (2 MB)
//      3. Content-based type detection via finfo, not the
//         browser-supplied $_FILES['type'] which is forgeable
//      4. Randomised filenames so users cannot overwrite each
//         other's files or guess names
// ============================================================


// Maximum allowed file size in bytes (2 MB)
define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024);


// ─── save_receipt_upload ───────────────────────────────────
// Reads a $_FILES['receipt'] upload, validates it, moves it to
// /uploads/receipts/ under a random name, and inserts a row
// into the attachments table linking it to the given
// transaction. Returns an array describing the outcome.
//
// USAGE:
//   $result = save_receipt_upload($conn, $user_id, $txn_id);
//   if (!$result['ok']) {
//       // $result['error'] holds a human-readable message
//   }
//
// If no file was provided in the upload (the user left the
// field empty), this is treated as success rather than error —
// receipts are optional.
function save_receipt_upload($conn, int $user_id, int $transaction_id): array
{
    // Whitelist: mime → file extension
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    // No file uploaded — that's fine, receipts are optional
    if (empty($_FILES['receipt']) || $_FILES['receipt']['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'uploaded' => false];
    }

    $file = $_FILES['receipt'];

    // Other upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = [
            UPLOAD_ERR_INI_SIZE   => 'File too large (server limit)',
            UPLOAD_ERR_FORM_SIZE  => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the file',
        ][$file['error']] ?? 'Unknown upload error';
        return ['ok' => false, 'error' => $msg];
    }

    // Size check
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'error' => 'File too large (maximum 2 MB)'];
    }
    if ($file['size'] <= 0) {
        return ['ok' => false, 'error' => 'Empty file'];
    }

    // Verify this is actually an uploaded file (not a regular
    // file path being injected through the request).
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Invalid upload'];
    }

    // Detect MIME type from the actual file contents.
    // Never trust $_FILES['type'] — the browser sets it freely.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return ['ok' => false, 'error' => 'Server cannot inspect uploads'];
    }
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, and WebP images are allowed'];
    }

    // Randomise the filename so users cannot overwrite each
    // other's files or guess the URLs.
    $ext         = $allowed[$mime];
    $stored_name = bin2hex(random_bytes(16)) . '.' . $ext;
    $upload_dir  = UPLOAD_BASE . 'receipts/';

    // Make sure the destination directory exists
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return ['ok' => false, 'error' => 'Server could not create upload directory'];
        }
    }

    $dest = $upload_dir . $stored_name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Could not save file'];
    }

    // Insert the metadata row
    $orig_name = substr($file['name'], 0, 255);
    $ok = db_run($conn, "
        INSERT INTO attachments
            (user_id, transaction_id, filename, stored_name, file_size, mime_type)
        VALUES (?, ?, ?, ?, ?, ?)
    ", "iissis", [
        $user_id, $transaction_id,
        $orig_name, $stored_name,
        (int)$file['size'], $mime
    ]);

    if (!$ok) {
        // Roll back the file copy if the DB insert failed
        @unlink($dest);
        return ['ok' => false, 'error' => 'Could not record attachment'];
    }

    return ['ok' => true, 'uploaded' => true, 'stored_name' => $stored_name];
}


// ─── remove_receipt_files ──────────────────────────────────
// Deletes all receipt files from disk that belong to a given
// transaction. Does not touch the attachments table — the
// foreign-key cascade handles that automatically when the
// transaction itself is deleted.
//
// Use this BEFORE deleting a transaction so the file cleanup
// happens while the attachments rows can still be queried.
function remove_receipt_files($conn, int $transaction_id): void
{
    $rows = db_all($conn,
        "SELECT stored_name FROM attachments WHERE transaction_id = ?",
        "i", [$transaction_id]
    );
    foreach ($rows as $r) {
        $path = UPLOAD_BASE . 'receipts/' . $r['stored_name'];
        if (is_file($path)) {
            @unlink($path);
        }
    }
}


// ─── purge_attachments_for_transaction ─────────────────────
// Removes both the files AND the attachments rows for a given
// transaction. Used when an edit explicitly removes the
// receipt while keeping the transaction.
function purge_attachments_for_transaction($conn, int $transaction_id): void
{
    remove_receipt_files($conn, $transaction_id);
    db_run($conn,
        "DELETE FROM attachments WHERE transaction_id = ?",
        "i", [$transaction_id]
    );
}


// ─── get_receipt_for_transaction ───────────────────────────
// Returns the single most-recent attachment row for a
// transaction, or null if none exists. Used by the edit page
// to display the current receipt.
function get_receipt_for_transaction($conn, int $transaction_id): ?array
{
    return db_one($conn, "
        SELECT id, filename, stored_name, mime_type, uploaded_at
        FROM attachments
        WHERE transaction_id = ?
        ORDER BY uploaded_at DESC
        LIMIT 1
    ", "i", [$transaction_id]);
}

// ============================================================
//  NO CLOSING PHP TAG
// ============================================================
