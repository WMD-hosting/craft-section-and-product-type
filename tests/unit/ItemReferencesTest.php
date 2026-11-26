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

    public function test_legacy_ids_resolve_on_this_install(): void
    {
        self::assertSame([14, 31], ItemReferences::toIds(['14', 31], self::UIDS));
    }

    public function test_uids_resolve_to_their_ids(): void
    {
        self::assertSame([31, 14], ItemReferences::toIds([self::UIDS[31], self::UIDS[14]], self::UIDS));
    }

    public function test_mixed_lists_are_distinct_in_list_order(): void
    {
        self::assertSame([18, 14], ItemReferences::toIds(['18', self::UIDS[14], self::UIDS[18], '14'], self::UIDS));
    }

    public function test_references_this_install_lacks_are_left_out_of_ids(): void
    {
        $missingUid = 'dddddddd-0000-4000-8000-000000000099';

        self::assertSame([14], ItemReferences::toIds(['99', $missingUid, '', null, '14'], self::UIDS));
    }

    public function test_saved_lists_hold_uids(): void
    {
        self::assertSame(
            [self::UIDS[14], self::UIDS[31]],
            ItemReferences::toUids(['14', self::UIDS[31], 14], self::UIDS),
        );
    }

    public function test_uids_from_another_environment_are_kept_on_save(): void
    {
        $otherInstall = 'dddddddd-0000-4000-8000-000000000099';

        self::assertSame([$otherInstall, self::UIDS[14]], ItemReferences::toUids([$otherInstall, '14', ''], self::UIDS));
    }

    /**
     * Written back, ID 99 would name whatever has that ID on the next install.
     */
    public function test_ids_this_install_lacks_are_dropped_on_save(): void
    {
        self::assertSame([self::UIDS[18]], ItemReferences::toUids(['99', '18', 7], self::UIDS));
    }

    /**
     * Product types while Commerce is off: nothing resolves, so nothing may be lost.
     */
    public function test_lists_are_kept_as_given_when_the_install_has_no_items(): void
    {
        self::assertSame(['2', 'eaa2390a-6a37-4b51-bdf8-b3f7fe4072a0'], ItemReferences::toUids(['2', 'eaa2390a-6a37-4b51-bdf8-b3f7fe4072a0', ''], []));
    }

    /**
     * What resolves to nothing here is an item deleted since: the form leaves it out, so its save drops it.
     */
    public function test_the_form_shows_what_this_install_has(): void
    {
        $deleted = 'dddddddd-0000-4000-8000-000000000099';

        self::assertSame(
            [self::UIDS[14], self::UIDS[31]],
            ItemReferences::forForm(['14', $deleted, '99', self::UIDS[31], '', 14], self::UIDS),
        );
    }

    /**
     * Product types while Commerce is off: resolving first would hand the form an empty list to post back.
     */
    public function test_the_form_shows_the_list_as_given_when_the_install_has_no_items(): void
    {
        self::assertSame(
            ['2', 'eaa2390a-6a37-4b51-bdf8-b3f7fe4072a0'],
            ItemReferences::forForm(['2', 'eaa2390a-6a37-4b51-bdf8-b3f7fe4072a0', '', 2], []),
        );
    }

    public function test_unresolved_lists_what_cannot_be_found(): void
    {
        self::assertSame(['99', 'nope'], ItemReferences::unresolved(['14', '99', '', 'nope', self::UIDS[18]], self::UIDS));
        self::assertSame([], ItemReferences::unresolved([], self::UIDS));
    }

    public function test_select_all_offers_everything_but_the_excluded(): void
    {
        self::assertSame([14, 31], ItemReferences::availableIds([14, 18, 31], true, [], [], [18]));
    }

    public function test_select_all_ignores_the_allow_list(): void
    {
        self::assertSame([14, 18, 31], ItemReferences::availableIds([14, 18, 31], true, [14], [], []));
    }

    public function test_exclusions_apply_without_select_all(): void
    {
        self::assertSame([14], ItemReferences::availableIds([14, 18, 31], false, [14, 18], [], [18]));
    }

    public function test_exclusions_apply_to_handle_matches(): void
    {
        self::assertSame([14, 31], ItemReferences::availableIds([14, 18, 31], false, [14], [18, 31], [18]));
    }

    public function test_only_items_that_exist_are_offered(): void
    {
        self::assertSame([31], ItemReferences::availableIds([14, 31], false, [31, 99], [], []));
    }

    public function test_nothing_allowed_offers_nothing(): void
    {
        self::assertSame([], ItemReferences::availableIds([14, 18, 31], false, [], [], []));
    }
}
