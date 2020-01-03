<?php

namespace Apeisia\EasyEbics;

use AndrewSvirin\Ebics\Contracts\KeyRingManagerInterface;
use AndrewSvirin\Ebics\EbicsClient;
use AndrewSvirin\Ebics\Exceptions\EbicsException;
use AndrewSvirin\Ebics\Handlers\ResponseHandler;
use AndrewSvirin\Ebics\Models\Bank;
use AndrewSvirin\Ebics\Models\KeyRing;
use AndrewSvirin\Ebics\Models\User;
use DateTime;
use Kingsquare\Parser\Banking\Mt940;
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
    private $keyRing;
    /**
     * @var EbicsClient
     */
    private $client;

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
        $responseHandler = new ResponseHandler();

        // Send public certificate of signature A006 to the bank
        $ini           = $this->client->INI(); // INI generates new keys
        $status['INI'] = [
            'code'       => $responseHandler->retrieveH004ReturnCode($ini),
            'reportText' => $responseHandler->retrieveH004ReportText($ini),
        ];

        // Send public certificates of authentication (X002) and encryption (E002) to the bank
        $hia           = $this->client->HIA(); // HIA also generates new keys
        $status['HIA'] = [
            'code'       => $responseHandler->retrieveH004ReturnCode($hia),
            'reportText' => $responseHandler->retrieveH004ReportText($hia),
        ];

        // Retrieve the Bank public certificates authentication (X002) and encryption (E002)
        $hpb = $this->client->HPB();

        $status['HPB'] = [
            'code'       => $responseHandler->retrieveH004ReturnCode($hpb),
            'reportText' => $responseHandler->retrieveH004ReportText($hpb),
        ];

        $this->saveKeyRing();

        return $status;
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
     * @return Statement[]|null
     * @throws ClientExceptionInterface
     * @throws EbicsException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getTransactions(DateTime $dateTime = null, DateTime $startDateTime = null, DateTime $endDateTime = null)
    {
        $sta = $this->client->STA($dateTime, $startDateTime, $endDateTime);
        if (!$sta) return null;

        $data = $sta->getDecryptedOrderData()->getOrderData();

        $parser     = new Mt940();
        $statements = $parser->parse($data);

        // change the classes from Kingsquare\Banking\* to Apeisia\EasyEbics\*
        $ser = serialize($statements);
        $ser = strtr($ser, [
            'O:30:"Kingsquare\Banking\Transaction"' => 'O:' . strlen(Transaction::class) . ':"' . Transaction::class . '"',
            'O:28:"Kingsquare\Banking\Statement"'   => 'O:' . strlen(Statement::class) . ':"' . Statement::class . '"',
        ]);
        /** @var Statement[] $statements */
        $statements = unserialize($ser);
        foreach ($statements as $statement) {
            foreach ($statement->getTransactions() as $transaction) {
                $transaction->setStructuredDescription(DescriptionParser::parseDescription($transaction->getDescription()));
            }
        }

        return $statements;
    }
}
