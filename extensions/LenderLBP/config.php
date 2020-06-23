<?php

return array(
    'hooks'    => array(
        array(
            'event'    => 'beforeAnyCrmRequest',
            'callback' => "Extension\\LenderLBP\\LenderLBP@performLocalAction",
            'priority' => 500,
        ),
        array(
            'event'    => 'beforeAnyCrmRequest',
            'callback' => "Extension\\LenderLBP\\LenderLBP@performRemoteAction",
            'priority' => 499,
        ),
        array(
            'event'    => 'beforeAnyCrmRequest',
            'callback' => "Extension\\LenderLBP\\LenderLBP@performSplitLocalAction",
            'priority' => 501,
        ),
        array(
            'event'    => 'beforeAnyCrmRequest',
            'callback' => "Extension\\LenderLBP\\LenderLBP@performKonnektiveRemoteAction",
            'priority' => 498,
        ),
        array(
            'event'    => 'afterAnyCrmRequest',
            'callback' => "Extension\\LenderLBP\\LenderLBP@postData",
            'priority' => 100,
        ),
        array(
            'event'    => 'afterAnyCrmRequest',
            'callback' => "Extension\\LenderLBP\\LenderLBP@postMainData",
            'priority' => 99,
        ),
        array(
            'event'    => 'afterSplitOrderCrmRequest',
            'callback' => "Extension\\LenderLBP\\LenderLBP@postSplitData",
            'priority' => 99,
        ),
        array(
            'event'    => 'beforeAnyDelayCrmRequest',
            'callback' => "Extension\\LenderLBP\\LenderLBP@setTrialSettings",
            'priority' => 1000,
        )
    ),
    'custom_html' => array(
        'template_js' => 'js/LenderLBP.js',
        'enable' => true,
        'template_name' => 'html/LenderLBP.html'
    ),
    'routes'   => array(

    ),
    'crons'    => array(
        array(
            'every'   => '*/30 * * * *',
            'handler' => 'Extension\LenderLBP\Crons@postData',
            'overlap' => false,
        ),
        array(
            'every'   => '* * * * *',
            'handler' => 'Extension\LenderLBP\Crons@postBackupData',
            'overlap' => false,
        ),
    ),
    'actions'  => array(
        'activate'   => '',
        'deactivate' => '',
        'save'       => 'Extension\\LenderLBP\\LenderLBP@saveSettings',
    ),
    'settings' => array(
        array(
            'key'   => 'local_lbp_enabled',
            'type'  => 'boolean',
            'label' => 'Activate Local Lender LBP',
            'value' => false,
        ),
        array(
            'key'      => 'local_lbp_steps',
            'type'     => 'string',
            'textarea' => true,
            'label'    => 'Enter Steps For Local LBP',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'key'      => 'local_lbp_data_path',
            'type'     => 'string',
            'label'    => 'Enter Local LBP Data Path',
            'value'    => '',
            'optional' => true,
            'flex'     => 100,
        ),
        array(
            'key'   => 'remote_lbp_enabled',
            'type'  => 'boolean',
            'label' => 'Activate Remote Lender LBP',
            'value' => false,
        ),
        array(
            'key'      => 'remote_lbp_steps',
            'type'     => 'string',
            'textarea' => true,
            'label'    => 'Enter Steps For Remote LBP',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'key'      => 'remote_lbp_auth_key',
            'type'     => 'string',
            'textarea' => true,
            'label'    => 'Remote LBP API KEY',
            'value'    => '',
            'optional' => true,
        ),
        array(
            'key'      => 'remote_lbp_category',
            'type'     => 'enum',
            'textarea' => true,
            'label'    => 'Remote LBP Category',
            'value'    => array('ProtectShip', 'eMagazine'),
            'optional' => true,
        ),
        
        
        array(
            'key'   => 'split_enabled',
            'type'  => 'boolean',
            'label' => 'Activate Split Logic',
            'value' => false,
        ),
        array(
            'key'      => 'split_steps',
            'type'     => 'string',
            'textarea' => true,
            'label'    => 'Enter Steps For Split',
            'value'    => '',
            'optional' => true,
        ),       
        array(
            'key'      => 'gateway_select_type',
            'type'     => 'enum',
            'textarea' => true,
            'label'    => 'Gateways select type for Split Logic',
            'value'    => array('default','filebased'),
            'optional' => true,
        ),
        array(
            'key'      => 'split_local_gateways',
            'type'     => 'string',
            'textarea' => true,
            'label'    => 'Main Step Gateways for Split Logic',
            'value'    => "",
            'optional' => true,
            'hint'=> 'Use for default type'
        ),
        array(
            'key'      => 'split_local_file_path',
            'type'     => 'string',
            'textarea' => true,
            'label'    => 'File path for Split Logic',
            'value'    => "",
            'optional' => true,
            'hint'=> 'Use Comma separated data file path for filebased type'
        ),
        array(
            'key'      => 'steps_for_trial_completion',
            'type'     => 'string',
            'textarea' => true,
            'label'    => 'Enter Steps For Trail Completion',
            'value'    => '',
            'optional' => true,
            'hint'     => 'Use Comma separated'
        ),
    ),
);
