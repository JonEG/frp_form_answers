<?php
namespace Frappant\FrpFormAnswers\Controller;

use Frappant\FrpFormAnswers\Domain\Model\FormEntry;
use TYPO3\CMS\Core\Imaging\IconSize;
use Frappant\FrpFormAnswers\DataExporter\DataExporter;
use Frappant\FrpFormAnswers\Domain\Model\FormEntryDemand;
use Frappant\FrpFormAnswers\Domain\Repository\FormEntryRepository;
use Frappant\FrpFormAnswers\Utility\FormAnswersUtility;
use Frappant\FrpFormAnswers\View\FormEntry\ExportCsv;
use Frappant\FrpFormAnswers\View\FormEntry\ExportXls;
use Frappant\FrpFormAnswers\View\FormEntry\ExportXml;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/***
 *
 * This file is part of the "Form Answer Saver" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2022 !Frappant <support@frappant.ch>
 *
 ***/

/**
 * FormEntryController
 */
class FormEntryController extends ActionController
{
    /**
     * @var ModuleTemplateFactory $moduleTemplateFactory
     */
    protected ModuleTemplateFactory $moduleTemplateFactory;

    /**
     * @var IconFactory $iconFactory
     */
    protected IconFactory $iconFactory;

    /**
     * @var FormAnswersUtility $formAnswersUtility
     */
    protected FormAnswersUtility $formAnswersUtility;

    /**
     * @var FormEntryRepository $formEntryRepository
     */
    protected FormEntryRepository $formEntryRepository;

    /**
     * @var DataExporter
     */
    protected DataExporter $dataExporter;

    /**
    * @var PageRepository $pageRepository
    */
    protected PageRepository $pageRepository;

    /**
     * @var PersistenceManager  $persistenceManager
     */
    protected PersistenceManager $persistenceManager;

    /**
     * @var string $filename
     */
    protected string $filename = '';

    /**
     * @var integer
     */
   protected $pid;

    /**
     * http headers to send with filedownload request @see exportAction
     *
     * @var array<int, array{0: string, 1: string|string[]}>
     */
    protected array $requestHeaders = [];

