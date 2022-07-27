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
 * Implements logic to interpret CSV format public output from ING Bank
 *
 * @author Daniel Popiniuc <danielpopiniuc@gmail.com>
 */
class CsvIng
{
    use TraitBasicFunctionality;

    private $aColumnsOrder = [];
    private $aCsvHeaders = [];
    private $strCsvFileContentType = null;

    public function __construct($bolDocDateDiffersThanPostDate)
    {
        $this->aryCol                                  = $this->arrayOutputColumnLine();
        $this->bolDocumentDateDifferentThanPostingDate = $bolDocDateDiffersThanPostDate;
    }

    private function addDebitOrCredit($arrayLinePieces, $strColumnForDebit, $strColumnForCredit)
    {
        $numberDebitAmount = $this->transformAmountFromStringIntoNumber($arrayLinePieces[2]);
        if ($numberDebitAmount != 0) {
            $this->aryRsltLn[$this->intOpNo][$strColumnForDebit] = $numberDebitAmount;
        }
        $numberCreditAmount = $this->transformAmountFromStringIntoNumber($arrayLinePieces[3]);
        if ($numberCreditAmount != 0) {
            $this->aryRsltLn[$this->intOpNo][$strColumnForCredit] = $numberCreditAmount;
        }
    }

    private function addDebitOrCreditAssigned($arrayLinePieces, $strColumnForDebit, $strColumnForCredit)
    {
        $strDebit          = $arrayLinePieces[array_search('Debit', $this->aColumnsOrder)];
        $numberDebitAmount = $this->transformAmountFromStringIntoNumber($strDebit);
        if ($numberDebitAmount != 0) {
            $this->aryRsltLn[$this->intOpNo][$strColumnForDebit] = $numberDebitAmount;
        }
        $strCredit          = $arrayLinePieces[array_search('Credit', $this->aColumnsOrder)];
        $numberCreditAmount = $this->transformAmountFromStringIntoNumber($strCredit);
        if ($numberCreditAmount != 0) {
            $this->aryRsltLn[$this->intOpNo][$strColumnForCredit] = $numberCreditAmount;
        }
    }

