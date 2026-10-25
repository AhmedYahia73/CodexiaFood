<?php

namespace App\Services;

use App\Models\BusinessSetup;
use App\Models\HallTable;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    /**
     * Generate and store QR code for a hall table.
     */
    public function generateForTable(HallTable $table, $request): string
    {
        $content = $request->base_url."/table/{$table->code}";
        $directory = 'qrcodes/tables';
        $fileName = "table_{$table->code}.svg";
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

    /**
     * Build customer order tracking URL for an order.
     */
    public function getOrderTrackingUrl(Order|string $order): string
    {
        $code = $order instanceof Order ? $order->code : $order;
        $businessSetup = BusinessSetup::first();
        $baseUrl = $businessSetup?->table_order_url;

        if (empty($baseUrl)) {
            $baseUrl = config('app.url');
        }

        $baseUrl = rtrim($baseUrl, '/');

        if (str_ends_with($baseUrl, '/my_order')) {
            return "{$baseUrl}/{$code}";
        }

        return "{$baseUrl}/my_order/{$code}";
    }

    /**
     * Generate SVG string for order QR code without saving to server.
     */
    public function generateOrderQrSvg(Order|string $order, int $size = 250): string
    {
        $targetUrl = $this->getOrderTrackingUrl($order);

        return (string) QrCode::size($size)->generate($targetUrl);
    }

    /**
     * Generate base64 data URI for order QR code without saving to server.
     */
    public function generateOrderQrBase64(Order|string $order, int $size = 250): string
    {
        $svg = $this->generateOrderQrSvg($order, $size);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
