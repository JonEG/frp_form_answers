<?php
namespace Frappant\FrpFormAnswers\ViewHelpers\Be\Link;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Routing\UriBuilder;

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
 * Renders a link for a new record.
 *
 * /typo3/index.php?route=/record/edit&token=d7b2e14e24824711081ee8731549ca58afac0648&edit[tx_frpredirects_domain_model_redirect][2]=edit&returnUrl=/typo3/index.php?M=web_list&moduleToken=ae0ea6fabda3a2a34a8873319b91f8dc6010bf2f&id=0&imagemode=1
 */
class BeLinkViewHelper extends AbstractTagBasedViewHelper
{
    protected $tagName = 'a';

    public function __construct(private readonly UriBuilder $uriBuilder)
    {
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('pageUid', 'int', 'Page Uid', false);
    }

    public function render(): string
    {
        $returnUrl = $this->getRequestUri();
        $urlParameters = [
            'returnUrl' => $returnUrl,
            'id' => $this->arguments['pageUid'],
        ];
        $uri = $this->getModuleUrl($urlParameters);
        $this->tag->addAttribute('href', $uri);
        $this->tag->setContent($this->renderChildren());
        $this->tag->forceClosingTag(true);

        return $this->tag->render();
    }

    protected function getRequestUri(): string
    {
        return (string)GeneralUtility::getIndpEnv('REQUEST_URI');
    }

    /**
     * @param array<string, mixed> $urlParameters
     * @throws RouteNotFoundException
     */
    protected function getModuleUrl(array $urlParameters): string
    {
        return (string)$this->uriBuilder->buildUriFromRoute('web_FrpFormAnswersFormanswers', $urlParameters);
    }
}