    private function assignBasedOnIdentifier($strHaystack, $aryIdentifier)
    {
        foreach ($aryIdentifier as $strIdentifier => $strIdentifierAttributes) {
            if (substr($strHaystack, 0, strlen($strIdentifier)) == $strIdentifier) {
                $strFinalString = trim(str_ireplace($strIdentifier, '', $strHaystack));
                if ($strIdentifierAttributes['AssignmentType'] === 'Header') {
                    $strColumnToAssign = $strIdentifierAttributes['Label'];
                    $strHaystack       = $strIdentifierAttributes['S'];
                } else {
                    $strColumnToAssign = $this->aryCol[$strIdentifierAttributes['ColumnToAssign']];
                }
                $aryParameters = [
                    'AssignmentType' => $strIdentifierAttributes['AssignmentType'],
                    'Column'         => $strColumnToAssign,
                ];
                $this->assignBasedOnIdentifierSingle($strHaystack, $strFinalString, $aryParameters);
                if ($strIdentifier == 'Data:') {
                    $strAut                                             = substr($strHaystack
                            . '', strpos($strHaystack, 'Autorizare:') + strlen('Autorizare:') + 1, 100);
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[18]] = $strAut;
                }
            }
        }
    }

    private function assignBasedOnIdentifierSingle($strHaystack, $strFinalString, $aryParams)
    {
        switch ($aryParams['AssignmentType']) {
            case 'Header':
                $this->aryRsltHdr[$aryParams['Column']]                = $this->transformAmountFromStringIntoNumber(''
                        . $strHaystack);
                break;
            case 'Plain':
                $this->aryRsltLn[$this->intOpNo][$aryParams['Column']] = $strFinalString;
                break;
            case 'PlainAndPartner':
                $this->aryRsltLn[$this->intOpNo][$aryParams['Column']] = $strFinalString;
                // avoiding overwriting Partner property
                if (!array_key_exists($this->aryCol[16], $this->aryRsltLn[$this->intOpNo])) {
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[16]] = $strFinalString;
                }
                break;
            case 'PlainWithoutLastCharacter':
                $this->aryRsltLn[$this->intOpNo][$aryParams['Column']] = substr(''
                        . str_replace(['.', '^'], ['', '.'], $strFinalString), 0, strlen($strFinalString) - 1);
                break;
            case 'SqlDate':
                $this->aryRsltLn[$this->intOpNo][$aryParams['Column']] = $this->transformCustomDateFormatIntoSqlDate(''
                        . $strFinalString, 'dd-MM-yyyy');
                break;
        }
    }

    private function containsCaseInsesitiveString($strNeedle, $strHaystack)
    {
        if (strlen(str_ireplace($strNeedle, '', $strHaystack)) != strlen($strHaystack)) {
            return true;
        }
        return false;
    }

    private function initializeHeader($strFileNameToProcess)
    {
        $strJustFileName     = pathinfo($strFileNameToProcess);
        $arrayFileNamePieces = explode('_', $strJustFileName['filename']);
        $this->aryRsltHdr    = [
            'FileName' => $strJustFileName['basename'],
        ];
        foreach($arrayFileNamePieces as $strValue) {
            if ((strlen($strValue) == 24) && (substr($strValue, 0, 2) == 'RO')) {
                $this->aryRsltHdr['Account'] = $strValue;
            }
            if (strlen($strValue) == 3) {
                $this->aryRsltHdr['Currency'] = $strValue;
            }
        }
    }

    private function isCommission($strLineContent, $strFirstPiece)
    {
        $bolReturn = false;
        if ($this->isTwoDigitNumberFollowedBySpace($strLineContent)) {
            if (trim($strFirstPiece) == 'Comision pe operatiune') {
                $bolReturn = true;
            }
        }
        return $bolReturn;
    }

    private function isTwoDigitNumberFollowedBySpace($strLineContent)
    {
        $bolReturn = false;
        if (is_numeric(substr($strLineContent, 0, 2)) && (substr($strLineContent, 2, 1) == ' ')) {
            $bolReturn = true;
        }
        return $bolReturn;
    }

    public function processCsvFileFromIng($strFileNameToProcess, $aryLn)
    {
        $this->initializeHeader($strFileNameToProcess);
        $arrayLinePieces = null;
        foreach ($aryLn as $intLineNumber => $strLineContent) {
            if ($this->containsCaseInsesitiveString(',Detalii tranzactie,Debit', $strLineContent)) {
                $this->aColumnsOrder = explode(',', trim($strLineContent));
                if (is_null($this->strCsvFileContentType)) {
                    $this->strCsvFileContentType = 'DebitCredit';
                }
            }
            if ($this->aColumnsOrder != []) {
                $gDoubleQuotePosition = strpos($strLineContent, '"');
                if ($gDoubleQuotePosition !== false) {
                    $aDoubleQuotePositions   = [];
                    $intLineLength           = strlen($strLineContent);
                    $aDoubleQuotePositions[] = $gDoubleQuotePosition;
                    $gDoubleQuotePosition++;
                    for ($intCounter = $gDoubleQuotePosition; $intCounter < $intLineLength; $intCounter++) {
                        if (substr($strLineContent, $intCounter, 1) == '"') {
                            $aDoubleQuotePositions[] = $intCounter;
                        }
                    }
                    $intCycles              = count($aDoubleQuotePositions) / 2;
                    $aLinePiecesToRebuild   = [];
                    $aLinePiecesToRebuild[] = substr($strLineContent, 0, ($gDoubleQuotePosition - 1));
                    for ($intCounter = 1; $intCounter <= $intCycles; $intCounter++) {
                        $intStarting            = $aDoubleQuotePositions[(($intCounter - 1) * 2)];
                        $intEnding              = $aDoubleQuotePositions[(($intCounter - 1) * 2 + 1)];
                        $aLinePiecesToRebuild[] = str_replace(',', '^', substr($strLineContent
                                        . '', $intStarting, ($intEnding - $intStarting)));
                        if ($intCounter != $intCycles) {
                            $intNext                = $aDoubleQuotePositions[(($intCounter - 1) * 2 + 2)];
                            $aLinePiecesToRebuild[] = substr($strLineContent, $intEnding, ($intNext - $intEnding));
                        }
                    }
                    $aLinePiecesToRebuild[] = substr($strLineContent, $intEnding, 1);
                    $strLineContent         = implode('', $aLinePiecesToRebuild);
                }
                $arrayLinePieces = explode(',', str_ireplace(["\n", "\r"], '', $strLineContent));
            } else {
                $arrayLinePieces = explode(',,', str_ireplace(["\n", "\r"], '', $strLineContent));
            }
            // as of 20201-03-06 a new CSV structure is born
            if ($this->containsCaseInsesitiveString('numar cont;data procesarii;suma;valuta;', $strLineContent)) {
                $this->aColumnsOrder = explode(',', trim($strLineContent));
                if (is_null($this->strCsvFileContentType)) {
                    $this->strCsvFileContentType = 'AccountAndFullBeneficiaryDetails';
                }
                $arrayLinePieces = str_getcsv(trim($strLineContent), ";");
                if ($this->aCsvHeaders == []) {
                    $this->aCsvHeaders = array_map('trim', $arrayLinePieces);
                }
            }
            if ($this->containsCaseInsesitiveString('ING Bank N.V.', $strLineContent)) {
                $arrayCrtPieces = explode('-', $arrayLinePieces[0]);
                if (count($arrayCrtPieces) >= 2) {
                    $this->aryRsltHdr['Agency'] = trim($arrayCrtPieces[1]);
                }
            }
            if (($_SESSION['FirstName'] == 'Daniel') && ($_SESSION['LastName'] == 'Gheorghe')) {
                $this->processRegularLine($strLineContent, $intLineNumber, $arrayLinePieces);
            } else {
                $this->processRegularLine($strLineContent, $intLineNumber, $arrayLinePieces);
            }
        }
        return ['Header' => $this->aryRsltHdr, 'Lines' => $this->aryRsltLn,];
    }

    private function processRegularLine($strLineContent, $intLineNumber, $arrayLinePieces)
    {
        switch($this->strCsvFileContentType) {
            default:
            case 'DebitCredit':
                $this->processRegularLineDebitCredit($strLineContent, $intLineNumber, $arrayLinePieces);
                break;
            case 'AccountAndFullBeneficiaryDetails':
                if ($intLineNumber > 0) {
                    $this->processRegularLineAccountAndFullBeneficiaryDetails($strLineContent, $intLineNumber, $arrayLinePieces);
                }
                break;
        }
    }

    private function processRegularLineAccountAndFullBeneficiaryDetails($strLineContent, $intLineNumber, $arrayLinePieces)
    {
        $this->intOpNo++;
        //echo '<hr/>Linia ' . $intLineNumber . ' ===> ' . $strLineContent . '<br>';
        if ($intLineNumber > 0) {
            //echo 'Tranzactia cu numarul ' . $this->intOpNo . '...';
            $arrayContent = str_getcsv(trim($strLineContent), ";");
            $arrayLineContent = array_combine($this->aCsvHeaders, $arrayContent);
            //echo $this->showArrayWithinFloatingBox($arrayLineContent, 'red');
            //echo $this->showArrayWithinFloatingBox($this->aryCol, 'blue');
            foreach($arrayLineContent as $key => $value) {
                switch($key) {
                    case 'adresa beneficiar/ordonator':
                        if (trim($value) != '') {
                            $this->aryRsltLn[$this->intOpNo][$this->aryCol[21]] = $value;
                        }
                        break;
                    case 'cont beneficiar/ordonator':
                        if ($value != '') {
                            $this->aryRsltLn[$this->intOpNo][$this->aryCol[11]] = $value;
                        }
                        break;
                    case 'data procesarii':
                        $this->aryRsltLn[$this->intOpNo][$this->aryCol[4]] = $this->transformCustomDateFormatIntoSqlDate($value, 'yyyyMMdd');
                        break;
                    case 'detalii tranzactie':
                        if ($value != '') {
                            if ($arrayLineContent['tip tranzactie'] == 'Comision pe operatiune') {
                                $this->aryRsltLn[$this->intOpNo][$this->aryCol[15]] = $value;
                            } else {
                                $this->aryRsltLn[$this->intOpNo][$this->aryCol[9]] = $value;
                            }
                            preg_match_all('/\sData:\s[0-9]{2}-[0-9]{2}-[0-9]{4}$/', $value, $strDocumentDateDetails, PREG_SET_ORDER);
                            if ($strDocumentDateDetails != []) {
                                $this->aryRsltLn[$this->intOpNo][$this->aryCol[5]] = $this->transformCustomDateFormatIntoSqlDate(str_replace(' Data: ', '', $strDocumentDateDetails[0][0]), 'dd-MM-yyyy');
                            }
                        }
                        break;
                    case 'numar cont':
                        if ($value != '') {
                            if (substr($arrayLineContent, 0, 1) == '-') {
                                $this->aryRsltLn[$this->intOpNo][$this->aryCol[12]] = $value;
                            } else {
                                $this->aryRsltLn[$this->intOpNo][$this->aryCol[10]] = $value;
                            }
                        }
                        break;
                    case 'nume beneficiar/ordonator':
                        if ($value != '') {
                            if (substr($arrayLineContent, 0, 1) == '-') {
                                $this->aryRsltLn[$this->intOpNo][$this->aryCol[10]] = $value;
                            } else {
                                $this->aryRsltLn[$this->intOpNo][$this->aryCol[12]] = $value;
                            }
                        }
                        break;
                    case 'suma':
                        $intAmount = abs($this->transformAmountFromStringIntoNumber($value));
                        if ($arrayLineContent['tip tranzactie'] == 'Comision pe operatiune') {
                            if ($intAmount > 0) {
                                $this->aryRsltLn[$this->intOpNo][$this->aryCol[8]] = $intAmount;
                            } else {
                                $this->aryRsltLn[$this->intOpNo][$this->aryCol[7]] = $intAmount;
                            }
                        } else {
                            if ($intAmount > 0) {
                                $this->aryRsltLn[$this->intOpNo][$this->aryCol[3]] = $intAmount;
                            } else {
                                $this->aryRsltLn[$this->intOpNo][$this->aryCol[2]] = $intAmount;
                            }
                        }
                        break;
                    case 'tip tranzactie':
                        $this->aryRsltLn[$this->intOpNo][$this->aryCol[1]] = $value;
                        break;
                    case 'valuta':
                        $this->aryRsltLn[$this->intOpNo][$this->aryCol[20]] = $value;
                        break;
                }
            }
            // fall-back scenario to get same value for DocumentDate as PostingDate when not specifically defined
            if (!array_key_exists($this->aryCol[5], $this->aryRsltLn[$this->intOpNo])) {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[5]] = $this->aryRsltLn[$this->intOpNo][$this->aryCol[4]];
            }
            if (array_key_exists('LineWithinFile', $this->aryRsltLn[$this->intOpNo])) {
                $this->aryRsltLn[$this->intOpNo]['LineWithinFile'] .= ',' . ($intLineNumber + 1);
            } else {
                $this->aryRsltLn[$this->intOpNo]['LineWithinFile'] = ($intLineNumber + 1);
            }
            ksort($this->aryRsltLn[$this->intOpNo]);
            //echo $this->showArrayWithinFloatingBox($this->aryRsltLn[$this->intOpNo], 'green');
            //echo '<div style="height:1px;float:none;clear:both;">&nbsp;</div>';
        }
    }

    private function processRegularLineDebitCredit($strLineContent, $intLineNumber, $arrayLinePieces)
    {
        if ($this->aColumnsOrder != []) {
            $strTransactionDetails = $arrayLinePieces[array_search('Detalii tranzactie', $this->aColumnsOrder)];
            if ($this->isCommission($strLineContent, $strTransactionDetails)) {
                $this->intOpNo++;
                $this->addDebitOrCreditAssigned($arrayLinePieces, $this->aryCol[7], $this->aryCol[8]);
                if (array_key_exists('LineWithinFile', $this->aryRsltLn[$this->intOpNo])) {
                    $this->aryRsltLn[$this->intOpNo]['LineWithinFile'] .= ',' . ($intLineNumber + 1);
                } else {
                    $this->aryRsltLn[$this->intOpNo]['LineWithinFile'] = ($intLineNumber + 1);
                }
            } elseif (!in_array($arrayLinePieces[array_search('Data', $this->aColumnsOrder)], ['', 'Data'])) {
                $aTransactionTypeWithPriorCommission = [
                    "Transfer Home'Bank",
                    'Suma transferata din linia de credit',
                ];
                if (array_key_exists($this->intOpNo, $this->aryRsltLn) && (
                        array_key_exists($this->aryCol[2], $this->aryRsltLn[$this->intOpNo]) || array_key_exists($this->aryCol[3], $this->aryRsltLn[$this->intOpNo])
                        )
                ) {
                    $this->intOpNo++;
                } elseif (!in_array($strTransactionDetails, $aTransactionTypeWithPriorCommission)) {
                    $this->intOpNo++;
                }
                if (array_key_exists($this->intOpNo, $this->aryRsltLn) && array_key_exists('LineWithinFile', $this->aryRsltLn[$this->intOpNo])) {
                    $this->aryRsltLn[$this->intOpNo]['LineWithinFile'] .= ',' . ($intLineNumber + 1);
                } else {
                    $this->aryRsltLn[$this->intOpNo]['LineWithinFile'] = ($intLineNumber + 1);
                }
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[0]] = ''
                        . $arrayLinePieces[array_search('Data', $this->aColumnsOrder)];
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[1]] = str_replace(['\'', '"'], '', ''
                        . $strTransactionDetails);
                $this->addDebitOrCreditAssigned($arrayLinePieces, $this->aryCol[2], $this->aryCol[3]);
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[4]] = $this->transformCustomDateFormatIntoSqlDate(''
                        . $arrayLinePieces[0], 'dd MMMM yyyy');
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[5]] = $this->aryRsltLn[$this->intOpNo][$this->aryCol[4]];
            } elseif (substr($strLineContent, 0, 3) == ',,,') {
                // "Nr. card:" will be ignored as only 4 characters are shown all other being replaced with ****
                $this->assignBasedOnIdentifier($arrayLinePieces[3], [
                    'Banca:'                      => [
                        'AssignmentType' => 'Plain',
                        'ColumnToAssign' => 13,
                    ],
                    'Beneficiar:'                 => [
                        'AssignmentType' => 'PlainAndPartner',
                        'ColumnToAssign' => 12,
                    ],
                    'Comision administrare card:' => [
                        'AssignmentType' => 'Plain',
                        'ColumnToAssign' => 9,
                    ],
                    'Data:'                       => [
                        'AssignmentType' => 'SqlDate',
                        'ColumnToAssign' => 5,
                    ],
                    'Detalii:'                    => [
                        'AssignmentType' => 'Plain',
                        'ColumnToAssign' => 9,
                    ],
                    'Din contul:'                 => [
                        'AssignmentType' => 'PlainAndPartner',
                        'ColumnToAssign' => 11,
                    ],
                    'In contul:'                  => [
                        'AssignmentType' => 'PlainAndPartner',
                        'ColumnToAssign' => 10,
                    ],
                    'Nr. card:'                   => [
                        'AssignmentType' => 'Plain',
                        'ColumnToAssign' => 19,
                    ],
                    'Referinta:'                  => [
                        'AssignmentType' => 'Plain',
                        'ColumnToAssign' => 14,
                    ],
                    '"Suma:'                      => [
                        'AssignmentType' => 'PlainWithoutLastCharacter',
                        'ColumnToAssign' => 17,
                    ],
                    'Terminal:'                   => [
                        'AssignmentType' => 'PlainAndPartner',
                        'ColumnToAssign' => 6,
                    ],
                ]);
            }
        } elseif ($this->isCommission($strLineContent, $arrayLinePieces[1])) {
            $this->addDebitOrCredit($arrayLinePieces, $this->aryCol[7], $this->aryCol[8]);
            $this->aryRsltLn[$this->intOpNo]['LineWithinFile'] .= ', ' . ($intLineNumber + 1);
        } elseif ($this->isTwoDigitNumberFollowedBySpace($strLineContent)) {
            $this->intOpNo++;
            $this->aryRsltLn[$this->intOpNo]['LineWithinFile'] = ($intLineNumber + 1);
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[0]] = $arrayLinePieces[0];
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[1]] = str_replace(['\'', '"'], '', $arrayLinePieces[1]);
            $this->addDebitOrCredit($arrayLinePieces, $this->aryCol[2], $this->aryCol[3]);
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[4]] = $this->transformCustomDateFormatIntoSqlDate(''
                    . $arrayLinePieces[0], 'dd MMMM yyyy');
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[5]] = $this->aryRsltLn[$this->intOpNo][$this->aryCol[4]];
        } elseif (substr($strLineContent, 0, 2) == ',,') {
            // "Nr. card:" will be ignored as only 4 characters are shown all other being replaced with ****
            $this->assignBasedOnIdentifier($arrayLinePieces[1], [
                'Data:'                       => ['AssignmentType' => 'SqlDate', 'ColumnToAssign' => 5,],
                'Detalii:'                    => ['AssignmentType' => 'Plain', 'ColumnToAssign' => 9,],
                'Comision administrare card:' => ['AssignmentType' => 'Plain', 'ColumnToAssign' => 9,],
                'Banca:'                      => ['AssignmentType' => 'Plain', 'ColumnToAssign' => 13,],
                'Referinta:'                  => ['AssignmentType' => 'Plain', 'ColumnToAssign' => 14,],
                'Terminal:'                   => ['AssignmentType' => 'PlainAndPartner', 'ColumnToAssign' => 6,],
                'In contul:'                  => ['AssignmentType' => 'PlainAndPartner', 'ColumnToAssign' => 10,],
                'Din contul:'                 => ['AssignmentType' => 'PlainAndPartner', 'ColumnToAssign' => 11,],
                'Beneficiar:'                 => ['AssignmentType' => 'PlainAndPartner', 'ColumnToAssign' => 12,],
            ]);
        } elseif (substr($strLineContent, 0, 5) === 'Sold ') {
            $this->assignBasedOnIdentifier($strLineContent, [
                'Sold initial' => [
                    'AssignmentType' => 'Header',
                    'S'              => $arrayLinePieces[1],
                    'Label'          => 'InitialSold',
                ],
                'Sold final'   => [
                    'AssignmentType' => 'Header',
                    'S'              => $arrayLinePieces[1],
                    'Label'          => 'FinalSold',
                ],
            ]);
        }
    }
}
