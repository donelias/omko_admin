<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Property;
use App\Models\PropertyAlert;
use App\Models\SavedSearch;
use App\Models\Usertokens;
use App\Services\HelperService;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckSavedSearchAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    public function handle(): void
    {
        $activeSearches = SavedSearch::where('is_active', true)
            ->with('customer:id,name,email,notification,full_mobile')
            ->get();

        foreach ($activeSearches as $search) {
            $this->processSearch($search);
        }
    }

    private function processSearch(SavedSearch $search): void
    {
        try {
            if ($search->frequency === 'instant') {
                $since = now()->subMinutes(5);
            } elseif ($search->frequency === 'daily') {
                $since = now()->subDay();
            } else {
                $since = now()->subWeek();
            }

            $newProperties = $this->findMatchingProperties($search->filters, $since);

            if ($newProperties->isEmpty()) {
                return;
            }

            foreach ($newProperties as $property) {
                $alreadySent = PropertyAlert::where('customer_id', $search->customer_id)
                    ->where('saved_search_id', $search->id)
                    ->where('property_id', $property->id)
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                $emailSent = false;
                $pushSent = false;
                $whatsappSent = false;

                if ($search->customer && $search->customer->notification) {
                    $emailSent = $this->sendEmailAlert($search, $property);
                }

                $pushSent = $this->sendPushAlert($search, $property);
                $whatsappSent = $this->sendWhatsAppAlert($search, $property);

                PropertyAlert::create([
                    'customer_id' => $search->customer_id,
                    'saved_search_id' => $search->id,
                    'property_id' => $property->id,
                    'email_sent' => $emailSent,
                    'push_sent' => $pushSent,
                    'whatsapp_sent' => $whatsappSent,
                ]);
            }

            $search->update(['last_checked_at' => now()]);

        } catch (\Throwable $e) {
            Log::error('CheckSavedSearchAlerts error for search '.$search->id.': '.$e->getMessage());
        }
    }

    private function findMatchingProperties(array $filters, $since)
    {
        $query = Property::query()
            ->where('status', 1)
            ->where('request_status', 'approved')
            ->where('created_at', '>=', $since);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (! empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }
        if (! empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        if (! empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }
        if (isset($filters['propery_type'])) {
            $query->where('propery_type', $filters['propery_type']);
        }
        if (! empty($filters['min_bedrooms'])) {
            $query->where('bedrooms', '>=', $filters['min_bedrooms']);
        }
        if (! empty($filters['max_bedrooms'])) {
            $query->where('bedrooms', '<=', $filters['max_bedrooms']);
        }
        if (! empty($filters['min_bathrooms'])) {
            $query->where('bathrooms', '>=', $filters['min_bathrooms']);
        }
        if (! empty($filters['min_build_area'])) {
            $query->where('build_area', '>=', $filters['min_build_area']);
        }
        if (! empty($filters['max_build_area'])) {
            $query->where('build_area', '<=', $filters['max_build_area']);
        }

        return $query->get();
    }

    private function sendEmailAlert(SavedSearch $search, Property $property): bool
    {
        try {
            $customer = $search->customer;
            if (! $customer || empty($customer->email)) {
                return false;
            }

            HelperService::sendMail([
                'email_template' => $this->buildEmailHtml($search, $property),
                'email' => $customer->email,
                'title' => 'Nueva propiedad que coincide con tu búsqueda: '.$search->name,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('SavedSearch email alert failed: '.$e->getMessage());
            return false;
        }
    }

    private function sendPushAlert(SavedSearch $search, Property $property): bool
    {
        try {
            $tokens = Usertokens::where('customer_id', $search->customer_id)
                ->pluck('fcm_id')
                ->filter()
                ->toArray();

            if (empty($tokens)) {
                return false;
            }

            $msg = [
                'title' => 'Nueva propiedad: '.$property->title,
                'message' => 'Se publicó una propiedad que coincide con tu búsqueda "'.$search->name.'"',
                'type' => 'saved_search_alert',
                'body' => $property->title.' - '.$property->city,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'sound' => 'default',
                'property_id' => (string) $property->id,
                'saved_search_id' => (string) $search->id,
            ];

            send_push_notification($tokens, $msg);

            return true;
        } catch (\Throwable $e) {
            Log::warning('SavedSearch push alert failed: '.$e->getMessage());
            return false;
        }
    }

    private function sendWhatsAppAlert(SavedSearch $search, Property $property): bool
    {
        try {
            $mobile = $search->customer?->full_mobile ?? null;
            if (! $mobile) {
                return false;
            }

            $tipo = $property->propery_type == 0 ? 'Alquiler' : 'Venta';
            $precio = number_format($property->price, 0, ',', '.');
            $text = 'Omko: nueva propiedad que coincide con tu búsqueda "'.$search->name.'". '
                .$property->title.' ('.$tipo.', '.$property->city.'). Precio: '.$property->currency.' '.$precio
                .'. Mira: '.url('/my-property/'.$property->slug_id);

            $result = app(WhatsAppService::class)->sendMessage($mobile, $text);

            return ! empty($result['success']);
        } catch (\Throwable $e) {
            Log::warning('SavedSearch WhatsApp alert failed: '.$e->getMessage());
            return false;
        }
    }

    private function buildEmailHtml(SavedSearch $search, Property $property): string
    {
        $link = url('/my-property/'.$property->slug_id);
        $tipo = $property->propery_type == 0 ? 'Alquiler' : 'Venta';
        $precio = number_format($property->price, 0, ',', '.');

        return "<h2>Nueva propiedad que coincide con tu búsqueda</h2>"
            ."<p>Hola {$search->customer->name},</p>"
            ."<p>Se publicó una nueva propiedad que coincide con tu búsqueda <strong>\"{$search->name}\"</strong>:</p>"
            ."<div style='border:1px solid #ddd;padding:15px;border-radius:8px;margin:15px 0'>"
            ."<h3>{$property->title}</h3>"
            ."<p><strong>Ciudad:</strong> {$property->city}</p>"
            ."<p><strong>Tipo:</strong> {$tipo}</p>"
            ."<p><strong>Precio:</strong> {$property->currency} {$precio}</p>"
            ."</div>"
            ."<p><a href='{$link}' style='background:#007bff;color:#fff;padding:10px 20px;text-decoration:none;border-radius:5px'>Ver propiedad</a></p>"
            ."<p>Omko - Plataforma Inmobiliaria RD</p>";
    }
}
