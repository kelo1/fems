<?php

namespace App\Http\Controllers;

use App\Models\QRCode;
use App\Models\Equipment;
use App\Models\Certificate;
use App\Models\Notification;
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
        try {
            $equipment = Equipment::where('serial_number', $serial_number)->firstOrFail();

            // Check if the authenticated user can generate the QR code
            if (Gate::denies('generateQrCode', $equipment)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Generate the URL to embed in the QR code
            $url = URL::to('/equipment-service?equipment=' . $serial_number);

            // Generate QR code from the URL
            $qrCodePng = SimpleQrCode::format('png')->size(300)->generate($url);

            // Save QR code as a PNG file in the S3 bucket
            $filePath = 'qrcodes/equipment/' . $equipment->serial_number . '.png';
            Storage::disk('s3')->put($filePath, $qrCodePng);

            // Create or update the QRCode record
            $qrCode = QRCode::updateOrCreate(
                ['serial_number' => $equipment->serial_number],
                ['qr_code_path' => $filePath]
            );

            // Update isActive column in equipment table
            $equipment->update(['isActive' => true]);

            // Generate the public URL for the QR code
            $qrCodeUrl = Storage::disk('s3')->url($filePath);

            // Find the service provider (adjust relationship as needed)
            $serviceProvider = $equipment->serviceProvider ?? null;
            if ($serviceProvider) {
                // Send notification using Laravel's notification system only (no manual Notification::create)
                try {
                    $serviceProvider->notify(new \App\Notifications\EquipmentQrNotification($equipment, $qrCodeUrl));
                } catch (\Exception $e) {
                    \Log::error('Error sending equipment QR notification', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            return response()->json(['message' => 'QR code generated successfully', 'qr_code_url' => $qrCodeUrl], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Equipment not found'], 404);
        } catch (\Exception $e) {
            \Log::error('Error generating equipment QR code', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'An error occurred while generating the equipment QR code'], 500);
        }
    }

    public function generateCertificateQrCode($serial_number)
    {
        try {
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
            $url = URL::to('/certificate-details?certifcate=' . $serial_number);

            // Generate QR code from the URL
            $qrCodePng = SimpleQrCode::format('png')
                ->size(300)
                ->margin(2)
                ->color(255, 0, 0)
                ->backgroundColor(255, 255, 255)
                ->generate($url);

            // Save QR code as a PNG file in the S3 bucket
            $filePath = 'qrcodes/certificate/' . $certificate->serial_number . '.png';
            Storage::disk('s3')->put($filePath, $qrCodePng);

            // Update or create the QRCode record
            $qrCode = QRCode::updateOrCreate(
                ['serial_number' => $certificate->serial_number],
                ['qr_code_path' => $filePath]
            );

            // Update isVerified column in certificate table
            $certificate->update(['isVerified' => true]);

            // Generate the public URL for the QR code
            $qrCodeUrl = Storage::disk('s3')->url($filePath);

            // Find the FSA_AGENT (adjust relationship as needed)
            $fireServiceAgent = $certificate->fireServiceAgent ?? null;
            if ($fireServiceAgent) {
                // Send notification using Laravel's notification system only (no manual Notification::create)
                try {
                    $fireServiceAgent->notify(new \App\Notifications\CertificateQrNotification($certificate, $qrCodeUrl));
                } catch (\Exception $e) {
                    \Log::error('Error sending certificate QR notification', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            return response()->json(['message' => 'QR code updated successfully', 'qr_code_url' => $qrCodeUrl], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Certificate not found'], 404);
        } catch (\Exception $e) {
            \Log::error('Error generating certificate QR code', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'An error occurred while generating the certificate QR code'], 500);
        }
    }

    public function decodeQrCode($serial_number)
    {
        $qrCodePath = QRCode::where('serial_number', $serial_number)->value('qr_code_path');

        // Generate the public URL for the QR code
        $qrCodeUrl = Storage::disk('s3')->url($qrCodePath);

        return response()->json(['qr_code_url' => $qrCodeUrl]);
    }
}
