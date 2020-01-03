<?php

namespace Apeisia\EasyEbics;

use AndrewSvirin\Ebics\Models\KeyRing;
use AndrewSvirin\Ebics\Services\CryptService;
use LogicException;

class InitializationLetter
{

    /**
     * @var KeyRing
     */
    private $keyRing;

    public function __construct(KeyRing $keyRing)
    {
        $this->keyRing = $keyRing;
    }

    public function getKeys()
    {
        return [
            'A' => $this->getSingleKey('A'),
            'E' => $this->getSingleKey('E'),
            'X' => $this->getSingleKey('X'),
        ];
    }

    public function getSingleKey(string $keyIndicator)
    {
        $method = 'getUserCertificate' . $keyIndicator;
        if (!method_exists($this->keyRing, $method)) {
            throw new LogicException('Requested key with indicator "' . $keyIndicator . '", but no ' . $method . ' was found on the keyring');
        }
        $cs       = new CryptService();
        $key      = $cs->getPublicKeyDetails($this->keyRing->$method()->getPublicKey());
        $exponent = $this->hexDump($key['e']);
        $modulo   = $this->hexDump($key ['m']);

        return [
            'exponent' => $exponent,
            'modulo'   => $modulo,
            'hash'     => $this->ebicsHash($exponent, $modulo),
        ];
    }

    private function ebicsHash($exponent, $modulo)
    {
        $exponent = str_replace(["\n", " "], '', $exponent);
        $modulo   = str_replace(["\n", " "], '', $modulo);
        if ($exponent[0] == '0') $exponent = substr($exponent, 1);
        if ($modulo[0] == '0') $modulo = substr($modulo, 1); // unsure if required
        $hash     = strtoupper(hash('sha256', strtolower($exponent . ' ' . $modulo)));
        $hash     = join(' ', str_split($hash, 2));
        $hash[47] = "\n";

        return $hash;
    }

    private function hexDump($data)
    {
        $out = '';
        $i   = 0;
        foreach (unpack('C*', $data) as $byte) {
            $out .= sprintf("%02X ", $byte);
            if (++$i == 16) {
                $out .= "\n";
                $i   = 0;
            }
        }

        return trim($out);
    }
}
