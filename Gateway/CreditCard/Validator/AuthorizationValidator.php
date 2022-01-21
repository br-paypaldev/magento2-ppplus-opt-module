<?php

namespace Paypal\PaypalPlusBrasil\Gateway\CreditCard\Validator;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Paypal\PaypalPlusBrasil\Logger\Logger;

class AuthorizationValidator extends AbstractValidator
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param Logger $logger
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        Logger $logger
    ) {
        $this->logger = $logger;

        parent::__construct($resultFactory);
    }

    /**
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject)
    {
        $response = SubjectReader::readResponse($validationSubject);

        $errorMessages = [];
        $isValid = true;

        if (isset($response['response']['transactions'][0]['related_resources'][0]['sale']['state'])) {
            $state = $response['response']['transactions'][0]['related_resources'][0]['sale']['state'];
            if ($state === 'denied') {
                $isValid = false;
                $errorMessages[] = __("The payment was denied.");
            }
        }

        if (!$response['success']) {
            $isValid = false;
            $errorMessages[] = $response['msg'];
        }

        return $this->createResult($isValid, $errorMessages);
    }
}