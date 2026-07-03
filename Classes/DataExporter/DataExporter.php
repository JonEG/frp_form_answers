<?php

namespace Frappant\FrpFormAnswers\DataExporter;

use Frappant\FrpFormAnswers\Domain\Model\FormEntry;
use Frappant\FrpFormAnswers\Domain\Model\FormEntryDemand;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class DataExporter
{
    /**
     * @param list<FormEntry> $rowAnswers
     * @return array<int|string, array<int|string, mixed>>
     */
    public function getExport(array $rowAnswers, FormEntryDemand $formEntryDemand, bool $useSubmitUid): array
    {
        $rows = [];
        $header = [];
        $headerKeys = array_values($rowAnswers[0]->getAnswers());

        // add header for crdate
        $headerKeys[] = [
            'value' => '',
            'conf' => [
                'label' => LocalizationUtility::translate('LLL:EXT:frp_form_answers/Resources/Private/Language/locallang_db.xlf:tx_frpformanswers_domain_model_formentry.crdate'),
                'inputType' => 'DateTime',
            ],
        ];

        $this->setHeaders($formEntryDemand, $headerKeys, $header);

        foreach ($rowAnswers as $entry) {
            $uid = ($useSubmitUid) ? $entry->getSubmitUid() : $entry->getUid();

            if ($formEntryDemand->getUidLabel()) {
                $rows[$uid][$formEntryDemand->getUidLabel()] = $uid;
            }
            foreach ($entry->getAnswers() as $fieldName => $field) {
                if ($this->isExportableType($field['conf']['inputType'])) {
                    $rows[$uid][$fieldName] = (is_array($field['value'] ?? '') ? implode(',', $field['value']) : ($field['value'] ?? ''));
                }
            }
            $rows[$uid]['crdate'] = $entry->getCrdate();
        }

        array_unshift($rows, $header);

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $headerKeys
     * @param list<string> $header
     */
    protected function setHeaders(FormEntryDemand $formEntryDemand, array $headerKeys, array &$header): void
    {
        if ($formEntryDemand->getUidLabel()) {
            $header[] = (string)$formEntryDemand->getUidLabel();
        }

        foreach ($headerKeys as $field => $val) {
            if ($this->isExportableType($val['conf']['inputType'])) {
                $header[] = (string)($val['conf']['label'] ?: $field);
            }
        }
    }

    private function isExportableType(string $inputType): bool
    {
        $typesToSkip = [
            'Fieldset',
            'StaticText',
            'GridRow'
        ];
        return !\in_array($inputType, $typesToSkip);
    }
}
