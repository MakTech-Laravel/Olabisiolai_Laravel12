<?php

namespace App\Http\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait FileManagementTrait
{
    public function handleFileUpload(UploadedFile $file, string $folderName = 'uploads', ?string $fileName = null): string
    {
        $baseName = $fileName !== null && $fileName !== ''
            ? Str::slug($fileName)
            : Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        if ($baseName === '') {
            $baseName = 'file';
        }

        $generatedName = $baseName.'_'.time().rand(1000, 9999).'.'.$file->getClientOriginalExtension();

        return $file->storeAs($folderName, $generatedName, 'public');
    }

    public function fileDelete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
