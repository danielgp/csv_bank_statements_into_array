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
 * Purpose of this code is to ensure import bank statements
 */
trait TraitImportBankStatement
{

    /**
     * The only method should be called
     *
     * @param string $strFileNameToProcess
     * @param string $strBankLabel
     * @return array Containing 2 branches: 1st one named Header, 2nd one named Lines
     */
    public function processCsvFileFromBank($strFileNameToProcess, $strBankLabel, $bolDocDateDiffersThanPostDate)
    {
        $aryLn       = file($strFileNameToProcess);
        $arrayResult = [];
        switch ($strBankLabel) {
            case 'GarantiBank':
                $cBank       = new CsvGaranti($bolDocDateDiffersThanPostDate);
                $arrayResult = $cBank->processCsvFileFromGaranti($strFileNameToProcess, $aryLn);
                break;
            case 'ING':
                $cBank       = new CsvIng($bolDocDateDiffersThanPostDate);
                $arrayResult = $cBank->processCsvFileFromIng($strFileNameToProcess, $aryLn);
                break;
            default:
                throw new \RuntimeException('Bank ' . $strBankLabel . ' is not implemented yet!');
            // intentionally left out open
        }
        return $arrayResult;
    }
}
