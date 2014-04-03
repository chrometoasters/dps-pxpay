<?php

    /**
     * DPS PxPay 2.0 cURL API
     *
     * cURL implementation of the DPS PxPay 2.0 API
     *
     * @category
     * @package     xplore-dps-pxpay
     * @version     1.0.0
     * @copyright   2014 Xplore Net Solutions
     * @license     http://opensource.org/licenses/MIT MIT License
     * @author      Matt Dwen @mattdwen
     * @link        http://www.paymentexpress.com/Downloads/DPSECOM_PXPay_2_0_IntegrationGuide.pdf
     */

    namespace pxpay;

    /**
     * PxPay client library
     *
     * Instantiate a new instance of the client library by passing it your DPS UserId and Key:
     *
     * <code>
     * $pxPay = new \pxpay\PxPay($userId, $key);
     * </code>
     *
     * Create a TransactionRequest and send to DPS:
     *
     * <code>
     * $request = new \pxpay\TransactionRequest();
     * // set transaction properties
     * $pxPay->SendRequest($request);
     * </code>
     *
     * Check for a response from DPS:
     *
     * <code>
     * $response = $pxPay->ProcessResponse();
     * if (!is_null($response)) {
     *   // Handle response
     * }
     * </code>
     *
     * @package xplore-dps-pxpay
     */
    class PxPay {

        #region Constructor

        /**
         * Create a new instance of the PxPay client
         *
         * Your userId and key and optionally be provided
         *
         * @param string $userId
         * @param string $key
         */
        public function __construct($userId, $key) {
            $this->userId = $userId;
            $this->key = $key;
        }

        #endregion Constructor

        #region Constants

        const PX_PAY_URL = 'https://sec.paymentexpress.com/pxaccess/pxpay.aspx';

        #endregion Constants

        #region Declarations

        /**
         * DPS PxPay Key
         * @var string
         */
        private $key = null;

        /**
         * DPS PxPay UserId
         * @var string
         */
        private $userId = null;

        #endregion Declarations

        #region Private Methods

        /**
         * Send the xml message to the given url
         *
         * @param string $message
         * @param string $url
         * @return string
         */
        private function Curl_Send($message, $url) {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

            $output = curl_exec($ch);

            curl_close($ch);

            return $output;
        }

        #endregion Private Methods

        #region Public Methods

        /**
         * Send the request to DPS and redirect to the payment page
         *
         * @param TransactionRequest $request
         * @throws RequestException if any request parameters are missing or invalid
         */
        public function SendRequest(TransactionRequest $request) {
            $request
                ->SetUserId($this->userId)
                ->SetKey($this->key);

            $xml = $request->ToXml();

            $output = $this->Curl_Send($xml, $this::PX_PAY_URL);
            $response = new TransactionResponse($output);
            $url = $response->GetUrl();

            header('Location: ' . $url);
        }

        /**
         * Check for a response from DPS and return the response object
         *
         * @return Response Result of the request
         * @return null If there is no response available
         * @throws ResultException
         */
        public function ProcessResponse() {
            if (empty($this->userId)) throw new ResultException('UserId is not set');

            $resultString = trim($_GET['result']);
            if (empty($resultString)) {
                // No result to process
                return null;
            }

            $resultRequest = new ResponseRequest();
            $resultRequest
                ->SetUserId($this->userId)
                ->SetKey($this->key)
                ->SetResponse($resultString);
            $xml = $resultRequest->ToXml();

            $output = $this->Curl_Send($xml, $this::PX_PAY_URL);
            $result = new Response($output);

            return $result;
        }

        #endregion Public Methods
    }

    /**
     * Transaction request message to send to DPS
     *
     * This results in a URL for the payment page if the request is valid
     *
     * @package xplore-dps-pxpay
     */
    class TransactionRequest {

        #region Declarations

        private $PxPayUserId;
        private $PxPayKey;
        private $AmountInput;
        private $BillingId;
        private $CurrencyInput;
        private $EmailAddress;
        private $EnableAddBillCard;
        private $MerchantReference;
        private $DpsBillingId;
        private $TxnData1;
        private $TxnData2;
        private $TxnData3;
        private $TxnType;
        private $TxnId;
        private $UrlFail;
        private $UrlSuccess;
        private $Opt;

        #endregion Declarations

        #region Private Methods

        /**
         * Ensure the message is valid
         *
         * @throws RequestException
         */
        private function Validate() {
            // Required fields
            if (empty($this->PxPayUserId)) throw new RequestException('PxPayUserId is missing');
            if (empty($this->PxPayKey)) throw new RequestException('PxPayKey is missing');
            if (empty($this->AmountInput)) throw new RequestException('AmountInput is missing');
            if (empty($this->CurrencyInput)) throw new RequestException('CurrencyInput is missing');
            if (empty($this->TxnType)) throw new RequestException('TxnType is missing');
            if (empty($this->UrlFail)) throw new RequestException('UrlFail is missing');
            if (empty($this->UrlSuccess)) throw new RequestException('UrlSuccess is missing');

            // Field length
            $lengths = array(
                'PxPayUserId' => 32,
                'PxPayKey' => 64,
                'AmountInput' => 13,
                'BillingId' => 32,
                'CurrencyInput' => 4,
                'EmailAddress' => 255,
                'EnableAddBillCard' => 1,
                'MerchantReference' => 64,
                'DpsBillingId' => 16,
                'TxnData1' => 255,
                'TxnData2' => 255,
                'TxnData3' => 255,
                'TxnType' => 8,
                'TxnId' => 16,
                'UrlFail' => 255,
                'UrlSuccess' => 255,
                'Opts' => 64
            );
            foreach ($lengths as $field => $length) {
                if (!isset($this->{$field})) {
                    continue;
                }

                if (strlen($this->{$field}) > $length) {
                    throw new RequestException($field . ' cannot be more than ' . $ $length . ' characters');
                }
            }

            // Additional validation
            if (!is_numeric($this->AmountInput)) throw new RequestException('Amount input is not a valid number: ' . $this->AmountInput);
            if (!in_array($this->CurrencyInput, Helpers::$Currencies)) throw new RequestException('InputCurrency is not a valid currency');
            if (!in_array($this->TxnType, Helpers::$TransactionTypes)) throw new RequestException('TxnType is not a valid transaction type');
        }

        #endregion Private Methods

        #region Public Methods

        /**
         * Set the total amount to be charged for the transaction
         *
         * Numeric formats will be converted to d.cc format
         *
         * @param float $amount Total of the transaction
         * @return $this
         */
        public function SetAmount($amount) {
            $this->AmountInput = number_format($amount, 2, '.', '');
            return $this;
        }

        /**
         * Set BillingId to be used with token billing transactions
         *
         * This is an identifier to be used to identify a customer or billing entry, and can be
         * used instead of a card number and expiry date for subsequent billing transactions.
         *
         * @param string $billingId
         * @return $this
         */
        public function SetBillingId($billingId) {
            $this->BillingId = $billingId;
            return $this;
        }

        /**
         * Set the currency to bill the transaction in
         *
         * @see DPS_CURRENCY
         * @param string $currency
         * @return $this
         */
        public function SetCurrencyInput($currency) {
            $this->CurrencyInput = $currency;
            return $this;
        }

        /**
         * Previously generated DPS Billing ID to make future transactions against a known card
         *
         * @param string $dpsBillingId
         * @return $this
         */
        public function SetDpsBillingId($dpsBillingId) {
            $this->DpsBillingId = $dpsBillingId;
            return $this;
        }

        /**
         * Optional field to store the users email address
         *
         * Returned as part of the transaction response.
         *
         * @param string $emailAddress
         * @return $this
         */
        public function SetEmailAddress($emailAddress) {
            $this->EmailAddress = $emailAddress;
            return $this;
        }

        /**
         * Enable subsequent billing against the users card
         *
         * A DpsBillingId (DPS generated) or BillingId (merchant generated) is used to reference the card.
         *
         * @param bool $enableAddBillCard
         * @return $this
         */
        public function SetEnableAddBillCard($enableAddBillCard) {
            $this->EnableAddBillCard = $enableAddBillCard;
            return $this;
        }

        /**
         * Set PxPayKey
         *
         * @param string $key
         * @return $this
         */
        public function SetKey($key) {
            $this->PxPayKey = $key;
            return $this;
        }

        /**
         * Merchant generated reference to find and identity the transaction in Payline and DPS reports
         *
         * @param string $reference
         * @return $this
         */
        public function SetMerchantReference($reference) {
            $this->MerchantReference = $reference;
            return $this;
        }

        /**
         * Free field returned with the response to store data such as a users phone number
         *
         * @param string $data
         * @return $this
         */
        public function SetTransactionData1($data) {
            $this->TxnData1 = $data;
            return $this;
        }

        /**
         * Free field returned with the response to store data such as a users phone number
         *
         * @param string $data
         * @return $this
         */
        public function SetTransactionData2($data) {
            $this->TxnData2 = $data;
            return $this;
        }

        /**
         * Free field returned with the response to store data such as a users phone number
         *
         * @param string $data
         * @return $this
         */
        public function SetTransactionData3($data) {
            $this->TxnData3 = $data;
            return $this;
        }

        /**
         * Unique merchant generated code to uniquely identify the transaction
         *
         * Duplicate Transaction IDs sent within 48 hours will be returned as **Approved** but not
         * charged again to the user.
         *
         * @param string $transactionId
         * @return $this
         */
        public function SetTransactionId($transactionId) {
            $this->TxnId = $transactionId;
            return $this;
        }

        /**
         * Set TxnType
         *
         * @see DPS_TRANSACTION_TYPES
         * @param string $transactionType
         * @return $this
         */
        public function SetTransactionType($transactionType) {
            $this->TxnType = $transactionType;
            return $this;
        }

        /**
         * Set UrlFail
         *
         * @param string $url absolute URL to return to on request failure
         * @return $this
         */
        public function SetUrlFail($url) {
            $this->UrlFail = $url;
            return $this;
        }

        /**
         * Set UrlSuccess
         *
         * @param string $url Absolute URL to return to on request success
         * @return $this
         */
        public function SetUrlSuccess($url) {
            $this->UrlSuccess = $url;
            return $this;
        }

        /**
         * Set PxPayUserId
         *
         * @param string $userId
         * @return $this
         */
        public function SetUserId($userId) {
            $this->PxPayUserId = $userId;
            return $this;
        }

        /**
         * Convert the request to xml
         *
         * @return string
         * @throws RequestException
         */
        public function ToXml() {
            $this->Validate();
            $values = get_object_vars($this);

            $xml = '<GenerateRequest>';


            while (list($key, $val) = each($values)) {
                if (!empty($val)) {
                    $xml .= "<$key>$val</$key>";
                }
            }
            $xml .= '</GenerateRequest>';

            return $xml;
        }

        #endregion Public Methods

    }

    /**
     * Response message from a TransactionRequest
     *
     * @package xplore-dps-pxpay
     */
    class TransactionResponse {

        #region Constructor

        /**
         * @param string $responseXml
         */
        public function __construct($responseXml) {
            $xml = new XmlMessage($responseXml);

            $valid = (bool)$xml->GetAttribute('Request', 'valid');
            if ($valid !== true) {
                throw new RequestException('Request is no valid');
            }

            $this->url = $xml->GetValue('URI');

            if (empty($this->url)) {
                $responseCode = $xml->GetValue('Reco');
                $message = Helpers::ResponseMessage($responseCode);
                throw new RequestException($message);
            }
        }

        #endregion Constructor

        #region Declarations

        /**
         * URL to direct the user to for payment details
         * @var null
         */
        private $url;

        #endregion Declarations

        #region Public Methods

        /**
         * Return the URL to direct the user to for payment details
         *
         * @return string
         */
        public function GetUrl() {
            return $this->url;
        }

        #endregion Public Methods
    }

    /**
     * Result request message to send to DPS for decryption
     *
     * @package xplore-dps-pxpay
     */
    class ResponseRequest {

        #region Declarations

        public $PxPayUserId;
        public $PxPayKey;
        public $Response;

        #endregion Declarations

        #region Private Methods

        private function Validate() {
            // Required fields
            if (empty($this->PxPayUserId)) throw new RequestException('PxPayUserId is missing');
            if (empty($this->PxPayKey)) throw new RequestException('PxPayKey is missing');
            if (empty($this->Response)) throw new RequestException('Response is missing');

            // Field length
            $lengths = array(
                'PxPayUserId' => 32,
                'PxPayKey' => 64
            );
            foreach ($lengths as $field => $length) {
                if (!isset($this->{$field})) {
                    continue;
                }

                if (strlen($this->{$field}) > $length) {
                    throw new RequestException($field . ' cannot be more than ' . $ $length . ' characters');
                }
            }
        }

        #endregion Private Methods

        #region Public Methods

        /**
         * Set PxPayKey
         *
         * @param string $key
         * @return $this
         */
        public function SetKey($key) {
            $this->PxPayKey = $key;
            return $this;
        }

        /**
         * Set Response
         *
         * @param string $response
         * @return $this
         */
        public function SetResponse($response) {
            $this->Response = $response;
            return $this;
        }

        /**
         * Set PxPayUserId
         *
         * @param string $userId
         * @return $this
         */
        public function SetUserId($userId) {
            $this->PxPayUserId = $userId;
            return $this;
        }

        /**
         * Convert the request to xml
         *
         * @return string
         * @throws ResultException
         */
        public function ToXml() {
            $this->Validate();

            $values = get_object_vars($this);

            $xml = '<ProcessResponse>';


            while (list($key, $val) = each($values)) {
                if (!empty($val)) {
                    $xml .= "<$key>$val</$key>";
                }
            }
            $xml .= '</ProcessResponse>';

            return $xml;
        }

        #endregion Public Methods
    }

    /**
     * Response object
     *
     * @package xplore-dps-pxpay
     */
    class Response {

        #region Constructor

        /**
         * Create a new response object from the returned XML
         *
         * @param string $responseXml
         */
        public function __construct($responseXml) {
            $xml = new XmlMessage($responseXml);
            $this->isValid = (bool)$xml->GetAttribute('Response', 'valid');
            $this->amountSettlement = $xml->GetValue('AmountSettlement');
            $this->authCode = $xml->GetValue('AuthCode');
            $this->cardName = $xml->GetValue('CardName');
            $this->cardNumber = $xml->GetValue('CardNumber');
            $this->dateExpiry = $xml->GetValue('DateExpiry');
            $this->dpsTxnRef = $xml->GetValue('DpsTxnRef');
            $this->success = (bool)$xml->GetValue('Success');
            $this->responseText = $xml->GetValue('ResponseText');
            $this->dpsBillingId = $xml->GetValue('DpsBillingId');
            $this->cardHolderName = $xml->GetValue('CardHolderName');
            $this->currencySettlement = $xml->GetValue('CurrencySettlement');
            $this->txnData1 = $xml->GetValue('TxnData1');
            $this->txnData2 = $xml->GetValue('TxnData2');
            $this->txnData3 = $xml->GetValue('TxnData3');
            $this->txnType = $xml->GetValue('TxnType');
            $this->currencyInput = $xml->GetValue('CurrencyInput');
            $this->merchantReference = $xml->GetValue('MerchantReference');
            $this->clientInfo = $xml->GetValue('ClientInfo');
            $this->txnId = $xml->GetValue('TxnId');
            $this->emailAddress = $xml->GetValue('emailAddress');
            $this->billingId = $xml->GetValue('BillingId');
            $this->txnMac = $xml->GetValue('TxnMac');
            $this->cardNumber2 = $xml->GetValue('CardNumber2');
            $this->cvs2ResultCode = $xml->GetValue('Cvs2ResultCode');
        }

        #endregion Constructor

        #region Declarations

        private $isValid = false;

        private $amountSettlement;
        private $authCode;
        private $cardName;
        private $cardNumber;
        private $dateExpiry;
        private $dpsTxnRef;
        private $success;
        private $responseText;
        private $dpsBillingId;
        private $cardHolderName;
        private $currencySettlement;
        private $txnData1;
        private $txnData2;
        private $txnData3;
        private $txnType;
        private $currencyInput;
        private $merchantReference;
        private $clientInfo;
        private $txnId;
        private $emailAddress;
        private $billingId;
        private $txnMac;
        private $cardNumber2;
        private $cvs2ResultCode;

        #endregion Declarations

        #region Public Methods

        /**
         * The amount of funds settled in the transaction
         *
         * @return float|null
         */
        public function AmountSettled() {
            return floatval($this->amountSettlement);
        }

        /**
         * Authorisation code returned for approved transactions
         *
         * @return string
         */
        public function AuthCode() {
            return $this->authCode;
        }

        /**
         * Merchant supplied billing id if token billing enabled
         *
         * @return string
         */
        public function BillingId() {
            return $this->billingId;
        }

        /**
         * Card type used in the transaction, e.g. Visa
         *
         * @return string
         */
        public function CardName() {
            return $this->cardName;
        }

        /**
         * Name as it appears on the customers card
         *
         * @return string
         */
        public function CardHolderName() {
            return $this->cardHolderName;
        }

        /**
         * Users card number used for the transaction
         *
         * @return string
         */
        public function CardNumber() {
            return $this->cardNumber;
        }

        /**
         * A token generated by DPS for recurring billing
         *
         * @return string
         */
        public function CardNumber2() {
            return $this->cardNumber2;
        }

        /**
         * IP address of the user who processed the transaction
         *
         * @return string
         */
        public function ClientInfo() {
            return $this->clientInfo;
        }

        /**
         * Currency the transaction was settled in
         *
         * @return string
         */
        public function CurrencySettlement() {
            return $this->currencySettlement;
        }

        /**
         * Result of a CVC validation
         *
         * - M: CVC Matched
         * - N: CVC did not match
         * - P: CVC request not processed
         * - S: CVC should be on the card but merchant has indicated there is no CVC
         * - U: Issuer does not support CVC
         *
         * @return string
         */
        public function Cvs2ResultCode() {
            return $this->cvs2ResultCode;
        }

        /**
         * Expiry date of the card used for the transaction in the format MMYY
         *
         * @return string
         */
        public function DateExpiry() {
            return $this->dateExpiry;
        }

        /**
         * Payment Express generated BillingId for token billing requests
         *
         * Only returned for transactions when EnableAddBillCard is set
         *
         * @return string
         */
        public function DpsBillingId() {
            return $this->dpsBillingId;
        }

        /**
         * Unique DPS code for the transaction which can be used for refunds
         *
         * @return string
         */
        public function DpsTxnRef() {
            return $this->dpsTxnRef;
        }

        /**
         * Email address of the user
         *
         * @return string
         */
        public function EmailAddress() {
            return $this->emailAddress;
        }

        /**
         * Indicates if the initial request was valid or not
         *
         * @return bool
         */
        public function IsValid() {
            return $this->isValid;
        }

        /**
         * Merchant supplied reference for the transaction
         *
         * @return string
         */
        public function MerchantReference() {
            return $this->merchantReference;
        }

        /**
         * Response message associated with the response code of the transaction
         *
         * @return string
         */
        public function ResponseText() {
            return $this->responseText;
        }

        /**
         * Merchant supplied transaction data
         *
         * @return string
         */
        public function TransactionData1() {
            return $this->txnData1;
        }

        /**
         * Merchant supplied transaction data
         *
         * @return string
         */
        public function TransactionData2() {
            return $this->txnData2;
        }

        /**
         * Merchant supplied transaction data
         *
         * @return string
         */
        public function TransactionData3() {
            return $this->txnData3;
        }

        /**
         * Unique merchant supplied transaction identifier used for matching responses
         *
         * DPS check this code for 48 hours to check for duplicate transaction requests
         *
         * @return string
         */
        public function TransactionId() {
            return $this->txnId;
        }

        /**
         * Indication of the uniqueness of a card number
         *
         * @return string
         */
        public function TransactionMac() {
            return $this->txnMac;
        }

        /**
         * Purchase or Auth
         *
         * @return string
         */
        public function TransactionType() {
            return $this->txnType;
        }

        /**
         * Indicates success or failure of the transaction
         *
         * @return bool
         */
        public function WasSuccessful() {
            return $this->success;
        }

        #endregion Public Methods
    }


    /**
     * XML translation class
     *
     * @package xplore-dps-pxpay
     */
    class XmlMessage {

        #region Constructor

        /**
         * Create a new XML message from an xml string
         *
         * @param string $xml
         */
        public function __construct($xml) {
            $parser = xml_parser_create();
            xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
            $ok = xml_parse_into_struct($parser, $xml, $values, $index);
            xml_parser_free($parser);

            if ($ok) {
                $this->xml = $xml;
                $this->values = $values;
                $this->index = $index;
            }
        }

        #endregion Constructor

        #region Private Methods

        /**
         * Return the index of a given element
         *
         * @param string $element The name of the element
         * @param int $rootIndex The index of another element to check below
         */
        private function GetIndex($element, $rootIndex = 0) {
            $pos = strpos($element, '/');

            if ($pos !== false) {
                $startPath = substr($element, 0, $pos);
                $remainPath = substr($element, $pos + 1);
                $index = $this->GetIndex($startPath, $rootIndex);

                if ($index == 0) {
                    return 0;
                }

                return $this->GetIndex($remainPath, $index);
            } else {
                $level = $this->values[$rootIndex]['level'];

                if ($this->values[$rootIndex]['type'] == 'complete') {
                    return 0;
                }

                $index = $rootIndex + 1;

                while ($index < count($this->values) &&
                        !($this->values[$index]['level'] == $level &&
                          $this->values[$index]['type'] == 'close')) {
                    if ($this->values[$index]['level'] == $level + 1 && $this->values[$index]['tag'] == $element) {
                        return $index;
                    }

                    $index++;
                }

                return 0;
            }
        }

        #endregion Private Methods

        #region Public Methods

        /**
         * Return the value of a given element
         *
         * Separate the names of nested elements with a '/', e.g. "First/Second/Third".
         *
         * @param string $element The name of the element
         * @return string|null
         */
        public function GetValue($element) {
            $index = $this->GetIndex($element, 0);

            if ($index == 0) {
                return null;
            }

            $element = $this->values[$index];

            if (!array_key_exists('value', $element)) {
                return null;
            }

            return $this->values[$index]['value'];
        }

        public function GetAttribute($element, $key) {
            $index = $this->GetIndex($element, 0);

            $object = $this->values[$index];

            if (!array_key_exists('attributes', $object)) return null;
            if (!array_key_exists($key, $object['attributes'])) return null;

            return $object['attributes'][$key];
        }

        #endregion Public Methods
    }

    /**
     * Exception generated by invalid requests
     *
     * @package xplore-dps-pxpay
     */
    class RequestException extends \Exception {
    }

    /**
     * Exception generated by invalid results
     * @package xplore-dps-pxpay
     */
    class ResultException extends \Exception {
    }

    /**
     * Helper methods and values
     *
     * @package xplore-dps-pxpay
     */
    class Helpers {

        /**
         * Array of valid currencies
         *
         * @var array(string)
         */
        public static $Currencies = array(
            'AUD',
            'BND',
            'CAD',
            'CHF',
            'EUR',
            'FJD',
            'FRH',
            'GBP',
            'HKD',
            'INR',
            'JPY',
            'KWD',
            'MYR',
            'NZD',
            'PGK',
            'SBD',
            'SGB',
            'THB',
            'TOP',
            'USD',
            'VUV',
            'WST',
            'ZAR'
        );

        /**
         * Return error message for a given response code
         *
         * @param string $responseCode
         * @return string
         * @throws RequestException
         */
        public static function ResponseMessage($responseCode) {
            $errors = array(
                'IC' => 'Invalid Key or Username. Also check that if a TxnId is being supplied that it is unique.',
                'ID' => 'Invalid transaction type. Ensure that the transaction type is either Auth or Purchase.',
                'IK' => 'Invalid UrlSuccess. Ensure that the URL being supplied does not contain a query string.',
                'IL' => 'Invalid UrlFail. Ensure that the URL being supplied does not contain a query string.',
                'IM' => 'Invalid PxPayUserId.',
                'IN' => 'Blank PxPayUserId.',
                'IP' => 'Invalid parameter. Ensure that only documented properties are being supplied.',
                'IQ' => 'Invalid TxnType. Ensure the transaction type being submitted is either "Auth" or "Purchase".',
                'IT' => 'Invalid currency. Ensure that the CurrencyInput is correct and in the correct format e.g. "USD".',
                'IU' => 'Invalid AmountInput. Ensure that the amount is in the correct format e.g. "1.80".',
                'NF' => 'Invalid Username.',
                'NK' => 'Request not found. Check the key and the mcrypt library if in use.',
                'NL' => 'User not enabled. Contact DPS.',
                'NM' => 'User not enabled. Contact DPS.',
                'NN' => 'Invalid MAC.',
                'NO' => 'Request contains non ASCII characters.',
                'NP' => 'Closing Request tag not found.',
                'NQ' => 'User not enabled for PxPay 2.0. Contact DPS.',
                'NT' => 'Key is not 64 characters.',
                'W4' => 'Duplicate transaction'
            );

            if (array_key_exists($responseCode, $errors)) {
                return $errors[$responseCode];
            }

            throw new RequestException('Unknown response code: ' . $responseCode);
        }

        /**
         * Array of possible TxnType values
         * @var array(string)
         */
        public static $TransactionTypes = array(
            'Auth',
            'Purchase'
        );
    }

    /**
     * Available Currencies
     *
     * @package xplore-dps-pxpay
     */
    abstract class DPS_CURRENCY {
        const AustralianDollar = 'AUD';
        const NewZealandDollar = 'NZD';
    }

    /**
     * Available Transaction Types
     *
     * @package xplore-dps-pxpay
     */
    abstract class DPS_TRANSACTION_TYPES {
        const Auth = 'Auth';
        const Purchase = 'Purchase';
    }
