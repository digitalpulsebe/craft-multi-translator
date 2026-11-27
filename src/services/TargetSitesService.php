<?php

namespace digitalpulsebe\craftmultitranslator\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\ElementHelper as CraftElementHelper;
use craft\helpers\StringHelper;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\MultiTranslator;

/**
 * Resolves translation targets.
 *
 * A target value is one of:
 *   - 'all'             every available target site (the legacy '_ALL_' is accepted as an alias)
 *   - 'group:<handle>'  the sites of a configured target group
 *   - a site id or site handle
 *
 * A site is an available target when it is enabled as a translation target in the settings,
 * the current user can edit it and, when an element is given, the element supports it.
 */
class TargetSitesService extends Component
{
    public const ALL = 'all';
    public const LEGACY_ALL = '_ALL_';
    public const GROUP_PREFIX = 'group:';

    /**
     * Whether the site may be used as translation target (setting "Target sites").
     */
    public function isTargetEnabled(Site $site): bool
    {
        return !in_array($site->uid, $this->getDisabledTargetSiteUids(), true);
    }

    /**
     * Only the disabled sites are stored, so every site (also one that is added later) is a target by default.
     *
     * @return string[] uids of the sites that are disabled as translation target
     */
    public function getDisabledTargetSiteUids(): array
    {
        $uids = MultiTranslator::getInstance()->settingsService->getProviderSettings()->getSetting('disabledTargetSites', []);

        return is_array($uids) ? array_values(array_filter($uids, 'is_string')) : [];
    }

    /**
     * Convert the posted "Target sites" table (rows keyed by site uid with an 'enabled' lightswitch)
     * to the list of disabled site uids.
     *
     * @return string[]
     */
    public function normalizeTargetSiteRows(array $rows): array
    {
        $disabled = [];

        foreach ($rows as $siteUid => $row) {
            if (is_array($row) && empty($row['enabled']) && in_array((string) $siteUid, $this->getAllSiteUids(), true)) {
                $disabled[] = (string) $siteUid;
            }
        }

        return $disabled;
    }

