<?php

use Application\Config;
use Application\Controller\ConfigsController;
use Application\Extension;
use Application\Lang;
use Application\Model\Pixel;
use Application\Resource;
use Application\Session;

function perfom_head_tag_close_actions()
{
    $pixel = new Pixel();
    echo $pixel->getHeadPixelsAsHtml();
    $extension = Extension::getInstance();
    $extension->performEventActions('beforeHeadTagClose');
}

function perform_body_tag_open_actions()
{
    echo App::getDelayPixels();
    $pixel = new Pixel();
    echo $pixel->getTopPixelsAsHtml();
    $extension = Extension::getInstance();
    $extension->performEventActions('afterBodyTagOpen');
}

function perform_body_tag_close_actions()
{
    $pixel = new Pixel();
    echo $pixel->getBottomPixelsAsHtml();
    $configsController = new ConfigsController();
    $allConfig         = $configsController->oldConfig();
    if (!is_array($allConfig)) {
        $allConfig = array();
    }
    if (!empty($allConfig['allowed_tc']) && is_array($allConfig['allowed_tc'])) {
        $key                     = '8rmjlVdHJq5hkk5ROXlN';
        $allConfig['allowed_tc'] = encrypt_allowed_tc(
            json_encode($allConfig['allowed_tc']), $key
        );
    }
    echo sprintf(
        '<script type="text/javascript">AJAX_PATH="%s"; app_config=%s</script>',
        AJAX_PATH, json_encode($allConfig)
    );
    $allLang = Lang::get();
    $lang    = array();
    if (is_array($allLang)) {
        $required_keys = array('error_messages', 'exceptions');
        foreach ($required_keys as $key) {
            $lang[$key] = $allLang[$key];
        }
    }
    echo sprintf(
        '<script type="text/javascript">app_lang=%s;</script>', json_encode($lang)
    );
    $extension = Extension::getInstance();
    $extension->performEventActions('beforeRenderScripts');
    echo Resource::getAllAsHtml('script');
    $extension->performEventActions('beforeBodyTagClose');
}

function encrypt_allowed_tc($plainText, $keyString)
{
    $keyStringParts      = str_split($keyString);
    $plainTextParts      = str_split($plainText);
    $count               = $flag               = 0;
    $plainTextPartsLen   = count($plainTextParts);
    $chipherTextPartsLen = 2 * $plainTextPartsLen;
    $chipherTextParts    = array_fill(0, $chipherTextPartsLen, 'x');
    $keyStringPartsLen   = count($keyStringParts);
    for ($ii = 0; $ii < $plainTextPartsLen; $ii++) {
        if ($flag) {
            $chipherTextParts[$ii] = $plainTextParts[$ii];
            $chipherTextParts[
                $ii + $plainTextPartsLen
            ] = $keyStringParts[$count];
        } else {
            $chipherTextParts[$ii] = $keyStringParts[$count];
            $chipherTextParts[
                $ii + $plainTextPartsLen
            ] = $plainTextParts[$ii];
        }
        $flag  = 1 - $flag;
        $count = ($count + 1) % $keyStringPartsLen;
    }
    return implode('', $chipherTextParts);
}

function get_exit_pop_url($step = 'step1', $downsellNumber = 1)
{
    $currentConfigId = (int) Session::get('steps.current.configId');
    $url             = Config::configurations(
        sprintf('%d.exit_popup_page', $currentConfigId)
    );

    $queryParams = Session::get('queryParams', array());
    if (!empty($url) && !empty($queryParams)) {
        $url = sprintf('%s?%s', $url, http_build_query($queryParams));
    }

    return $url;
}

function get_no_thank_you_link()
{
    $queryParams = Session::get('queryParams', array());
    $nextPage    = Session::get('steps.next.link', '');
    if (stripos(strrev($nextPage), 'php.') !== 0) {
        $nextPage .= '.php';
    }
    if (!empty($nextPage) && !empty($queryParams)) {
        $nextPage = sprintf(
            '%s?%s', $nextPage, http_build_query(
                Session::get('queryParams')
            )
        );
    }
    return $nextPage;
}

function get_years()
{
    $year    = date('Y');
    $options = '<option value="">Year</option>';
    for ($i = $year; $i < $year + 20; $i++) {
        $options .= sprintf('<option value="%s">%s</option>', substr($i, 2), $i);
    }
    echo $options;
}

function get_months()
{

    $months = array(
        '01' => 'January',
        '02' => 'February',
        '03' => 'March',
        '04' => 'April',
        '05' => 'May',
        '06' => 'June',
        '07' => 'July',
        '08' => 'August',
        '09' => 'September',
        '10' => 'October',
        '11' => 'November',
        '12' => 'December',
    );

    $options = '<option value="">Month</option>';
    foreach ($months as $key => $value) {
        $options .= sprintf('<option value="%s">%s</option>', $key, "($key) " . $value);
    }

    echo $options;
}

function get_meta_details($type = 'site_title', $step = 1)
{
    $currentConfigId = (int) Session::get('steps.current.configId');
    return Config::configurations(sprintf('%d.%s', $currentConfigId, $type));
}

function make_query_string($return = false, $qs = '')
{
    $query_string = '';
    $query_params = Session::get('queryParams', array());
    if (!empty($query_params) && is_array($query_params)) {
        $query_string = sprintf('?%s', http_build_query($query_params));
    }
    if (!empty($qs)) {
        $query_string = empty($query_string) ? '?' : $query_string . '&';
        $query_string .= $qs;
    }
    if ($return) {
        return $query_string;
    }
    echo $query_string;
}

function is_user_country_matched($continentCode = false)
{

    $country_codes = array(
        'EU' => array(
            'AT', 'BE', 'BG', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT',
            'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'GB',
        )
    );

    if (empty($country_codes[$continentCode])) {
        return false;
    }

    if (!empty($_SERVER['HTTP_CF_IPCOUNTRY']) && in_array($_SERVER['HTTP_CF_IPCOUNTRY'], $country_codes[$continentCode])) {
        return true;
    }
    return false;
}
