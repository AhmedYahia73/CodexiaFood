<?php

namespace App\Services;

use App\Models\HallTable;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    /**
     * Generate and store QR code for a hall table.
     */
    public function generateForTable(HallTable $table): string
    {
        $content = "tableOrder/{$table->id}";
        $directory = 'qrcodes/tables';
        $fileName = "table_{$table->id}.svg";
        $path = "{$directory}/{$fileName}";

        $svg = QrCode::size(250)->generate($content);

        Storage::disk('public')->put($path, (string) $svg);

        $table->update(['qr' => $path]);

        return $path;
    }

    /**
     * Delete QR code file for a hall table.
     */
    public function deleteForTable(HallTable $table): void
    {
        if ($table->qr && Storage::disk('public')->exists($table->qr)) {
            Storage::disk('public')->delete($table->qr);
        }
    }
}
