<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * FASE 8 (T6 restante): planes Developer / Agencia (suscripción de periodo
     * fijo vía packages con duración) + feature de acceso pagado a leads
     * (crm_leads_access) para la máscara/unlock de contactos.
     */
    public function up(): void
    {
        // 1. Agregar roles developer/agencia al enum de packages
        DB::statement("ALTER TABLE packages MODIFY COLUMN user_type ENUM('user','agent','developer','agencia') NOT NULL DEFAULT 'user'");

        // 2. Agregar type crm_leads_access al enum de features
        DB::statement("ALTER TABLE features MODIFY COLUMN type ENUM('property_list','project_list','property_feature','project_feature','mortgage_calculator_detail','premium_properties','project_access','premium_projects','agent_watermark','crm_leads_access') NULL");

        // 3. Feature crm_leads_access (compartida a roles; se incluye vía package_features)
        $feature = DB::table('features')->where('type', 'crm_leads_access')->first();
        if (! $feature) {
            DB::table('features')->insert([
                'name' => 'Lead Contact Access',
                'type' => 'crm_leads_access',
                'status' => 1,
                'user_type' => 'all',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Planes pagados (suscripción de periodo fijo, renovables)
        $developerFeatureId = DB::table('features')->where('type', 'project_list')->value('id');
        $developerProjectFeatureId = DB::table('features')->where('type', 'project_feature')->value('id');
        $developerPremiumProjectsId = DB::table('features')->where('type', 'premium_projects')->value('id');

        $agencyPropertyListId = DB::table('features')->where('type', 'property_list')->value('id');
        $agencyProjectListId = DB::table('features')->where('type', 'project_list')->value('id');
        $agencyLeadAccessId = DB::table('features')->where('type', 'crm_leads_access')->value('id');
        $agencyPremiumPropertiesId = DB::table('features')->where('type', 'premium_properties')->value('id');

        $now = now();

        // --- Plan Developer ---
        $developerPackage = DB::table('packages')->where('user_type', 'developer')->first();
        if (! $developerPackage) {
            $developerPackageId = DB::table('packages')->insertGetId([
                'name' => 'Plan Developer',
                'package_type' => 'paid',
                'purchase_type' => 'unlimited',
                'price' => 49.99,
                'duration' => 730, // ~1 mes
                'status' => 1,
                'user_type' => 'developer',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('package_features')->insert([
                ['package_id' => $developerPackageId, 'feature_id' => $developerFeatureId, 'limit_type' => 'unlimited', 'limit' => null, 'created_at' => $now, 'updated_at' => $now],
                ['package_id' => $developerPackageId, 'feature_id' => $developerProjectFeatureId, 'limit_type' => 'limited', 'limit' => 10, 'created_at' => $now, 'updated_at' => $now],
                ['package_id' => $developerPackageId, 'feature_id' => $developerPremiumProjectsId, 'limit_type' => 'limited', 'limit' => 5, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        // --- Plan Agencia ---
        $agencyPackage = DB::table('packages')->where('user_type', 'agencia')->first();
        if (! $agencyPackage) {
            $agencyPackageId = DB::table('packages')->insertGetId([
                'name' => 'Plan Agencia',
                'package_type' => 'paid',
                'purchase_type' => 'unlimited',
                'price' => 99.99,
                'duration' => 730, // ~1 mes
                'status' => 1,
                'user_type' => 'agencia',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('package_features')->insert([
                ['package_id' => $agencyPackageId, 'feature_id' => $agencyPropertyListId, 'limit_type' => 'limited', 'limit' => 100, 'created_at' => $now, 'updated_at' => $now],
                ['package_id' => $agencyPackageId, 'feature_id' => $agencyProjectListId, 'limit_type' => 'limited', 'limit' => 20, 'created_at' => $now, 'updated_at' => $now],
                ['package_id' => $agencyPackageId, 'feature_id' => $agencyLeadAccessId, 'limit_type' => 'limited', 'limit' => 50, 'created_at' => $now, 'updated_at' => $now],
                ['package_id' => $agencyPackageId, 'feature_id' => $agencyPremiumPropertiesId, 'limit_type' => 'limited', 'limit' => 10, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $planIds = DB::table('packages')->whereIn('user_type', ['developer', 'agencia'])->pluck('id');
        DB::table('package_features')->whereIn('package_id', $planIds)->delete();
        DB::table('packages')->whereIn('user_type', ['developer', 'agencia'])->delete();
        DB::table('features')->where('type', 'crm_leads_access')->delete();

        DB::statement("ALTER TABLE features MODIFY COLUMN type ENUM('property_list','project_list','property_feature','project_feature','mortgage_calculator_detail','premium_properties','project_access','premium_projects','agent_watermark') NULL");
        DB::statement("ALTER TABLE packages MODIFY COLUMN user_type ENUM('user','agent') NOT NULL DEFAULT 'user'");
    }
};