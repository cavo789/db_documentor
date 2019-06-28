<?php

namespace Classes;

class Csv2Md
{
    /**
     * The CSV string (multilines)
     *
     * @var string
     */
    private $csv = '';

    /**
     * The column's delimiter (probably a ,)
     *
     * @var string
     */
    private $delim = ',';

    /**
     * The enclosure is the character to put for isolating string
     * Probably a double-quote
     *
     * @var string
     */
    private $enclosure = '"';

    /**
     * Character to use to delimit the columns in markdown$
     * The standard is the pipe character i.e. |
     *
     * @var string
     */
    private $tableSeparator = '|';

    private $header = '';
    private $rows = '';

    /**
     * Set the CSV content
     *
     * @param string $csv
     *
     * @return void
     */
    public function setCsv(string $csv)
    {
        $this->csv = $csv;
    }

    /**
     * Set the delimiter (; or ,)
     *
     * @param string $delim
     *
     * @return void
     */
    public function setDelimiter(string $delim)
    {
        $this->delim = $delim;
    }

    /**
     * Set the enclosure (f.i. " or ')
     *
     * @param string $enclosure
     *
     * @return void
     */
    public function setEnclosure(string $enclosure)
    {
        $this->enclosure = $enclosure;
    }

    /**
     * Set the table separator (f.i. | or :)
     *
     * @param string $tableSeparator
     *
     * @return void
     */
    public function setTableSeparator(string $tableSeparator)
    {
        $this->tableSeparator = $tableSeparator;
    }

    /**
     * Convert a string into an array.
     *
     * @param string $csv Convert a string into a PHP array
     *
     * @return array
     */
    private function toArray(): array
    {

        // Parse the rows
        $parsed = str_getcsv(trim($this->csv), "\n");

        $parsed_array = [];
        foreach ($parsed as &$row) {
            // Parse the items in rows
            if (null == $row) {
                continue;
            }
            $row = str_getcsv($row, $this->delim, $this->enclosure);
            array_push($parsed_array, $row);
        }

        $this->length     = $this->minRowLength($parsed_array);
        $this->col_widths = $this->maxColumnWidths($parsed_array);
        $header_array     = array_shift($parsed_array);
        $this->header     = $this->createHeader($header_array);
        $this->rows       = $this->createRows($parsed_array);

        return $parsed_array;
    }

    public function getMarkup(): string
    {
        $this->toArray();
        return $this->header . $this->rows;
    }

    protected function createRows(array $rows): string
    {
        $output = '';
        foreach ($rows as $row) {
            $output .= $this->createRow($row);
        }

        return $output;
    }

    protected function createRow(array $row): string
    {
        $output = $this->tableSeparator . ' ';

        // Only create as many columns as the minimal number of elements
        // in all rows. Otherwise this would not be a valid Markdown table
        for ($i = 0; $i < $this->length; ++$i) {
            $element = $this->padded(trim($row[$i]), $this->col_widths[$i]);
            $output .= $element;
            $output .= ' ' . $this->tableSeparator . ' ';
        }

        // row ends with a newline
        $output = trim($output) . "\n";

        return $output;
    }

    private function createHeader(array $header_array): string
    {
        return $this->createRow($header_array) . $this->createSeparator();
    }

    private function createSeparator(): string
    {
        $output = $this->tableSeparator . ' ';

        for ($i = 0; $i < $this->length; ++$i) {
            $output .= str_repeat('-', $this->col_widths[$i]);
            $output .= ' ' . $this->tableSeparator . ' ';
        }

        return trim($output) . "\n";
    }

    /**
     * Add padding to a string.
     */
    private function padded(string $str, int $width): string
    {
        if ($width < strlen($str)) {
            return $str;
        }

        $padding_length = $width - strlen($str);
        $padding        = str_repeat(' ', $padding_length);

        return $str . $padding;
    }

    private function minRowLength(array $arr): int
    {
        $min = PHP_INT_MAX;

        foreach ($arr as $row) {
            $row_length = count($row);
            if ($row_length < $min) {
                $min = $row_length;
            }
        }

        return $min;
    }

    // Calculate the maximum width of each column in characters
    private function maxColumnWidths(array $arr): array
    {
        // Set all column widths to zero.
        $column_widths = array_fill(0, $this->length, 0);

        foreach ($arr as $row) {
            foreach ($row as $k => $v) {
                if ($column_widths[$k] < strlen($v)) {
                    $column_widths[$k] = strlen($v);
                }

                if ($k == $this->length - 1) {
                    // We don't need to look any further since these elements
                    // will be dropped anyway because all table rows must have the
                    // same length to create a valid Markdown table.
                    break;
                }
            }
        }

        return $column_widths;
    }
}
