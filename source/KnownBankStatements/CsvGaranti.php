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

/**
 * Implements logic to interpret CSV format public output from Garanti Bank
 *
 * @author Daniel Popiniuc <danielpopiniuc@gmail.com>
 */
class CsvGaranti
{

    use TraitBasicFunctionality;

    protected $bolHeaderFound        = false;
    protected $intEmptyLineCounter   = 0;
    protected $intRegisteredComision = 0;
    protected $isRegularTransaction;
    private   $decAmountFromPriorTransaction = 0;
    private   $arrayStringsToClean = [
        'Detalii',
        'TRANSFER ONLINE INTERBANCAR Beneficiar',
    ];

    public function __construct($bolDocDateDiffersThanPostDate)
    {
        $this->aryCol                                  = $this->arrayOutputColumnLine();
        $this->bolDocumentDateDifferentThanPostingDate = $bolDocDateDiffersThanPostDate;
    }

    private function assignBasedOnIdentifier($strHaystack, $floatAmount, $aryIdentifier)
    {
        foreach ($aryIdentifier as $strIdentifier => $strIdentifierAttributes) {
            $strFinalString                           = str_ireplace($strIdentifier, '', $strHaystack);
            $strIdentifierAttributes['CleanedString'] = $strFinalString;
            $strIdentifierAttributes['Column']        = $this->aryCol[1];
            $strIdentifierAttributes['Key']           = $strIdentifier;
            $bolProceed                               = false;
            if (substr($strHaystack, 0, strlen($strIdentifier)) == $strIdentifier) {
                $bolProceed = true;
            } elseif (strlen($strFinalString) != strlen($strHaystack)) {
                $bolProceed = true;
            }
            if ($bolProceed) {
                if ($strIdentifier == 'DCvalues') {
                    $this->assignBasedOnDebitOrCredit($floatAmount, 1, ''
                            . $aryIdentifier['DCvalues']['Debit'], $aryIdentifier['DCvalues']['Credit']);
                }
                $this->assignBasedOnIdentifierSingle($strIdentifierAttributes);
                // avoiding overwriting Partner property
                $this->assignOnlyIfNotAlreadyWithExtraCheck($this->aryCol[16], $this->aryCol[12]);
            }
        }
    }

    private function assignBasedOnIdentifierSingle($aryParams)
    {
        if (in_array($aryParams['AssignmentType'], [
                    'Key',
                    'Value',
                    'ValuePlusDocumentDate',
                ])) {
            $this->assignOnlyIfNotAlready($aryParams['Column'], $aryParams[$aryParams['AssignmentType']]);
        } elseif (in_array($aryParams['AssignmentType'], ['ValueDifferentForDebitAndCredit'])) {
            $this->assignOnlyIfNotAlready($this->aryCol[12], '' .
                str_replace($this->arrayStringsToClean, '', trim($aryParams['Key'])));
        }
        if (in_array($aryParams['AssignmentType'], ['ValuePlusBeneficiaryAndPartner'])) {
            $strRest          = $aryParams['CleanedString'];
            $strValueToAssign = $this->applyStringManipulationsArray($strRest, [
                'replace dash with space',
                'replace numeric sequence followed by single space',
                'trim',
            ]);
            $this->assignOnlyIfNotAlready($this->aryCol[12], '' .
                str_replace($this->arrayStringsToClean, '', $strValueToAssign));
        }
        if (in_array($aryParams['AssignmentType'], ['ValuePlusDocumentDate'])) {
            $this->processDocumentDate(trim($aryParams['CleanedString']));
        }
    }

    private function initializeHeader($strFileNameToProcess)
    {
        $this->aryRsltHdr['FileName'] = pathinfo($strFileNameToProcess, PATHINFO_FILENAME);
    }

    private function isCurrentLineTheHeader($aryLinePieces)
    {
        $bolReturn = false;
        if ((count($aryLinePieces) >= 2) && ($aryLinePieces[1] == 'Explicatii')) {
            $bolReturn = true;
        }
        return $bolReturn;
    }

    public function processCsvFileFromGaranti($strFileNameToProcess, $aryLn)
    {
        $this->initializeHeader($strFileNameToProcess);
        $aryHeaderToMap = $this->knownHeaders();
        foreach ($aryLn as $intLineNumber => $strLineContent) {
            $aryLinePieces = explode(';', str_replace(':', '', $strLineContent));
            $this->processLineByLine($aryHeaderToMap, $strLineContent, $aryLinePieces, $intLineNumber);
            if ($this->intEmptyLineCounter == 2) {
                return [
                    'Header' => $this->aryRsltHdr,
                    'Lines'  => $this->aryRsltLn,
                ];
            }
        }
    }

