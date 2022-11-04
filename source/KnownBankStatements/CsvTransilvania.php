<?php

/*
 * The MIT License
 *
 * Copyright 2022 Daniel Popiniuc.
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
 * Implements logic to interpret CSV format public output from Transilvania Bank
 *
 * @author Daniel Popiniuc <danielpopiniuc@gmail.com>
 */
class CsvTransilvania
{

    use TraitBasicFunctionality;

    private $aColumnsOrder       = [];
    private $aEtlConversionRules = [
        'String Conversion to Float' => [
            'Thousand Separator' => ',',
            'Decimal Separator'  => '.',
        ]
    ];

    public function __construct($bolDocDateDiffersThanPostDate)
    {
        $this->aryCol                                  = $this->arrayOutputColumnLine();
        $this->bolDocumentDateDifferentThanPostingDate = $bolDocDateDiffersThanPostDate;
    }

    private function initializeHeader($strFileNameToProcess)
    {
        $strJustFileName     = pathinfo($strFileNameToProcess);
        $arrayFileNamePieces = explode('-', $strJustFileName['filename']);
        $this->aryRsltHdr    = [
            'Account'  => $arrayFileNamePieces[0],
            'Currency' => substr($arrayFileNamePieces[0], 8, 3),
            'FileName' => $strJustFileName['basename'],
        ];
    }

    public function processCsvFileFromTransilvania($strFileNameToProcess, $aryLn)
    {
        $this->initializeHeader($strFileNameToProcess);
        $arrayLinePieces = [];
        foreach ($aryLn as $intLineNumber => $strLineContent) {
            if ($this->containsCaseInsesitiveString('Data tranzactie,Data valuta,Descriere', $strLineContent)) {
                $this->aColumnsOrder = explode(',', trim($strLineContent));
            } else {
                if ($this->aColumnsOrder != []) {
                    $strLineReminder        = substr($strLineContent, 23, 1000);
                    $strLineReminderTweaked = str_replace(',,', ',"0",', $strLineReminder);
                    $arrayReminderPieces    = explode('"', $strLineReminderTweaked);
                    $strReference           = str_replace(',', '', $arrayReminderPieces[1]);
                    if (array_key_exists('Referinta tranzactiei', $arrayLinePieces)) {
                        if ($arrayLinePieces['Referinta tranzactiei'] == $strReference) {
                            if (array_key_exists('LineWithinFile', $arrayLinePieces)) {
                                $arrayLinePieces['LineWithinFile'] = [
                                    $arrayLinePieces['LineWithinFile'],
                                    ($intLineNumber + 1),
                                ];
                            } else {
                                $arrayLinePieces['LineWithinFile']        = ($intLineNumber + 1);
                                $arrayLinePieces[$this->aColumnsOrder[3]] = $strReference;
                            }
                        } else {
                            $this->processRegularLine($strLineContent, $intLineNumber, $arrayLinePieces);
                            $arrayLinePieces                          = [];
                            $arrayLinePieces['LineWithinFile']        = ($intLineNumber + 1);
                            $arrayLinePieces[$this->aColumnsOrder[3]] = $strReference;
                        }
                    } else {
                        $arrayLinePieces['LineWithinFile']        = ($intLineNumber + 1);
                        $arrayLinePieces[$this->aColumnsOrder[3]] = $strReference;
                    }
                    if (substr($arrayReminderPieces[0], 0, 8) == 'Comision') {
                        $arrayLinePieces[$this->aryCol[7]]  = $this->applyEtlConversions($arrayReminderPieces[2], [
                            'String Conversion to Float' => $this->aEtlConversionRules['String Conversion to Float']
                        ]);
                        $arrayLinePieces[$this->aryCol[8]]  = $this->applyEtlConversions(($arrayReminderPieces[3] == ',' ? $arrayReminderPieces[4] : $arrayReminderPieces[3]), [
                            'String Conversion to Float' => $this->aEtlConversionRules['String Conversion to Float']
                        ]);
                        $arrayLinePieces[$this->aryCol[15]] = $arrayReminderPieces[0];
                    } else {
                        $arrayLinePieces[$this->aColumnsOrder[2]] = $arrayReminderPieces[0];
                        $arrayLinePieces[$this->aColumnsOrder[4]] = $this->applyEtlConversions($arrayReminderPieces[2], [
                            'String Conversion to Float' => $this->aEtlConversionRules['String Conversion to Float']
                        ]);
                        $arrayLinePieces[$this->aColumnsOrder[5]] = $this->applyEtlConversions(($arrayReminderPieces[3] == ',' ? $arrayReminderPieces[4] : $arrayReminderPieces[3]), [
                            'String Conversion to Float' => $this->aEtlConversionRules['String Conversion to Float']
                        ]);
                    }
                    $arrayLinePieces[$this->aColumnsOrder[0]] = substr($strLineContent, 0, 10);
                    $arrayLinePieces[$this->aColumnsOrder[1]] = substr($strLineContent, 11, 10);
                    $arrayLinePieces[$this->aColumnsOrder[6]] = $this->applyEtlConversions($arrayReminderPieces[6], [
                        'String Conversion to Float' => $this->aEtlConversionRules['String Conversion to Float']
                    ]);
                }
            }
        }
        return [
            'Header' => $this->aryRsltHdr,
            'Lines'  => $this->aryRsltLn,
        ];
    }

