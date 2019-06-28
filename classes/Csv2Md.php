<?php

declare(strict_types = 1);

namespace Avonture;

/**
 * AUTHOR : AVONTURE Christophe
 *
 * Written date : 3 october 2018
 *
 * CSV to markdown converter.
 * Need to be rework for adding a form where we can specify the input string,
 * delimiters (like "," or ";"), click on a submit button and, thanks to an ajax
 * request, get the result in a dynamic div; without leaving the form.
 *
 * Class "CSVTable" based on https://github.com/mre/CSVTable
 *     - Modified for PHP 7 compatibility
 *     - Add a transpose feature
 *     - Add the column separator as first / last character of the line
 *     - Add a space before / after the column separator
 *     - Add an interface for easily use the conversion tool
 *
 * Last mod:
 * 2019-01-01 - Abandonment of jQuery and migration to vue.js
 * 
 * @phan-suppress PhanUnreferencedClass
 */
class Csv2Md
{
    /**
     * The CSV string (multilines).
     *
     * @var string
     */
    private $csv = '';

    /**
     * The column's delimiter (probably a ,).
     *
     * @var string
     */
    private $delim = ',';

    /**
     * The enclosure is the character to put for isolating string
     * Probably a double-quote.
     *
     * @var string
     */
    private $enclosure = '"';

    /**
     * Character to use to delimit the columns in markdown$
     * The standard is the pipe character i.e. |.
     *
     * @var string
     */
    private $tableSeparator = '|';

    /**
     * Transpose.
     *
     * @var bool
     */
    private $transpose = false;

    /**
     * @var string
     */
    private $header = '';

    /**
     * @var string
     */
    private $rows   = '';

    /**
     * Row lenght
     *
     * @var integer
     */
    private $length = 0;

    /**
     * Max length in each columns
     *
     * @var array
     */
    private $colWidths = [];

    /**
     * Set the CSV content.
     *
     * @param string $csv
     *
     * @return void
     * 
     * @phan-suppress PhanUnreferencedPublicMethod
     */
    public function setCsv(string $csv)
    {
        $this->csv = $csv;
    }

    /**
     * Set the delimiter (; or ,).
     *
     * @param string $delim
     *
     * @return void
     * 
     * @phan-suppress PhanUnreferencedPublicMethod
     */
    public function setDelimiter(string $delim)
    {
        $this->delim = $delim;
    }

    /**
     * Transpose or not?
     *
     * @param bool $transpose
     *
     * @return void
     * 
     * @phan-suppress PhanUnreferencedPublicMethod
     */
    public function setTranspose(bool $transpose)
    {
        $this->transpose = $transpose;
    }

    /**
     * Set the enclosure (f.i. " or ').
     *
     * @param string $enclosure
     *
     * @return void
     * 
     * @phan-suppress PhanUnreferencedPublicMethod
     */
    public function setEnclosure(string $enclosure)
    {
        $this->enclosure = $enclosure;
    }

    /**
     * Set the table separator (f.i. | or :).
     *
     * @param string $tableSeparator
     *
     * @return void
     * 
     * @phan-suppress PhanUnreferencedPublicMethod
     */
    public function setTableSeparator(string $tableSeparator)
    {
        $this->tableSeparator = $tableSeparator;
    }

    /**
     * Convert CSV to MD 
     *
     * @return string The markdown result
     * 
     * @phan-suppress PhanUnreferencedPublicMethod
     */
    public function getMarkup(): string
    {
        $this->toArray();

        return $this->header . $this->rows;
    }

    /**
     * Create the rows for the markdown table
     *
     * @param array $rows
     * @return string
     */
    protected function createRows(array $rows): string
    {
        $output = '';
        foreach ($rows as $row) {
            $output .= $this->createRow($row);
        }

        return $output;
    }

    /**
     * Create a row in the markdown table
     *
     * @param array $row
     * @return string
     */
    protected function createRow(array $row): string
    {
        $output = $this->tableSeparator . ' ';

        // Only create as many columns as the minimal number of elements
        // in all rows. Otherwise this would not be a valid Markdown table
        for ($i = 0; $i < $this->length; ++$i) {
            $element = $this->padded(trim($row[$i]), $this->colWidths[$i]);
            $output .= $element;
            $output .= ' ' . $this->tableSeparator . ' ';
        }

        // row ends with a newline
        $output = trim($output) . "\n";

        return $output;
    }

