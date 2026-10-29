<?php

declare(strict_types=1);

namespace wmd\sectionandproducttype\tests\unit;

use PHPUnit\Framework\TestCase;
use wmd\sectionandproducttype\helpers\ItemReferences;

/**
 * The allow/exclude lists: legacy IDs and UIDs on read, UIDs on save, and which items an author is offered.
 */
final class ItemReferencesTest extends TestCase
{
    /**
     * This install: section 14 is `aaa…`, 18 is `bbb…`, 31 is `ccc…`.
     */
    private const UIDS = [
        14 => 'aaaaaaaa-0000-4000-8000-000000000014',
        18 => 'bbbbbbbb-0000-4000-8000-000000000018',
        31 => 'cccccccc-0000-4000-8000-000000000031',
    ];

    public function testLegacyIdsResolveOnThisInstall(): void
    {
        self::assertSame([14, 31], ItemReferences::toIds(['14', 31], self::UIDS));
    }

    public function testUidsResolveToTheirIds(): void
    {
        self::assertSame([31, 14], ItemReferences::toIds([self::UIDS[31], self::UIDS[14]], self::UIDS));
    }

    public function testMixedListsAreDistinctInListOrder(): void
    {
        self::assertSame([18, 14], ItemReferences::toIds(['18', self::UIDS[14], self::UIDS[18], '14'], self::UIDS));
    }

    public function testReferencesThisInstallLacksAreLeftOutOfIds(): void
    {
        $missingUid = 'dddddddd-0000-4000-8000-000000000099';

        self::assertSame([14], ItemReferences::toIds(['99', $missingUid, '', null, '14'], self::UIDS));
    }

    public function testSavedListsHoldUids(): void
    {
        self::assertSame(
            [self::UIDS[14], self::UIDS[31]],
            ItemReferences::toUids(['14', self::UIDS[31], 14], self::UIDS),
        );
    }

    public function testUidsFromAnotherEnvironmentAreKeptOnSave(): void
    {
        $otherInstall = 'dddddddd-0000-4000-8000-000000000099';

        self::assertSame([$otherInstall, self::UIDS[14]], ItemReferences::toUids([$otherInstall, '14', ''], self::UIDS));
    }

    /**
     * Written back, ID 99 would name whatever has that ID on the next install.
     */
    public function testIdsThisInstallLacksAreDroppedOnSave(): void
    {
        self::assertSame([self::UIDS[18]], ItemReferences::toUids(['99', '18', 7], self::UIDS));
    }

    /**
     * Product types while Commerce is off: nothing resolves, so nothing may be lost.
     */
    public function testListsAreKeptAsGivenWhenTheInstallHasNoItems(): void
    {
        self::assertSame(['2', 'eaa2390a-6a37-4b51-bdf8-b3f7fe4072a0'], ItemReferences::toUids(['2', 'eaa2390a-6a37-4b51-bdf8-b3f7fe4072a0', ''], []));
    }

    public function testUnresolvedListsWhatCannotBeFound(): void
    {
        self::assertSame(['99', 'nope'], ItemReferences::unresolved(['14', '99', '', 'nope', self::UIDS[18]], self::UIDS));
        self::assertSame([], ItemReferences::unresolved([], self::UIDS));
    }

    public function testSelectAllOffersEverythingButTheExcluded(): void
    {
        self::assertSame([14, 31], ItemReferences::availableIds([14, 18, 31], true, [], [], [18]));
    }

    public function testSelectAllIgnoresTheAllowList(): void
    {
        self::assertSame([14, 18, 31], ItemReferences::availableIds([14, 18, 31], true, [14], [], []));
    }

    public function testExclusionsApplyWithoutSelectAll(): void
    {
        self::assertSame([14], ItemReferences::availableIds([14, 18, 31], false, [14, 18], [], [18]));
    }

    public function testExclusionsApplyToHandleMatches(): void
    {
        self::assertSame([14, 31], ItemReferences::availableIds([14, 18, 31], false, [14], [18, 31], [18]));
    }

    public function testOnlyItemsThatExistAreOffered(): void
    {
        self::assertSame([31], ItemReferences::availableIds([14, 31], false, [31, 99], [], []));
    }

    public function testNothingAllowedOffersNothing(): void
    {
        self::assertSame([], ItemReferences::availableIds([14, 18, 31], false, [], [], []));
    }
}
