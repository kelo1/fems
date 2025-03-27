<?php

namespace App\Http\Controllers;

use App\Models\QRCode;
use App\Models\Equipment;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode as SimpleQrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;

class QRCodeController extends Controller
{
    public function generateQrCode($serial_number)
    {
        $equipment = Equipment::where('serial_number', $serial_number)->firstOrFail();

        // Check if the authenticated user can generate the QR code
        if (Gate::denies('generateQrCode', $equipment)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Serialize equipment attributes to JSON
        $equipmentJson = json_encode($equipment->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Generate QR code from JSON string
        $qrCodePng = SimpleQrCode::format('png')->size(300)->generate($equipmentJson);

        // Save QR code as a PNG file
        $filePath = 'qrcodes/' . $equipment->serial_number . '.png';
        Storage::disk('public')->put($filePath, $qrCodePng);

        // Create or update the QRCode record
        $qrCode = QRCode::updateOrCreate(
            ['serial_number' => $equipment->serial_number],
            ['qr_code_path' => $filePath]
        );

        return response()->json(['message' => 'QR code generated successfully', 'qr_code_url' => Storage::url($filePath)], 201);
    }

    public function updateQrCode($serial_number)
    {
        $equipment = Equipment::where('serial_number', $serial_number)->firstOrFail();

        // Check if the authenticated user can update the QR code
        if (Gate::denies('generateQrCode', $equipment)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Serialize equipment attributes to JSON
        $equipmentJson = json_encode($equipment->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Generate QR code from JSON string
        $qrCodePng = SimpleQrCode::format('png')->size(300)->generate($equipmentJson);

        // Save QR code as a PNG file
        $filePath = 'qrcodes/' . $equipment->serial_number . '.png';
        Storage::disk('public')->put($filePath, $qrCodePng);

        // Update the QRCode record
        $qrCode = QRCode::where('serial_number', $equipment->serial_number)->first();
        if ($qrCode) {
            $qrCode->qr_code_path = $filePath;
            $qrCode->save();
        } else {
            return response()->json(['message' => 'QR code not found'], 404);
        }

        return response()->json(['message' => 'QR code updated successfully', 'qr_code_url' => Storage::url($filePath)], 200);
    }

    public function decodeQrCode($serial_number)
    {
        $equipment = Equipment::where('serial_number', $serial_number)->firstOrFail();
        $qrCodePath = $equipment->qrCode->qr_code_path;

        // Return the QR code file path as a JSON response
        return response()->json(['qr_code_url' => Storage::url($qrCodePath)]);
    }
}
