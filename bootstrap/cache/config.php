<?php return array (
  'app' => 
  array (
    'name' => 'OMKO',
    'env' => 'production',
    'debug' => false,
    'url' => 'https://adminrealestate.omko.do',
    'asset_url' => NULL,
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'key' => 'base64:7mNxZtWps3y12BM0gR8RrUT73fOpyQUho6XFqgq7Etc=',
    'cipher' => 'AES-256-CBC',
    'maintenance' => 
    array (
      'driver' => 'file',
    ),
    'providers' => 
    array (
      0 => 'Illuminate\\Auth\\AuthServiceProvider',
      1 => 'Illuminate\\Broadcasting\\BroadcastServiceProvider',
      2 => 'Illuminate\\Bus\\BusServiceProvider',
      3 => 'Illuminate\\Cache\\CacheServiceProvider',
      4 => 'Illuminate\\Foundation\\Providers\\ConsoleSupportServiceProvider',
      5 => 'Illuminate\\Cookie\\CookieServiceProvider',
      6 => 'Illuminate\\Database\\DatabaseServiceProvider',
      7 => 'Illuminate\\Encryption\\EncryptionServiceProvider',
      8 => 'Illuminate\\Filesystem\\FilesystemServiceProvider',
      9 => 'Illuminate\\Foundation\\Providers\\FoundationServiceProvider',
      10 => 'Illuminate\\Hashing\\HashServiceProvider',
      11 => 'Illuminate\\Mail\\MailServiceProvider',
      12 => 'Illuminate\\Notifications\\NotificationServiceProvider',
      13 => 'Illuminate\\Pagination\\PaginationServiceProvider',
      14 => 'Illuminate\\Pipeline\\PipelineServiceProvider',
      15 => 'Illuminate\\Queue\\QueueServiceProvider',
      16 => 'Illuminate\\Redis\\RedisServiceProvider',
      17 => 'Illuminate\\Auth\\Passwords\\PasswordResetServiceProvider',
      18 => 'Illuminate\\Session\\SessionServiceProvider',
      19 => 'Illuminate\\Translation\\TranslationServiceProvider',
      20 => 'Illuminate\\Validation\\ValidationServiceProvider',
      21 => 'Illuminate\\View\\ViewServiceProvider',
      22 => 'Unicodeveloper\\Paystack\\PaystackServiceProvider',
      23 => 'Barryvdh\\DomPDF\\ServiceProvider',
      24 => 'App\\Providers\\AppServiceProvider',
      25 => 'App\\Providers\\AuthServiceProvider',
      26 => 'App\\Providers\\EventServiceProvider',
      27 => 'App\\Providers\\RouteServiceProvider',
      28 => 'Collective\\Html\\HtmlServiceProvider',
      29 => 'App\\Providers\\ViewServiceProvider',
      30 => 'Intervention\\Image\\ImageServiceProvider',
    ),
    'aliases' => 
    array (
      'App' => 'Illuminate\\Support\\Facades\\App',
      'Arr' => 'Illuminate\\Support\\Arr',
      'Artisan' => 'Illuminate\\Support\\Facades\\Artisan',
      'Auth' => 'Illuminate\\Support\\Facades\\Auth',
      'Blade' => 'Illuminate\\Support\\Facades\\Blade',
      'Broadcast' => 'Illuminate\\Support\\Facades\\Broadcast',
      'Bus' => 'Illuminate\\Support\\Facades\\Bus',
      'Cache' => 'Illuminate\\Support\\Facades\\Cache',
      'Config' => 'Illuminate\\Support\\Facades\\Config',
      'Cookie' => 'Illuminate\\Support\\Facades\\Cookie',
      'Crypt' => 'Illuminate\\Support\\Facades\\Crypt',
      'Date' => 'Illuminate\\Support\\Facades\\Date',
      'DB' => 'Illuminate\\Support\\Facades\\DB',
      'Eloquent' => 'Illuminate\\Database\\Eloquent\\Model',
      'Event' => 'Illuminate\\Support\\Facades\\Event',
      'File' => 'Illuminate\\Support\\Facades\\File',
      'Gate' => 'Illuminate\\Support\\Facades\\Gate',
      'Hash' => 'Illuminate\\Support\\Facades\\Hash',
      'Http' => 'Illuminate\\Support\\Facades\\Http',
      'Js' => 'Illuminate\\Support\\Js',
      'Lang' => 'Illuminate\\Support\\Facades\\Lang',
      'Log' => 'Illuminate\\Support\\Facades\\Log',
      'Mail' => 'Illuminate\\Support\\Facades\\Mail',
      'Notification' => 'Illuminate\\Support\\Facades\\Notification',
      'Number' => 'Illuminate\\Support\\Number',
      'Password' => 'Illuminate\\Support\\Facades\\Password',
      'Process' => 'Illuminate\\Support\\Facades\\Process',
      'Queue' => 'Illuminate\\Support\\Facades\\Queue',
      'RateLimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
      'Redirect' => 'Illuminate\\Support\\Facades\\Redirect',
      'Request' => 'Illuminate\\Support\\Facades\\Request',
      'Response' => 'Illuminate\\Support\\Facades\\Response',
      'Route' => 'Illuminate\\Support\\Facades\\Route',
      'Schema' => 'Illuminate\\Support\\Facades\\Schema',
      'Session' => 'Illuminate\\Support\\Facades\\Session',
      'Storage' => 'Illuminate\\Support\\Facades\\Storage',
      'Str' => 'Illuminate\\Support\\Str',
      'URL' => 'Illuminate\\Support\\Facades\\URL',
      'Validator' => 'Illuminate\\Support\\Facades\\Validator',
      'View' => 'Illuminate\\Support\\Facades\\View',
      'Vite' => 'Illuminate\\Support\\Facades\\Vite',
      'Paystack' => 'Unicodeveloper\\Paystack\\Facades\\Paystack',
      'PDF' => 'Barryvdh\\DomPDF\\Facade\\Pdf',
      'Image' => 'Intervention\\Image\\Facades\\Image',
    ),
  ),
  'auth' => 
  array (
    'defaults' => 
    array (
      'guard' => 'web',
      'passwords' => 'users',
    ),
    'guards' => 
    array (
      'web' => 
      array (
        'driver' => 'session',
        'provider' => 'users',
      ),
      'sanctum' => 
      array (
        'driver' => 'sanctum',
        'provider' => NULL,
      ),
    ),
    'providers' => 
    array (
      'users' => 
      array (
        'driver' => 'eloquent',
        'model' => 'App\\Models\\User',
      ),
    ),
    'passwords' => 
    array (
      'users' => 
      array (
        'provider' => 'users',
        'table' => 'password_resets',
        'expire' => 60,
        'throttle' => 60,
      ),
    ),
    'password_timeout' => 10800,
  ),
  'broadcasting' => 
  array (
    'default' => 'log',
    'connections' => 
    array (
      'pusher' => 
      array (
        'driver' => 'pusher',
        'key' => NULL,
        'secret' => NULL,
        'app_id' => NULL,
        'options' => 
        array (
          'host' => 'api-mt1.pusher.com',
          'port' => 443,
          'scheme' => 'https',
          'encrypted' => true,
          'useTLS' => true,
        ),
        'client_options' => 
        array (
        ),
      ),
      'ably' => 
      array (
        'driver' => 'ably',
        'key' => NULL,
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'default',
      ),
      'log' => 
      array (
        'driver' => 'log',
      ),
      'null' => 
      array (
        'driver' => 'null',
      ),
    ),
  ),
  'cache' => 
  array (
    'default' => 'database',
    'stores' => 
    array (
      'apc' => 
      array (
        'driver' => 'apc',
      ),
      'array' => 
      array (
        'driver' => 'array',
        'serialize' => false,
      ),
      'database' => 
      array (
        'driver' => 'database',
        'table' => 'cache',
        'connection' => NULL,
        'lock_connection' => NULL,
      ),
      'file' => 
      array (
        'driver' => 'file',
        'path' => '/var/www/omko-admin/storage/framework/cache/data',
      ),
      'memcached' => 
      array (
        'driver' => 'memcached',
        'persistent_id' => NULL,
        'sasl' => 
        array (
          0 => NULL,
          1 => NULL,
        ),
        'options' => 
        array (
        ),
        'servers' => 
        array (
          0 => 
          array (
            'host' => '127.0.0.1',
            'port' => 11211,
            'weight' => 100,
          ),
        ),
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
      ),
      'dynamodb' => 
      array (
        'driver' => 'dynamodb',
        'key' => NULL,
        'secret' => NULL,
        'region' => 'us-east-1',
        'table' => 'cache',
        'endpoint' => NULL,
      ),
      'octane' => 
      array (
        'driver' => 'octane',
      ),
      'none' => 
      array (
        'driver' => 'null',
      ),
      'gplaces' => 
      array (
        'driver' => 'file',
        'path' => '/var/www/omko-admin/storage/framework/cache/gplaces',
      ),
      'osmmaps' => 
      array (
        'driver' => 'file',
        'path' => '/var/www/omko-admin/storage/framework/cache/osmmaps',
      ),
      'gemini' => 
      array (
        'driver' => 'file',
        'path' => '/var/www/omko-admin/storage/framework/cache/gemini',
      ),
    ),
    'prefix' => 'omko_cache_',
  ),
  'constants' => 
  array (
    'CACHE' => 
    array (
      'SYSTEM' => 
      array (
        'DEFAULT_LANGUAGE' => 'default_language',
        'SETTINGS' => 'systemSettings',
      ),
    ),
    'RESPONSE_CODE' => 
    array (
      'EXCEPTION_ERROR' => 500,
      'SUCCESS' => 200,
      'VALIDATION_ERROR' => 400,
      'UNAUTHORIZED' => 401,
    ),
    'FEATURES' => 
    array (
      'PROPERTY_LIST' => 
      array (
        'NAME' => 'Property List',
        'TYPE' => 'property_list',
      ),
      'PROPERTY_FEATURE' => 
      array (
        'NAME' => 'Property Feature List',
        'TYPE' => 'property_feature',
      ),
      'PROJECT_LIST' => 
      array (
        'NAME' => 'Project List',
        'TYPE' => 'project_list',
      ),
      'PROJECT_FEATURE' => 
      array (
        'NAME' => 'Project Feature List',
        'TYPE' => 'project_feature',
      ),
      'MORTGAGE_CALCULATOR_DETAIL' => 
      array (
        'NAME' => 'Mortgage Calculator Detail Access',
        'TYPE' => 'mortgage_calculator_detail',
      ),
      'PREMIUM_PROPERTIES' => 
      array (
        'NAME' => 'Premium Properties Access',
        'TYPE' => 'premium_properties',
      ),
      'PREMIUM_PROJECTS' => 
      array (
        'NAME' => 'Premium Projects Access',
        'TYPE' => 'premium_projects',
      ),
      'AGENT_WATERMARK' => 
      array (
        'NAME' => 'Agent Watermark',
        'TYPE' => 'agent_watermark',
      ),
      'CRM_LEADS_ACCESS' => 
      array (
        'NAME' => 'Lead Contact Access',
        'TYPE' => 'crm_leads_access',
      ),
    ),
    'HOMEPAGE_SECTION_TYPES' => 
    array (
      'AGENTS_LIST_SECTION' => 
      array (
        'TYPE' => 'agents_list_section',
        'TITLE' => 'Agent Sections List',
      ),
      'ARTICLES_SECTION' => 
      array (
        'TYPE' => 'articles_section',
        'TITLE' => 'Article Sections List',
      ),
      'CATEGORIES_SECTION' => 
      array (
        'TYPE' => 'categories_section',
        'TITLE' => 'Category Sections List',
      ),
      'FAQS_SECTION' => 
      array (
        'TYPE' => 'faqs_section',
        'TITLE' => 'FAQ Sections List',
      ),
      'FEATURED_PROPERTIES_SECTION' => 
      array (
        'TYPE' => 'featured_properties_section',
        'TITLE' => 'Featured Properties Sections List',
      ),
      'FEATURED_PROJECTS_SECTION' => 
      array (
        'TYPE' => 'featured_projects_section',
        'TITLE' => 'Featured Projects Sections List',
      ),
      'MOST_LIKED_PROPERTIES_SECTION' => 
      array (
        'TYPE' => 'most_liked_properties_section',
        'TITLE' => 'Most Liked Properties Sections List',
      ),
      'MOST_VIEWED_PROPERTIES_SECTION' => 
      array (
        'TYPE' => 'most_viewed_properties_section',
        'TITLE' => 'Most Viewed Properties Sections List',
      ),
      'NEARBY_PROPERTIES_SECTION' => 
      array (
        'TYPE' => 'nearby_properties_section',
        'TITLE' => 'Nearby Properties Sections List',
      ),
      'PROJECTS_SECTION' => 
      array (
        'TYPE' => 'projects_section',
        'TITLE' => 'Project Sections List',
      ),
      'PREMIUM_PROJECTS_SECTION' => 
      array (
        'TYPE' => 'premium_projects_section',
        'TITLE' => 'Premium Projects Sections List',
      ),
      'PREMIUM_PROPERTIES_SECTION' => 
      array (
        'TYPE' => 'premium_properties_section',
        'TITLE' => 'Premium Properties Sections List',
      ),
      'USER_RECOMMENDATIONS_SECTION' => 
      array (
        'TYPE' => 'user_recommendations_section',
        'TITLE' => 'User Recommendations Sections List',
      ),
      'PROPERTIES_BY_CITIES_SECTION' => 
      array (
        'TYPE' => 'properties_by_cities_section',
        'TITLE' => 'Properties by Cities Sections List',
      ),
      'PROPERTIES_ON_MAP_SECTION' => 
      array (
        'TYPE' => 'properties_on_map_section',
        'TITLE' => 'Properties on Map Sections List',
      ),
    ),
    'API_RESPONSE_KEY' => 
    array (
      'ACCOUNT_DEACTIVATED' => 'accountDeactivated',
      'EMAIL_NOT_VERIFIED' => 'emailNotVerified',
      'REQUIRED_AGENT_ROLE' => 'requiredAgentRole',
      'REQUIRED_USER_ROLE' => 'requiredUserRole',
    ),
  ),
  'cors' => 
  array (
    'paths' => 
    array (
      0 => 'api/*',
      1 => 'sanctum/csrf-cookie',
    ),
    'allowed_methods' => 
    array (
      0 => 'GET',
      1 => 'POST',
      2 => 'PUT',
      3 => 'PATCH',
      4 => 'DELETE',
      5 => 'OPTIONS',
    ),
    'allowed_origins' => 
    array (
      0 => '*',
    ),
    'allowed_origins_patterns' => 
    array (
    ),
    'allowed_headers' => 
    array (
      0 => '*',
    ),
    'exposed_headers' => 
    array (
    ),
    'max_age' => 0,
    'supports_credentials' => false,
  ),
  'database' => 
  array (
    'default' => 'mysql',
    'connections' => 
    array (
      'sqlite' => 
      array (
        'driver' => 'sqlite',
        'url' => NULL,
        'database' => 'omko_admin_prod',
        'prefix' => '',
        'foreign_key_constraints' => true,
      ),
      'mysql' => 
      array (
        'driver' => 'mysql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'omko_admin_prod',
        'username' => 'dbadmin',
        'password' => 'veA6bXvozjOaFT+1TxKCeHcJfyU2BLYY2soNUn8qCD4=',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => false,
        'engine' => NULL,
        'options' => 
        array (
          12 => true,
        ),
      ),
      'pgsql' => 
      array (
        'driver' => 'pgsql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'omko_admin_prod',
        'username' => 'dbadmin',
        'password' => 'veA6bXvozjOaFT+1TxKCeHcJfyU2BLYY2soNUn8qCD4=',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
      ),
      'sqlsrv' => 
      array (
        'driver' => 'sqlsrv',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'omko_admin_prod',
        'username' => 'dbadmin',
        'password' => 'veA6bXvozjOaFT+1TxKCeHcJfyU2BLYY2soNUn8qCD4=',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
      ),
    ),
    'migrations' => 'migrations',
    'redis' => 
    array (
      'client' => 'phpredis',
      'options' => 
      array (
        'cluster' => 'redis',
        'prefix' => 'omko_database_',
      ),
      'default' => 
      array (
        'url' => NULL,
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '0',
      ),
      'cache' => 
      array (
        'url' => NULL,
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '1',
      ),
    ),
  ),
  'debugbar' => 
  array (
    'enabled' => NULL,
    'hide_empty_tabs' => true,
    'except' => 
    array (
      0 => 'telescope*',
      1 => 'horizon*',
      2 => '_boost/browser-logs',
    ),
    'storage' => 
    array (
      'enabled' => true,
      'open' => NULL,
      'driver' => 'file',
      'path' => '/var/www/omko-admin/storage/debugbar',
      'connection' => NULL,
      'provider' => '',
      'hostname' => '127.0.0.1',
      'port' => 2304,
    ),
    'editor' => 'phpstorm',
    'remote_sites_path' => NULL,
    'local_sites_path' => NULL,
    'include_vendors' => true,
    'capture_ajax' => true,
    'add_ajax_timing' => false,
    'ajax_handler_auto_show' => true,
    'ajax_handler_enable_tab' => true,
    'defer_datasets' => false,
    'error_handler' => false,
    'error_level' => 30719,
    'clockwork' => false,
    'collectors' => 
    array (
      'phpinfo' => false,
      'messages' => true,
      'time' => true,
      'memory' => true,
      'exceptions' => true,
      'log' => true,
      'db' => true,
      'views' => true,
      'route' => false,
      'auth' => false,
      'gate' => true,
      'session' => false,
      'symfony_request' => true,
      'mail' => true,
      'laravel' => true,
      'events' => false,
      'default_request' => false,
      'logs' => false,
      'files' => false,
      'config' => false,
      'cache' => false,
      'models' => true,
      'livewire' => true,
      'jobs' => false,
      'pennant' => false,
    ),
    'options' => 
    array (
      'time' => 
      array (
        'memory_usage' => false,
      ),
      'messages' => 
      array (
        'trace' => true,
        'capture_dumps' => false,
      ),
      'memory' => 
      array (
        'reset_peak' => false,
        'with_baseline' => false,
        'precision' => 0,
      ),
      'auth' => 
      array (
        'show_name' => true,
        'show_guards' => true,
      ),
      'gate' => 
      array (
        'trace' => false,
      ),
      'db' => 
      array (
        'with_params' => true,
        'exclude_paths' => 
        array (
        ),
        'backtrace' => true,
        'backtrace_exclude_paths' => 
        array (
        ),
        'timeline' => false,
        'duration_background' => true,
        'explain' => 
        array (
          'enabled' => false,
        ),
        'hints' => false,
        'show_copy' => true,
        'only_slow_queries' => true,
        'slow_threshold' => false,
        'memory_usage' => false,
        'soft_limit' => 100,
        'hard_limit' => 500,
      ),
      'mail' => 
      array (
        'timeline' => true,
        'show_body' => true,
      ),
      'views' => 
      array (
        'timeline' => true,
        'data' => false,
        'group' => 50,
        'inertia_pages' => 'js/Pages',
        'exclude_paths' => 
        array (
          0 => 'vendor/filament',
        ),
      ),
      'route' => 
      array (
        'label' => true,
      ),
      'session' => 
      array (
        'hiddens' => 
        array (
        ),
      ),
      'symfony_request' => 
      array (
        'label' => true,
        'hiddens' => 
        array (
        ),
      ),
      'events' => 
      array (
        'data' => false,
        'excluded' => 
        array (
        ),
      ),
      'logs' => 
      array (
        'file' => NULL,
      ),
      'cache' => 
      array (
        'values' => true,
      ),
    ),
    'inject' => true,
    'route_prefix' => '_debugbar',
    'route_middleware' => 
    array (
    ),
    'route_domain' => NULL,
    'theme' => 'auto',
    'debug_backtrace_limit' => 50,
  ),
  'dompdf' => 
  array (
    'show_warnings' => false,
    'public_path' => NULL,
    'convert_entities' => true,
    'options' => 
    array (
      'font_dir' => '/var/www/omko-admin/storage/fonts',
      'font_cache' => '/var/www/omko-admin/storage/fonts',
      'temp_dir' => '/tmp',
      'chroot' => '/var/www/omko-admin',
      'allowed_protocols' => 
      array (
        'data://' => 
        array (
          'rules' => 
          array (
          ),
        ),
        'file://' => 
        array (
          'rules' => 
          array (
          ),
        ),
        'http://' => 
        array (
          'rules' => 
          array (
          ),
        ),
        'https://' => 
        array (
          'rules' => 
          array (
          ),
        ),
      ),
      'artifactPathValidation' => NULL,
      'log_output_file' => NULL,
      'enable_font_subsetting' => false,
      'pdf_backend' => 'CPDF',
      'default_media_type' => 'screen',
      'default_paper_size' => 'a4',
      'default_paper_orientation' => 'portrait',
      'default_font' => 'serif',
      'dpi' => 96,
      'enable_php' => false,
      'enable_javascript' => true,
      'enable_remote' => false,
      'allowed_remote_hosts' => NULL,
      'font_height_ratio' => 1.1,
      'enable_html5_parser' => true,
    ),
  ),
  'filesystems' => 
  array (
    'default' => 'local',
    'disks' => 
    array (
      'local' => 
      array (
        'driver' => 'local',
        'root' => '/var/www/omko-admin/storage/app',
        'throw' => false,
      ),
      'public' => 
      array (
        'driver' => 'local',
        'root' => '/var/www/omko-admin/storage/app/public',
        'url' => 'https://adminrealestate.omko.do/storage',
        'visibility' => 'public',
        'throw' => false,
      ),
      's3' => 
      array (
        'driver' => 's3',
        'key' => NULL,
        'secret' => NULL,
        'region' => NULL,
        'bucket' => NULL,
        'url' => NULL,
        'endpoint' => NULL,
        'use_path_style_endpoint' => false,
        'throw' => false,
      ),
    ),
    'links' => 
    array (
      '/var/www/omko-admin/public/storage' => '/var/www/omko-admin/storage/app/public',
    ),
  ),
  'flutterwave' => 
  array (
    'publicKey' => NULL,
    'secretKey' => NULL,
    'secretHash' => '',
  ),
  'global' => 
  array (
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
    'PRICE_BASE_CURRENCY' => 'DOP',
    'PRICE_EXCHANGE_RATES' => 
    array (
      'DOP' => 1.0,
      'USD' => 58.5,
      'EUR' => 63.0,
    ),
    'PRICE_EXCHANGE_RATES_SOURCE' => 'google',
    'PRICE_EXCHANGE_RATES_API' => 'https://apisantanderexpress.bhd.com.do/TasasCambioBCRD/api/TasasDeCambio/BuscarTasasCambio',
    'PRICE_EXCHANGE_RATES_API_KEY' => '',
    'PRICE_EXCHANGE_RATES_RANGE_DAYS' => 10,
    'PRICE_EXCHANGE_RATES_CACHE_TTL' => 21600,
    'PRICE_SUGGESTION_VALID_DAYS' => 30,
  ),
  'goutte' => 
  array (
    'client' => 
    array (
      'max_redirects' => 0,
    ),
  ),
  'hashing' => 
  array (
    'driver' => 'bcrypt',
    'bcrypt' => 
    array (
      'rounds' => 10,
    ),
    'argon' => 
    array (
      'memory' => 65536,
      'threads' => 1,
      'time' => 4,
    ),
  ),
  'ide-helper' => 
  array (
    'filename' => '_ide_helper.php',
    'models_filename' => '_ide_helper_models.php',
    'meta_filename' => '.phpstorm.meta.php',
    'include_fluent' => false,
    'include_factory_builders' => false,
    'write_model_magic_where' => true,
    'write_model_external_builder_methods' => true,
    'write_model_relation_count_properties' => true,
    'write_eloquent_model_mixins' => false,
    'include_helpers' => false,
    'helper_files' => 
    array (
      0 => '/var/www/omko-admin/vendor/laravel/framework/src/Illuminate/Support/helpers.php',
    ),
    'model_locations' => 
    array (
      0 => 'app',
    ),
    'ignored_models' => 
    array (
    ),
    'model_hooks' => 
    array (
    ),
    'extra' => 
    array (
      'Eloquent' => 
      array (
        0 => 'Illuminate\\Database\\Eloquent\\Builder',
        1 => 'Illuminate\\Database\\Query\\Builder',
      ),
      'Session' => 
      array (
        0 => 'Illuminate\\Session\\Store',
      ),
    ),
    'magic' => 
    array (
    ),
    'interfaces' => 
    array (
    ),
    'custom_db_types' => 
    array (
    ),
    'model_camel_case_properties' => false,
    'type_overrides' => 
    array (
      'integer' => 'int',
      'boolean' => 'bool',
    ),
    'include_class_docblocks' => false,
    'force_fqn' => false,
    'use_generics_annotations' => true,
    'additional_relation_types' => 
    array (
    ),
    'additional_relation_return_types' => 
    array (
    ),
    'post_migrate' => 
    array (
    ),
  ),
  'image' => 
  array (
    'driver' => 'gd',
  ),
  'image_mimes' => 
  array (
    'backend' => 
    array (
      'image' => 'jpg,png,jpeg,webp',
      'image_with_gif' => 'jpg,png,jpeg,gif,webp',
    ),
    'frontend' => 
    array (
      'image' => 'image/jpg,image/png,image/jpeg,image/webp',
      'image_with_gif' => 'image/jpg,image/png,image/jpeg,image/gif,image/webp',
    ),
  ),
  'installer' => 
  array (
    'icon' => '/assets/images/logo/mainlogo.svg',
    'background' => '/images/default/background.jpg',
    'support_url' => 'https://join.skype.com/invite/Wy96gZvdk2oW',
    'server' => 
    array (
      'php' => 
      array (
        'name' => 'PHP Version',
        'version' => '>= 8.1.0',
        'check' => 
        array (
          'type' => 'php',
          'value' => 80100,
        ),
      ),
      'pdo' => 
      array (
        'name' => 'PDO',
        'check' => 
        array (
          'type' => 'extension',
          'value' => 'pdo_mysql',
        ),
      ),
      'mbstring' => 
      array (
        'name' => 'Mbstring extension',
        'check' => 
        array (
          'type' => 'extension',
          'value' => 'mbstring',
        ),
      ),
      'fileinfo' => 
      array (
        'name' => 'Fileinfo extension',
        'check' => 
        array (
          'type' => 'extension',
          'value' => 'fileinfo',
        ),
      ),
      'openssl' => 
      array (
        'name' => 'OpenSSL extension',
        'check' => 
        array (
          'type' => 'extension',
          'value' => 'openssl',
        ),
      ),
      'tokenizer' => 
      array (
        'name' => 'Tokenizer extension',
        'check' => 
        array (
          'type' => 'extension',
          'value' => 'tokenizer',
        ),
      ),
      'json' => 
      array (
        'name' => 'Json extension',
        'check' => 
        array (
          'type' => 'extension',
          'value' => 'json',
        ),
      ),
      'curl' => 
      array (
        'name' => 'Curl extension',
        'check' => 
        array (
          'type' => 'extension',
          'value' => 'curl',
        ),
      ),
    ),
    'folders' => 
    array (
      'storage.framework' => 
      array (
        'name' => '/storage/framework',
        'check' => 
        array (
          'type' => 'directory',
          'value' => '../storage/framework',
        ),
      ),
      'storage.logs' => 
      array (
        'name' => '/storage/logs',
        'check' => 
        array (
          'type' => 'directory',
          'value' => '../storage/logs',
        ),
      ),
      'storage.cache' => 
      array (
        'name' => '/bootstrap/cache',
        'check' => 
        array (
          'type' => 'directory',
          'value' => '../bootstrap/cache',
        ),
      ),
    ),
    'database' => 
    array (
      'seeders' => false,
    ),
    'commands' => 
    array (
      0 => 'db:seed --class=DatabaseSeeder',
    ),
    'admin_area' => 
    array (
      'user' => 
      array (
        'email' => 'admin@gmail.com',
        'password' => 'admin123',
      ),
    ),
    'login' => '/',
  ),
  'jwt' => 
  array (
    'secret' => NULL,
    'keys' => 
    array (
      'public' => NULL,
      'private' => NULL,
      'passphrase' => NULL,
    ),
    'ttl' => 60,
    'refresh_ttl' => 20160,
    'algo' => 'HS256',
    'required_claims' => 
    array (
      0 => 'iss',
      1 => 'iat',
      2 => 'exp',
      3 => 'nbf',
      4 => 'sub',
      5 => 'jti',
    ),
    'persistent_claims' => 
    array (
    ),
    'lock_subject' => true,
    'leeway' => 0,
    'blacklist_enabled' => true,
    'blacklist_grace_period' => 0,
    'decrypt_cookies' => false,
    'providers' => 
    array (
      'jwt' => 'Tymon\\JWTAuth\\Providers\\JWT\\Lcobucci',
      'auth' => 'Tymon\\JWTAuth\\Providers\\Auth\\Illuminate',
      'storage' => 'Tymon\\JWTAuth\\Providers\\Storage\\Illuminate',
    ),
  ),
  'log-viewer' => 
  array (
    'enabled' => true,
    'api_only' => false,
    'require_auth_in_production' => true,
    'route_domain' => NULL,
    'route_path' => 'log-viewer',
    'assets_path' => 'vendor/log-viewer',
    'back_to_system_url' => 'https://adminrealestate.omko.do/home',
    'back_to_system_label' => NULL,
    'timezone' => NULL,
    'datetime_format' => 'Y-m-d H:i:s',
    'middleware' => 
    array (
      0 => 'web',
      1 => 'Opcodes\\LogViewer\\Http\\Middleware\\AuthorizeLogViewer',
    ),
    'api_middleware' => 
    array (
      0 => 'Opcodes\\LogViewer\\Http\\Middleware\\EnsureFrontendRequestsAreStateful',
      1 => 'Opcodes\\LogViewer\\Http\\Middleware\\AuthorizeLogViewer',
    ),
    'api_stateful_domains' => NULL,
    'hosts' => 
    array (
      'local' => 
      array (
        'name' => 'Production',
      ),
    ),
    'include_files' => 
    array (
      0 => '*.log',
      1 => '**/*.log',
      2 => '/var/log/httpd/*',
      3 => '/var/log/nginx/*',
      4 => '/opt/homebrew/var/log/nginx/*',
      5 => '/opt/homebrew/var/log/httpd/*',
      6 => '/opt/homebrew/var/log/php-fpm.log',
      7 => '/opt/homebrew/var/log/postgres*log',
      8 => '/opt/homebrew/var/log/redis*log',
      9 => '/opt/homebrew/var/log/supervisor*log',
    ),
    'exclude_files' => 
    array (
    ),
    'hide_unknown_files' => true,
    'shorter_stack_trace_excludes' => 
    array (
      0 => '/vendor/symfony/',
      1 => '/vendor/laravel/framework/',
      2 => '/vendor/barryvdh/laravel-debugbar/',
    ),
    'cache_driver' => NULL,
    'cache_key_prefix' => 'lv',
    'lazy_scan_chunk_size_in_mb' => 50,
    'strip_extracted_context' => true,
    'per_page_options' => 
    array (
      0 => 10,
      1 => 25,
      2 => 50,
      3 => 100,
      4 => 250,
      5 => 500,
    ),
    'defaults' => 
    array (
      'use_local_storage' => true,
      'folder_sorting_method' => 'ModifiedTime',
      'folder_sorting_order' => 'desc',
      'file_sorting_method' => 'ModifiedTime',
      'log_sorting_order' => 'desc',
      'per_page' => 25,
      'theme' => 'System',
      'shorter_stack_traces' => false,
    ),
    'exclude_ip_from_identifiers' => false,
    'root_folder_prefix' => 'root',
    'route' => 'log-viewer',
  ),
  'logging' => 
  array (
    'default' => 'stack',
    'deprecations' => 
    array (
      'channel' => 'null',
      'trace' => false,
    ),
    'channels' => 
    array (
      'stack' => 
      array (
        'driver' => 'stack',
        'channels' => 
        array (
          0 => 'single',
        ),
        'ignore_exceptions' => false,
      ),
      'single' => 
      array (
        'driver' => 'single',
        'path' => '/var/www/omko-admin/storage/logs/laravel.log',
        'level' => 'error',
      ),
      'daily' => 
      array (
        'driver' => 'daily',
        'path' => '/var/www/omko-admin/storage/logs/laravel.log',
        'level' => 'error',
        'days' => 14,
      ),
      'slack' => 
      array (
        'driver' => 'slack',
        'url' => NULL,
        'username' => 'Laravel Log',
        'emoji' => ':boom:',
        'level' => 'error',
      ),
      'papertrail' => 
      array (
        'driver' => 'monolog',
        'level' => 'error',
        'handler' => 'Monolog\\Handler\\SyslogUdpHandler',
        'handler_with' => 
        array (
          'host' => NULL,
          'port' => NULL,
          'connectionString' => 'tls://:',
        ),
      ),
      'stderr' => 
      array (
        'driver' => 'monolog',
        'level' => 'error',
        'handler' => 'Monolog\\Handler\\StreamHandler',
        'formatter' => NULL,
        'with' => 
        array (
          'stream' => 'php://stderr',
        ),
      ),
      'syslog' => 
      array (
        'driver' => 'syslog',
        'level' => 'error',
      ),
      'errorlog' => 
      array (
        'driver' => 'errorlog',
        'level' => 'error',
      ),
      'null' => 
      array (
        'driver' => 'monolog',
        'handler' => 'Monolog\\Handler\\NullHandler',
      ),
      'emergency' => 
      array (
        'path' => '/var/www/omko-admin/storage/logs/laravel.log',
      ),
      'none' => 
      array (
        'driver' => 'monolog',
        'handler' => 'Monolog\\Handler\\NullHandler',
      ),
    ),
  ),
  'mail' => 
  array (
    'default' => 'log',
    'mailers' => 
    array (
      'smtp' => 
      array (
        'transport' => 'smtp',
        'host' => 'smtp.mailgun.org',
        'port' => 587,
        'encryption' => 'tls',
        'username' => NULL,
        'password' => NULL,
        'timeout' => NULL,
        'local_domain' => NULL,
      ),
      'ses' => 
      array (
        'transport' => 'ses',
      ),
      'mailgun' => 
      array (
        'transport' => 'mailgun',
      ),
      'postmark' => 
      array (
        'transport' => 'postmark',
      ),
      'sendmail' => 
      array (
        'transport' => 'sendmail',
        'path' => '/usr/sbin/sendmail -bs -i',
      ),
      'log' => 
      array (
        'transport' => 'log',
        'channel' => NULL,
      ),
      'array' => 
      array (
        'transport' => 'array',
      ),
      'failover' => 
      array (
        'transport' => 'failover',
        'mailers' => 
        array (
          0 => 'smtp',
          1 => 'log',
        ),
      ),
      'godaddy' => 
      array (
        'transport' => 'godaddy',
      ),
    ),
    'from' => 
    array (
      'address' => 'hello@example.com',
      'name' => 'Example',
    ),
    'markdown' => 
    array (
      'theme' => 'default',
      'paths' => 
      array (
        0 => '/var/www/omko-admin/resources/views/vendor/mail',
      ),
    ),
  ),
  'paystack' => 
  array (
    'publicKey' => NULL,
    'secretKey' => NULL,
    'paymentUrl' => NULL,
    'merchantEmail' => NULL,
  ),
  'querydetector' => 
  array (
    'enabled' => NULL,
    'threshold' => 1,
    'except' => 
    array (
    ),
    'log_channel' => 'daily',
    'output' => 
    array (
      0 => 'BeyondCode\\QueryDetector\\Outputs\\Alert',
      1 => 'BeyondCode\\QueryDetector\\Outputs\\Log',
    ),
  ),
  'queue' => 
  array (
    'default' => 'database',
    'connections' => 
    array (
      'sync' => 
      array (
        'driver' => 'sync',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => false,
      ),
      'beanstalkd' => 
      array (
        'driver' => 'beanstalkd',
        'host' => 'localhost',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => 0,
        'after_commit' => false,
      ),
      'sqs' => 
      array (
        'driver' => 'sqs',
        'key' => NULL,
        'secret' => NULL,
        'prefix' => 'https://sqs.us-east-1.amazonaws.com/your-account-id',
        'queue' => 'default',
        'suffix' => NULL,
        'region' => 'us-east-1',
        'after_commit' => false,
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => NULL,
        'after_commit' => false,
      ),
    ),
    'failed' => 
    array (
      'driver' => 'database-uuids',
      'database' => 'mysql',
      'table' => 'failed_jobs',
    ),
  ),
  'rolepermission' => 
  array (
    'dashboard' => 
    array (
      0 => 'read',
    ),
    'facility' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
    ),
    'categories' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
    ),
    'near_by_places' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
    ),
    'customer' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
    ),
    'verify_customer_form' => 
    array (
      0 => 'read',
      1 => 'create',
      2 => 'update',
      3 => 'delete',
    ),
    'approve_agent_verification' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'property' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
    ),
    'city_images' => 
    array (
      0 => 'read',
      1 => 'update',
      2 => 'delete',
    ),
    'project' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
    ),
    'report_reason' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
    ),
    'user_reports' => 
    array (
      0 => 'read',
    ),
    'users_inquiries' => 
    array (
      0 => 'read',
    ),
    'chat' => 
    array (
      0 => 'create',
      1 => 'read',
    ),
    'slider' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
    ),
    'article' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
    ),
    'advertisement' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'package-feature' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'package' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
    ),
    'user_package' => 
    array (
      0 => 'read',
    ),
    'payment' => 
    array (
      0 => 'read',
    ),
    'calculator' => 
    array (
      0 => 'read',
    ),
    'faqs' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
    ),
    'users_accounts' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
    ),
    'about_us' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'privacy_policy' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'terms_conditions' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'language' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
    ),
    'system_settings' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'app_settings' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'web_settings' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'firebase_settings' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'notification_settings' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'email_configurations' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'email_templates' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'admin_appointment_preferences' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'admin_appointment_schedules' => 
    array (
      0 => 'read',
      1 => 'update',
      2 => 'delete',
    ),
    'appointment_management' => 
    array (
      0 => 'read',
      1 => 'update',
      2 => 'delete',
    ),
    'appointment_reports' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'crm_leads' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
    ),
    'short_term_reservations' => 
    array (
      0 => 'read',
      1 => 'update',
      2 => 'delete',
    ),
    'short_term_availability' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
    ),
    'project_inventory' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
    ),
    'crm_leads_reports' => 
    array (
      0 => 'read',
    ),
    'system_update' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'notification' => 
    array (
      0 => 'read',
      1 => 'create',
      2 => 'delete',
    ),
    'homepage-sections' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
    ),
    'seo_settings' => 
    array (
      0 => 'read',
      1 => 'create',
      2 => 'update',
      3 => 'delete',
    ),
    'ad-banners' => 
    array (
      0 => 'create',
      1 => 'read',
      2 => 'update',
      3 => 'delete',
    ),
    'assign_package' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'watermark_settings' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'gemini_settings' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'payment_gateway_settings' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
    'demo_data' => 
    array (
      0 => 'read',
      1 => 'update',
    ),
  ),
  'sanctum' => 
  array (
    'stateful' => 
    array (
      0 => 'localhost',
      1 => 'localhost:3000',
      2 => '127.0.0.1',
      3 => '127.0.0.1:8000',
      4 => '::1',
      5 => 'adminrealestate.omko.do',
    ),
    'guard' => 
    array (
      0 => 'web',
    ),
    'expiration' => NULL,
    'token_prefix' => '',
    'middleware' => 
    array (
      'verify_csrf_token' => 'App\\Http\\Middleware\\VerifyCsrfToken',
      'encrypt_cookies' => 'App\\Http\\Middleware\\EncryptCookies',
    ),
  ),
  'services' => 
  array (
    'mailgun' => 
    array (
      'domain' => NULL,
      'secret' => NULL,
      'endpoint' => 'api.mailgun.net',
      'scheme' => 'https',
    ),
    'postmark' => 
    array (
      'token' => NULL,
    ),
    'ses' => 
    array (
      'key' => NULL,
      'secret' => NULL,
      'region' => 'us-east-1',
    ),
    'gemini' => 
    array (
      'api_key' => NULL,
      'api_url' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite',
    ),
    'whatsapp' => 
    array (
      'mode' => 'mock',
      'token' => '',
      'phone_id' => '',
      'sender_number' => '',
      'language' => 'es',
    ),
  ),
  'session' => 
  array (
    'driver' => 'database',
    'lifetime' => '120',
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => '/var/www/omko-admin/storage/framework/sessions',
    'connection' => NULL,
    'table' => 'sessions',
    'store' => NULL,
    'lottery' => 
    array (
      0 => 2,
      1 => 100,
    ),
    'cookie' => 'omko_session',
    'path' => '/',
    'domain' => NULL,
    'secure' => NULL,
    'http_only' => true,
    'same_site' => 'lax',
  ),
  'telescope' => 
  array (
    'enabled' => false,
    'domain' => NULL,
    'path' => 'telescope',
    'driver' => 'database',
    'storage' => 
    array (
      'database' => 
      array (
        'connection' => 'mysql',
        'chunk' => 1000,
      ),
    ),
    'queue' => 
    array (
      'connection' => NULL,
      'queue' => NULL,
    ),
    'middleware' => 
    array (
      0 => 'web',
      1 => 'Laravel\\Telescope\\Http\\Middleware\\Authorize',
    ),
    'only_paths' => 
    array (
    ),
    'ignore_paths' => 
    array (
      0 => 'livewire*',
      1 => 'nova-api*',
      2 => 'pulse*',
    ),
    'ignore_commands' => 
    array (
    ),
    'watchers' => 
    array (
      'Laravel\\Telescope\\Watchers\\BatchWatcher' => true,
      'Laravel\\Telescope\\Watchers\\CacheWatcher' => 
      array (
        'enabled' => true,
        'hidden' => 
        array (
        ),
      ),
      'Laravel\\Telescope\\Watchers\\ClientRequestWatcher' => true,
      'Laravel\\Telescope\\Watchers\\CommandWatcher' => 
      array (
        'enabled' => true,
        'ignore' => 
        array (
        ),
      ),
      'Laravel\\Telescope\\Watchers\\DumpWatcher' => 
      array (
        'enabled' => true,
        'always' => false,
      ),
      'Laravel\\Telescope\\Watchers\\EventWatcher' => 
      array (
        'enabled' => true,
        'ignore' => 
        array (
        ),
      ),
      'Laravel\\Telescope\\Watchers\\ExceptionWatcher' => true,
      'Laravel\\Telescope\\Watchers\\GateWatcher' => 
      array (
        'enabled' => true,
        'ignore_abilities' => 
        array (
        ),
        'ignore_packages' => true,
        'ignore_paths' => 
        array (
        ),
      ),
      'Laravel\\Telescope\\Watchers\\JobWatcher' => true,
      'Laravel\\Telescope\\Watchers\\LogWatcher' => 
      array (
        'enabled' => true,
        'level' => 'error',
      ),
      'Laravel\\Telescope\\Watchers\\MailWatcher' => true,
      'Laravel\\Telescope\\Watchers\\ModelWatcher' => 
      array (
        'enabled' => true,
        'events' => 
        array (
          0 => 'eloquent.*',
        ),
        'hydrations' => true,
      ),
      'Laravel\\Telescope\\Watchers\\NotificationWatcher' => true,
      'Laravel\\Telescope\\Watchers\\QueryWatcher' => 
      array (
        'enabled' => true,
        'ignore_packages' => true,
        'ignore_paths' => 
        array (
        ),
        'slow' => 100,
      ),
      'Laravel\\Telescope\\Watchers\\RedisWatcher' => true,
      'Laravel\\Telescope\\Watchers\\RequestWatcher' => 
      array (
        'enabled' => true,
        'size_limit' => 64,
        'ignore_http_methods' => 
        array (
        ),
        'ignore_status_codes' => 
        array (
        ),
      ),
      'Laravel\\Telescope\\Watchers\\ScheduleWatcher' => true,
      'Laravel\\Telescope\\Watchers\\ViewWatcher' => true,
    ),
  ),
  'update-generator' => 
  array (
    'exclude_update' => 
    array (
      0 => '.vscode',
      1 => 'storage',
      2 => 'vendor',
      3 => '.env',
      4 => 'node_modules',
      5 => '.git',
      6 => '.idea',
      7 => 'composer.lock',
      8 => 'package-lock.json',
      9 => 'yarn.lock',
      10 => 'public/storage',
      11 => 'public/uploads',
      12 => 'tests',
      13 => 'phpunit.xml',
      14 => '.gitignore',
      15 => '.env.example',
      16 => 'README.md',
      17 => 'CHANGELOG.md',
      18 => 'vendor.zip',
    ),
    'add_update_file' => 
    array (
      0 => 'vendor/autoload.php',
      1 => 'vendor/mahesh-kerai',
      2 => 'vendor/composer',
      3 => 'vendor/league/csv',
      4 => 'storage/app/example-csvs',
    ),
    'exclude_new' => 
    array (
      0 => 'storage/app/public/*',
      1 => 'storage/logs/*',
      2 => 'storage/framework/cache/data',
      3 => 'storage/framework/sessions/*',
      4 => 'storage/framework/views/*',
      5 => 'storage/debugbar/*',
      6 => '.git',
      7 => '.idea',
      8 => 'node_modules',
      9 => 'public/storage',
      10 => 'public/uploads',
      11 => '.vscode',
      12 => 'storage/installed',
    ),
    'output_directory' => 'storage/app/update_files',
    'git_timeout' => 300,
    'enable_logging' => true,
    'clear_cache_before_generation' => true,
    'sanitize_env_file' => true,
    'env_sanitization_rules' => 
    array (
      'APP_KEY' => 'base64:your-app-key-here',
      'APP_DEBUG' => 'false',
      'DEMO_MODE' => 'false',
      'DB_PASSWORD' => '',
      'DB_USERNAME' => 'root',
      'DB_DATABASE' => 'laravel',
      'DB_HOST' => '127.0.0.1',
      'DB_PORT' => '3306',
      'MAIL_PASSWORD' => '',
      'MAIL_USERNAME' => '',
      'MAIL_HOST' => 'smtp.gmail.com',
      'MAIL_PORT' => '587',
      'MAIL_ENCRYPTION' => 'tls',
      'MAIL_FROM_ADDRESS' => 'hello@example.com',
      'PUSHER_APP_KEY' => '',
      'PUSHER_APP_SECRET' => '',
      'PUSHER_APP_ID' => '',
      'PUSHER_APP_CLUSTER' => 'mt1',
      'MIX_PUSHER_APP_KEY' => '',
      'MIX_PUSHER_APP_CLUSTER' => 'mt1',
      'AWS_ACCESS_KEY_ID' => '',
      'AWS_SECRET_ACCESS_KEY' => '',
      'AWS_DEFAULT_REGION' => 'us-east-1',
      'AWS_BUCKET' => '',
    ),
  ),
  'view' => 
  array (
    'paths' => 
    array (
      0 => '/var/www/omko-admin/resources/views',
    ),
    'compiled' => '/var/www/omko-admin/storage/framework/views',
  ),
  'tinker' => 
  array (
    'commands' => 
    array (
    ),
    'alias' => 
    array (
    ),
    'dont_alias' => 
    array (
      0 => 'App\\Nova',
    ),
    'trust_project' => 'always',
  ),
);
