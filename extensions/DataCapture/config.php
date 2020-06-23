<?php

return array(
'enable' => true,
    'custom_html' => array(
        'template_js' => 'js/datacapture.js',
        'enable' => true,
        'template_name' => 'html/datacapture.html'
    ),
   'hooks'    => array(
        array(
            'event'    => 'beforeAnyCrmRequest',
            'callback' => "Extension\\DataCapture\\DataCapture@captureCrmPayload",
            'priority' => 500,
        ),
        array(
            'event'    => 'afterAnyCrmRequest',
            'callback' => "Extension\\DataCapture\\DataCapture@syncDeclineData",
            'priority' => 100,
        ),
        array(
            'event'    => 'beforeBodyTagClose',
            'callback' => "Extension\\DataCapture\\DataCapture@injectScript",
            'priority' => 100,
        ),
        array(
            'event'    => 'afterAnyCrmRequest',
            'callback' => "Extension\\DataCapture\\DataCapture@syncLocalData",
            'priority' => 99,
        )
    ),
    'settings' => array(
        array(
            'label' => 'Enable capture for decline orders',
            'key'   => 'enable_capture_for_decline',
            'type'  => 'boolean',
            'value' => '',
            'flex'  => 100
        ),
        array(
            'label' => 'Exclude Decline Reasons',
            'key'   => 'exlude_decline_reasons',
            'type'  => 'string',
            'textarea' => true,
            'value' => '',
            'optional' => true,
            'flex'  => 100,
            'hint'  => 'Add multiple declined reasons in new line.'
        ),
        array(
            'label' => 'Enable local capture',
            'key'   => 'enable_local_capture',
            'type'  => 'boolean',
            'value' => '',
        ),
    ),
    'routes'   => array(
        array(
            'slug'     => 'sync-info',
            'callback' => "Extension\\DataCapture\\DataCapture@syncData",
        ),
    ),  
    'actions'  => array(
        'activate'   => "Extension\\DataCapture\\DataCapture@activate",
        'deactivate' => '',
        'save'       => "Extension\\DataCapture\\DataCapture@checkDbCredentials",
    ),
    'crons'    => array(
        array(
            'every'   => '*/5 * * * *',
            'handler' => 'Extension\DataCapture\Crons@clrPayload',
            'overlap' => false,
        ),
        array(
            'every'   => '* * * * *',
            'handler' => 'Extension\DataCapture\Crons@postData',
            'overlap' => false,
        ),
    ),
);