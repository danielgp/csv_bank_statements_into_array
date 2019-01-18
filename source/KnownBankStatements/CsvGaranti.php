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

    public function __construct($bolDocDateDiffersThanPostDate)
    {
        $this->aryCol                                  = $this->arrayOutputColumnLine();
        $this->bolDocumentDateDifferentThanPostingDate = $bolDocDateDiffersThanPostDate;
    }

    private function addDebitOrCredit($floatAmount, $intColumnNumberForDebit, $intColumnNumberForCredit)
    {
        if ($floatAmount < 0) {
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[$intColumnNumberForDebit]] = abs($floatAmount);
        } else {
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[$intColumnNumberForCredit]] = $floatAmount;
        }
    }

    private function assignBasedOnDebitOrCredit($floatAmount, $intColumn, $strDebit, $strCredit)
    {
        if ($floatAmount < 0) {
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[$intColumn]] = $strDebit;
        } else {
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[$intColumn]] = $strCredit;
        }
    }

    private function assignBasedOnIdentifier($strHaystack, $aryIdentifier)
    {
        foreach ($aryIdentifier as $strIdentifier => $strIdentifierAttributes) {
            $strFinalString                           = str_ireplace($strIdentifier, '', $strHaystack);
            $strIdentifierAttributes['CleanedString'] = $strFinalString;
            $strIdentifierAttributes['Column']        = $this->aryCol[$strIdentifierAttributes['ColumnToAssign']];
            $strIdentifierAttributes['Key']           = $strIdentifier;
            $bolProceed                               = false;
            if (substr($strHaystack, 0, strlen($strIdentifier)) == $strIdentifier) {
                $bolProceed = true;
            } elseif (strlen($strFinalString) != strlen($strHaystack)) {
                $bolProceed = true;
            }
            if ($bolProceed) {
                $this->assignBasedOnIdentifierSingle($strIdentifierAttributes);
            }
        }
    }

    private function assignBasedOnIdentifierSingle($aryParams)
    {
        if (in_array($aryParams['AssignmentType'], [
                    'Key',
                    'Value',
                    'ValuePlusBeneficiaryAndPartner',
                    'ValuePlusDocumentDate',
                ])) {
            $this->assignOnlyIfNotAlready($aryParams['Column'], $aryParams[$aryParams['AssignmentType']]);
        } elseif ($aryParams['AssignmentType'] === 'ValueDifferentForDebitAndCredit') {
            $aryDebitCredit                                     = [
                'C' => $aryParams['DCvalues']['Credit'],
                'D' => $aryParams['DCvalues']['Debit'],
            ];
            $this->assignBasedOnDebitOrCredit($aryParams['Amount'], 1, $aryDebitCredit['D'], $aryDebitCredit['C']);
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[12]] = trim($aryParams['Key']);
        }
        if (in_array($aryParams['AssignmentType'], ['ValuePlusBeneficiaryAndPartner'])) {
            $strRest                                            = $aryParams['CleanedString'];
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[12]] = $this->applyStringManipulationsArray($strRest, [
                'replace dash with space',
                'replace numeric sequence followed by single space',
                'trim',
            ]);
        }
        if (in_array($aryParams['AssignmentType'], ['ValuePlusDocumentDate'])) {
            $this->processDocumentDate(trim($aryParams['CleanedString']));
        }
        // avoiding overwriting Partner property
        $this->assignOnlyIfNotAlreadyWithExtraCheck($this->aryCol[16], $this->aryCol[12]);
    }

    private function assignOnlyIfNotAlready($strColumnToAssignTo, $strValueToAssign)
    {
        if (!is_null($strValueToAssign) && !array_key_exists($strColumnToAssignTo, $this->aryRsltLn[$this->intOpNo])) {
            $this->aryRsltLn[$this->intOpNo][$strColumnToAssignTo] = $strValueToAssign;
        }
    }

    private function assignOnlyIfNotAlreadyWithExtraCheck($strColumnToAssignTo, $strColumnToAssignFrom)
    {
        if (array_key_exists($strColumnToAssignFrom, $this->aryRsltLn[$this->intOpNo])) {
            $strValueToAssign = $this->aryRsltLn[$this->intOpNo][$strColumnToAssignFrom];
            if (!array_key_exists($strColumnToAssignTo, $this->aryRsltLn[$this->intOpNo])) {
                $this->aryRsltLn[$this->intOpNo][$strColumnToAssignTo] = $strValueToAssign;
            }
        }
    }

    private function initializeHeader($strFileNameToProcess)
    {
        $this->aryRsltHdr['FileName'] = pathinfo($strFileNameToProcess, PATHINFO_FILENAME);
    }

    public function processCsvFileFromGaranti($strFileNameToProcess, $aryLn)
    {
        $this->initializeHeader($strFileNameToProcess);
        $intEmptyLineCounter   = 0;
        $intRegisteredComision = 0;
        $aryHeaderToMap        = $this->knownHeaders();
        $bolHeaderFound        = false;
        foreach ($aryLn as $intLineNumber => $strLineContent) {
            $aryLinePieces = explode(';', str_replace(':', '', $strLineContent));
            if ((count($aryLinePieces) >= 2) && ($aryLinePieces[1] == 'Explicatii')) {
                $bolHeaderFound = true;
            } elseif (trim($strLineContent) == '') {
                $intEmptyLineCounter++;
            } elseif ($bolHeaderFound) {
                $isRegularTransaction = true;
                $floatAmount          = filter_var(str_replace(',', '.', $aryLinePieces[2]), FILTER_VALIDATE_FLOAT);
                if ($this->intOpNo != 0) {
                    if (trim($aryLinePieces[1]) == $this->aryRsltLn[$this->intOpNo][$this->aryCol[9]]) {
                        if ($intRegisteredComision == 0) {
                            $isRegularTransaction = false;
                            $intRegisteredComision++;
                        }
                    }
                    if (strlen(str_replace('COMISION PLATA', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                        if ($intRegisteredComision == 0) {
                            $isRegularTransaction                               = false;
                            $intRegisteredComision++;
                            $this->aryRsltLn[$this->intOpNo][$this->aryCol[15]] = trim($aryLinePieces[1]);
                        }
                    }
                }
                if ($isRegularTransaction) {
                    $this->intOpNo++;
                    $this->processRegularLine($floatAmount, $intLineNumber, $aryLinePieces);
                    $intRegisteredComision = 0;
                } else {
                    $this->addDebitOrCredit($floatAmount, 7, 8);
                    $intExistingLine                                   = $this->aryRsltLn[$this->intOpNo]['LineWithinFile'];
                    $this->aryRsltLn[$this->intOpNo]['LineWithinFile'] = [
                        $intExistingLine,
                        ($intLineNumber + 1),
                    ];
                }
                $intEmptyLineCounter = 0;
            } elseif (array_key_exists($aryLinePieces[0], $aryHeaderToMap)) {
                $this->aryRsltHdr[$aryHeaderToMap[$aryLinePieces[0]]['Name']] = $this->applyEtlConversions(''
                        . $aryLinePieces[1], $aryHeaderToMap[$aryLinePieces[0]]['ETL']);
            } else {
                $this->aryRsltHdr[$aryLinePieces[0]] = trim($aryLinePieces[1]);
            }
            if ($intEmptyLineCounter == 2) {
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

    private function processDocumentDate($strDocumentDate)
    {
        if ($this->bolDocumentDateDifferentThanPostingDate) {
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[5]] = ''
                    . $this->transformCustomDateFormatIntoSqlDate($strDocumentDate, 'dd.MM.yyyy');
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
        $this->assignBasedOnIdentifier($aryLinePieces[1], [
            'Comision administrare' => ['AssignmentType' => 'Key', 'ColumnToAssign' => 1,],
            'Comision'              => ['AssignmentType' => 'Key', 'ColumnToAssign' => 1,],
            ' BONUS '               => [
                'AssignmentType' => 'Value',
                'Value'          => 'Depunere numerar',
                'ColumnToAssign' => 1,
            ],
            ' DMSC '                => [
                'AssignmentType' => 'Value',
                'Value'          => 'Depunere numerar',
                'ColumnToAssign' => 1,
            ],
            ' INTI '                => [
                'AssignmentType' => 'Value',
                'Value'          => 'Depunere numerar',
                'ColumnToAssign' => 1,
            ],
            'Bugetul de Stat'       => [
                'AssignmentType' => 'Value',
                'Value'          => 'Plata obligatii stat',
                'ColumnToAssign' => 1,
            ],
            'Plata TVA'             => [
                'AssignmentType' => 'Value',
                'Value'          => 'Plata obligatii stat',
                'ColumnToAssign' => 1,
            ],
            'Casa Asig Sanatate'    => [
                'AssignmentType' => 'Value',
                'Value'          => 'Plata obligatii stat',
                'ColumnToAssign' => 1,
            ],
            'Cumparaturi POS'       => [
                'AssignmentType' => 'Value',
                'Value'          => 'Plata factura cumparare',
                'ColumnToAssign' => 1,
            ],
            'cv fact'               => [
                'AssignmentType'                 => 'ValuePlusBeneficiaryAndPartner',
                'ValuePlusBeneficiaryAndPartner' => 'Plata factura',
                'ColumnToAssign'                 => 1,
            ],
            'plata fact'            => [
                'AssignmentType'                 => 'ValuePlusBeneficiaryAndPartner',
                'ValuePlusBeneficiaryAndPartner' => 'Plata factura',
                'ColumnToAssign'                 => 1,
            ],
            'AVANS FACTURA'         => [
                'AssignmentType'                 => 'ValuePlusBeneficiaryAndPartner',
                'ValuePlusBeneficiaryAndPartner' => 'Plata avans factura',
                'ColumnToAssign'                 => 1,
            ],
            'Plata ramburs'         => [
                'AssignmentType'                 => 'ValuePlusBeneficiaryAndPartner',
                'ValuePlusBeneficiaryAndPartner' => 'Plata ramburs',
                'ColumnToAssign'                 => 1,
            ],
            'Rambursuri'            => [
                'AssignmentType'                 => 'ValuePlusBeneficiaryAndPartner',
                'ValuePlusBeneficiaryAndPartner' => 'Plata ramburs',
                'ColumnToAssign'                 => 1,
            ],
            'Transfer Cont Cole'    => [
                'AssignmentType' => 'Value',
                'Value'          => 'Transfer cont colector',
                'ColumnToAssign' => 1,
            ],
            'Cumparare valuta'      => ['AssignmentType' => 'Key', 'ColumnToAssign' => 1,],
            'Transfer numerar'      => ['AssignmentType' => 'Key', 'ColumnToAssign' => 1,],
            'TRANSILVANIA POST SR'  => [
                'Amount'         => $floatAmount,
                'AssignmentType' => 'ValueDifferentForDebitAndCredit',
                'DCvalues'       => ['Debit' => 'Plata factura cumparare', 'Credit' => 'Incasare',],
                'ColumnToAssign' => 1,
            ],
            'DANIELA MARCU'         => [
                'Amount'         => $floatAmount,
                'AssignmentType' => 'ValueDifferentForDebitAndCredit',
                'DCvalues'       => ['Debit' => 'Plata', 'Credit' => 'Incasare',],
                'ColumnToAssign' => 1,
            ],
            'GLS GENERAL LOGISTIC'  => [
                'Amount'         => $floatAmount,
                'AssignmentType' => 'ValueDifferentForDebitAndCredit',
                'DCvalues'       => ['Debit' => 'Plata ramburs', 'Credit' => 'Incasare ramburs',],
                'ColumnToAssign' => 1,
            ],
            'Dobanda'               => [
                'AssignmentType'        => 'ValuePlusDocumentDate',
                'ValuePlusDocumentDate' => 'Dobanda',
                'ColumnToAssign'        => 1,
            ],
        ]);
        if (strlen(str_ireplace('-POS Fee-', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[1]]  = 'POS Fee';
            $strRest                                            = explode('-', $aryLinePieces[1]);
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[12]] = $strRest[2];
            // avoiding overwriting Partner property
            $this->assignOnlyIfNotAlreadyWithExtraCheck($this->aryCol[16], $this->aryCol[12]);
        } elseif (!array_key_exists($this->aryCol[1], $this->aryRsltLn[$this->intOpNo])) {
            $this->assignOnlyIfNotAlready($this->aryCol[1], 'Altele');
            $strRest                                            = $aryLinePieces[1];
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[12]] = $this->applyStringManipulationsArray($strRest, [
                'remove dot',
                'remove slash',
                'replace numeric sequence followed by single space',
                'trim',
            ]);
            // avoiding overwriting Partner property
            $this->assignOnlyIfNotAlreadyWithExtraCheck($this->aryCol[16], $this->aryCol[12]);
        }
        if ($this->aryRsltLn[$this->intOpNo][$this->aryCol[1]] == 'Depunere numerar') {
            $this->processDocumentDateDifferentThanPostingDate($aryLinePieces);
            if (!array_key_exists($this->aryCol[12], $this->aryRsltLn[$this->intOpNo])) {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[12]] = trim(''
                        . substr($this->aryRsltLn[$this->intOpNo][$this->aryCol[9]], 0, strlen(''
                                        . $this->aryRsltLn[$this->intOpNo][$this->aryCol[9]]) - 8));
            }
            // avoiding overwriting Partner property
            $this->assignOnlyIfNotAlreadyWithExtraCheck($this->aryCol[16], $this->aryCol[12]);
        }
    }
}
