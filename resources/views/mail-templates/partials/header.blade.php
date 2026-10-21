@php($brand = \App\Services\MailBrandService::variables())
<div style="background: #ffffff; padding: 20px 24px; border-radius: 8px 8px 0 0; border: 1px solid #e2e8f0; border-bottom: 4px solid #41BA77; text-align: center;">
    @if ($brand['mail_logo'])
        <img src="{{ $brand['mail_logo'] }}" alt="{{ $brand['mail_company_name'] }}" style="height: 48px; display: inline-block;">
    @else
        <span style="color: #1a1a1a; font-size: 20px; font-weight: bold;">{{ $brand['mail_company_name'] }}</span>
    @endif
</div>
