<?php

namespace Extension\AsyncProspect;

use Application\CrmPayload;
use Application\CrmResponse;
use Application\Helper\Provider;
use Application\Request;
use Application\Response;
use Application\Session;
use Application\Http;
use Application\Config;
use Application\Model\Konnektive;
use Application\Model\Configuration;

class AsyncProspect
{

    public function __construct()
    {
        $this->currentStepId = (int) Session::get('steps.current.id');
        $this->fireOnProspect = Config::extensionsConfig('AsyncProspect.note_for_prospect');
    }

    public function captureCrmPayload()
    {
        if (
                Request::attributes()->get('action') !== 'prospect'
        )
        {
            return;
        }

        Session::set(
                'extensions.asyncProspect', array(
            'crmPayload' => CrmPayload::all(),
                )
        );

        CrmPayload::update(array(
            'meta.bypassCrmHooks' => true,
            'meta.terminateCrmRequest' => true,
        ));

        if (CrmPayload::get('meta.crmType') === 'konnektive')
        {
            $prospectId = strtoupper(uniqid());
        }
        else
        {
            $prospectId = rand(10000, 99999);
        }

        CrmResponse::replace(
                array('success' => true, 'prospectId' => $prospectId)
        );
    }

    public function createProspect()
    {
        if (Session::has('extensions.asyncProspect') !== true)
        {
            return;
        }

        CrmPayload::replace(Session::get('extensions.asyncProspect.crmPayload'));
        CrmPayload::set('meta.bypassCrmHooks', true);
        CrmPayload::set('meta.terminateCrmRequest', false);

        $crmClass = sprintf(
                '\Application\Model\%s', ucfirst(CrmPayload::get('meta.crmType'))
        );

        $crmInstance = new $crmClass(CrmPayload::get('meta.crmId'));

        call_user_func(array($crmInstance, 'prospect'));

        if (CrmResponse::has('success') && CrmResponse::get('success'))
        {
            $crmResponse = CrmResponse::all();
            foreach ($crmResponse as $key => $value)
            {
                if ($key === 'success')
                {
                    continue;
                }
                Session::set(
                        sprintf(
                                'steps.%d.%s', $this->currentStepId, $key
                        ), $value
                );
            }

            Session::set('queryParams.prospect_id', $crmResponse['prospectId']);

            if ($this->fireOnProspect && CrmPayload::get('meta.crmType') === 'konnektive')
            {
                $this->sendCustomerNote();
            }
        }

        Session::remove('extensions.asyncProspect');
        Response::send(CrmResponse::all());
    }

    public function sendCustomerNote()
    {
        try
        {
            $configId = Session::get('steps.current.configId');
            $configuration = new Configuration($configId);
            $crmId = $configuration->getCrmId();
        }
        catch (Exception $ex)
        {
            return;
        }

        $rawResponse = json_decode(Http::getResponse());

        if (!empty($rawResponse->message->customerId))
        { 
            CrmPayload::replace(
                    array(
                        'customerId' => $rawResponse->message->customerId,
                        'message' => !empty(Config::extensionsConfig('AsyncProspect.exclude_source_url')) ? sprintf(
                                        '%s', CrmPayload::get('userAgent')) : sprintf(
                                        '%s | %s', CrmPayload::get('userIsAt'), CrmPayload::get('userAgent')
                                ),
                    )
            );
          
            CrmPayload::set('meta.bypassCrmHooks', true);

            $crmInstance = new Konnektive($crmId);
            $crmInstance->addCustomerNote();
            $rawNoteResponse = json_decode(Http::getResponse());

            Session::set('asyncProspect.customerNoteResponse', $rawNoteResponse);
        }
    }

    public function injectScript()
    {
        if (Session::has('extensions.asyncProspect'))
        {
            echo Provider::asyncScript(
                    AJAX_PATH . 'extensions/asyncprospect/create-prospect'
            );
        }
    }

}
