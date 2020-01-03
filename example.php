<?php

use AndrewSvirin\Ebics\Models\Bank;
use AndrewSvirin\Ebics\Models\User;
use AndrewSvirin\Ebics\Services\KeyRingManager;
use Apeisia\EasyEbics\EasyEbics;

if(php_sapi_name() != 'cli') {
    echo "Run this script in the console.\n";
    exit(1);
}

if(!file_exists('config.php')) {
    echo "You need to create a config.php file.\n";
    exit(1);
}

require 'vendor/autoload.php';
require 'config.php';

$easyEbics = new EasyEbics(
    new KeyRingManager(KEYRING_FILE, KEYRING_PASSWORD),
    new Bank(HOST_ID, HOST_URL, IS_CERTIFIED),
    new User(PARTNER_ID, USER_ID)
);

$argv = $_SERVER['argv'];

if (count($argv) == 1) {
    echo "please have a look into this file to see the parameters\n";
    exit(1);
}

switch ($argv[1]) {
    case 'init':
        $easyEbics->generateKeysAndPushToBank();
        break;
    case 'letter':
        $letter = $easyEbics->createInitializationLetter();
        foreach ($letter->getKeys() as $indicator => $key) {
            echo "\nPublic Key $indicator:\n" .
                "Exponent: {$key['exponent']}\n" .
                "Modulo:\n{$key['modulo']}\n" .
                "Hash:\n{$key['hash']}\n\n\n";
        }
        break;
   case 'transactions':
      var_dump($easyEbics->getTransactions());
}
