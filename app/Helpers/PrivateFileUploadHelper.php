<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PrivateFileUploadHelper
{
    /**
     * Upload a file and optionally replace an old one.
     * Works for both store & update.
     */
    public static function upload(
        ?UploadedFile $file,
        string $path,
        ?string $oldFile = null,
         bool $deleteOldFile = true,
        string $disk = 'private',
       
    ): ?string {

        // No new file → keep old file (important for update)
        if (!$file) {
            return $oldFile;
        }

        // Delete old file if exists        
        if ($oldFile && Storage::disk($disk)->exists($oldFile) && $deleteOldFile) {
            Storage::disk($disk)->delete($oldFile);
        }

        // Store new file and return relative path
        return $file->store($path, $disk);
    }
}
