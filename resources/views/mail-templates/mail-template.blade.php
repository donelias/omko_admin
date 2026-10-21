<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $title }}</title>
</head>
<body style="margin: 0; padding: 0; background: #f4f5f7;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f4f5f7; padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; font-family: Arial, Helvetica, sans-serif; color: #231f20;">
                    <tr>
                        <td>
                            @include('mail-templates.partials.header')
                        </td>
                    </tr>
                    <tr>
                        <td style="background: #ffffff; border: 1px solid #e2e8f0; border-top: none; padding: 24px; font-size: 14px; line-height: 1.6; color: #231f20;">
                            {!! $email_template !!}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            @include('mail-templates.partials.footer')
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
