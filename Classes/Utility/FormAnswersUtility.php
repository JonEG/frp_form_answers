<?php
namespace Frappant\FrpFormAnswers\Utility;

use Frappant\FrpFormAnswers\Domain\Repository\FormEntryRepository;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;

class FormAnswersUtility
{
    protected FormEntryRepository $formEntryRepository;

    protected PageRepository $pageRepository;

    public function __construct(PageRepository $pageRepository, FormEntryRepository $formEntryRepository)
    {
        $this->pageRepository = $pageRepository;
        $this->formEntryRepository = $formEntryRepository;
    }

    /**
     * @return array<int, array<string, array<string, int>>>
     */
    public function prepareFormAnswersArray(): array
    {
        $act_pid = (int)($_GET['id'] ?? 0);
        $pageIds = [];

        $startPointPids = ($act_pid > 0 ? [$act_pid] : $GLOBALS['BE_USER']->returnWebmounts());
        foreach ($startPointPids as $pageId) {
            foreach ($this->formEntryRepository->findAllInPidAndRootline($pageId) as $formEntry) {
                if ((is_int($formEntry->getPid())) && ($formEntry->getForm() !== null)) {
                    if (isset($pageIds[$formEntry->getPid()][$formEntry->getForm()]['tot'])) {
                        $pageIds[$formEntry->getPid()][$formEntry->getForm()]['tot'] += 1;
                    } else {
                        $pageIds[$formEntry->getPid()][$formEntry->getForm()]['tot'] = 1;
                    }

                    if (!$formEntry->isExported()) {
                        if (isset($pageIds[$formEntry->getPid()][$formEntry->getForm()]['new'])) {
                            $pageIds[$formEntry->getPid()][$formEntry->getForm()]['new'] += 1;
                        } else {
                            $pageIds[$formEntry->getPid()][$formEntry->getForm()]['new'] = 1;
                        }
                    }
                }
            }
        }

        $id = (int)($_GET['id'] ?? 0);
        unset($pageIds[$id]);

        return $pageIds;
    }

    /**
     * @param list<int> $pid
     * @return list<string>
     */
    public function getAllFormNames(array $pid): array
    {
        $querySettings = GeneralUtility::makeInstance(QuerySettingsInterface::class);
        $querySettings->setRespectStoragePage(true);
        $querySettings->setStoragePageIds($pid);
        $this->formEntryRepository->setDefaultQuerySettings($querySettings);
        $allFormAnswers = $this->formEntryRepository->findAll();
        $formNames = [];
        foreach ($allFormAnswers as $answer) {
            $formNames[$answer->getForm()] = $answer->getForm();
        }

        return array_keys($formNames);
    }

    /**
     * @return list<string>
     */
    public function getAllFormHashes(int $pid): array
    {
        $querySettings = GeneralUtility::makeInstance(QuerySettingsInterface::class);
        $querySettings->setRespectStoragePage(true);
        $querySettings->setStoragePageIds([$pid]);
        $this->formEntryRepository->setDefaultQuerySettings($querySettings);
        $allFormAnswers = $this->formEntryRepository->findAll();

        $formHashes = [];
        foreach ($allFormAnswers as $answer) {
            $formHashes[$answer->getFieldHash()] = $answer->getFieldHash();
        }

        return array_keys($formHashes);
    }

    public function injectFormEntryRepository(FormEntryRepository $formEntryRepository): void
    {
        $this->formEntryRepository = $formEntryRepository;
    }

    public function injectPageRepository(PageRepository $pageRepository): void
    {
        $this->pageRepository = $pageRepository;
    }
}
