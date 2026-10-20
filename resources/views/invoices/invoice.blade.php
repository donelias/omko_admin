<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 0; size: letter; }
        * { box-sizing: border-box; }
        /*
         * El comprobante de referencia usa Helvetica / Helvetica-Light / HelveticaNeue.
         * dompdf no puede embeber Helvetica, así que se declara Arial; en el
         * servidor Arial resuelve a Liberation Sans (clone métrico) y en local
         * a la Arial real. Nunca dejar el default: cae a Times.
         */
        body {
            font-family: Arial, "Liberation Sans", "Nimbus Sans", Helvetica, sans-serif;
            font-size: 8pt;
            color: #231f20;
            margin: 0;
            line-height: 1.3;
        }
        /* La banda oscura del comprobante es #221f1f y el rojo del NCF #ed1c24. */
    </style>
</head>
<body>

    {{-- ===================== ENCABEZADO ===================== --}}
    {{-- Logo (esquina superior izquierda). El original lo sitúa en y≈41..89. --}}
    @if (!empty($settings['logo']))
        <img src="{{ $settings['logo'] }}" alt="" style="position:absolute; left:35pt; top:58pt; max-height:60pt; max-width:175pt;">
    @endif

    {{-- Datos de la empresa JUSTO DEBAJO del logo (dirección, contacto).
         El original: y=102 (dirección) y y=111 (teléfono/email). --}}
    <div style="position:absolute; left:35pt; top:101pt; font-size:7.2pt; font-weight:normal; line-height:9pt; width:260pt;">
        {{ $settings['company_address'] }}<br>
        T.: {{ $settings['company_tel1'] }} &nbsp;&nbsp; E.: {{ $settings['company_email'] }}
        @if (!empty($settings['company_website'])) &nbsp;&nbsp; w: {{ $settings['company_website'] }} @endif
    </div>
    <div style="position:absolute; left:35pt; top:130pt; font-size:10pt; font-weight:bold;">
        RNC: {{ $settings['company_rnc'] }}
    </div>
    <div style="position:absolute; left:35pt; top:145pt; font-size:9pt;">
        FECHA: {{ $invoice->issue_date->format('d / m / Y') }}
    </div>

    {{-- Columna derecha: documento fiscal --}}
    <div style="position:absolute; left:414pt; top:65pt; font-size:11.3pt; font-weight:normal;">
        FACTURA DE CREDITO FISCAL
    </div>
    <div style="position:absolute; left:414pt; top:79pt; font-size:10pt; font-weight:bold;">
        NCF: {{ $invoice->invoice_number }}
    </div>

    {{-- SECUENCIA: etiqueta pequeña (5pt) arriba, número grande en rojo aparte --}}
    <div style="position:absolute; left:421pt; top:106pt; font-size:5pt; font-weight:normal;">
        SECUENCIA:
    </div>
    <div style="position:absolute; left:539pt; top:40pt; font-size:11.5pt; font-weight:bold; color:#ed1c24;">
        {{ $invoice->formatted_number }}
    </div>

    {{-- Vencimiento dentro de un cuadro con 3 divisiones (día | mes | año).
         El original: rectángulo (454,102)-(578,117) con divisores x=480 y x=538. --}}
    @if ($invoice->due_date)
        <div style="position:absolute; left:417pt; top:99pt; font-size:5pt; font-weight:normal;">
            VENCIMIENTO
        </div>
        <div style="position:absolute; left:454pt; top:102pt; width:124pt; height:15pt; border:0.5pt solid #231f20;">
            <div style="position:absolute; left:0; top:0; width:26pt; height:15pt; border-right:0.5pt solid #231f20; text-align:center; font-size:8pt; font-style:italic; line-height:15pt;">
                {{ $invoice->due_date->format('d') }}
            </div>
            <div style="position:absolute; left:26pt; top:0; width:58pt; height:15pt; border-right:0.5pt solid #231f20; text-align:center; font-size:8pt; font-style:italic; line-height:15pt;">
                {{ $months_es[$invoice->due_date->format('m')] }}
            </div>
            <div style="position:absolute; left:84pt; top:0; width:40pt; height:15pt; text-align:center; font-size:8pt; font-style:italic; line-height:15pt;">
                {{ $invoice->due_date->format('Y') }}
            </div>
        </div>
    @endif

    {{-- Slogan --}}
    <div style="position:absolute; left:421pt; top:210pt; font-size:6pt; font-weight:normal;">
        ¡Hacemos tu mejor inversión!
    </div>

    {{-- QR al lado del slogan --}}
    @if (!empty($settings['qr_image']))
        <img src="{{ $settings['qr_image'] }}" alt=""
             style="position:absolute; left:508pt; top:180pt; width:68pt;">
    @endif

    @if ($invoice->status === 'anulada')
        <div style="position:absolute; left:0; top:360pt; width:612pt; text-align:center; font-size:16pt; font-weight:bold; color:#ed1c24;">
            FACTURA ANULADA
        </div>
    @endif

    {{-- ===================== CLIENTE ===================== --}}
    <div style="position:absolute; left:35pt; top:168pt; font-size:10pt; font-weight:normal; line-height:17pt;">
        RNC: {{ $invoice->client_rnc ?: '—' }}<br>
        Cliente:&nbsp;&nbsp;{{ $invoice->client_name }}<br>
        Teléfono: {{ $invoice->client_phone ?: '—' }}<br>
        Ciudad:&nbsp;&nbsp;&nbsp;{{ $invoice->client_city ?: '—' }}
    </div>

    {{-- ===================== TABLA =====================
         Réplica de los trazos del comprobante de referencia:
           - borde exterior: (34,250)-(579,670)
           - divisores verticales: x=112 (cantidad|concepto),
             x=406 (concepto|itbis), x=492 (itbis|valor)
           - fondo oscuro de cabecera: (34,250)-(579,266)
           - línea inferior del header en y=266 --}}
    <div style="position:absolute; left:34pt; top:250pt; width:545pt; height:420pt; border:0.5pt solid #231f20;"></div>
    <div style="position:absolute; left:112pt; top:250pt; height:402pt; border-left:0.5pt solid #231f20;"></div>
    <div style="position:absolute; left:406pt; top:250pt; height:402pt; border-left:0.5pt solid #231f20;"></div>
    <div style="position:absolute; left:492pt; top:250pt; height:420pt; border-left:0.5pt solid #231f20;"></div>
    <div style="position:absolute; left:34pt; top:266pt; width:545pt; border-top:0.5pt solid #231f20;"></div>

    {{-- Cabecera oscura --}}
    <div style="position:absolute; left:34pt; top:250pt; width:545pt; height:16pt; background:#221f1f;"></div>
    <div style="position:absolute; left:112pt; top:252pt; width:294pt; font-size:9pt; font-weight:bold; color:#ffffff; text-align:center;">CONCEPTO</div>
    <div style="position:absolute; left:34pt; top:252pt; width:78pt; font-size:9pt; font-weight:bold; color:#ffffff; text-align:center;">CANTIDAD</div>
    <div style="position:absolute; left:406pt; top:252pt; width:86pt; font-size:9pt; font-weight:bold; color:#ffffff; text-align:center;">ITBIS</div>
    <div style="position:absolute; left:492pt; top:252pt; width:86pt; font-size:9pt; font-weight:bold; color:#ffffff; text-align:center;">VALOR</div>

    {{-- Fila de datos --}}
    <div style="position:absolute; left:118pt; top:271pt; width:282pt; font-size:8pt; line-height:11pt;">{{ $invoice->concept }}</div>
    <div style="position:absolute; left:34pt; top:272pt; width:74pt; font-size:8pt; text-align:center;">{{ $invoice->quantity }}</div>
    <div style="position:absolute; left:410pt; top:272pt; width:78pt; font-size:8pt; text-align:right;">${{ App\Models\Invoice::moneyPad($invoice->itbis_amount) }}</div>
    <div style="position:absolute; left:496pt; top:272pt; width:79pt; font-size:8pt; text-align:right;">${{ App\Models\Invoice::moneyPad($invoice->subtotal) }}</div>

    {{-- ===================== TOTALES =====================
         El comprobante los dibuja dentro de la misma tabla: divisores
         verticales x=492, líneas horizontales en y=581, 599, 617, 634, 652,
         y la banda oscura del "TOTAL A PAGAR" en (492,652)-(579,671). --}}
    @php
        $totalConItbis = $invoice->subtotal + $invoice->itbis_amount;
        $retLabel = rtrim(rtrim(number_format((float) $invoice->retention_rate, 2, ',', '.'), '0'), ',');
        $labelRight = 489;
        $amountLeft = 523;
    @endphp

    <div style="position:absolute; left:406pt; top:581pt; width:173pt; border-top:0.5pt solid #231f20;"></div>
    <div style="position:absolute; left:406pt; top:599pt; width:173pt; border-top:0.5pt solid #231f20;"></div>
    <div style="position:absolute; left:406pt; top:617pt; width:173pt; border-top:0.5pt solid #231f20;"></div>
    @if ((float) $invoice->retention_rate > 0)
        <div style="position:absolute; left:406pt; top:634pt; width:173pt; border-top:0.5pt solid #231f20;"></div>
    @endif
    <div style="position:absolute; left:34pt; top:652pt; width:545pt; border-top:0.5pt solid #231f20;"></div>

    <div style="position:absolute; left:34pt; top:582pt; width:455pt; text-align:right; font-size:8pt;">SUB-TOTAL</div>
    <div style="position:absolute; left:{{ $amountLeft }}pt; top:582pt; font-size:8pt; white-space:pre;">${{ App\Models\Invoice::moneyPad($invoice->subtotal) }}</div>

    <div style="position:absolute; left:34pt; top:600pt; width:455pt; text-align:right; font-size:8pt;">ITBIS</div>
    <div style="position:absolute; left:{{ $amountLeft }}pt; top:600pt; font-size:8pt; white-space:pre;">${{ App\Models\Invoice::moneyPad($invoice->itbis_amount) }}</div>

    <div style="position:absolute; left:34pt; top:618pt; width:455pt; text-align:right; font-size:8pt;">TOTAL</div>
    <div style="position:absolute; left:{{ $amountLeft }}pt; top:618pt; font-size:8pt; white-space:pre;">${{ App\Models\Invoice::moneyPad($totalConItbis) }}</div>

    @if ((float) $invoice->retention_rate > 0)
        <div style="position:absolute; left:34pt; top:636pt; width:455pt; text-align:right; font-size:8pt;">Retención {{ $retLabel }}% ITBIS</div>
        <div style="position:absolute; left:{{ $amountLeft }}pt; top:636pt; font-size:8pt; white-space:pre;">${{ App\Models\Invoice::moneyPad($invoice->retention_amount) }}</div>
    @endif

    {{-- TOTAL A PAGAR: banda oscura en (492,652)-(579,671). La etiqueta va en
         negro sobre el fondo blanco (termina en x=489, justo antes de la banda,
         como el original) y el monto en blanco dentro de la banda. --}}
    <div style="position:absolute; left:492pt; top:652pt; width:87pt; height:19pt; background:#221f1f;"></div>
    <div style="position:absolute; left:34pt; top:654pt; width:455pt; text-align:right; font-size:8pt; font-weight:bold; color:#231f20;">TOTAL A PAGAR RD$</div>
    <div style="position:absolute; left:{{ $amountLeft }}pt; top:654pt; font-size:8pt; font-weight:bold; color:#ffffff; white-space:pre;">${{ App\Models\Invoice::moneyPad($invoice->total) }}</div>

    {{-- ===================== NOTA
         Los datos salen de los settings del sistema; si están vacíos se usan
         los valores por defecto de Omko. ===================== --}}
    @php
        $noteCompany = 'OMKO BIENES RAÍCES SRL';
        $noteRnc     = $settings['company_rnc'] ?: '133337975';
        $noteEmail   = $settings['invoice_payment_email'] ?: 'elisvernithherrera@gmail.com';
        $noteAccount = $settings['bank_account'] ?: '1034398768';
        $noteBank    = $settings['bank_name'] ?: 'Asociación Popular (APAP)';
    @endphp
    <div style="position:absolute; left:34pt; top:673pt; font-size:6pt; line-height:6.7pt; width:460pt;">
        <span style="font-weight:bold;">NOTA:</span>
        <span style="font-weight:normal;"> Realizar cheques o transferencias a nombre de </span><span style="font-weight:bold; font-style:italic;">{{ $noteCompany }}</span><br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;RNC: <span style="font-weight:bold;">{{ $noteRnc }}</span><br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Email: {{ $noteEmail }}<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Cuenta de Ahorro: <span style="font-weight:bold;">{{ $noteAccount }}</span><br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Banco: <span style="font-weight:bold;">{{ $noteBank }}</span>
    </div>

    {{-- ===================== FIRMAS =====================
         Líneas en y=734: (131,734)-(267,734) y (345,734)-(481,734).
         Firma manuscrita ENCIMA de la línea (grande); nombre, cargo y
         etiquetas DEBAJO de la línea. Sello a tamaño de logo físico,
         superpuesto a la derecha de la firma. --}}
    <div style="position:absolute; left:131pt; top:734pt; width:136pt; border-top:0.5pt solid #231f20;"></div>
    <div style="position:absolute; left:345pt; top:734pt; width:136pt; border-top:0.5pt solid #231f20;"></div>

    {{-- Firma manuscrita: la rúbrica queda justo encima de la línea derecha
         (y=734). La imagen es apaisada (~4.26:1) y la tinta ocupa casi todo
         el alto, así que se alinea por su base con la línea. --}}
    @if (!empty($settings['signature_image']))
        <img src="{{ $settings['signature_image'] }}" alt=""
             style="position:absolute; left:328pt; top:699pt; width:170pt;">
    @endif

    {{-- Sello: centrado entre las dos firmas, atravesando la línea. --}}
    @if (!empty($settings['seal_image']))
        <img src="{{ $settings['seal_image'] }}" alt=""
             style="position:absolute; left:241pt; top:660pt; width:130pt;">
    @endif

    {{-- Nombre DEBAJO de la línea --}}
    <div style="position:absolute; left:345pt; top:737pt; width:136pt; text-align:center; font-size:10pt; font-weight:normal;">
        {{ $signer_name }}
    </div>

    {{-- Etiquetas debajo de la línea --}}
    <div style="position:absolute; left:131pt; top:738pt; width:136pt; text-align:center; font-size:6pt; font-weight:bold; font-style:italic;">
        Recibido
    </div>
    <div style="position:absolute; left:345pt; top:752pt; width:136pt; text-align:center; font-size:6pt; font-weight:bold; font-style:italic;">
        Autorizado y despachado
    </div>

    <div style="position:absolute; left:345pt; top:764pt; width:136pt; text-align:center; font-size:8pt; font-weight:normal;">
        {{ $signer_role }}
    </div>

</body>
</html>
