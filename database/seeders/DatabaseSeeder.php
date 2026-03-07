<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Duplicate the file (only if destination doesn't exist)
        $sourceFile = resource_path('lang/en.json');
        $destinationFile = resource_path('lang/en-new.json');
        if (File::exists($sourceFile) && !File::exists($destinationFile)) {
            File::copy($sourceFile, $destinationFile);
        }

        $sourceFile = public_path('languages/en.json');
        $destinationFile = public_path('languages/en-new.json');
        if (File::exists($sourceFile) && !File::exists($destinationFile)) {
            File::copy($sourceFile, $destinationFile);
        };

        $sourceFile = public_path('web_languages/en.json');
        $destinationFile = public_path('web_languages/en-new.json');
        if (File::exists($sourceFile) && !File::exists($destinationFile)) {
            File::copy($sourceFile, $destinationFile);
        }

        // Use insertOrIgnore to avoid duplicate entry error
        DB::table('languages')->insertOrIgnore(
            [
                'name' => 'English',
                'code' => 'en-new',
                'file_name' => 'en-new.json',
                'status' => '1',
            ],
        );


        // Use insertOrIgnore to avoid duplicate entry error for settings
        $settingsData = [
                [
                    'type' => 'company_name',
                    'data' => 'OMKO'
                ],
                [
                    'type' => 'currency_symbol',
                    'data' => '$'
                ],
                [
                    'type' => 'ios_version',
                    'data' => '1.0.0'
                ],
                [
                    'type' => 'default_language',
                    'data' => 'en-new'
                ],
                [
                    'type' => 'force_update',
                    'data' => '0'
                ],
                [
                    'type' => 'android_version',
                    'data' => '1.0.0'
                ],
                [
                    'type' => 'number_with_suffix',
                    'data' => '0'
                ],
                [
                    'type' => 'maintenance_mode',
                    'data' => 0,
                ],
                [
                    'type' => 'privacy_policy',
                    'data' => 'Privacy Policy here',
                ],
                [
                    'type' => 'terms_conditions',
                    'data' => 'Terms and Conditions here',
                ],
                [
                    'type' => 'company_tel1',
                    'data' => '+1 809 555 0100',
                ],
                [
                    'type' => 'company_tel2',
                    'data' => '+1 809 555 0101',
                ],
                [
                    'type' => 'razorpay_gateway',
                    'data' => '0',
                ],
                [
                    'type' => 'paystack_gateway',
                    'data' => '0',
                ],
                [
                    'type' => 'paypal_gateway',
                    'data' => '0',
                ],
                [
                    'type' => 'system_version',
                    'data' => '1.3.0',
                ],
                [
                    'type' => 'company_logo',
                    'data' => 'logo.png',
                ],
                [
                    'type' => 'web_logo',
                    'data' => 'web_logo.png',
                ],
                [
                    'type' => 'favicon_icon',
                    'data' => 'favicon.png',
                ],
                [
                    'type' => 'web_favicon',
                    'data' => 'favicon.png',
                ],
                [
                    'type' => 'web_footer_logo',
                    'data' => 'Logo_white.svg',
                ],
                [
                    'type' => 'web_placeholder_logo',
                    'data' => 'placeholder.svg',
                ],
                [
                    'type' => 'app_home_screen',
                    'data' => 'homeLogo.png',
                ],
                [
                    'type' => 'placeholder_logo',
                    'data' => 'placeholder.png',
                ],
                [
                    'type' => 'system_color',
                    'data' => '#087c7c',
                ],
                [
                    'type' => 'facebook_id',
                    'data' => 'https://www.facebook.com/omko.do',
                ],
                [
                    'type' => 'instagram_id',
                    'data' => 'https://www.instagram.com/omko.do',
                ],
                [
                    'type' => 'twitter_id',
                    'data' => 'https://twitter.com/omko_do',
                ],
                [
                    'type' => 'youtube_id',
                    'data' => 'https://www.youtube.com/@OmkoRD',
                ],
                [
                    'type' => 'iframe_link',
                    'data' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3784.5754!2d-69.9312!3d18.4861!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2sSanto%20Domingo!5e0!3m2!1ses!2sdo',
                ],
                [
                    'type' => 'latitude',
                    'data' => '18.4861',
                ],
                [
                    'type' => 'longitude',
                    'data' => '-69.9312',
                ],
                [
                    'type' => 'company_address',
                    'data' => 'Santo Domingo, República Dominicana',
                ],
                [
                    'type' => 'company_email',
                    'data' => 'info@omko.do',
                ],
                [
                    'type' => 'playstore_id',
                    'data' => 'https://play.google.com/store/apps/details?id=com.omko.android',
                ],
                [
                    'type' => 'appstore_id',
                    'data' => 'https://apps.apple.com/app/omko',
                ],
            ];
        
        // Insert each setting only if it doesn't exist
        foreach ($settingsData as $setting) {
            DB::table('settings')->insertOrIgnore($setting);
        }
    }
}

