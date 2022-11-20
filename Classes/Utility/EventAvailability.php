<?php

declare(strict_types=1);

/*
 * This file is part of the Extension "sf_event_mgt" for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace DERHANSEN\SfEventMgt\Utility;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Check if an event record is available in a language
 */
class EventAvailability
{
    /**
     * Checks if an event record is available the given language id
     */
    public function check(int $languageId, int $eventId): bool
    {
        /** @var Site $site */
        $site = $this->getRequest()->getAttribute('site');
        $allAvailableLanguagesOfSite = $site->getAllLanguages();

        $targetLanguage = $this->getLanguageFromAllLanguages($allAvailableLanguagesOfSite, $languageId);
        if (!$targetLanguage) {
            throw new \UnexpectedValueException('Target language could not be found', 1608059129);
        }
        return $this->mustBeIncluded($eventId, $targetLanguage);
    }

    protected function mustBeIncluded(int $eventId, SiteLanguage $language): bool
    {
        // @extensionScannerIgnoreLine
        if ($language->getFallbackType() === 'strict' &&
            !$this->isEventAvailableInLanguage($eventId, $language->getLanguageId())
        ) {
            return false;
        }
        return true;
    }

    /**
     * @param SiteLanguage[] $allLanguages
     * @param int $languageId
     * @return SiteLanguage|null
     */
    protected function getLanguageFromAllLanguages(array $allLanguages, int $languageId): ?SiteLanguage
    {
        foreach ($allLanguages as $siteLanguage) {
            if ($siteLanguage->getLanguageId() === $languageId) {
                return $siteLanguage;
            }
        }
        return null;
    }

    protected function isEventAvailableInLanguage(int $eventId, int $language): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_sfeventmgt_domain_model_event');
        if ($language === 0) {
            $where = [
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($language, Connection::PARAM_INT)),
                    $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(-1, Connection::PARAM_INT))
                ),
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($eventId, Connection::PARAM_INT)),
            ];
        } else {
            $where = [
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->andX(
                        $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(-1, Connection::PARAM_INT)),
                        $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($eventId, Connection::PARAM_INT))
                    ),
                    $queryBuilder->expr()->andX(
                        $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($eventId, Connection::PARAM_INT)),
                        $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($language, Connection::PARAM_INT))
                    ),
                    $queryBuilder->expr()->andX(
                        $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($eventId, Connection::PARAM_INT)),
                        $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                        $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($language, Connection::PARAM_INT))
                    )
                ),
            ];
        }

        $eventsFound = $queryBuilder
           ->count('uid')
           ->from('tx_sfeventmgt_domain_model_event')
           ->where(...$where)
           ->execute()
           ->fetchOne();

        return $eventsFound > 0;
    }

    protected function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