    /**
     * Configured target groups.
     *
     * @return array<string, array{name: string, handle: string, siteUids: string[]}> keyed by handle
     */
    public function getTargetGroups(): array
    {
        $rows = MultiTranslator::getInstance()->settingsService->getProviderSettings()->getSetting('targetGroups', []);

        if (!is_array($rows)) {
            return [];
        }

        $groups = [];

        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['handle'])) {
                continue;
            }

            $groups[$row['handle']] = [
                'name' => ($row['name'] ?? '') ?: $row['handle'],
                'handle' => $row['handle'],
                'siteUids' => is_array($row['sites'] ?? null) ? $row['sites'] : [],
            ];
        }

        return $groups;
    }

    /**
     * Clean up the posted target groups: drop empty rows, make sure every group has a unique handle
     * and only keep existing site uids.
     *
     * @return array<int, array{name: string, handle: string, sites: string[]}>
     */
    public function normalizeTargetGroupRows(array $rows): array
    {
        $normalized = [];
        $usedHandles = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $handle = trim((string) ($row['handle'] ?? ''));
            $handle = StringHelper::toHandle($handle !== '' ? $handle : $name);

            if ($handle === '') {
                continue;
            }

            $uniqueHandle = $handle;
            $i = 2;
            while (in_array($uniqueHandle, $usedHandles, true)) {
                $uniqueHandle = $handle . $i++;
            }
            $usedHandles[] = $uniqueHandle;

            $sites = is_array($row['sites'] ?? null) ? $row['sites'] : [];
            $sites = array_values(array_unique(array_filter(
                $sites,
                fn($uid) => is_string($uid) && in_array($uid, $this->getAllSiteUids(), true)
            )));

            $normalized[] = [
                'name' => $name !== '' ? $name : $uniqueHandle,
                'handle' => $uniqueHandle,
                'sites' => $sites,
            ];
        }

        return $normalized;
    }

    public function isMultiTarget(mixed $target): bool
    {
        $target = (string) $target;

        return $target === self::ALL || $target === self::LEGACY_ALL || str_starts_with($target, self::GROUP_PREFIX);
    }

    /**
     * Sites the current user may translate to, in the order of the site settings.
     *
     * @param ElementInterface|null $element limit to the sites supported by this element
     * @param int|null $excludeSiteId usually the source site
     * @param bool $withUnpropagatedSites include supported sites the element is not propagated to yet
     * @return Site[]
     */
    public function getAvailableTargetSites(?ElementInterface $element = null, ?int $excludeSiteId = null, bool $withUnpropagatedSites = true, ?User $user = null): array
    {
        $user = $user ?? Craft::$app->getUser()->getIdentity();

        $supportedSiteIds = $element ? $this->getSupportedSiteIds($element, $withUnpropagatedSites) : null;

        return array_values(array_filter(
            Craft::$app->getSites()->getAllSites(),
            fn(Site $site) => $site->id !== $excludeSiteId
                && $this->isTargetEnabled($site)
                && ($supportedSiteIds === null || in_array($site->id, $supportedSiteIds, true))
                && $user?->can('editSite:' . $site->uid)
        ));
    }

    /**
     * Resolve a target value to the sites to translate to.
     *
     * For a multi target ('all' or a group) the source site is always skipped. A single site is returned
     * as long as it is enabled as translation target; the permission check is left to the caller.
     *
     * @return Site[]
     */
    public function resolveTargetSites(int|string $target, ?ElementInterface $element, int $sourceSiteId, bool $withUnpropagatedSites = true): array
    {
        $target = (string) $target;

        if ($this->isMultiTarget($target)) {
            $sites = $this->getAvailableTargetSites($element, $sourceSiteId, $withUnpropagatedSites);

            if (str_starts_with($target, self::GROUP_PREFIX)) {
                $group = $this->getTargetGroups()[substr($target, strlen(self::GROUP_PREFIX))] ?? null;
                $groupSiteUids = $group['siteUids'] ?? [];
                $sites = array_values(array_filter($sites, fn(Site $site) => in_array($site->uid, $groupSiteUids, true)));
            }

            return $sites;
        }

        $sitesService = Craft::$app->getSites();
        $site = ctype_digit($target) ? $sitesService->getSiteById((int) $target) : $sitesService->getSiteByHandle($target);

        return $site && $this->isTargetEnabled($site) ? [$site] : [];
    }

    /**
     * Options for 'All sites' and the target groups, leaving out the ones that would not translate anything.
     *
     * @return array<int, array{value: string, label: string, sites: Site[]}>
     */
    public function getMultiTargetOptions(?ElementInterface $element, int $sourceSiteId, bool $withUnpropagatedSites = true): array
    {
        $options = [];

        $allSites = $this->resolveTargetSites(self::ALL, $element, $sourceSiteId, $withUnpropagatedSites);
        if (!empty($allSites)) {
            $options[] = [
                'value' => self::ALL,
                'label' => Craft::t('multi-translator', 'All sites'),
                'sites' => $allSites,
            ];
        }

        foreach ($this->getTargetGroups() as $handle => $group) {
            $value = self::GROUP_PREFIX . $handle;
            $sites = $this->resolveTargetSites($value, $element, $sourceSiteId, $withUnpropagatedSites);

            if (!empty($sites)) {
                $options[] = [
                    'value' => $value,
                    'label' => Craft::t('site', $group['name']),
                    'sites' => $sites,
                ];
            }
        }

        return $options;
    }

    /**
     * Options for a target site select field: a placeholder, 'All sites' and the target groups, then the single sites.
     *
     * @param bool $includeSource list the source site itself as single target
     * @param bool $withUnpropagatedSites for the multi targets, see resolveTargetSites()
     */
    public function getTargetSelectOptions(?ElementInterface $element, int $sourceSiteId, bool $includeSource = false, bool $withUnpropagatedSites = true): array
    {
        $options = [
            ['label' => Craft::t('multi-translator', '- select target -'), 'value' => ''],
        ];

        $multiTargetOptions = $this->getMultiTargetOptions($element, $sourceSiteId, $withUnpropagatedSites);

        foreach ($multiTargetOptions as $option) {
            $options[] = ['label' => $option['label'], 'value' => $option['value']];
        }

        $sites = $this->getAvailableTargetSites($element, $includeSource ? null : $sourceSiteId);

        if (!empty($multiTargetOptions) && !empty($sites)) {
            $options[] = ['optgroup' => Craft::t('multi-translator', 'Sites')];
        }

        foreach ($sites as $site) {
            $options[] = ['label' => "{$site->name} ({$site->locale})", 'value' => $site->id];
        }

        return $options;
    }

    /**
     * Ids of the sites the element can be translated to.
     *
     * A nested entry (Matrix block) can also be translated to the sites its owner exists in: when the block
     * doesn't exist there (e.g. propagation method "Only save entries to the site they were created in"),
     * the translation is added as a new block to the owner in that site.
     *
     * @return int[]
     */
    private function getSupportedSiteIds(ElementInterface $element, bool $withUnpropagatedSites): array
    {
        $siteIds = array_map(
            fn(array $site) => (int) $site['siteId'],
            CraftElementHelper::supportedSitesForElement($element, $withUnpropagatedSites)
        );

        if ($element instanceof Entry && $element->fieldId && ($owner = $element->getOwner())) {
            // only the sites the owner already exists in, a new block needs an owner to belong to
            foreach (CraftElementHelper::supportedSitesForElement($owner, false) as $site) {
                $siteIds[] = (int) $site['siteId'];
            }
        }

        return array_values(array_unique($siteIds));
    }

    /**
     * @return string[]
     */
    private function getAllSiteUids(): array
    {
        return array_map(fn(Site $site) => $site->uid, Craft::$app->getSites()->getAllSites(true));
    }
}
