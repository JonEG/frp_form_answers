<?php
namespace Frappant\FrpFormAnswers\View\FormEntry;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 !frappant <support@frappant.ch>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * ExportCSV
 */
class ExportCsv
{
    /**
     * @var array<string, string>
     */
    protected array $delimiter = [
        'komma' => ',',
        'semikolon' => ';',
        'tab' => '\t',
    ];

    /**
     * @var array<string, string>
     */
    protected array $enclosure = [
        'single' => '\'',
        'double' => '"',
    ];

    /**
     * @var array<string, mixed>
     */
    protected array $variables = [];

    public function assign(string $key, mixed $value): self
    {
        $this->variables[$key] = $value;
        return $this;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function assignMultiple(array $values): self
    {
        foreach ($values as $key => $value) {
            $this->assign($key, $value);
        }

        return $this;
    }

    public function initializeView(): void
    {
    }

    public function render(): string
    {
        ob_start();
        /** @var array<int|string, array<int|string, mixed>> $rows */
        $rows = $this->variables['rows'];
        /** @var FormEntryDemand $formEntryDemand */
        $formEntryDemand = $this->variables['formEntryDemand'];
        foreach ($rows as $fields) {
            echo $this->fputcsv2(
                $fields,
                $this->delimiter[$formEntryDemand->getDelimiter()],
                $this->enclosure[$formEntryDemand->getEnclosure()]
            );
        }

        return (string)ob_get_clean();
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function renderPartial(string $partialName, string $sectionName, array $variables, bool $ignoreUnknown = false): string
    {
        return $this->render();
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function renderSection(string $sectionName, array $variables = [], bool $ignoreUnknown = false): string
    {
        return $this->render();
    }

    /**
     * @param array<int|string, mixed> $fields
     */
    private function fputcsv2(array $fields, string $delimiter = ';', string $enclosure = '"', bool $mysqlNull = false): string
    {
        $delimiterEsc = preg_quote($delimiter, '/');
        $enclosureEsc = preg_quote($enclosure, '/');

        $output = [];
        foreach ($fields as $field) {
            if ($field === null && $mysqlNull) {
                $output[] = 'NULL';
                continue;
            }
            if ($field instanceof \DateTime) {
                $field = $field->format('r');
            }

            $output[] = preg_match("/(?:{$delimiterEsc}|{$enclosureEsc}|\s)/", (string)$field) ? (
                $enclosure . str_replace($enclosure, $enclosure . $enclosure, (string)$field) . $enclosure
            ) : $field;
        }

        return join($delimiter, $output) . "\n";
    }
}
