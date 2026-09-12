<?php

return [
    'CATEGORY_IMG_PATH' => 'category/',
    'SEO_IMG_PATH' => 'seo_setting/',
    'PROPERTY_SEO_IMG_PATH' => 'property_seo_img/',
    'PROJECT_SEO_IMG_PATH' => 'project_seo_img/',
    'SLIDER_IMG_PATH' => 'slider/',
    'NOTIFICATION_IMG_PATH' => 'notification/',
    'USER_IMG_PATH' => 'users/',
    'CHAT_FILE' => 'chat/',
    'CHAT_AUDIO' => 'chat_audio/',
    'PROPERTY_TITLE_IMG_PATH' => 'property_title_img/',
    'PROJECT_TITLE_IMG_PATH' => 'project_title_img/',
    'PROPERTY_GALLERY_IMG_PATH' => 'property_gallery_img/',
    'PROPERTY_DOCUMENT_PATH' => 'property_document_img/',
    'PROJECT_DOCUMENT_PATH' => 'project_document_img/',
    'ARTICLE_IMG_PATH' => 'article_img/',
    'ADVERTISEMENT_IMAGE_PATH' => 'advertisement_img/',
    'IMG_PATH' => 'images',
    'PARAMETER_IMG_PATH' => 'parameter_img/',
    '3D_IMG_PATH' => '3d_img/',
    'PARAMETER_IMAGE_PATH' => 'parameter_img/',
    'PROPERTY_VIDEO_PATH' => 'property_video/',
    'PROJECT_VIDEO_PATH' => 'project_video/',
    'FACILITY_IMAGE_PATH' => 'facility_img/',
    'CITY_IMAGE_PATH' => 'city_image/',
    'AGENT_VERIFICATION_DOC_PATH' => 'agent-verification/',
    'ADMIN_PROFILE_IMG_PATH' => 'admin_profile/',
    'BANK_RECEIPT_FILE_PATH' => 'bank_receipt_file/',
    'ADBANNER_IMAGE_PATH' => 'adbanner_img/',
    'CUSTOM_PAGE_ICON_PATH' => 'custom_page_icons/',
    'AGENT_PROFILE_IMG_PATH' => 'agent_profile/',
    'AGENT_PROFILE_BANNER_PATH' => 'agent_banner/',
    'AGENT_WATERMARK_IMG_PATH' => 'agent_watermark/',
    'CUSTOMER_PROFILE_IMG_PATH' => 'customer_profile/',
    'PRE_QUALIFICATION_PATH' => 'pre_qualification/',

    // ============================================
    // PRICE INTELLIGENCE / CONVERSIÓN DE PRECIOS
    // ============================================
    'PRICE_BASE_CURRENCY' => 'DOP',
    'PRICE_EXCHANGE_RATES' => [
        'DOP' => 1.0,
        'USD' => 58.5,
        'EUR' => 63.0,
    ],
    // Fuente de tasas "del día vigente".
    // - 'google': página pública de Google Finance (USD/DOP). Sin API oficial; scraping
    //   no documentado de https://www.google.com/finance/quote/USD-DOP (puede cambiar).
    // - 'bcrd': proxy público de las tasas del Banco Central de RD (compra/venta USD)
    //   vía PRICE_EXCHANGE_RATES_API (el de BSantander quedó inalcanzable).
    // - 'allratestoday': servicio AllRatesToday (requiere PRICE_EXCHANGE_RATES_API_KEY).
    // - 'setting': solo usa lo guardado en settings (type=price_exchange_rates), sin llamadas externas.
    // - 'static': siempre usa el fallback PRICE_EXCHANGE_RATES.
    'PRICE_EXCHANGE_RATES_SOURCE' => env('PRICE_EXCHANGE_RATES_SOURCE', 'google'),
    'PRICE_EXCHANGE_RATES_API' => env('PRICE_EXCHANGE_RATES_API', 'https://apisantanderexpress.bhd.com.do/TasasCambioBCRD/api/TasasDeCambio/BuscarTasasCambio'),
    'PRICE_EXCHANGE_RATES_API_KEY' => env('PRICE_EXCHANGE_RATES_API_KEY', ''),
    // Ventana de días a consultar cuando el proveedor devuelve histórico (días no laborables/fines de semana).
    'PRICE_EXCHANGE_RATES_RANGE_DAYS' => 10,
    // TTL del caché en segundos (6 horas por defecto).
    'PRICE_EXCHANGE_RATES_CACHE_TTL' => 21600,
    'PRICE_SUGGESTION_VALID_DAYS' => 30,
];
