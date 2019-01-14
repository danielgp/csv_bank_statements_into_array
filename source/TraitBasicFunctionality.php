<?php

/*
 * The MIT License
 *
 * Copyright 2019 Daniel Popiniuc <danielpopiniuc@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace danielgp\csv_bank_statements_into_array;

trait TraitBasicFunctionality
{

    use \danielgp\io_operations\InputOutputStrings;

    private function arrayOutputColumnLine()
    {
        return [
            0  => 'Data',
            1  => 'TransactionType',
            2  => 'AmountDebit',
            3  => 'AmountCredit',
            4  => 'PostingDate',
            5  => 'DocumentDate',
            6  => 'Terminal',
            7  => 'ComisionDebit',
            8  => 'ComisionCredit',
            9  => 'Details',
            10 => 'IntoBankAccount',
            11 => 'FromBankAccount',
            12 => 'Beneficiary',
            13 => 'Bank',
            14 => 'Reference',
            15 => 'DetailsComision',
            16 => 'Partner',
        ];
    }

    private function knownHeaders()
    {
        return [
            'Inceput de Perioada'      => [
                'ETL'  => [
                    'Date Conversion from Format' => 'dd.MM.yyyy',
                ],
                'Name' => 'PeriodStart',
            ],
            'Sfarsit de Perioada'      => [
                'ETL'  => [
                    'Date Conversion from Format' => 'dd.MM.yyyy',
                ],
                'Name' => 'PeriodEnd',
            ],
            'Sold initial'             => [
                'ETL'  => [
                    'String Conversion to Float' => [
                        'Thousand Separator' => '.',
                        'Decimal Separator'  => ',',
                    ],
                ],
                'Name' => 'InitialSold',
            ],
            'Sold initial intermediar' => [
                'ETL'  => [
                    'String Conversion to Float' => [
                        'Thousand Separator' => '.',
                        'Decimal Separator'  => ',',
                    ],
                ],
                'Name' => 'InitialIntermediarySold',
            ],
            'Sold final'               => [
                'ETL'  => [
                    'String Conversion to Float' => [
                        'Thousand Separator' => '.',
                        'Decimal Separator'  => ',',
                    ],
                ],
                'Name' => 'FinalSold',
            ],
            'Sold final intermediar'   => [
                'ETL'  => [
                    'String Conversion to Float' => [
                        'Thousand Separator' => '.',
                        'Decimal Separator'  => ',',
                    ],
                ],
                'Name' => 'FinalIntermediarySold',
            ],
            'Titular cont'             => [
                'ETL'  => [
                    'String Manipulation' => 'trim'
                ],
                'Name' => 'Owner',
            ],
            'Tranzactiile Contului'    => [
                'ETL'  => [
                    'String Manipulation' => [
                        'remove pipeline',
                        'trim',
                    ],
                ],
                'Name' => 'Account',
            ],
            'Nume Agentie'             => [
                'ETL'  => [
                    'String Manipulation' => [
                        'trim',
                    ],
                ],
                'Name' => 'Agency',
            ],
        ];
    }

    private function applyEtlConversions($strInputString, $strEtlConversionRules)
    {
        $strResult = $strInputString;
        foreach ($strEtlConversionRules as $strRuleName => $ruleDetails) {
            switch ($strRuleName) {
                case 'Date Conversion from Format':
                    $strResult   = $this->transformCustomDateFormatIntoSqlDate($strResult, $ruleDetails);
                    break;
                case 'String Conversion to Float':
                    $aryReplace  = [
                        'source'      => [$ruleDetails['Thousand Separator'], $ruleDetails['Decimal Separator']],
                        'destination' => ['', '.'],
                    ];
                    $strTemp     = str_replace($aryReplace['source'], $aryReplace['destination'], $strResult);
                    $floatAmount = filter_var($strTemp, FILTER_VALIDATE_FLOAT);
                    $strResult   = $floatAmount;
                    break;
                case 'String Manipulation':
                    $strResult   = $this->applyStringManipulations($strResult, $ruleDetails);
                    break;
            }
        }
        return $strResult;
    }

    private function transformAmountFromStringIntoNumber($strAmount)
    {
        $intAmount = 0;
        if (strlen($strAmount) >= 5) {
            $strAmountCleaned = $this->applyStringManipulationsArray($strAmount, [
                'remove comma followed by double quotes',
                'remove double quotes followed by comma',
                'remove double quotes',
                'remove dot',
                'replace comma with dot',
                'trim',
            ]);
            $intAmount        = filter_var($strAmountCleaned, FILTER_VALIDATE_FLOAT);
        }
        return $intAmount;
    }

    private function transformCustomDateFormatIntoSqlDate($inRomanianLongDate, $strSourceFormat)
    {
        $dateFormatterIn  = new \IntlDateFormatter('ro_RO', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, ''
                . 'Europe/Bucharest', \IntlDateFormatter::GREGORIAN, $strSourceFormat);
        $intDate          = $dateFormatterIn->parse($inRomanianLongDate);
        $dateFormatterOut = new \IntlDateFormatter('ro_RO', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, ''
                . 'Europe/Bucharest', \IntlDateFormatter::GREGORIAN, 'yyyy-MM-dd');
        return $dateFormatterOut->format($intDate);
    }
}
