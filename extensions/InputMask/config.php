<?php

return array(
    'hooks'    => array(
        array(
            'event'    => 'beforeRenderScripts',
            'callback' => "Extension\InputMask\InputMask@inputMaskLoadData",
            'priority' => 100,
        ),
    ),
    'custom_html' => array(
        'template_js' => 'js/customInputMask.js',
        'enable' => true,
        'template_name' => 'html/customInputMask.html'
    ),
    'settings' => array(
        array(
            'label' => 'Enable Masking',
            'key'   => 'enable_masking',
            'type'  => 'boolean',
            'value' => '',
        ),
        array(
            "label" => "Allowed Device",
            "key"   => "masking_device",
            "type"  => "multi_select",
            "hint"  => "",
            "value" => array('desktop', 'mobile'),
        ),
        
        array(
            'label' => 'Turn On Credit Card Place Holder',
            'key'   => 'credit_card_place_holder_active',
            'type'  => 'boolean',
            "value" => false,
        ),
        array(
            'label' => 'Credit Card Masking Placeholder',
            'key'   => 'credit_card_masking_placeholder',
            'type'  => 'enum',
            "value" => array('blank', 'cross'),
        ),
        array(
            'label' => 'Credit Card Masking',
            'key'   => 'credit_card_masking',
            'type'  => 'enum',
            "value" => array('no_masking', 'dash_masking', 'space_masking'),
        ),
        array(
            'label' => 'Phone Number Masking',
            'key'   => 'phone_number_masking',
            'type'  => 'string',
            'value' => '00000000000',
        ),
        array(
            'key' => 'enable_credit_Card_masking',
            'type' => 'boolean'
        )
    ),
    'scripts'  => array(
        'inputMaskLibrary' => 'js/jquery.inputmask.bundle.min.js',
        'inputMask'        => 'js/input-mask.js',
    ),
);
