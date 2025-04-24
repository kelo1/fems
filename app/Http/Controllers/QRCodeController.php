<?php

namespace App\Http\Controllers;

use App\Models\QRCode;
use App\Models\Equipment;
use App\Models\Certificate;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode as SimpleQrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Auth;

class QRCodeController extends Controller
{
    public function generateEquipmentQrCode($serial_number)
    {
        $equipment = Equipment::where('serial_number', $serial_number)->firstOrFail();

        // Check if the authenticated user can generate the QR code
        if (Gate::denies('generateQrCode', $equipment)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Generate the URL to embed in the QR code
        $url = URL::to('/equipment/qrcode/?serial_number=' . $serial_number);

        // Generate QR code from the URL
        $qrCodePng = SimpleQrCode::format('png')->size(300)->generate($url);

        // Save QR code as a PNG file
        $filePath = 'qrcodes/equipment/' . $equipment->serial_number . '.png';
        Storage::disk('public')->put($filePath, $qrCodePng);

        // Create or update the QRCode record
        $qrCode = QRCode::updateOrCreate(
            ['serial_number' => $equipment->serial_number],
            ['qr_code_path' => $filePath]
        );

        // Update isActive column in equipment table
        $equipment->update(['isActive' => true]);

        return response()->json(['message' => 'QR code generated successfully', 'qr_code_url' => Storage::url($filePath)], 201);
    }

    public function generateCertificateQrCode($serial_number)
    {
         // Verify if the user is authenticated
         $user = Auth::user();
         if (!$user) {
             return response()->json(['message' => 'Unauthorized'], 401);
         }
 
         // Check if the user has the required role
         if (get_class($user) != "App\Models\FEMSAdmin") {
             return response()->json(['message' => 'Unauthorized'], 403);
         }
         

        $certificate = Certificate::where('serial_number', $serial_number)->firstOrFail();
       

        // Generate the URL to embed in the QR code
        $url = URL::to('/certificate/qrcode/?serial_number=' . $serial_number);

        // Generate QR code from the URL
        $qrCodePng = SimpleQrCode::format('png')
        ->size(300)
        ->margin(2)
        ->color(255, 0, 0) // Red color
        ->backgroundColor(255, 255, 255) // White background
        ->generate($url);

        // Save QR code as a PNG file
        $filePath = 'qrcodes/certificate/' . $certificate->serial_number . '.png';
        Storage::disk('public')->put($filePath, $qrCodePng);

        // Update or create the QRCode record
        $qrCode = QRCode::updateOrCreate(
            ['serial_number' => $certificate->serial_number],
            ['qr_code_path' => $filePath]
        );

        // Update isVerified column in certificate table
        $certificate->update(['isVerified' => true]);

        return response()->json(['message' => 'QR code updated successfully', 'qr_code_url' => Storage::url($filePath)], 200);
    }

    public function decodeQrCode($serial_number)
    {
        //$equipment = Equipment::where('serial_number', $serial_number)->firstOrFail();
        $qrCodePath = QRCode::where('serial_number', $serial_number)->value('qr_code_path');
        $baseURL = env('APP_BASE_URL', config('app.url')); // Fallback to app.url if APP_BASE_URL is not set

        // Return the QR code file path as a JSON response
        return response()->json(['qr_code_url' => $baseURL . Storage::url($qrCodePath)]);
    }
}
