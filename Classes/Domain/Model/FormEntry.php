<?php
namespace Frappant\FrpFormAnswers\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/***
 *
 * This file is part of the "Form Answer Saver" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 !frappant <support@frappant.ch>
 *
 ***/
/**
 * FormEntry
 */
class FormEntry extends AbstractEntity
{
    /**
     * answers
     *
     * @var string
     */
    protected $answers = '';

    /**
     * fieldHash
     *
     * @var string
     */
    protected $fieldHash = '';

    /**
     * form
     *
     * @var string
     */
    protected $form = '';

    /**
     * exported
     *
     * @var bool
     */
    protected $exported = false;

    /**
     * @var int
     */
    protected int $crdate = 0;

    /**
     * @var int
     */
    protected int $submitUid = 0;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAnswers(): array
    {
        return json_decode($this->answers, true) ?? [];
    }

    /**
     * @param array<string, array<string, mixed>> $answers
     */
    public function setAnswers(array $answers): void
    {
        $this->answers = json_encode($answers);
        ksort($answers);

        $fields = "";
        foreach ($answers as $field => $value) {
            $fields .= $field;
        }
        $this->fieldHash = md5($fields);
    }

    /**
     * Returns the fieldHash
     *
     * @return string $fieldHash
     */
    public function getFieldHash()
    {
        return $this->fieldHash;
    }

    /**
     * Sets the fieldHash
     *
     * @param string $fieldHash
     * @return void
     */
    public function setFieldHash($fieldHash)
    {
        $this->fieldHash = md5($fieldHash);
    }

    /**
     * Returns the form
     *
     * @return string $form
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * Sets the form
     *
     * @param string $form
     * @return void
     */
    public function setForm($form)
    {
        $this->form = $form;
    }

    /**
     * Returns the exported
     *
     * @return bool $exported
     */
    public function getExported()
    {
        return $this->exported;
    }

    /**
     * Sets the exported
     *
     * @param bool $exported
     * @return void
     */
    public function setExported($exported)
    {
        $this->exported = $exported;
    }

    /**
     * Returns the boolean state of exported
     *
     * @return bool
     */
    public function isExported()
    {
        return $this->exported;
    }

    public function setCrdate(int $crdate): void
    {
        $this->crdate = $crdate;
    }

    public function getCrdate(): int
    {
        return $this->crdate;
    }

    public function setSubmitUid(int $submitUid): void
    {
        $this->submitUid = $submitUid;
    }

    public function getSubmitUid(): int
    {
        return $this->submitUid;
    }
}