    private function processDocumentDateDifferentThanPostingDate($aryLinePieces)
    {
        if ($this->bolDocumentDateDifferentThanPostingDate) {
            $strDocumentDate                                   = substr(''
                            . str_replace(' K', '', trim($aryLinePieces[1])), -5) . '/' . substr($aryLinePieces[0], -4);
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[5]] = $this->transformCustomDateFormatIntoSqlDate(''
                    . $strDocumentDate, 'MM/dd/yyyy');
        }
    }

    private function processFurtherCashDeposit($aryLinePieces)
    {
        $knownOperations = [
            'Depunere numerar', 
            'Transfer online intrabancar',
        ];
        if (in_array($this->aryRsltLn[$this->intOpNo][$this->aryCol[1]], $knownOperations)) {
            $this->processDocumentDateDifferentThanPostingDate($aryLinePieces);
            $strDetails = str_replace($this->arrayStringsToClean, '', ''
                . $this->aryRsltLn[$this->intOpNo][$this->aryCol[9]]);
            if (!array_key_exists($this->aryCol[12], $this->aryRsltLn[$this->intOpNo])) {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[12]] = ''
                    . trim(substr($strDetails, 0, strlen($strDetails) - 8));
            }
        }
    }

    private function processLineByLine($aryHeaderToMap, $strLineContent, $aryLinePieces, $intLineNumber)
    {
        if ($this->isCurrentLineTheHeader($aryLinePieces)) {
            $this->bolHeaderFound = true;
        } elseif (trim($strLineContent) == '') {
            $this->intEmptyLineCounter++;
        } elseif ($this->bolHeaderFound) {
            $this->isRegularTransaction = true;
            $floatAmount                = $this->transformAmountFromStringIntoNumber($aryLinePieces[2]);
            $this->processTransactions($aryLinePieces, [
                'Amount' => $floatAmount,
                'LineNo' => $intLineNumber,
            ]);
            $this->intEmptyLineCounter  = 0;
        } elseif (array_key_exists($aryLinePieces[0], $aryHeaderToMap)) {
            $this->aryRsltHdr[$aryHeaderToMap[$aryLinePieces[0]]['Name']] = $this->applyEtlConversions(''
                    . $aryLinePieces[1], $aryHeaderToMap[$aryLinePieces[0]]['ETL']);
        } else {
            $this->aryRsltHdr[$aryLinePieces[0]] = trim($aryLinePieces[1]);
        }
    }

    private function processRegularLine($floatAmount, $intLineNumber, $aryLinePieces)
    {
        $this->aryRsltLn[$this->intOpNo]['LineWithinFile'] = ($intLineNumber + 1);
        $this->aryRsltLn[$this->intOpNo][$this->aryCol[0]] = trim($aryLinePieces[0]);
        $this->aryRsltLn[$this->intOpNo][$this->aryCol[5]] = $this->transformCustomDateFormatIntoSqlDate(''
                . trim($aryLinePieces[0]), 'dd.MM.yyyy');
        $this->aryRsltLn[$this->intOpNo][$this->aryCol[4]] = $this->aryRsltLn[$this->intOpNo][$this->aryCol[5]];
        $this->aryRsltLn[$this->intOpNo][$this->aryCol[9]] = trim($aryLinePieces[1]);
        $this->addDebitOrCredit($floatAmount, 2, 3);
        $aryParameters                                     = $this->getArrayFromJsonFile(__DIR__, ''
                . 'CsvGarantiLineMatchingRules.min.json');
        $this->assignBasedOnIdentifier($aryLinePieces[1], $floatAmount, $aryParameters);
        if (!array_key_exists($this->aryCol[1], $this->aryRsltLn[$this->intOpNo])) {
            $this->assignOnlyIfNotAlready($this->aryCol[1], 'Altele');
            $strRest        = str_replace($this->arrayStringsToClean, '', $aryLinePieces[1]);
            $strBeneficiary = $this->applyStringManipulationsArray($strRest, [
                'remove dot',
                'remove slash',
                'replace numeric sequence followed by single space',
                'trim',
            ]);
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[12]] = $strBeneficiary;
        }
        $this->processFurtherCashDeposit($aryLinePieces);
        // avoiding overwriting Partner property
        $this->assignOnlyIfNotAlreadyWithExtraCheck($this->aryCol[16], $this->aryCol[12]);
    }

    private function processTransactions($aryLinePieces, $aryOtherParameters)
    {
        if ($this->decAmountFromPriorTransaction != $aryOtherParameters['Amount']) {
            if (($this->intOpNo != 0) && ($this->intRegisteredComision == 0)) {
                if (trim($aryLinePieces[1]) == $this->aryRsltLn[$this->intOpNo][$this->aryCol[9]]) {
                    $this->isRegularTransaction = false;
                    $this->intRegisteredComision++;
                }
                if (strlen(str_replace('COMISION PLATA', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                    $this->isRegularTransaction                         = false;
                    $this->intRegisteredComision++;
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[15]] = str_replace($this->arrayStringsToClean, '' 
                        . '', trim($aryLinePieces[1]));
                }
            }
        }
        if ($this->isRegularTransaction) {
            $this->intOpNo++;
            $this->processRegularLine($aryOtherParameters['Amount'], $aryOtherParameters['LineNo'], $aryLinePieces);
            $this->intRegisteredComision = 0;
            $this->decAmountFromPriorTransaction = $aryOtherParameters['Amount'];
        } else {
            $this->addDebitOrCredit($aryOtherParameters['Amount'], 7, 8);
            $this->aryRsltLn[$this->intOpNo]['LineWithinFile'] = [
                $this->aryRsltLn[$this->intOpNo]['LineWithinFile'],
                ($aryOtherParameters['LineNo'] + 1),
            ];
        }
    }
}
