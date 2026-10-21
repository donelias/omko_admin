@php($brand = \App\Services\MailBrandService::variables())
<div style="border: 1px solid #e2e8f0; border-top: none; background: #1a1a1a; padding: 22px 24px; border-radius: 0 0 8px 8px; text-align: center;">
    @if ($brand['mail_social_icons'])
        <div style="margin-bottom: 18px; font-size: 0;">
            @foreach ($brand['mail_social_icons'] as $social)
                <a href="{{ $social['url'] }}" target="_blank" style="text-decoration: none; display: inline-block; margin: 0 6px; background: #ffffff; border-radius: 50%; width: 32px; height: 32px; vertical-align: middle;">
                    <img src="{{ $social['icon'] }}" alt="{{ ucfirst($social['name']) }}" width="18" height="18" style="display: block; width: 18px; height: 18px; margin: 7px auto 0;">
                </a>
            @endforeach
        </div>
    @endif

    @if ($brand['mail_footer_logo'])
        <img src="{{ $brand['mail_footer_logo'] }}" alt="{{ $brand['mail_company_name'] }}" style="height: 28px; display: inline-block;">
    @else
        <p style="margin: 0; color: #ffffff; font-size: 13px; font-weight: bold;">{{ $brand['mail_company_name'] }}</p>
    @endif

    @if ($brand['mail_company_address'])
        <p style="margin: 10px 0 0; color: #b5b5b5; font-size: 12px;">{{ $brand['mail_company_address'] }}</p>
    @endif

    @if ($brand['mail_company_tel1'] || $brand['mail_company_tel2'])
        <p style="margin: 6px 0 0; color: #b5b5b5; font-size: 12px;">
            @if ($brand['mail_company_tel1'])Tel: {{ $brand['mail_company_tel1'] }}@endif
            @if ($brand['mail_company_tel1'] && $brand['mail_company_tel2']) · @endif
            @if ($brand['mail_company_tel2']){{ $brand['mail_company_tel2'] }}@endif
        </p>
    @endif

    @if ($brand['mail_company_email'])
        <p style="margin: 6px 0 0; font-size: 12px;">
            <a href="mailto:{{ $brand['mail_company_email'] }}" style="color: #41BA77; text-decoration: none;">{{ $brand['mail_company_email'] }}</a>
        </p>
    @endif

    <p style="margin: 14px 0 0; color: #6b7280; font-size: 11px;">Este correo fue enviado automáticamente. Por favor no responda directamente.</p>
</div>