    protected FormEntryDemand $formEntryDemand;


    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        IconFactory $iconFactory,
        FormAnswersUtility $formAnswersUtility,
        FormEntryRepository $formEntryRepository,
        DataExporter $dataExporter,
        PageRepository $pageRepository,
        PersistenceManager $persistenceManager,
        private readonly ConnectionPool $connectionPool
    ) {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->iconFactory = $iconFactory;
        $this->formAnswersUtility = $formAnswersUtility;
        $this->formEntryRepository = $formEntryRepository;
        $this->dataExporter = $dataExporter;
        $this->pageRepository = $pageRepository;
        $this->pid = $_GET['id'] ?? 0;
        $this->persistenceManager = $persistenceManager;
    }

    /**
     * action list, Show saved form entries from database
     *
     * @return ResponseInterface
     */
    public function listAction(): ResponseInterface
    {
        $pageIds = $this->formAnswersUtility->prepareFormAnswersArray();
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        if (count($pageIds) > 0) {
            $moduleTemplate->assign('subPagesWithFormEntries', $this->pageRepository->getMenuForPages(array_keys($pageIds)));
            $moduleTemplate->assign('formEntriesStatus', $pageIds);
        }
        $moduleTemplate->assign('pid', $this->pid);
        $moduleTemplate->assign('formNames', $this->formAnswersUtility->getAllFormNames([$this->pid]));
        $moduleTemplate->assign('settings', $this->settings);


        $this->createMenu($moduleTemplate);
	    $this->createButtons($moduleTemplate);
        return $moduleTemplate->renderResponse($this->templateFilenameFromRequest());
    }

    /**
     * action show
     *
     * @param FormEntry $formEntry
     * @return ResponseInterface
     */
    public function showAction(FormEntry $formEntry): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->assign('formEntry', $formEntry);

        return $moduleTemplate->renderResponse('FormEntry/Show');
    }

    /**
     * action prepareRemove, Show form entries which are marked as deleted
     *
     * @return ResponseInterface
     */
    public function prepareRemoveAction(): ResponseInterface
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_frpformanswers_domain_model_formentry');
        $queryBuilder->getRestrictions()->removeAll();

        $count = $queryBuilder->count('*')
            ->from('tx_frpformanswers_domain_model_formentry')
            ->where($queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($this->pid, Connection::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)))
            ->executeQuery()->fetchFirstColumn();
        //DebuggerUtility::var_dump($count);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->assign('count', $count[0]);

        $this->createMenu($moduleTemplate);
	    $this->createButtons($moduleTemplate);

        return $moduleTemplate->renderResponse($this->templateFilenameFromRequest());
    }

    /**
     * action mark single entry as deleted
     *
     * @throws IllegalObjectTypeException
     */
    public function removeEntryAction(): ResponseInterface
    {
        $arguments = $this->request->getArguments();
        $uid = $arguments['uid'];
        $pid = $arguments['pid'];
        $entry = $this->formEntryRepository->findByUid($uid);

        $this->formEntryRepository->remove($entry);
        $this->persistenceManager->persistAll();

        $this->addFlashMessage(
            'Deleted entry with uid: ' . $uid,
            'Entry deleted',
            ContextualFeedbackSeverity::OK,
            true
        );

        return $this->redirect('list', null, null, ['id' => $pid]);
    }

    /**
     * action remove, Remove form entries which are marked as deleted
     *
     * @return ResponseInterface
     */
    public function removeAction()
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_frpformanswers_domain_model_formentry');

        $queryBuilder->delete('tx_frpformanswers_domain_model_formentry')
            ->where($queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($this->pid, Connection::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)))
            ->executeStatement();

        $this->addFlashMessage(
            LocalizationUtility::translate('LLL:EXT:frp_form_answers/Resources/Private/Language/locallang_be.xlf:flashmessage.removeEntries.body', null, [$this->pid]),
            LocalizationUtility::translate('LLL:EXT:frp_form_answers/Resources/Private/Language/locallang_be.xlf:flashmessage.removeEntries.header'),
            ContextualFeedbackSeverity::OK,
            true);

        return $this->redirect('list', null, null, ['id' => $this->pid]);
    }

    /**
     * action prepareExport
     *
     * @return ResponseInterface
     */
    public function prepareExportAction(): ResponseInterface
    {
        $demandObject = GeneralUtility::makeInstance(FormEntryDemand::class);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        $this->formEntryDemand = $demandObject;
        $moduleTemplate->assign('formEntryDemand', $demandObject);
        $moduleTemplate->assign('formHashes', $this->formAnswersUtility->getAllFormHashes($this->pid));

        $this->createMenu($moduleTemplate);
	    $this->createButtons($moduleTemplate);

        return $moduleTemplate->renderResponse($this->templateFilenameFromRequest());
    }

    public function initializeExportAction(): void
    {
        $args = $this->request->getArguments();
        $format = $args['format'];
        // $this->filename = $args['formEntryDemand']['formName'];

        $charset = (strlen($args['formEntryDemand']['charset'] ?? '') > 0 ? $args['formEntryDemand']['charset'] : 'iso-8859-1');

        switch ($format){
            case 'Csv':
                $this->filename = (strlen($this->filename) > 0 ? $this->filename.'.csv' : 'export.csv');
                $this->setRequestHeader('Content-Type', 'application/force-download');
                $this->setRequestHeader('Content-Type', 'text/csv');
                $this->setRequestHeader('Content-Disposition', "attachment;filename=$this->filename");
                $this->setRequestHeader('Content-Transfer-Encoding', 'binary');
                $this->setRequestHeader('Content-Type', "application/download; charset=$charset");
            break;
            case 'Xls':
                $this->filename = (strlen($this->filename) > 0 ? $this->filename.'.xlsx' : 'export.xlsx');
                $this->setRequestHeader('Content-Type', 'application/force-download');
                $this->setRequestHeader('Content-Disposition', "attachment;filename=$this->filename");
                $this->setRequestHeader('Content-Type', "application/download; charset=$charset");
            break;
            case 'Xml':
                $this->filename = (strlen($this->filename) > 0 ? $this->filename.'.xml' : 'export.xml');
                $this->setRequestHeader('Content-Type', 'application/force-download');
                $this->setRequestHeader('Content-Type', 'application/xml');
                $this->setRequestHeader('Content-Disposition', "attachment;filename=$this->filename");
                $this->setRequestHeader('Content-Transfer-Encoding', 'binary');
                $this->setRequestHeader('Content-Type', "application/download; charset=$charset");
            break;
        }
    }

	/**
	 * export Action
     *
	 * @param FormEntryDemand|null $formEntryDemand
	 */
    public function exportAction(?FormEntryDemand $formEntryDemand = null): ResponseInterface
    {
        $format = $this->request->getArguments()['format'];
        $pid = (int)($_GET['id'] ?? 0);

        if ($formEntryDemand === null) {
            $this->addFlashMessage('No Demand set',
                'No Demand found',
                ContextualFeedbackSeverity::ERROR,
                true
            );
            return $this->redirect('list', null, null, ['id' => $this->pid]);
        }

        $formEntryDemand->setAllPids($this->request->getArguments()['allPids'] ?? false);
        $formEntries = $this->formEntryRepository->findByDemand($formEntryDemand, $pid);
        if (count($formEntries) === 0) {
            $this->addFlashMessage('No entries found with your criteria',
                'No Entries found',
                ContextualFeedbackSeverity::WARNING,
                true
            );
            return $this->redirect('list', null, null, ['id' => $this->pid]);
        }

        $extensionConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['frp_formanswers'] ?? null;
        $exportData = $this->dataExporter->getExport(
            iterator_to_array($formEntries),
            $formEntryDemand,
            $extensionConfiguration['useSubmitUid']['value'] ?? false
        );

        $this->formEntryRepository->setFormsToExported($formEntries);


        $exporter = match ($format) {
            'Csv' => new ExportCsv(),
            'Xls' => new ExportXls(),
            'Xml' => new ExportXml(),
            default => throw new \InvalidArgumentException('Unsupported export format: ' . $format),
        };

        $exporter->assign('rows', $exportData);
        $exporter->assign('formEntryDemand', $formEntryDemand);

        $content = $exporter->render();

        $stream = new Stream('php://memory', 'rw');
        $stream->write($content);

        return match ($format) {
            'Csv' => new Response($stream, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="export.csv"',
            ]),
            'Xls' => new Response($stream, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="export.xlsx"',
            ]),
            'Xml' => new Response($stream, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="export.xml"',
            ]),
        };
    }

    /**
     * Prepare the download request
     *
     * @param string $renderedContent File contents which would be downloaded
     */
    protected function generateDownloadResponse(string $renderedContent): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();

        foreach($this->getRequestHeaders() as $header) {
            $response = $response->withHeader("$header[0]", "$header[1]");
        }

        $response = $response->withBody($this->streamFactory->createStream($renderedContent));

        return $response;
    }

    /**
     * @todo check where this method is used
     */
    public function deleteFormnameAction(string $formName = ''): ResponseInterface
    {
        if(strlen($formName) > 0){

            $queryBuilder = $this->connectionPool->getConnectionForTable('tx_frpformanswers_domain_model_formentry');

            $queryBuilder->update(
                'tx_frpformanswers_domain_model_formentry',
                [ 'deleted' => 1 ], // set
                [ 'form' => $formName, 'pid' => $this->pid]
            );

            $this->addFlashMessage(
                LocalizationUtility::translate('LLL:EXT:frp_form_answers/Resources/Private/Language/de.locallang_be.xlf:flashmessage.deleteFormName.body', 'FrpFormAnswers', [$formName, $this->pid]),
                LocalizationUtility::translate('LLL:EXT:frp_form_answers/Resources/Private/Language/de.locallang_be.xlf:flashmessage.deleteFormName.header'),
                ContextualFeedbackSeverity::OK,
                true);
        }
        return $this->redirect('list', null, null, ['id' => $this->pid]);
    }

    /**
     * Create menu
     *
     */
    protected function createMenu(ModuleTemplate $moduleTemplate): void
    {
        $this->uriBuilder->setRequest($this->request);

        $menu = $moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        // $menu = $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('frpformanswers_main');

        $actions = [
            ['action' => 'list', 'label' => 'Overview'],
            ['action' => 'prepareExport', 'label' => 'Export'],
            ['action' => 'prepareRemove', 'label' => 'Remove'],
        ];

        foreach ($actions as $action) {
            $item = $menu->makeMenuItem()
                ->setTitle($action['label'])
                ->setHref($this->uriBuilder->reset()->uriFor($action['action'], [], 'FormEntry'))
                ->setActive($this->request->getControllerActionName() === $action['action']);
            $menu->addMenuItem($item);
        }

        $moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
    }

    /**
     * Create the panel of buttons
     *
     */
    protected function createButtons(ModuleTemplate $moduleTemplate): void
    {
        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();

        $this->uriBuilder->setRequest($this->request);

        // Refresh
        $refreshButton = $buttonBar->makeLinkButton()
            ->setHref(GeneralUtility::getIndpEnv('REQUEST_URI'))
            ->setTitle($this->getLanguageService()->sL('core.core:labels.reload'))
            ->setIcon($this->iconFactory->getIcon('actions-refresh', IconSize::SMALL));
        $buttonBar->addButton($refreshButton, ButtonBar::BUTTON_POSITION_RIGHT);

    }

    /**
     * @param string $name Case-insensitive header field name.
     * @param string|string[] $value Header value(s).
     */
    protected function setRequestHeader(string $name, string|array $value): void
    {
        $this->requestHeaders[] = [$name, $value];
    }

    /**
     * @return array<int, array{0: string, 1: string|string[]}>
     */
    protected function getRequestHeaders(): array
    {
        return $this->requestHeaders;
    }

    /**
     * Returns the LanguageService
     *
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    private function templateFilenameFromRequest(): string
    {
        $extbaseRequestParameters = $this->request->getAttribute('extbase');
        $templateFileName = $extbaseRequestParameters->getControllerName() . '/' .
            ucfirst($extbaseRequestParameters->getControllerActionName());
        return $templateFileName;
    }
}
