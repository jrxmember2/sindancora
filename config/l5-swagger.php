<?php

return [
    'default' => 'default',

    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'SindÂncora API',
            ],

            'routes' => [
                'api' => 'api/documentation',
                'docs' => 'docs',
                'oauth2_callback' => 'api/oauth2-callback',
                'middleware' => [
                    'api' => [],
                    'asset' => [],
                    'docs' => [],
                    'oauth2_callback' => [],
                ],
                'group_options' => [],
            ],

            'paths' => [
                'docs' => storage_path('api-docs'),
                'docs_json' => 'api-docs.json',
                'docs_yaml' => 'api-docs.yaml',
                'annotations' => [
                    app_path('Http/Controllers/Api'),
                ],
                'base' => env('L5_SWAGGER_BASE_PATH', null),
                'views' => base_path('resources/views/vendor/l5-swagger'),
                'excludes' => [],
            ],

            'scanOptions' => [
                'analyser' => null,
                'analysis' => null,
                'processors' => [],
                'pattern' => null,
                'exclude' => [],
                'open_api_spec_version' => env('L5_SWAGGER_SPEC_VERSION', \L5Swagger\Generator::OPEN_API_DEFAULT_SPEC_VERSION),
            ],

            'securityDefinitions' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
                'security' => [
                    ['bearerAuth' => []],
                ],
            ],

            'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),
            'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),
            'proxy' => false,
            'additional_config_url' => null,
            'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', null),
            'validator_url' => null,
            'ui' => [
                'display' => [
                    'doc_expansion' => env('L5_SWAGGER_UI_DOC_EXPANSION', 'none'),
                    'filter' => env('L5_SWAGGER_UI_FILTERS', true),
                    'show_extensions' => env('L5_SWAGGER_UI_SHOW_EXTENSIONS', false),
                    'show_common_extensions' => env('L5_SWAGGER_UI_SHOW_COMMON_EXTENSIONS', false),
                    'try_it_out_enabled' => env('L5_SWAGGER_TRY_IT_OUT_ENABLED', true),
                    'request_snippets_enabled' => env('L5_SWAGGER_UI_REQUEST_SNIPPETS_ENABLED', false),
                ],
                'authorization' => [
                    'persist_authorization' => env('L5_SWAGGER_UI_PERSIST_AUTHORIZATION', false),
                    'oauth2' => [
                        'use_pkce_with_authorization_code_grant' => false,
                    ],
                ],
            ],
            'constants' => [
                'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', 'http://localhost'),
            ],
        ],
    ],

    'defaults' => [
        'routes' => [
            'docs' => 'docs',
            'oauth2_callback' => 'api/oauth2-callback',
            'middleware' => [
                'api' => [],
                'asset' => [],
                'docs' => [],
                'oauth2_callback' => [],
            ],
            'group_options' => [],
        ],

        'paths' => [
            'docs' => storage_path('api-docs'),
            'docs_json' => 'api-docs.json',
            'docs_yaml' => 'api-docs.yaml',
            'annotations' => [base_path('app')],
            'views' => base_path('resources/views/vendor/l5-swagger'),
            'base' => env('L5_SWAGGER_BASE_PATH', null),
            'excludes' => [],
        ],

        'scanOptions' => [
            'analyser' => null,
            'analysis' => null,
            'processors' => [],
            'pattern' => null,
            'exclude' => [],
            'open_api_spec_version' => env('L5_SWAGGER_SPEC_VERSION', '3.0'),
        ],

        'securityDefinitions' => [
            'securitySchemes' => [],
            'security' => [],
        ],

        'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),
        'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),
        'proxy' => false,
        'additional_config_url' => null,
        'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', null),
        'validator_url' => null,
        'ui' => [
            'display' => [
                'doc_expansion' => env('L5_SWAGGER_UI_DOC_EXPANSION', 'none'),
                'filter' => env('L5_SWAGGER_UI_FILTERS', true),
                'show_extensions' => false,
                'show_common_extensions' => false,
                'try_it_out_enabled' => env('L5_SWAGGER_TRY_IT_OUT_ENABLED', true),
                'request_snippets_enabled' => false,
            ],
            'authorization' => [
                'persist_authorization' => env('L5_SWAGGER_UI_PERSIST_AUTHORIZATION', false),
                'oauth2' => ['use_pkce_with_authorization_code_grant' => false],
            ],
        ],
        'constants' => [
            'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', 'http://localhost'),
        ],
    ],
];
