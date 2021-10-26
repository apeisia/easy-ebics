<?php

namespace Apeisia\EasyEbics;

use AndrewSvirin\Ebics\Contracts\KeyRingManagerInterface;
use AndrewSvirin\Ebics\EbicsClient;
use AndrewSvirin\Ebics\Exceptions\EbicsException;
use AndrewSvirin\Ebics\Handlers\ResponseHandlerV2;
use AndrewSvirin\Ebics\Models\Bank;
use AndrewSvirin\Ebics\Models\KeyRing;
use AndrewSvirin\Ebics\Models\User;
use DateTime;
use Genkgo\Camt\Config;
use Genkgo\Camt\DTO\Entry;
use Genkgo\Camt\Reader;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class EasyEbics
{
    /**
     * @var KeyRingManagerInterface
     */
    private $keyRingManager;
    /**
     * @var KeyRing
     */
    public $keyRing;
    /**
     * @var EbicsClient
     */
    public $client;

    public function __construct(
        KeyRingManagerInterface $keyRingManager,
        Bank $bank,
        User $user
    )
    {
        $this->keyRingManager = $keyRingManager;
        $this->loadKeyRing();
        $this->client = new EbicsClient($bank, $user, $this->keyRing);

    }

    private function loadKeyRing()
    {
        $this->keyRing = $this->keyRingManager->loadKeyRing();
    }

    private function saveKeyRing()
    {
        $this->keyRingManager->saveKeyRing($this->keyRing);
    }

    public function getKeyRing() {
        return $this->keyRing;
    }

    /**
     * generate new keys, send the new keys to the bank, retrieve the public
     * keys of the bank and save everything in the keyring
     *
     * @return array
     * @throws EbicsException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function generateKeysAndPushToBank()
    {
        $status          = [];
        $responseHandler = new ResponseHandlerV2();

        // Send public certificate of signature A006 to the bank
        $ini           = $this->client->INI(); // INI generates new keys
        $status['INI'] = [
            'code'       => $responseHandler->retrieveH00XReturnCode($ini),
            'reportText' => $responseHandler->retrieveH00XReportText($ini),
        ];

        // Send public certificates of authentication (X002) and encryption (E002) to the bank
        $hia           = $this->client->HIA(); // HIA also generates new keys
        $status['HIA'] = [
            'code'       => $responseHandler->retrieveH00XReturnCode($hia),
            'reportText' => $responseHandler->retrieveH00XReportText($hia),
        ];


        $status['HPB'] = $this->retrieveBankPublicKeys();

        $this->saveKeyRing();

        return $status;
    }

    /**
     * @return array
     * @throws ClientExceptionInterface
     * @throws EbicsException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function retrieveBankPublicKeys() {
        $responseHandler = new ResponseHandlerV2();
        // Retrieve the Bank public certificates authentication (X002) and encryption (E002)
        $hpb = $this->client->HPB();
        $this->saveKeyRing();
        return [
            'code'       => $responseHandler->retrieveH00XReturnCode($hpb),
            'reportText' => $responseHandler->retrieveH00XReportText($hpb),
        ];
    }

    /**
     * create a instance of InitializationLetter to get all the information that you need to send an
     * initialization letter to your bank
     *
     * @return InitializationLetter
     */
    public function createInitializationLetter()
    {
        return new InitializationLetter($this->keyRing);
    }

    /**
     * Get the parsed transactions
     *
     * @param DateTime|null $dateTime
     * @param DateTime|null $startDateTime
     * @param DateTime|null $endDateTime
     * @return Entry[]|null
     * @throws ClientExceptionInterface
     * @throws EbicsException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getTransactions(DateTime $dateTime = null, DateTime $startDateTime = null, DateTime $endDateTime = null)
    {
        $sta = $this->client->C53($dateTime, $startDateTime, $endDateTime);
        if (!$sta) return null;

        $allRecords = [];

        $t = $sta->getTransactions();
        foreach($t as $transaction) {
            foreach($transaction->getOrderDataItems() as $item) {
                $reader = new Reader(Config::getDefault());
                $message = $reader->readString($item->getContent());
                foreach ($message->getRecords() as $record) {
                    $allRecords[] = $record;
                }
            }
        }

        return $allRecords;
    }
}
