<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Individual_clients;
use App\Models\Corporate_clients;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class NotifyCertificateExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:certificate-expiry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notifications for certificates nearing expiry or already expired';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get today's date and the date 1 week from now
        $today = Carbon::now();
        $oneWeekFromNow = $today->copy()->addWeek();

        // Find certificates expiring in 1 week
        $certificatesExpiringSoon = Certificate::with('fireServiceAgent', 'client')
            ->where('expiry_date', $oneWeekFromNow->toDateString())
            ->where('status', 'active')
            ->get();

        // Notify fire service agents and clients about certificates expiring soon
        foreach ($certificatesExpiringSoon as $certificate) {
            $this->sendExpiryNotification($certificate, 'expiring_soon');
        }

        // Find certificates that have already expired
        $expiredCertificates = Certificate::with('fireServiceAgent', 'client')
            ->where('expiry_date', '<', $today->toDateString())
            ->where('status', 'active') // Only notify for active certificates
            ->get();

        // Update the status of expired certificates and notify
        foreach ($expiredCertificates as $certificate) {
            $certificate->status = 'expired';
            $certificate->save();

            $this->sendExpiryNotification($certificate, 'expired');
        }

        $this->info('Certificate expiry notifications sent successfully.');
        return 0;
    }

    /**
     * Send expiry notification email.
     *
     * @param Certificate $certificate
     * @param string $type
     * @return void
     */
    private function sendExpiryNotification($certificate, $type)
    {
        $fireServiceAgent = $certificate->fireServiceAgent;
        $client = $certificate->client;

        $subject = $type === 'expiring_soon'
            ? 'Certificate Expiry Notification: 1 Week Remaining'
            : 'Certificate Expiry Notification: Certificate Expired';

        $message = $type === 'expiring_soon'
            ? "The certificate with serial number {$certificate->serial_number} will expire on {$certificate->expiry_date}."
            : "The certificate with serial number {$certificate->serial_number} has expired on {$certificate->expiry_date}.";

        // Send email to fire service agent
        if ($fireServiceAgent && $fireServiceAgent->email) {
            Mail::raw($message, function ($mail) use ($fireServiceAgent, $subject) {
                $mail->to($fireServiceAgent->email)
                    ->subject($subject);
            });
        }

        // Retrieve client email based on client_type
        $clientEmail = $this->getClientEmail($client);

        // Send email to client
        if ($clientEmail) {
            Mail::raw($message, function ($mail) use ($clientEmail, $subject) {
                $mail->to($clientEmail)
                    ->subject($subject);
            });
        }
    }

    /**
     * Retrieve the client's email based on client_type.
     *
     * @param $client
     * @return string|null
     */
    private function getClientEmail($client)
    {
        if (!$client) {
            return null;
        }

        if ($client->client_type === 'INDIVIDUAL') {
            $individualClient = Individual_clients::where('client_id', $client->id)->first();
            return $individualClient ? $client->email : null;
        } elseif ($client->client_type === 'CORPORATE') {
            $corporateClient = Corporate_clients::where('client_id', $client->id)->first();
            return $corporateClient ? $corporateClient->company_email : null;
        }

        return null;
    }
}
