<?php

namespace App\Services;

/**
 * Datos de marca para los correos: logo y datos del pie (contacto y redes
 * sociales) leídos de la configuración.
 *
 * Las imágenes se referencian por URL absoluta pública. NO se usan data-URI
 * base64 porque la mayoría de clientes (Gmail, Outlook) los bloquean.
 */
class MailBrandService
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    public static function variables(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $socials = [
            'facebook' => HelperService::getSettingData('facebook_id'),
            'instagram' => HelperService::getSettingData('instagram_id'),
            'twitter' => HelperService::getSettingData('twitter_id'),
            'youtube' => HelperService::getSettingData('youtube_id'),
            'linkedin' => HelperService::getSettingData('linkedin_id'),
        ];

        $socialIcons = [];
        foreach ($socials as $net => $url) {
            if (empty($url)) {
                continue;
            }
            $socialIcons[] = [
                'name' => $net,
                'url' => $url,
                'icon' => self::url('assets/images/invoice/mail/'.$net.'.png'),
            ];
        }

        return self::$cache = [
            'mail_logo' => self::url('assets/images/invoice/logo_omko_mail_green.png'),
            'mail_footer_logo' => self::url('assets/images/logo/Logo_white.png'),
            'mail_company_name' => HelperService::getSettingData('company_name') ?: config('app.name'),
            'mail_company_email' => HelperService::getSettingData('company_email') ?: config('mail.from.address'),
            'mail_company_address' => HelperService::getSettingData('company_address'),
            'mail_company_tel1' => HelperService::getSettingData('company_tel1'),
            'mail_company_tel2' => HelperService::getSettingData('company_tel2'),
            'mail_social_icons' => $socialIcons,
        ];
    }

    private static function url(string $relativePath): string
    {
        if (! is_file(public_path($relativePath))) {
            return '';
        }

        return rtrim(config('app.url'), '/').'/'.$relativePath;
    }
}
