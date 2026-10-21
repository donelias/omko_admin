<?php

namespace App\Services;

/**
 * Datos de marca para los correos: logo embebido y datos del pie
 * (contacto y redes sociales) leídos de la configuración.
 *
 * Las imágenes se embeben en base64 como data-URI porque los clientes de
 * correo no cargan SVG ni rutas locales, y así el correo es autosuficiente.
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
                'icon' => self::embed('assets/images/invoice/mail/'.$net.'.png'),
            ];
        }

        return self::$cache = [
            'mail_logo' => self::embed('assets/images/invoice/logo_omko_mail_green.png'),
            'mail_footer_logo' => self::embed('assets/images/logo/Logo_white.png'),
            'mail_company_name' => HelperService::getSettingData('company_name') ?: config('app.name'),
            'mail_company_email' => HelperService::getSettingData('company_email') ?: config('mail.from.address'),
            'mail_company_address' => HelperService::getSettingData('company_address'),
            'mail_company_tel1' => HelperService::getSettingData('company_tel1'),
            'mail_company_tel2' => HelperService::getSettingData('company_tel2'),
            'mail_social_icons' => $socialIcons,
        ];
    }

    private static function embed(string $relativePath): string
    {
        $path = public_path($relativePath);

        if (! is_file($path)) {
            return '';
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }
}
