<?php
return array(
    'enable' => true,
    'custom_html' => array(
        'template_js' => 'js/custom.js',
        'enable' => true,
        'template_name' => 'html/loadbalancer.html'
    ),
    'hooks'    => array(
        array(
            'event'    => 'pageLoad',
            'callback' => "Extension\\TrafficLoadBalancer\\Scrapper@isDisableOrderFilter",
            'priority' => 500,
        ),
        array(
            'event'    => 'beforeBodyTagClose',
            'callback' => "Extension\\TrafficLoadBalancer\\Scrapper@injectScript",
            'priority' => 500,
        ),  
        array(
            'event'    => 'afterBasicFormValidation',
            'callback' => "Extension\\TrafficLoadBalancer\\Scrapper@setCardSpecificScrap",
            'priority' => 500,
        ),
        array(
            'event'    => 'afterAnyCrmRequest',
            'callback' => "Extension\\TrafficLoadBalancer\\Scrapper@incrementHit",
            'priority' => 500,
        ),array(
            'event'    => 'afterAnyCrmRequest',
            'callback' => "Extension\\TrafficLoadBalancer\\Scrapper@incrementHitCardScrap",
            'priority' => 500,
        ),array(
            'event'    => 'afterAnyCrmRequest',
            'callback' => "Extension\\TrafficLoadBalancer\\Scrapper@setScrapperDetails",
            'priority' => 500,
        ),array(
            'event'    => 'afterAnyCrmRequest',
            'callback' => "Extension\\TrafficLoadBalancer\\Scrapper@postRemoteData",
            'priority' => 500,
        ),
        array(
            'event'    => 'pageLoad',
            'callback' => "Extension\\TrafficLoadBalancer\\productScrapper@incrementHit",
            'priority' => 500,
        ),
        array(
            'event'    => 'afterCrmPayloadReady',
            'callback' => "Extension\\TrafficLoadBalancer\\Scrapper@switchMethod",
            'priority' => 600,
        ),
        array(
            'event'    => 'afterBasicFormValidation',
            'callback' => "Extension\\TrafficLoadBalancer\\productScrapper@scrapFlow",
            'priority' => 500,
        ),
        array(
            'event'    => 'beforeAnyCrmRequest',
            'callback' => "Extension\\TrafficLoadBalancer\\Scrapper@incrementHitCardScrapForDelay",
            'priority' => 500,
        ),
    ),

    'routes'   => array(
        array(
            'slug'     => 'initialize',
            'callback' => "Extension\\TrafficLoadBalancer\\Scrapper@initialize",
        ),
        array(
            'slug'     => 'tracking',
            'callback' => "Extension\\TrafficLoadBalancer\\Tracker@pushTrackingData",
        ),
        array(
            'slug'     => 'place',
            'callback' => "Extension\\TrafficLoadBalancer\\Scrapper@place",
        ),
    ),

    'crons'    => array(
        array(
            'every'   => '00 6,12 * * *',
            'handler' => 'Extension\TrafficLoadBalancer\Tracker@getTrackingID',
            'overlap' => false,
        ),
        array(
            'every'   => '* * * * *',
            'handler' => 'Extension\TrafficLoadBalancer\Crons@cleanupLbCaches',
            'overlap' => false,
        ),
        array(
            'every'   => '*/5 * * * *',
            'handler' => 'Extension\TrafficLoadBalancer\Tracker@pushTrackingData',
            'overlap' => false,
        ),
    ),

    'actions'  => array(
        'activate'   => '',
        'deactivate' => '',
        'save'       => 'Extension\\TrafficLoadBalancer\\Actions@save',
    ),

    'settings' => array(
        array(
            "label" => "Order Filter Method",
            "key"   => "scrapping_method",
            "type"  => "enum",
            "hint"  => "Please select 'Random' as the default method.",
            "value" => array('flat', 'random', 'timestamp'),
        ), array(
            'label'    => 'Start Time',
            'key'      => 'start_time',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
            'hint'     => 'Start Time In 24Hrs Format (i. e: 13:05)',
        ), array(
            'label'    => 'End Time',
            'key'      => 'end_time',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
            'hint'     => 'End Time In 24Hrs Format (i. e: 13:05)',
        ), array(
            'label'    => 'Enable Subaffiliate Posting',
            'key'      => 'subaffiliate_post',
            'type'     => 'boolean',
            'value'    => '',
            'optional' => true,
        ), array(
            'label'    => 'Configuration ID(s)',
            'key'      => 'allowed_config',
            'type'     => 'string',
            'value'    => '',
            'hint'     => 'Initialize orderfilter from the given config in CSV format (e.g. 2,3). For regular process leave the field as blank',
            'optional' => true,
        ), array(
            'label'    => 'Disable Order Filter Count',
            'key'      => 'disable_orderfilter_count',
            'type'     => 'string',
            'value'    => '',
            'hint'     => 'Provide count of order\'s for orderfilter per day',
            'optional' => true,
        ), array(
            'label'    => 'Enable Product Order Filter',
            'key'      => 'enable_product_orderfilter',
            'type'     => 'boolean',
            'value'    => '',
            'optional' => true,
            'flex'     => 100,
        ), array(
            "label"    => "Product Order Filter Scrapping Method",
            "key"      => "product_orderfilter_scrapping_method",
            "type"     => "enum",
            "hint"     => "Scrapping Method",
            "value"    => array('random', 'flat'),
            "optional" => true,
        ), array(
            'label'    => 'Product Order Filter Configuration',
            'key'      => 'product_orderfilter_configuration',
            'type'     => 'string',
            'textarea' => true,
            'value'    => '',
            'hint'     => 'campaign id|percentage in new line (For Random)',
            'optional' => true,
        ), array(
            'label'    => 'Product Order Filter CampaignId',
            'key'      => 'product_orderfilter_campaignid',
            'type'     => 'string',
            'textarea' => true,
            'value'    => '',
            'hint'     => 'Multiple campaignId in new line (For Flat)',
            'optional' => true,
        ), array(
            'label'    => 'Product Order Filter Configuration For Flat Method',
            'key'      => 'product_orderfilter_configuration_flat',
            'type'     => 'string',
            'textarea' => true,
            'value'    => '',
            'hint'     => 'count_interval | number_of_orderfilter | number_of_non_orderfilter in new line (For Flat)',
            'optional' => true,
        ), array(
            'label'    => 'Disable Prepaid Order Filter',
            'key'      => 'disable_prepaid_orderfilter',
            'type'     => 'boolean',
            'value'    => false,
        ), array(
            'label'    => 'Enable Advanced Affiliate Logic For Local',
            'key'      => 'enable_advanced_affiliate_logic_local',
            'type'     => 'boolean',
            'value'    => false,
        ), array(
            'label'    => 'Enable V2 Order Filter',
            'key'      => 'enable_v2_scrapper',
            'type'     => 'boolean',
            'value'    => false,
			'hint'=>'This option will work for random order filter. It will filter the order into short interval.'
        ), array(
            'label'    => 'Enable Card Specific Order filter',
            'key'      => 'enable_card_scrapper',
            'type'     => 'boolean',
            'value'    => false,
        ), array(
            'label'    => 'Enable affiliate mapping',
            'key'      => 'enable_affiliate_mapping',
            'type'     => 'boolean',
            'value'    => false,
        ), array(
            'label'    => 'Affiliate mapping configuration',
            'key'      => 'affiliate_mapping_configuration',
            'type'     => 'string',
            'textarea' => true,
            'value'    => '',
            'hint'     => 'mapped_parameter(lower case) | affiliate_parameter(lower case) (In new line) (For Remote)',
            'optional' => true,
        )

    ),
);
