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

    private $aryCol     = [];
    private $aryRsltHdr = [];
    private $aryRsltLn  = [];

    public function __construct()
    {
        $this->aryCol = $this->arrayOutputColumnLine();
    }

    private function addDebitOrCredit($intOp, $arrayLinePieces, $strColumnForDebit, $strColumnForCredit)
    {
        $numberDebitAmount = $this->transformAmountFromStringIntoNumber($arrayLinePieces[2]);
        if ($numberDebitAmount != 0) {
            $this->aryRsltLn[$intOp][$strColumnForDebit] = $numberDebitAmount;
        }
        $numberCreditAmount = $this->transformAmountFromStringIntoNumber($arrayLinePieces[3]);
        if ($numberCreditAmount != 0) {
            $this->aryRsltLn[$intOp][$strColumnForCredit] = $numberCreditAmount;
        }
    }

    private function assignBasedOnIdentifier($strHaystack, $intOp, $aryIdentifier)
    {
        foreach ($aryIdentifier as $strIdentifier => $strIdentifierAttributes) {
            $intIdentifierLength = strlen($strIdentifier);
            if (substr($strHaystack, 0, $intIdentifierLength) == $strIdentifier) {
                $this->assignBasedOnIdentifierSingle($strHaystack, $intOp, $strIdentifier, $strIdentifierAttributes);
            }
        }
    }

    private function assignBasedOnIdentifierSingle($strHaystack, $intOp, $strIdentifier, $strIdentifierAttributes)
    {
        $strColumnToAssign = $this->aryCol[$strIdentifierAttributes['ColumnToAssign']];
        $strFinalString    = str_ireplace($strIdentifier, '', $strHaystack);
        switch ($strIdentifierAttributes['AssignmentType']) {
            case 'Plain':
                $this->aryRsltLn[$intOp][$strColumnToAssign] = $strFinalString;
                break;
            case 'PlainAndPartner':
                $this->aryRsltLn[$intOp][$strColumnToAssign] = $strFinalString;
                // avoiding overwriting Partner property
                if (!array_key_exists($this->aryCol[16], $this->aryRsltLn[$intOp])) {
                    $this->aryRsltLn[$intOp][$this->aryCol[16]] = $strFinalString;
                }
                break;
            case 'SqlDate':
                $this->aryRsltLn[$intOp][$strColumnToAssign] = $this->transformCustomDateFormatIntoSqlDate(''
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
        $strJustFileName     = pathinfo($strFileNameToProcess, PATHINFO_FILENAME);
        $arrayFileNamePieces = explode('_', $strJustFileName);
        $this->aryRsltHdr    = [
            'Account'  => $arrayFileNamePieces[2],
            'Currency' => $arrayFileNamePieces[3],
            'FileName' => $strJustFileName,
        ];
    }

    private function isTwoDigitNumberFolledBySpace($strLineContent)
    {
        if (is_numeric(substr($strLineContent, 0, 2)) && (substr($strLineContent, 2, 1) == ' ')) {
            return true;
        }
        return false;
    }

    public function processCsvFileFromIng($strFileNameToProcess, $aryLn)
    {
        $this->initializeHeader($strFileNameToProcess);
        $intOp = 0;
        foreach ($aryLn as $intLineNumber => $strLineContent) {
            $arrayLinePieces = explode(',,', str_ireplace(["\n", "\r"], '', $strLineContent));
            if ($this->containsCaseInsesitiveString('ING Bank N.V.', $strLineContent)) {
                $arrayCrtPieces             = explode('-', $arrayLinePieces[0]);
                $this->aryRsltHdr['Agency'] = trim($arrayCrtPieces[1]);
                return ['Header' => $this->aryRsltHdr, 'Lines' => $this->aryRsltLn,];
            }
            if ($this->isTwoDigitNumberFolledBySpace($strLineContent) && (''
                    . trim($arrayLinePieces[1]) == 'Comision pe operatiune')) {
                $this->addDebitOrCredit($intOp, $arrayLinePieces, $this->aryCol[7], $this->aryCol[8]);
                $this->aryRsltLn[$intOp]['LineWithinFile'] .= ', ' . ($intLineNumber + 1);
            } elseif ($this->isTwoDigitNumberFolledBySpace($strLineContent)) {
                $intOp++;
                $this->aryRsltLn[$intOp]['LineWithinFile'] = ($intLineNumber + 1);
                $this->aryRsltLn[$intOp][$this->aryCol[0]] = $arrayLinePieces[0];
                $this->aryRsltLn[$intOp][$this->aryCol[1]] = str_replace(['\'', '"'], '', $arrayLinePieces[1]);
                $this->addDebitOrCredit($intOp, $arrayLinePieces, $this->aryCol[2], $this->aryCol[3]);
                $this->aryRsltLn[$intOp][$this->aryCol[4]] = $this->transformCustomDateFormatIntoSqlDate(''
                        . $arrayLinePieces[0], 'dd MMMM yyyy');
                $this->aryRsltLn[$intOp][$this->aryCol[5]] = $this->aryRsltLn[$intOp][$this->aryCol[4]];
            } elseif (strlen(str_ireplace('Sold initial', '', $strLineContent)) != strlen($strLineContent)) {
                $this->aryRsltHdr['InitialSold'] = $this->transformAmountFromStringIntoNumber($arrayLinePieces[1]);
            } elseif (strlen(str_ireplace('Sold final', '', $strLineContent)) != strlen($strLineContent)) {
                $this->aryRsltHdr['FinalSold'] = $this->transformAmountFromStringIntoNumber($arrayLinePieces[1]);
            } elseif (substr($strLineContent, 0, 2) == ',,') {
                // "Nr. card:" will be ignored as only 4 characters are shown all other being replaced with ****
                $this->assignBasedOnIdentifier($arrayLinePieces[1], $intOp, [
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
            }
        }
    }

}
