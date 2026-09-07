<?php

namespace App\Services\Items;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/**
 * Shared upload/delete logic for Item file attachments, previously duplicated
 * between ItemController::store()/update()/delete_file()/destroy().
 */
class ItemFileManager
{
    private const DIRECTORY = 'items';

    /**
     * Move a batch of uploaded files into storage and build their metadata entries.
     *
     * @param UploadedFile[] $files
     * @return array<int, array{original: string, stored: string, uploaded_at: string}>
     */
    public static function storeMany(array $files): array
    {
        $meta = [];
        foreach ($files as $file) {
            if (!$file) continue;
            $meta[] = self::storeOne($file);
        }
        return $meta;
    }

    public static function storeOne(UploadedFile $file): array
    {
        $original = $file->getClientOriginalName();
        $unique = uniqid(date('YmdHis') . '_');
        $ext = $file->getClientOriginalExtension();
        $storedName = $unique . ($ext ? ('.' . $ext) : '');
        $file->move(self::directory(), $storedName);

        return [
            'original' => $original,
            'stored' => $storedName,
            'uploaded_at' => now()->toDateTimeString(),
        ];
    }

    public static function delete(string $storedName): void
    {
        File::delete(self::path($storedName));
    }

    public static function path(string $storedName): string
    {
        return self::directory() . '/' . $storedName;
    }

    public static function directory(): string
    {
        return storage_path('app/private/' . self::DIRECTORY);
    }
}
