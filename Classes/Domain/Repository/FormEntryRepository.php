<?php
namespace Frappant\FrpFormAnswers\Domain\Repository;

use Frappant\FrpFormAnswers\Database\QueryGenerator;
use Frappant\FrpFormAnswers\Domain\Model\FormEntry;
use Frappant\FrpFormAnswers\Domain\Model\FormEntryDemand;
use Frappant\FrpFormAnswers\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

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
 * The repository for FormEntries
 *
 * @extends Repository<FormEntry>
 */
class FormEntryRepository extends Repository
{
    public function __construct(protected PersistenceManagerInterface $persistenceManager)
    {
    }

    /**
     * @return QueryResultInterface<int, FormEntry>
     */
    public function findByDemand(FormEntryDemand $formEntryDemand, int $pid = 0): QueryResultInterface
    {
        $query = $this->createQuery();

        if ($formEntryDemand->getAllPids()) {
            $settings = $query->getQuerySettings();
            $settings->setRespectStoragePage(false);
            $query->setQuerySettings($settings);
        } else {
            $query->getQuerySettings()->setRespectStoragePage(true);
            $query->getQuerySettings()->setStoragePageIds([$pid]);
        }

        $constraints = [];

        if (!$formEntryDemand->getSelectAll()) {
            $constraints[] = $query->equals('exported', false);
        }

        if ($formEntryDemand->getForm()) {
            $constraints[] = $query->equals('fieldHash', $formEntryDemand->getForm());
        }

        if ($formEntryDemand->getFormName()) {
            $constraints[] = $query->equals('form', $formEntryDemand->getFormName());
        }

        if (count($constraints)) {
            $query->matching($query->logicalAnd(...$constraints));
        }

        return $query->execute();
    }

    /**
     * @return QueryResultInterface<int, FormEntry>
     */
    public function findAllInPidAndRootline(int $pid): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);

        $queryGenerator = GeneralUtility::makeInstance(QueryGenerator::class);
        $pids = array_map(
            static fn (string $pageId): int => (int)$pageId,
            GeneralUtility::trimExplode(',', $queryGenerator->getTreeList($pid, 20, 0, '1'), true)
        );

        if (!BackendUtility::isBackendAdmin()) {
            $pids = BackendUtility::filterPagesForAccess($pids);
        }

        if (count($pids) > 0) {
            $query->matching($query->in('pid', $pids));
        }

        $query->setOrderings(['pid' => QueryInterface::ORDER_ASCENDING]);

        return $query->execute();
    }

    public function getLastFormAnswerByIdentifyer(string $form): ?FormEntry
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->setOrderings(
            [
                'submitUid' => QueryInterface::ORDER_DESCENDING,
            ]
        );

        $query->matching($query->equals('form', $form));
        $query->setLimit(1);

        $result = $query->execute()->getFirst();

        return $result instanceof FormEntry ? $result : null;
    }

    /**
     * @param iterable<FormEntry> $forms
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function setFormsToExported(iterable $forms): void
    {
        foreach ($forms as $entry) {
            $entry->setExported(true);
            $this->update($entry);
        }

        $this->persistenceManager->persistAll();
    }
}
