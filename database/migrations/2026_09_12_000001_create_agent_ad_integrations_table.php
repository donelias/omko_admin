<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Integraciones de marketing por agente (Meta Pixel/Conversions API + WhatsApp Cloud API).
     * Las credenciales se capturan desde el frontend (dashboard del agente) y los tokens
     * se guardan cifrados con Crypt::. Nunca se hardcodean en el código.
     */
    public function up(): void
    {
        Schema::create('agent_ad_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id')->unique();
            $table->foreign('agent_id')->references('id')->on('customers')->onDelete('cascade');

            $table->string('pixel_id', 191)->nullable();
            $table->string('ad_account_id', 191)->nullable();
            $table->text('capi_access_token')->nullable();
            $table->string('capi_test_event_code', 191)->nullable();

            $table->string('whatsapp_mode', 10)->default('mock');
            $table->text('whatsapp_token')->nullable();
            $table->string('whatsapp_phone_id', 191)->nullable();
            $table->string('whatsapp_sender_number', 191)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->string('utm_source', 191)->nullable()->after('whatsapp_number_id');
            $table->string('utm_campaign', 191)->nullable()->after('utm_source');
            $table->string('utm_medium', 191)->nullable()->after('utm_campaign');
            $table->string('utm_content', 191)->nullable()->after('utm_medium');
            $table->string('utm_term', 191)->nullable()->after('utm_content');
            $table->string('fbclid', 191)->nullable()->after('utm_term');
            $table->text('page_url')->nullable()->after('fbclid');
            $table->string('lead_uid', 64)->nullable()->after('page_url');
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropColumn([
                'utm_source',
                'utm_campaign',
                'utm_medium',
                'utm_content',
                'utm_term',
                'fbclid',
                'page_url',
                'lead_uid',
            ]);
        });

        Schema::dropIfExists('agent_ad_integrations');
    }
};