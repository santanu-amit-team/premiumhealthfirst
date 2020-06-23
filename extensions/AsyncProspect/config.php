<?php

return array(
    'custom_html' => array(
        'template_js' => 'js/asyncprospet_custom.js',
        'enable' => true,
        'template_name' => 'html/ayncprospect_custom.html'
    ),
    'hooks' => array(
        array(
            'event' => 'beforeAnyCrmRequest',
            'callback' => "Extension\\AsyncProspect\\AsyncProspect@captureCrmPayload",
            'priority' => 100,
        ),
        array(
            'event' => 'beforeBodyTagClose',
            'callback' => "Extension\\AsyncProspect\\AsyncProspect@injectScript",
            'priority' => 100,
        ),
    ),
    'routes' => array(
        array(
            'slug' => 'create-prospect',
            'callback' => "Extension\\AsyncProspect\\AsyncProspect@createProspect",
        ),
    ),
    'settings' => array(
        array(
            'label' => 'Allow note for prospect (Konnektive)',
            'key' => 'note_for_prospect',
            'type' => 'boolean',
            'value' => false,
        ),
    ),
);
