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
class csvGaranti
{

    use BasicFunctionality;

    private function processCsvFileFromGaranti($strFileNameToProcess, $aryLn)
    {
        $aryResultHeader       = [];
        $aryResultLine         = [];
        $aryCol                = [];
        $intOp                 = 0;
        $intEmptyLineCounter   = 0;
        $intRegisteredComision = 0;
        $aryHeaderToMap        = $this->knownHeaders();
        $bolHeaderFound        = false;
        foreach ($aryLn as $intLineNumber => $strLineContent) {
            $aryLinePieces = explode(';', str_replace(':', '', $strLineContent));
            if ((count($aryLinePieces) >= 2) && ($aryLinePieces[1] == 'Explicatii')) {
                $bolHeaderFound = true;
                $aryCol         = $this->arrayOutputColumnLine();
            } elseif (trim($strLineContent) == '') {
                $intEmptyLineCounter++;
            } elseif ($bolHeaderFound) {
                $isRegularTransaction = true;
                $floatAmount          = filter_var(str_replace(',', '.', $aryLinePieces[2]), FILTER_VALIDATE_FLOAT);
                if ($intOp != 0) {
                    if (trim($aryLinePieces[1]) == $aryResultLine[$intOp][$aryCol[9]]) {
                        if ($intRegisteredComision == 0) {
                            $isRegularTransaction = false;
                            $intRegisteredComision++;
                        }
                    }
                    if (strlen(str_replace('COMISION PLATA', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                        if ($intRegisteredComision == 0) {
                            $isRegularTransaction               = false;
                            $intRegisteredComision++;
                            $aryResultLine[$intOp][$aryCol[15]] = trim($aryLinePieces[1]);
                        }
                    }
                }
                if ($isRegularTransaction) {
                    $intOp++;
                    $aryResultLine[$intOp]['LineWithinFile'] = ($intLineNumber + 1);
                    $aryResultLine[$intOp][$aryCol[0]]       = trim($aryLinePieces[0]);
                    $aryResultLine[$intOp][$aryCol[5]]       = $this->transformCustomDateFormatIntoSqlDate(''
                            . trim($aryLinePieces[0]), 'dd.MM.yyyy');
                    $aryResultLine[$intOp][$aryCol[4]]       = $aryResultLine[$intOp][$aryCol[5]];
                    $aryResultLine[$intOp][$aryCol[9]]       = trim($aryLinePieces[1]);
                    if ($floatAmount < 0) {
                        $aryResultLine[$intOp][$aryCol[2]] = abs($floatAmount);
                    } else {
                        $aryResultLine[$intOp][$aryCol[3]] = $floatAmount;
                    }
                    if (substr($aryLinePieces[1], 0, 21) == 'Comision administrare') {
                        $aryResultLine[$intOp][$aryCol[1]] = 'Comision administrare';
                    } elseif (substr($aryLinePieces[1], 0, 7) == 'Dobanda') {
                        $aryResultLine[$intOp][$aryCol[1]] = 'Dobanda';
                        $aryResultLine[$intOp][$aryCol[5]] = $this->transformCustomDateFormatIntoSqlDate(''
                                . substr($aryLinePieces[1], 8, 10), 'dd.MM.yyyy');
                    } elseif (strtoupper(substr($aryLinePieces[1], 0, 18)) == 'TRANSFER CONT COLE') {
                        $aryResultLine[$intOp][$aryCol[1]] = 'Transfer cont colector';
                    } elseif (substr($aryLinePieces[1], 0, 20) == 'GLS GENERAL LOGISTIC') {
                        $aryResultLine[$intOp][$aryCol[1]]  = 'Plata ramburs';
                        $aryResultLine[$intOp][$aryCol[12]] = substr($aryLinePieces[1], 0, 20);
                        // avoiding overwriting Partner property
                        if (!array_key_exists($aryCol[16], $aryResult[$intOp])) {
                            $aryResultLine[$intOp][$aryCol[16]] = $aryResultLine[$intOp][$aryCol[12]];
                        }
                    } elseif (strlen(str_replace(' BONUS ', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                        $aryResultLine[$intOp][$aryCol[1]] = 'Depunere numerar';
                    } elseif (strlen(str_replace(' DMSC ', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                        $aryResultLine[$intOp][$aryCol[1]] = 'Depunere numerar';
                    } elseif (strlen(str_replace(' INTI ', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                        $aryResultLine[$intOp][$aryCol[1]] = 'Depunere numerar';
                    } elseif (strlen(str_ireplace('-POS Fee-', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                        $aryResultLine[$intOp][$aryCol[1]]  = 'POS Fee';
                        $strRest                            = explode('-', $aryLinePieces[1]);
                        $aryResultLine[$intOp][$aryCol[12]] = $strRest[2];
                        // avoiding overwriting Partner property
                        if (!array_key_exists($aryCol[16], $aryResult[$intOp])) {
                            $aryResultLine[$intOp][$aryCol[16]] = $aryResultLine[$intOp][$aryCol[12]];
                        }
                    } elseif (strlen(str_ireplace('AVANS FACTURA', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                        $aryResultLine[$intOp][$aryCol[1]]  = 'Plata avans factura';
                        $strRest                            = str_ireplace('AVANS FACTURA', '', $aryLinePieces[1]);
                        $aryResultLine[$intOp][$aryCol[12]] = $this->applyStringManipulationsArray($strRest, [
                            'replace dash with space',
                            'replace numeric sequence followed by single space',
                            'trim',
                        ]);
                        // avoiding overwriting Partner property
                        if (!array_key_exists($aryCol[16], $aryResult[$intOp])) {
                            $aryResultLine[$intOp][$aryCol[16]] = $aryResultLine[$intOp][$aryCol[12]];
                        }
                    } elseif (strlen(str_replace('BUGETUL DE STAT', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                        $aryResultLine[$intOp][$aryCol[1]] = 'Plata obligatii stat';
                    } elseif (strlen(str_replace('PLATA TVA', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                        $aryResultLine[$intOp][$aryCol[1]] = 'Plata obligatii stat';
                    } elseif (strlen(str_replace('CASA ASIG SANATATE', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                        $aryResultLine[$intOp][$aryCol[1]] = 'Plata obligatii stat';
                    } elseif (strlen(str_replace('CUMPARARE VALUTA', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                        $aryResultLine[$intOp][$aryCol[1]] = 'Cumparare Valuta';
                    } elseif ((strlen(str_ireplace('cv fact', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) || (strlen(str_ireplace('plata fact', '', $aryLinePieces[1])) != strlen($aryLinePieces[1]))) {
                        $aryResultLine[$intOp][$aryCol[1]]  = 'Plata factura';
                        $strRest                            = str_ireplace(['cv fact', 'plata fact'], ' ', $aryLinePieces[1]);
                        $aryResultLine[$intOp][$aryCol[12]] = $this->applyStringManipulationsArray($strRest, [
                            'replace dash with space',
                            'replace numeric sequence followed by single space',
                            'trim',
                        ]);
                        // avoiding overwriting Partner property
                        if (!array_key_exists($aryCol[16], $aryResult[$intOp])) {
                            $aryResultLine[$intOp][$aryCol[16]] = $aryResultLine[$intOp][$aryCol[12]];
                        }
                    } elseif (strlen(str_ireplace('RAMBURSURI', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                        $aryResultLine[$intOp][$aryCol[1]]  = 'Plata ramburs';
                        $strRest                            = str_ireplace('RAMBURSURI', ' ', $aryLinePieces[1]);
                        $aryResultLine[$intOp][$aryCol[12]] = $this->applyStringManipulationsArray($strRest, [
                            'replace dash with space',
                            'replace numeric sequence followed by single space',
                            'trim',
                        ]);
                        // avoiding overwriting Partner property
                        if (!array_key_exists($aryCol[16], $aryResult[$intOp])) {
                            $aryResultLine[$intOp][$aryCol[16]] = $aryResultLine[$intOp][$aryCol[12]];
                        }
                    } elseif (strlen(str_ireplace('Plata ramburs', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                        $aryResultLine[$intOp][$aryCol[1]]  = 'Plata ramburs';
                        $strRest                            = str_ireplace('Plata ramburs', ' ', $aryLinePieces[1]);
                        $aryResultLine[$intOp][$aryCol[12]] = $this->applyStringManipulationsArray($strRest, [
                            'replace dash with space',
                            'replace numeric sequence followed by single space',
                            'trim',
                        ]);
                        // avoiding overwriting Partner property
                        if (!array_key_exists($aryCol[16], $aryResult[$intOp])) {
                            $aryResultLine[$intOp][$aryCol[16]] = $aryResultLine[$intOp][$aryCol[12]];
                        }
                    } elseif (strlen(str_ireplace('TRANSFER NUMERAR', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                        $aryResultLine[$intOp][$aryCol[1]] = 'Transfer numerar';
                    } else {
                        $aryResultLine[$intOp][$aryCol[1]] = 'Altele';
                        $strRest                           = $aryLinePieces[1];
                        if (strlen(str_ireplace('DANIELA MARCU', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                            if (array_key_exists($aryCol[2], $aryResultLine[$intOp]) && !array_key_exists($aryCol[3], $aryResultLine[$intOp])) {
                                $aryResultLine[$intOp][$aryCol[1]] = 'Plata';
                            }
                            if (!array_key_exists($aryCol[2], $aryResultLine[$intOp]) && array_key_exists($aryCol[3], $aryResultLine[$intOp])) {
                                $aryResultLine[$intOp][$aryCol[1]] = 'Incasare';
                            }
                            $aryResultLine[$intOp][$aryCol[12]] = 'DANIELA MARCU';
                            $aryResultLine[$intOp][$aryCol[16]] = $aryResultLine[$intOp][$aryCol[12]];
                        } elseif (strlen(str_ireplace('COMISION', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                            $aryResultLine[$intOp][$aryCol[1]] = 'Comision';
                        } elseif (strlen(str_ireplace('CUMPARATURI POS', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                            $aryResultLine[$intOp][$aryCol[1]] = 'Plata factura cumparare';
                        } elseif (strlen(str_ireplace('TRANSILVANIA POST SR', '', $aryLinePieces[1])) != strlen($aryLinePieces[1])) {
                            if (array_key_exists($aryCol[2], $aryResultLine[$intOp]) && !array_key_exists($aryCol[3], $aryResultLine[$intOp])) {
                                $aryResultLine[$intOp][$aryCol[1]] = 'Plata factura cumparare';
                            }
                            if (!array_key_exists($aryCol[2], $aryResultLine[$intOp]) && array_key_exists($aryCol[3], $aryResultLine[$intOp])) {
                                $aryResultLine[$intOp][$aryCol[1]] = 'Incasare';
                            }
                            $aryResultLine[$intOp][$aryCol[12]] = $strRest;
                            $aryResultLine[$intOp][$aryCol[16]] = $aryResultLine[$intOp][$aryCol[12]];
                        } else {
                            $aryResultLine[$intOp][$aryCol[12]] = $this->applyStringManipulationsArray($strRest, [
                                'remove dot',
                                'remove slash',
                                'replace numeric sequence followed by single space',
                                'trim',
                            ]);
                            $aryResultLine[$intOp][$aryCol[16]] = $aryResultLine[$intOp][$aryCol[12]];
                        }
                    }
                    if ($aryResultLine[$intOp][$aryCol[1]] == 'Depunere numerar') {
                        $strDocumentDate                   = substr(str_replace(' K', '', trim($aryLinePieces[1])), -5)
                                . '/' . substr($aryLinePieces[0], -4);
                        $aryResultLine[$intOp][$aryCol[5]] = $this->transformCustomDateFormatIntoSqlDate(''
                                . $strDocumentDate, 'MM/dd/yyyy');
                        if (!array_key_exists($aryCol[12], $aryResultLine[$intOp])) {
                            $aryResultLine[$intOp][$aryCol[12]] = trim(''
                                    . substr($aryResultLine[$intOp][$aryCol[9]], 0, strlen(''
                                                    . $aryResultLine[$intOp][$aryCol[9]]) - 8));
                        }
                        // avoiding overwriting Partner property
                        if (!array_key_exists($aryCol[16], $aryResult[$intOp])) {
                            $aryResultLine[$intOp][$aryCol[16]] = $aryResultLine[$intOp][$aryCol[12]];
                        }
                    }
                    $intRegisteredComision = 0;
                } else {
                    if ($floatAmount < 0) {
                        $aryResultLine[$intOp][$aryCol[7]] = abs($floatAmount);
                    } else {
                        $aryResultLine[$intOp][$aryCol[8]] = $floatAmount;
                    }
                    $intExistingLine                         = $aryResultLine[$intOp]['LineWithinFile'];
                    $aryResultLine[$intOp]['LineWithinFile'] = [
                        $intExistingLine,
                        ($intLineNumber + 1),
                    ];
                }
                $intEmptyLineCounter = 0;
            } elseif (array_key_exists($aryLinePieces[0], $aryHeaderToMap)) {
                $aryResultHeader[$aryHeaderToMap[$aryLinePieces[0]]['Name']] = $this->applyEtlConversions(''
                        . $aryLinePieces[1], $aryHeaderToMap[$aryLinePieces[0]]['ETL']);
            } else {
                $aryResultHeader[$aryLinePieces[0]] = trim($aryLinePieces[1]);
            }
            if ($intEmptyLineCounter == 2) {
                return [
                    'Header' => $aryResultHeader,
                    'Lines'  => $aryResultLine,
                ];
            }
        }
    }

}