    /**
     * Transpose a two-dimensional array.
     *
     * ### Example
     *
     * We've an array by user and, for each user, we have a question and
     * the answer.
     *
     * $in = [
     *     'User1' => [
     *         'Question1' => 'Answer User1 - Q1',
     *         'Question2' => 'Answer User1 - Q2',
     *         'Question3' => 'Answer User1 - Q3'
     *     ],
     *     'User2' => [
     *         'Question1' => 'Answer User2 - Q1',
     *         'Question2' => 'Answer User2 - Q2',
     *         'Question3' => 'Answer User2 - Q3'
     *     ],
     *     'User3' => [
     *         'Question1' => 'Answer User3 - Q1',
     *         'Question2' => 'Answer User3 - Q2',
     *         'Question3' => 'Answer User3 - Q3'
     *     ]
     * ];
     *
     * We can transpose the array to have first the question then
     * the answer given to that question by each user.
     *
     * So User->Question->Answer should become Question->User->Answer
     *
     * $out = Transpose($in);
     *
     * This will give:
     *
     * $out = [
     *        'Question1' => [
     *            'User1' => 'Answer User1 - Q1',
     *            'User2' => 'Answer User2 - Q1',
     *            'User3' => 'Answer User3 - Q1'
     *        ],
     *        'Question2' => [
     *            'User1' => 'Answer User1 - Q2',
     *            'User2' => 'Answer User2 - Q2',
     *            'User3' => 'Answer User3 - Q2'
     *        ],
     *        'Question3' => [
     *            'User1' => 'Answer User1 - Q3',
     *            'User2' => 'Answer User2 - Q3',
     *            'User3' => 'Answer User3 - Q3'
     *        ]
     *    ]
     *
     *
     * @see https://stackoverflow.com/questions/797251/transposing-multidimensional-arrays-in-php/797268#797268
     *
     * @param array $arr
     *
     * @return array
     */
    private function transpose(array $arr): array
    {
        $out = [];
        foreach ($arr as $key => $subarr) {
            foreach ($subarr as $subkey => $subvalue) {
                $out[$subkey][$key] = $subvalue;
            }
        }

        return $out;
    }

    /**
     * Convert a string into an array.
     *
     * @return array
     */
    private function toArray(): array
    {
        // Parse the rows
        $parsed = str_getcsv(trim($this->csv), "\n");

        $arrParsed = [];
        foreach ($parsed as &$row) {
            // Parse the items in rows
            if (null == $row) {
                continue;
            }
            $row = str_getcsv($row, $this->delim, $this->enclosure);
            array_push($arrParsed, $row);
        }

        // Transpose only when the array has exactly two rows
        if ((2 == count($arrParsed)) && ($this->transpose)) {
            $arrParsed = self::transpose($arrParsed);
            // Add, as the first row, a heading row
            $arrHeader = ['code', 'value'];
            array_unshift($arrParsed, $arrHeader);
        }

        $this->length     = $this->minRowLength($arrParsed);
        $this->colWidths  = $this->maxColumnWidths($arrParsed);
        $arrHeader        = array_shift($arrParsed);
        $this->header     = $this->createHeader($arrHeader);
        $this->rows       = $this->createRows($arrParsed);

        return $arrParsed;
    }

    /**
     * Create the header of the markdown table
     *
     * @param array $arrHeader
     * @return string
     */
    private function createHeader(array $arrHeader): string
    {
        return $this->createRow($arrHeader) . $this->createSeparator();
    }

    /**
     * Add the row just below the header; with | --- | --- |
     * 
     * @return string
     */
    private function createSeparator(): string
    {
        $output = $this->tableSeparator . ' ';

        for ($i = 0; $i < $this->length; ++$i) {
            $output .= str_repeat('-', $this->colWidths[$i]);
            $output .= ' ' . $this->tableSeparator . ' ';
        }

        return trim($output) . "\n";
    }

    /**
     * Add padding to a string.
     *
     * @param string $str
     * @param integer $width
     * @return string
     */
    private function padded(string $str, int $width): string
    {
        if ($width < strlen($str)) {
            return $str;
        }

        $paddingLength = $width - strlen($str);
        $padding       = str_repeat(' ', $paddingLength);

        return $str . $padding;
    }

    /**
     * Get the minimum lenght of a row
     *
     * @param array $arr
     * @return integer
     */
    private function minRowLength(array $arr): int
    {
        $min = PHP_INT_MAX;

        foreach ($arr as $row) {
            $rowLength = count($row);
            if ($rowLength < $min) {
                $min = $rowLength;
            }
        }

        return $min;
    }

    /**
     * Calculate the maximum width of each column in characters
     *
     * @param array $arr
     * @return array
     */
    private function maxColumnWidths(array $arr): array
    {
        // Set all column widths to zero.
        $arrColumnsWidths = array_fill(0, $this->length, 0);

        foreach ($arr as $row) {
            foreach ($row as $k => $v) {
                if ($arrColumnsWidths[$k] < strlen($v)) {
                    $arrColumnsWidths[$k] = strlen($v);
                }

                if ($k == $this->length - 1) {
                    // We don't need to look any further since these elements
                    // will be dropped anyway because all table rows must have the
                    // same length to create a valid Markdown table.
                    break;
                }
            }
        }

        return $arrColumnsWidths;
    }
}
