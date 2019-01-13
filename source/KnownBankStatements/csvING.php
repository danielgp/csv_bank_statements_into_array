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

class csvING
{

    use BasicFunctionality;

    protected function processCsvFileFromIng($strFileNameToProcess, $aryLn)
    {
        $aryResultHeader = [];
        $aryResult       = [];
        $aryCol          = [];
        $intOp           = 0;
        $aryHeaderToMap  = $this->knownHeaders();
        foreach ($aryLn as $intLineNumber => $strLineContent) {
            $arrayLinePieces = explode(',,', $strLineContent);
            if (strlen(str_ireplace('ING Bank N.V.', '', $strLineContent)) != strlen($strLineContent)) {
                $arrayCrtPieces              = explode('-', $arrayLinePieces[0]);
                $aryResultHeader['Agency']   = trim($arrayCrtPieces[1]);
                $arrayFileNamePieces         = explode('_', pathinfo($strFileNameToProcess, PATHINFO_FILENAME));
                $aryResultHeader['Account']  = $arrayFileNamePieces[2];
                $aryResultHeader['Currency'] = $arrayFileNamePieces[3];
                return [
                    'Header' => $aryResultHeader,
                    'Lines'  => $aryResult,
                ];
            }
            if ($arrayLinePieces[1] == 'Detalii tranzactie') {
                $aryCol = $this->arrayOutputColumnLine();
            } elseif (is_numeric(substr($strLineContent, 0, 2)) && (''
                    . substr($strLineContent, 2, 1) == ' ') && (''
                    . trim($arrayLinePieces[1]) == 'Comision pe operatiune')) {
                $numberDebitAmount = $this->transformAmountFromStringIntoNumber($arrayLinePieces[2]);
                if ($numberDebitAmount != 0) {
                    $aryResult[$intOp][$aryCol[7]] = $numberDebitAmount;
                }
                $numberCreditAmount = $this->transformAmountFromStringIntoNumber($arrayLinePieces[3]);
                if ($numberCreditAmount != 0) {
                    $aryResult[$intOp][$aryCol[8]] = $numberCreditAmount;
                }
                $aryResult[$intOp]['LineWithinFile'] .= ', ' . ($intLineNumber + 1);
            } elseif (is_numeric(substr($strLineContent, 0, 2)) && (substr($strLineContent, 2, 1) == ' ')) {
                $intOp++;
                $aryResult[$intOp]['LineWithinFile'] = ($intLineNumber + 1);
                $aryResult[$intOp][$aryCol[0]]       = $arrayLinePieces[0];
                $aryResult[$intOp][$aryCol[1]]       = str_replace(['\'', '"'], '', $arrayLinePieces[1]);
                $numberDebitAmount                   = $this->transformAmountFromStringIntoNumber($arrayLinePieces[2]);
                if ($numberDebitAmount != 0) {
                    $aryResult[$intOp][$aryCol[2]] = $numberDebitAmount;
                }
                $numberCreditAmount = $this->transformAmountFromStringIntoNumber($arrayLinePieces[3]);
                if ($numberCreditAmount != 0) {
                    $aryResult[$intOp][$aryCol[3]] = $numberCreditAmount;
                }
                $aryResult[$intOp][$aryCol[4]] = $this->transformCustomDateFormatIntoSqlDate($arrayLinePieces[0], ''
                        . 'dd MMMM yyyy');
                $aryResult[$intOp][$aryCol[5]] = $aryResult[$intOp][$aryCol[4]];
            } elseif (strlen(str_ireplace('Sold initial', '', $strLineContent)) != strlen($strLineContent)) {
                $aryResultHeader['InitialSold'] = $this->transformAmountFromStringIntoNumber($arrayLinePieces[1]);
            } elseif (strlen(str_ireplace('Sold final', '', $strLineContent)) != strlen($strLineContent)) {
                $aryResultHeader['FinalSold'] = $this->transformAmountFromStringIntoNumber($arrayLinePieces[1]);
            } elseif (substr($strLineContent, 0, 2) == ',,') {
                // "Nr. card:" will be ignored as only 4 characters are shown all other being replaced with ****
                if (substr($arrayLinePieces[1], 0, 5) == 'Data:') {
                    $aryResult[$intOp][$aryCol[5]] = $this->transformCustomDateFormatIntoSqlDate(''
                            . str_replace('Data:', '', $arrayLinePieces[1]), 'dd-MM-yyyy');
                } elseif (substr($arrayLinePieces[1], 0, 9) == 'Terminal:') {
                    $aryResult[$intOp][$aryCol[6]] = str_replace('Terminal:', '', $arrayLinePieces[1]);
                    // avoiding overwriting Partner property
                    if (!array_key_exists($aryCol[16], $aryResult[$intOp])) {
                        $aryResult[$intOp][$aryCol[16]] = $aryResult[$intOp][$aryCol[6]];
                    }
                } elseif (substr($arrayLinePieces[1], 0, 8) == 'Detalii:') {
                    $aryResult[$intOp][$aryCol[9]] = str_replace('Detalii:', '', $arrayLinePieces[1]);
                } elseif (substr($arrayLinePieces[1], 0, 27) == 'Comision administrare card:') {
                    $aryResult[$intOp][$aryCol[9]] = 'Comision administrare card';
                } elseif (substr($arrayLinePieces[1], 0, 10) == 'In contul:') {
                    $aryResult[$intOp][$aryCol[10]] = str_replace('In contul:', '', $arrayLinePieces[1]);
                    // avoiding overwriting Partner property
                    if (!array_key_exists($aryCol[16], $aryResult[$intOp])) {
                        $aryResult[$intOp][$aryCol[16]] = $aryResult[$intOp][$aryCol[10]];
                    }
                } elseif (substr($arrayLinePieces[1], 0, 11) == 'Din contul:') {
                    $aryResult[$intOp][$aryCol[11]] = str_replace('Din contul:', '', $arrayLinePieces[1]);
                    // avoiding overwriting Partner property
                    if (!array_key_exists($aryCol[16], $aryResult[$intOp])) {
                        $aryResult[$intOp][$aryCol[16]] = $aryResult[$intOp][$aryCol[11]];
                    }
                } elseif (substr($arrayLinePieces[1], 0, 11) == 'Beneficiar:') {
                    $aryResult[$intOp][$aryCol[12]] = str_replace('Beneficiar:', '', $arrayLinePieces[1]);
                    // avoiding overwriting Partner property
                    if (!array_key_exists($aryCol[16], $aryResult[$intOp])) {
                        $aryResult[$intOp][$aryCol[16]] = $aryResult[$intOp][$aryCol[12]];
                    }
                } elseif (substr($arrayLinePieces[1], 0, 6) == 'Banca:') {
                    $aryResult[$intOp][$aryCol[13]] = str_replace('Banca:', '', $arrayLinePieces[1]);
                } elseif (substr($arrayLinePieces[1], 0, 10) == 'Referinta:') {
                    $aryResult[$intOp][$aryCol[14]] = str_replace('Referinta:', '', $arrayLinePieces[1]);
                }
            }
        }
    }

}
