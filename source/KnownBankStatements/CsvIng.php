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
            'Account'  => (count($arrayFileNamePieces) >= 3 ? $arrayFileNamePieces[2] : ''),
            'Currency' => (count($arrayFileNamePieces) >= 4 ? $arrayFileNamePieces[3] : ''),
            'FileName' => $strJustFileName['basename'],
        ];
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
        foreach ($aryLn as $intLineNumber => $strLineContent) {
            if ($this->containsCaseInsesitiveString(',Detalii tranzactie,Debit', $strLineContent)) {
                $this->aColumnsOrder = explode(',', trim($strLineContent));
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
            if ($this->containsCaseInsesitiveString('ING Bank N.V.', $strLineContent)) {
                $arrayCrtPieces = explode('-', $arrayLinePieces[0]);
                if (count($arrayCrtPieces) >= 2) {
                    $this->aryRsltHdr['Agency'] = trim($arrayCrtPieces[1]);
                }
            }
            $this->processRegularLine($strLineContent, $intLineNumber, $arrayLinePieces);
        }
        return ['Header' => $this->aryRsltHdr, 'Lines' => $this->aryRsltLn,];
    }

    private function processRegularLine($strLineContent, $intLineNumber, $arrayLinePieces)
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
                    'Autorizare:'                 => [
                        'AssignmentType' => 'Plain',
                        'ColumnToAssign' => 18,
                    ],
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
