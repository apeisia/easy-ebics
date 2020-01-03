<?php
/**
 * From https://github.com/mschindler83/fints-hbci-php/blob/master/lib/Fhp/Parser/MT940.php
 * MIT License
 * Copyright (c) 2016 Markus Schindler <mail@markus-schindler.de>
 */

namespace Apeisia\EasyEbics;

abstract class DescriptionParser
{

    static function parseDescription($descr)
    {
        $prepared = [];
        $result   = [];

        // prefill with empty values
        for ($i = 0; $i <= 63; $i++) {
            $prepared[$i] = null;
        }

        $descr = str_replace("\r\n", '', $descr);
        $descr = str_replace('? ', '?', $descr);
        preg_match_all('/\?[\r\n]*(\d{2})([^\?]+)/', $descr, $matches, PREG_SET_ORDER);

        $descriptionLines = [];
        $description1     = ''; // Legacy, could be removed.
        $description2     = ''; // Legacy, could be removed.
        foreach ($matches as $m) {
            $index = (int)$m[1];
            if ((20 <= $index && $index <= 29) || (60 <= $index && $index <= 63)) {
                if (20 <= $index && $index <= 29) {
                    $description1 .= $m[2];
                } else {
                    $description2 .= $m[2];
                }
                $m[2] = trim($m[2]);
                if (!empty($m[2])) {
                    $descriptionLines[] = $m[2];
                }
            } else {
                $prepared[$index] = $m[2];
            }
        }

        $description = [];
        if (empty($descriptionLines) || strlen($descriptionLines[0]) < 5 || $descriptionLines[0][4] !== '+') {
            $description['SVWZ'] = implode('', $descriptionLines);
        } else {
            $lastType = null;
            foreach ($descriptionLines as $line) {
                if (strlen($line) >= 5 && $line[4] === '+') {
                    if ($lastType != null) {
                        $description[$lastType] = trim($description[$lastType]);
                    }
                    $lastType               = substr($line, 0, 4);
                    $description[$lastType] = substr($line, 5);
                } else {
                    $description[$lastType] .= $line;
                }
                if (strlen($line) < 27) {
                    // Usually, lines are 27 characters long. In case characters are missing, then it's either the end
                    // of the current type or spaces have been trimmed from the end. We want to collapse multiple spaces
                    // into one and we don't want to leave trailing spaces behind. So add a single space here to make up
                    // for possibly missing spaces, and if it's the end of the type, it will be trimmed off later.
                    $description[$lastType] .= ' ';
                }
            }
            $description[$lastType] = trim($description[$lastType]);
        }

        $result['description']       = $description;
        $result['booking_text']      = trim($prepared[0]);
        $result['primanoten_nr']     = trim($prepared[10]);
        $result['description_1']     = trim($description1);
        $result['bank_code']         = trim($prepared[30]);
        $result['account_number']    = trim($prepared[31]);
        $result['name']              = trim($prepared[32] . $prepared[33]);
        $result['text_key_addition'] = trim($prepared[34]);
        $result['description_2']     = trim($description2);

        return $result;
    }
}
