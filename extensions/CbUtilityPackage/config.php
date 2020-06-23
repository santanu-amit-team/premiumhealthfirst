<?php

return array(
    'hooks'    => array(
        array(
            'event'    => 'afterAnyCrmRequest',
            'callback' => 'Extension\CbUtilityPackage\PrepaidRedirection@performRedirection',
            'priority' => 100,
        ),
        array(
            'event'    => 'afterAnyCrmRequest',
            'callback' => 'Extension\CbUtilityPackage\PreserveGateway@getGatewayId',
            'priority' => 100,
        ),
        array(
            'event'    => 'afterAnyCrmRequest',
            'callback' => 'Extension\CbUtilityPackage\DeclineRedirection@performRedirection',
            'priority' => 101,
        ),
        array(
            'event'    => 'afterAnyCrmRequest',
            'callback' => 'Extension\CbUtilityPackage\ReprocessOrders@reprocessOrders',
            'priority' => 99,
        ),
        array(
            'event'    => 'afterBasicFormValidation',
            'callback' => 'Extension\CbUtilityPackage\PrepaidRedirection@setPrepaidSession',
            'priority' => 100,
        ),
        array(
            'event'    => 'afterAnyCrmRequest',
            'callback' => 'Extension\CbUtilityPackage\OrderDecline@increaseDeclineCount',
            'priority' => 100,
        ),
        array(
            'event'    => 'afterBasicFormValidation',
            'callback' => 'Extension\CbUtilityPackage\OrderDecline@checkDecline',
            'priority' => 100,
        ),
        array(
            'event'    => 'afterBasicFormValidation',
            'callback' => 'Extension\CbUtilityPackage\OrderDecline@checkTimeBasedDecline',
            'priority' => 100,
        ),
        array(
            'event'    => 'afterBasicFormValidation',
            'callback' => 'Extension\CbUtilityPackage\OrderDecline@checkTimeBasedDeclineAdvanced',
            'priority' => 101,
        ),
        array(
            'event'    => 'afterAnyCrmRequest',
            'callback' => 'Extension\CbUtilityPackage\CustomDeclineMsg@updateDeclineReason',
            'priority' => 100,
        ),
        array(
            'event'    => 'beforeAnyCrmRequest',
            'callback' => 'Extension\CbUtilityPackage\AffiliateOverwrite@performOverwrite',
            'priority' => 900,
        ),
        array(
            'event'    => 'beforeAnyCrmRequest',
            'callback' => 'Extension\CbUtilityPackage\AffiliateOverwrite@performSplitOverwrite',
            'priority' => 900,
        ),
        array(
            'event'    => 'beforeAnyCrmRequest',
            'callback' => 'Extension\CbUtilityPackage\UserBlock@captureSessionCount',
            'priority' => 900,
        ),
        array(
            'event'    => 'beforeAnyCrmRequest',
            'callback' => 'Extension\CbUtilityPackage\PreserveGateway@preserveUpsellGateways',
            'priority' => 900,
        ),
        array(
            'event'    => 'beforeAnyCrmRequest',
            'callback' => 'Extension\CbUtilityPackage\ChangeIp@changeIp',
            'priority' => 901,
        ),
        array(
            'event'    => 'beforeAnyCrmRequest',
            'callback' => 'Extension\CbUtilityPackage\AlterAddress@alterAddress',
            'priority' => 902,
        ),
        array(
            'event'    => 'beforeAnyCrmRequest',
            'callback' => 'Extension\CbUtilityPackage\PostSiteUrl@postSiteUrl',
            'priority' => 902,
        ),
        array(
            'event'    => 'beforeBodyTagClose',
            'callback' => "Extension\CbUtilityPackage\GAScripts@injectGAScript",
            'priority' => 100,
        ),
        array(
            'event'    => 'beforeRenderScripts',
            'callback' => "Extension\CbUtilityPackage\ScriptSettings@render",
            'priority' => 100,
        ),
        array(
            'event'    => 'beforeHttpRequest',
            'callback' => "Extension\\CbUtilityPackage\\AdvancedDebug@captureRequestParams",
            'priority' => 100,
        ),
        array(
            'event'    => 'beforeControllerAction',
            'callback' => 'Extension\CbUtilityPackage\ScriptSettings@convertNonEnglishChar',
            'priority' => 100,
        ),
        array(
            'event'    => 'beforeAnyCrmRequest',
            'callback' => 'Extension\CbUtilityPackage\Preauth@verify',
            'priority' => 900,
        ),
    ),
    'custom_html' => array(
        'template_js' => 'js/unify-utility.js',
        'enable' => true,
        'template_name' => 'html/unify-utility.html'
    ),
    'routes'   => array(
        array(
            'slug'     => 'create-partial-prospect',
            'callback' => "Extension\\CbUtilityPackage\\PartialProspect@createProspect",
            'method'   => 'POST',
        ),
    ),
    'settings' => array(
        array(
            'label' => 'Enable GA Tracking',
            'key'   => 'ga_track_enabled',
            'type'  => 'boolean',
            'value' => false,
        ),
        array(
            'label'    => 'GA Key',
            'key'      => 'ga_key',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'Order Decline Limit',
            'key'      => 'order_decline_limit',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'Order Decline Message',
            'key'      => 'order_decline_msg',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label' => 'Enable Session Based User Block',
            'key'   => 'enable_session_based_uder_block',
            'type'  => 'boolean',
            'value' => false,
            'flex'  => 100,
        ),
        array(
            'label'    => 'Block Limit',
            'key'      => 'block_limit',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'Block Message',
            'key'      => 'block_msg',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label' => 'Enable Time Based User Block',
            'key'   => 'enable_time_based_user_block',
            'type'  => 'boolean',
            'value' => false,
        ),
        array(
            "label"    => "Block Type",
            "key"      => "time_based_block_type",
            "type"     => "multi_select",
            "hint"     => "",
            "value"    => array('EMAIL', 'CREDITCARD', 'IPADDRESS'),
            'optional' => true,
        ),
        array(
            'label'    => 'Block Time (In hours)',
            'key'      => 'block_time',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'Time Based Block Message',
            'key'      => 'time_based_block_msg',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'Add whitelist IPs',
            'key'      => 'whitelist_ip',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
            'textarea' => true,
            'hint'=>'Add multiple in new lines'
        ),
        array(
            'label'    => 'Add whitelist Email',
            'key'      => 'whitelist_email',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
            'textarea' => true,
            'hint'     => 'Add multiple in new lines'
        ),
        array(
            'label'    => 'Add whitelist Card',
            'key'      => 'whitelist_card',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
            'textarea' => true,
            'flex'     => 100,
            'hint'     => 'Add multiple in new lines'
        ),
        array(
            'label' => 'Enable Custom Decline Message',
            'key'   => 'enable_custom_decline_msg',
            'type'  => 'boolean',
            'value' => false,
            'flex' => 100
        ),
        array(
            'label'    => 'Custom Decline Message',
            'key'      => 'custom_decline_msg',
            'type'     => 'string',
            'value'    => '',
            'flex'     => 100,
            'optional' => true,
            'textarea' => true,
            'hint'     => 'CRM_MESSAGE|YOUR_CUSTOM_MESSAGE in new line',
        ),
        array(
            'label' => 'Enable Prepaid Redirection',
            'key'   => 'prepaid_redirection_enabled',
            'type'  => 'boolean',
            'value' => false,
        ),
        array(
            'label' => 'Enable Prepaid Flow',
            'key'   => 'enable_prepaid_flow',
            'type'  => 'boolean',
            'value' => false,
        ),
        array(
            'label' => 'Prepaid Redirection URL for desktop',
            'key'   => 'prepaid_redirection_url',
            'type'  => 'string',
            'value' => 'https://google.com',
        ),
        array(
            'label'    => 'Prepaid Redirection URL for mobile',
            'key'      => 'prepaid_redirection_url_mobile',
            'type'     => 'string',
            'value'    => 'https://google.com',
            'optional' => true,
        ),
        array(
            'label' => 'Disable Non English Character Input',
            'key'   => 'disable_non_english_char_input',
            'type'  => 'boolean',
            'value' => false,
        ),
        array(
            'label' => 'Enable Non English Character conversion',
            'key'   => 'enable_non_english_char_convert',
            'type'  => 'boolean',
            'value' => false,
        ),
        array(
            'label' => 'Enable Override for Prepaid',
            'key'   => 'enable_overwrite_prepaid',
            'type'  => 'boolean',
            'value' => '',
            'flex'  => '100',
        ),
        array(
            'label'    => 'AFID',
            'key'      => 'prepaid_afId',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'AFFID',
            'key'      => 'prepaid_affId',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'SID',
            'key'      => 'prepaid_sId',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'C1',
            'key'      => 'prepaid_c1',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'C2',
            'key'      => 'prepaid_c2',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'C3',
            'key'      => 'prepaid_c3',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'C4',
            'key'      => 'prepaid_c4',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'C5',
            'key'      => 'prepaid_c5',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'AID',
            'key'      => 'prepaid_aId',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'OPT',
            'key'      => 'prepaid_opt',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'Click Id',
            'key'      => 'prepaid_clickId',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
            'flex'     => '100',
        ),
        array(
            'label' => 'Enable Override for Order Filter',
            'key'   => 'enable_overwrite_order_filter',
            'type'  => 'boolean',
            'value' => '',
            'flex'  => '100',
        ),
        array(
            'label'    => 'AFID',
            'key'      => 'scrap_afId',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'AFFID',
            'key'      => 'scrap_affId',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'SID',
            'key'      => 'scrap_sId',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'C1',
            'key'      => 'scrap_c1',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'C2',
            'key'      => 'scrap_c2',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'C3',
            'key'      => 'scrap_c3',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'C4',
            'key'      => 'scrap_c4',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'C5',
            'key'      => 'scrap_c5',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'AID',
            'key'      => 'scrap_aId',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'OPT',
            'key'      => 'scrap_opt',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'Click Id',
            'key'      => 'scrap_clickId',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
            'flex'     => '100',
        ),
        array(
            'label' => 'Enable Override for split Order',
            'key'   => 'enable_overwrite_split_order',
            'type'  => 'boolean',
            'value' => '',
            'flex'  => '100',
        ),
        array(
            'label'    => 'AFID',
            'key'      => 'split_afId',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'AFFID',
            'key'      => 'split_affId',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'SID',
            'key'      => 'split_sId',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'C1',
            'key'      => 'split_c1',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'C2',
            'key'      => 'split_c2',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'C3',
            'key'      => 'split_c3',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'C4',
            'key'      => 'split_c4',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'C5',
            'key'      => 'split_c5',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'AID',
            'key'      => 'split_aId',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'OPT',
            'key'      => 'split_opt',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'Click Id',
            'key'      => 'split_clickId',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
            'flex'     => '100',
        ),
        array(
            'label' => 'Enable Advanced Debug',
            'key'   => 'enable_advanced_debug',
            'type'  => 'boolean',
            'value' => false,
        ),
        array(
            'label' => 'Convert IPv6 To IPv4',
            'key'   => 'convert_ip',
            'type'  => 'boolean',
            'value' => false,
        ),
        array(
            'label' => 'Preserve Gateway for upsell(s)',
            'key'   => 'preserve_gateway_upsells',
            'type'  => 'boolean',
            'value' => false,
        ),
        array(
            'label'    => 'Upsell Steps',
            'key'      => 'upsell_steps',
            'type'     => 'string',
            'value'    => '',
            'hint'     => 'comma separated',
            'optional' => true,
        ),
        array(
            'label' => 'Alter shipping and billing address',
            'key'   => 'alter_shipping_billing_address',
            'type'  => 'boolean',
            'value' => false,
        ),
        array(
            'label' => 'Enable Preauth for Regular flow',
            'key'   => 'enable_preauth_regular',
            'type'  => 'boolean',
            'value' => false,
            'flex'  => 100
        ),
        array(
            'label'    => 'Default Preauth Amount',
            'key'      => 'preauth_default_amount_regular',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
            'hint'     => 'Default pre auth amount, if not provided it will take from rebill amount of codebase campaign.',
        ),
        array(
            'label'    => 'Allow Preauth Step(s)',
            'key'      => 'allow_preauth_steps',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
            'hint'     => 'Comma separated steps',
        ),
        array(
            'label'    => 'Custom Preauth Message ',
            'key'      => 'custom_preauth_message',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
            'hint'     => 'CRM error message will be overidden if provided.',
            'flex'     => 100,
        ),
        array(
            'label' => 'Enable Retry For Preauth Regular flow',
            'key'   => 'enable_retry_preauth_regular',
            'type'  => 'boolean',
            'value' => false,
        ),
        array(
            'label'    => 'Retry CB Campaign ID For Preauth Regular flow ',
            'key'      => 'preauth_regular_campaign_id',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
            'hint'     => 'Codebase campaign id needs to be set here.',
        ),
        array(
            'label' => 'Reprocess Decline Orders',
            'key'   => 'reprocess_decline_orders',
            'type'  => 'boolean',
            'value' => false,
        ),
        array(
            'label'    => 'Reprocessing Configuration',
            'key'      => 'reprocessing_configuration',
            'type'     => 'string',
            'value'    => '',
            'textarea' => true,
            'optional' => true,
            'hint'     => 'CB campaign id|step(s) in new line',
        ),
        array(
            'label' => 'Post site URL',
            'key'   => 'post_site_url',
            'type'  => 'boolean',
            'value' => false,
            'flex'  => 100
        ),
        array(
            'label' => 'site URL Type',
            'key' => 'type',
            'type' => 'enum',
            'value' => array('static', 'siteurl', 'midrouting'),
            'optional' => true,
        ),
        array(
            'label'    => 'Site URL ',
            'key'      => 'site_url',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
            'hint'     => 'Static site URL.',
        ),
        array(
            'label' => 'Enable Redirection For Declines',
            'key'   => 'decline_redirection_enabled',
            'type'  => 'boolean',
            'value' => false,
            'flex'  => 100
        ),
        array(
            'label'    => 'Decline Redirection URL for desktop',
            'key'      => 'decline_redirection_url_desktop',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'label'    => 'Decline Redirection URL for mobile',
            'key'      => 'decline_redirection_url_mobile',
            'type'     => 'string',
            'value'    => '',
            'optional' => true,
        ),
    ),
    'scripts'  => array(
        'cbUtilPkg' => 'js/cb-util-pkg.js',
    ),
);
