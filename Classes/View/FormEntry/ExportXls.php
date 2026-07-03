<?php
namespace Frappant\FrpFormAnswers\View\FormEntry;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
 * ExportXls
 */
class ExportXls
{
    protected static ?Spreadsheet $spreadsheet = null;

    /**
     * View variables and their values
     *
     * @var array<string, mixed>
     * @see assign()     */
    protected array $variables = [];

    /**
     * Add a variable to $this->viewData.
     * Can be chained, so $this->view->assign(..., ...)->assign(..., ...); is possible
     */
    public function assign(string $key, mixed $value): self
    {
        $this->variables[$key] = $value;
        return $this;
    }

    /**
     * Add multiple variables to $this->viewData.
     *
     * @param array<string, mixed> $values
     */
    public function assignMultiple(array $values): self
    {
        foreach ($values as $key => $value) {
            $this->assign($key, $value);
        }

        return $this;
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function render(mixed $data = null): string
    {
        if (null === self::$spreadsheet) {
            self::$spreadsheet = new Spreadsheet();
            self::$spreadsheet->getProperties()->setCreator('Frappant Forms Export')
                ->setLastModifiedBy('Frappant Forms Export')
                ->setCreated(time());
        }

        /** @var array<int|string, array<int|string, mixed>> $rows */
        $rows = $this->variables['rows'];
        // PHPExcel does not work with associative arrays - then to indexed array
        foreach ($rows as $key => $value) {
            $this->setIndexedArray($rows[$key]);
        }

        self::$spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        $objWriter = new Xlsx(self::$spreadsheet);

        ob_start();
        $objWriter->save('php://output');

        return (string)ob_get_clean();
    }

    /**
     * function setIndexedArray
     * Sets an associative array to an indexed array
     * @param array<int|string, mixed> $arr
     */
    private function setIndexedArray(array &$arr): void
    {
        $arr = array_values($arr);
    }
}
