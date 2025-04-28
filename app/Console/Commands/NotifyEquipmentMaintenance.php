<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Equipment;
use App\Models\Client;
use App\Models\Corporate_clients;
use App\Models\Individual_clients;
use App\Models\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class NotifyEquipmentMaintenance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:equipment-maintenance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notifications for equipment nearing maintenance expiry or already expired';

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

        // Find equipment nearing maintenance expiry (1 week before expiry)
        $equipmentExpiringSoon = Equipment::with('client')
            ->where('expiry_date', $oneWeekFromNow->toDateString())
            ->where('status', 'active')
            ->get();

        // Notify service providers and clients about equipment expiring soon
        foreach ($equipmentExpiringSoon as $equipment) {
            $this->sendMaintenanceNotification($equipment, 'expiring_soon');
        }

        // Find equipment that has already expired
        $expiredEquipment = Equipment::with('client')
            ->where('expiry_date', '<', $today->toDateString())
            ->where('status', 'active') // Only notify for active equipment
            ->get();

        // Update the status of expired equipment and notify
        foreach ($expiredEquipment as $equipment) {
            $equipment->status = 'expired';
            $equipment->save();

            $this->sendMaintenanceNotification($equipment, 'expired');
        }

        $this->info('Equipment maintenance notifications sent successfully.');
        return 0;
    }

    /**
     * Send maintenance notification email.
     *
     * @param Equipment $equipment
     * @param string $type
     * @return void
     */
    private function sendMaintenanceNotification($equipment, $type)
    {
        // Retrieve the service provider who performed the maintenance
        $serviceProvider = DB::table('equipment_service_providers')
            ->join('service_providers', 'equipment_service_providers.service_provider_id', '=', 'service_providers.id')
            ->where('equipment_service_providers.equipment_id', $equipment->id)
            ->where('equipment_service_providers.status_service_provider', 1)
            ->select('service_providers.*')
            ->first();

        $client = $equipment->client;

        $subject = $type === 'expiring_soon'
            ? 'Equipment Maintenance Notification: 1 Week Remaining'
            : 'Equipment Maintenance Notification: Maintenance Expired';

        $message = $type === 'expiring_soon'
            ? "The equipment with serial number {$equipment->serial_number} will require maintenance on {$equipment->expiry_date}."
            : "The equipment with serial number {$equipment->serial_number} required maintenance on {$equipment->expiry_date} and is now expired.";

        // Send email to service provider
        if ($serviceProvider && $serviceProvider->email) {
            Mail::raw($message, function ($mail) use ($serviceProvider, $subject) {
                $mail->to($serviceProvider->email)
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
