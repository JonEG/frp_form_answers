<?php
namespace Frappant\FrpFormAnswers\Utility;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility as BackendUtilityCore;

/**
 * Class BackendUtility
 */
class BackendUtility extends BackendUtilityCore
{
    public static function isBackendAdmin(): bool
    {
        if (isset(self::getBackendUserAuthentication()->user)) {
            return self::getBackendUserAuthentication()->user['admin'] === 1;
        }

        return false;
    }

    /**
     * Filter a pid array with only the pages that are allowed to be viewed from the backend user.
     * If the backend user is an admin, show all of course - so ignore this filter.
     *
     * @param list<int> $pids
     * @return list<int>
     */
    public static function filterPagesForAccess(array $pids): array
    {
        if (!self::isBackendAdmin()) {
            $pageRepository = GeneralUtility::makeInstance(PageRepository::class);

            $newPids = [];
            foreach ($pids as $pid) {
                $page = $pageRepository->getPage($pid);
                if (self::getBackendUserAuthentication()->doesUserHaveAccess($page, 1)) {
                    $newPids[] = $pid;
                }
            }
            $pids = $newPids;
        }

        return $pids;
    }

    protected static function getBackendUserAuthentication(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * Get current PID in backend.
     * Uses various fallbacks depending on current view and backend module.
     */
    public static function getCurrentPid(?int $pageUid = null): int
    {
        if (!$pageUid) {
            $pageUid = (int) ($GLOBALS['_REQUEST']['popViewId'] ?? 0);
        }
        if (!$pageUid) {
            $pageUid = (int) preg_replace('/(.*)(id=)([0-9]*)(.*)/i', '\\3', (string)($GLOBALS['_REQUEST']['returnUrl'] ?? ''));
        }
        if (!$pageUid) {
            $pageUid = (int) preg_replace('/(.*)(id=)([0-9]*)(.*)/i', '\\3', (string)($GLOBALS['_POST']['returnUrl'] ?? ''));
        }
        if (!$pageUid) {
            $pageUid = (int) preg_replace('/(.*)(id=)([0-9]*)(.*)/i', '\\3', (string)($GLOBALS['_GET']['returnUrl'] ?? ''));
        }
        if (!$pageUid && isset($GLOBALS['TYPO3_REQUEST'])) {
            $pageUid = (int) $GLOBALS['TYPO3_REQUEST']->getAttribute('frontend.page.information')->getId();
        }
        if (!$pageUid) {
            $pageUid = (int) ($_GET['id'] ?? 0);
        }

        return $pageUid;
    }
}