    private function processRegularLine($strLineContent, $intLineNumber, $arrayLinePieces)
    {
        if ($this->aColumnsOrder != []) {
            $this->intOpNo++;
            if (is_array($arrayLinePieces['LineWithinFile'])) {
                $this->aryRsltLn[$this->intOpNo]['LineWithinFile'] = implode(', ', $arrayLinePieces['LineWithinFile']);
            } else {
                $this->aryRsltLn[$this->intOpNo]['LineWithinFile'] = $arrayLinePieces['LineWithinFile'];
            }
            $arrayDetails                                      = explode(';', $arrayLinePieces[$this->aColumnsOrder[2]]);
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[1]] = $arrayDetails[0];
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[2]] = $arrayLinePieces[$this->aColumnsOrder[4]];
            if ($this->bolDocumentDateDifferentThanPostingDate) {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[3]] = $arrayLinePieces[$this->aColumnsOrder[5]];
            } else {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[3]] = $this->aryRsltLn[$this->intOpNo][$this->aryCol[2]];
            }
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[4]] = $this->transformCustomDateFormatIntoSqlDate($arrayLinePieces[$this->aColumnsOrder[1]], 'yyyy-MM-dd');
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[5]] = $this->transformCustomDateFormatIntoSqlDate($arrayLinePieces[$this->aColumnsOrder[0]], 'yyyy-MM-dd');
            if (array_key_exists($this->aryCol[7], $this->aryRsltLn[$this->intOpNo])) {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[7]] = $arrayLinePieces[$this->aryCol[7]];
            } else {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[7]] = 0;
            }
            if (array_key_exists($this->aryCol[8], $this->aryRsltLn[$this->intOpNo])) {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[8]] = $arrayLinePieces[$this->aryCol[8]];
            } else {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[8]] = 0;
            }
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[9]] = $arrayLinePieces[$this->aColumnsOrder[2]];
            if (!array_key_exists($this->aryCol[10], $this->aryRsltLn[$this->intOpNo])) {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[10]] = '';
            }
            if (!array_key_exists($this->aryCol[11], $this->aryRsltLn[$this->intOpNo])) {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[11]] = '';
            }
            if (!array_key_exists($this->aryCol[12], $this->aryRsltLn[$this->intOpNo])) {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[12]] = '';
            }
            if (!array_key_exists($this->aryCol[13], $this->aryRsltLn[$this->intOpNo])) {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[13]] = '';
            }
            if (array_key_exists($this->aryCol[15], $arrayLinePieces)) {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[15]] = $arrayLinePieces[$this->aryCol[15]];
            } else {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[15]] = '';
            }
            if (!array_key_exists($this->aryCol[16], $this->aryRsltLn[$this->intOpNo])) {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[16]] = '';
            }
            if (!array_key_exists($this->aryCol[18], $this->aryRsltLn[$this->intOpNo])) {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[18]] = '';
            }
            if (!array_key_exists($this->aryCol[20], $this->aryRsltLn[$this->intOpNo])) {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[20]] = '';
            }
            if (!array_key_exists($this->aryCol[21], $this->aryRsltLn[$this->intOpNo])) {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[21]] = '';
            }
            switch ($this->aryRsltLn[$this->intOpNo][$this->aryCol[1]]) {
                case 'Depunere numerar ATM':
                    $matches                                            = '';
                    preg_match_all('/\sATM\s.*\sRRN/m', $arrayDetails[1], $matches, PREG_SET_ORDER, 0);
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[21]] = str_replace([' ATM ', ' RRN'], '', $matches[0][0]);
                    break;
                case 'Incasare OP':
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[11]] = $arrayDetails[3];
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[16]] = $arrayDetails[2];
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[20]] = $arrayDetails[4];
                    break;
                case 'Incasare OP - canal electronic':
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[11]] = $arrayDetails[4];
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[16]] = $arrayDetails[3];
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[20]] = $arrayDetails[5];
                    break;
                case 'Plata la POS':
                    $matches                                            = '';
                    preg_match_all('/RON\s.*\svaloare\strz:/m', $arrayDetails[1], $matches, PREG_SET_ORDER, 0);
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[12]] = str_replace(['RON ', ' valoare trz:'], '', $matches[0][0]);
                    break;
                case 'Plata la POS non-BT cu card VISA':
                    $matches                                            = '';
                    preg_match_all('/TID:[0-9]{1,8}\s.*\sRO\s/m', $arrayDetails[1], $matches, PREG_SET_ORDER, 0);
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[12]] = preg_replace(['/TID:[0-9]{1,8}\s/m', '/\sRO\s/m'], '', $matches[0][0]);
                    break;
                case 'Plata OP intra - canal electronic':
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[10]] = $arrayDetails[4];
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[12]] = $arrayDetails[3];
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[20]] = $arrayDetails[5];
                    break;
                case 'Plata OP inter - canal electronic':
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[10]] = $arrayDetails[5];
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[12]] = $arrayDetails[4];
                    $this->aryRsltLn[$this->intOpNo][$this->aryCol[20]] = $arrayDetails[6];
                    break;
            }
            if ($this->aryRsltLn[$this->intOpNo][$this->aryCol[20]] != '') {
                $this->aryRsltLn[$this->intOpNo][$this->aryCol[13]] = $this->getSwiftBankFromCode($this->aryRsltLn[$this->intOpNo][$this->aryCol[20]]);
            }
            $this->aryRsltLn[$this->intOpNo][$this->aryCol[14]] = $arrayLinePieces[$this->aColumnsOrder[3]];
        }
    }

}
