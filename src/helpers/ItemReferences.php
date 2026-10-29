<?php

namespace wmd\sectionandproducttype\helpers;

/**
 * Reads and writes the allow and exclude lists of a field's settings.
 *
 * The lists live in project config, which travels between installs, so they are written as
 * UIDs. Lists saved before 2.3.0 hold numeric IDs; those still resolve, on the install that
 * reads them. Craft-free: every method takes the `id => uid` map of the items the install has.
 *
 * @author WMD Hosting
 * @since 2.3.0
 */
final class ItemReferences
{
    // Public Methods
    // =========================================================================

    /**
     * The IDs a list refers to, distinct and in list order. References to items this install
     * does not have are left out.
     *
     * @param array $references UIDs, or numeric IDs from before 2.3.0
     * @param array<int, string> $uids UID of every item on this install, keyed by ID
     * @return int[]
     *
     * @author WMD Hosting
     * @since 2.3.0
     */
    public static function toIds(array $references, array $uids): array
    {
        $ids = [];
        $byUid = array_flip($uids);

        foreach ($references as $reference) {
            $id = self::_id($reference, $uids, $byUid);
            if ($id !== null) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * A list as it is saved: every reference as a UID, distinct and in list order.
     *
     * A UID this install does not have is kept (it can name a section of another environment).
     * A numeric ID it does not have is dropped: written back, it would name whichever item has
     * that ID on the next install, which is the bug UIDs fix. When the install has no items of
     * this kind at all (product types while Commerce is off), nothing can be resolved and the
     * list is kept as given, so such a save loses nothing.
     *
     * @param array $references UIDs, or numeric IDs from before 2.3.0
     * @param array<int, string> $uids UID of every item on this install, keyed by ID
     * @return string[]
     *
     * @author WMD Hosting
     * @since 2.3.0
     */
    public static function toUids(array $references, array $uids): array
    {
        $saved = [];
        $byUid = array_flip($uids);

        foreach ($references as $reference) {
            if (self::_isBlank($reference)) {
                continue;
            }

            $id = self::_id($reference, $uids, $byUid);
            if ($id !== null) {
                $saved[$uids[$id]] = $uids[$id];
                continue;
            }

            if ($uids === [] || !self::_isId($reference)) {
                $saved[(string)$reference] = (string)$reference;
            }
        }

        return array_values($saved);
    }

    /**
     * The references in a list that this install cannot resolve.
     *
     * @param array $references UIDs, or numeric IDs from before 2.3.0
     * @param array<int, string> $uids UID of every item on this install, keyed by ID
     * @return string[]
     *
     * @author WMD Hosting
     * @since 2.3.0
     */
    public static function unresolved(array $references, array $uids): array
    {
        $byUid = array_flip($uids);
        $unresolved = [];

        foreach ($references as $reference) {
            if (self::_isBlank($reference) || self::_id($reference, $uids, $byUid) !== null) {
                continue;
            }

            $unresolved[] = (string)$reference;
        }

        return $unresolved;
    }

    /**
     * The IDs an author may pick from, in the order of `$all`: every item when `$selectAll` is
     * on, otherwise the allowed items plus the handle matches; minus the excluded items in
     * either case.
     *
     * @param int[] $all Every item's ID
     * @param bool $selectAll
     * @param int[] $allowed
     * @param int[] $handleMatches IDs of the items whose handle contains the field's handle text
     * @param int[] $excluded
     * @return int[]
     *
     * @author WMD Hosting
     * @since 2.3.0
     */
    public static function availableIds(array $all, bool $selectAll, array $allowed, array $handleMatches, array $excluded): array
    {
        $picked = $selectAll ? $all : [...$allowed, ...$handleMatches];

        return array_values(array_filter(
            $all,
            static fn(int $id) => in_array($id, $picked, true) && !in_array($id, $excluded, true),
        ));
    }

    // Private Methods
    // =========================================================================

    /**
     * The ID one reference resolves to on this install, or null.
     *
     * @param mixed $reference
     * @param array<int, string> $uids
     * @param array<string, int> $byUid
     * @return int|null
     *
     * @author WMD Hosting
     * @since 2.3.0
     */
    private static function _id(mixed $reference, array $uids, array $byUid): ?int
    {
        if (self::_isId($reference)) {
            $id = (int)$reference;

            return isset($uids[$id]) ? $id : null;
        }

        if (!is_string($reference)) {
            return null;
        }

        return $byUid[$reference] ?? null;
    }

    /**
     * Whether a reference is a numeric ID, as lists saved before 2.3.0 hold them.
     *
     * @param mixed $reference
     * @return bool
     *
     * @author WMD Hosting
     * @since 2.3.0
     */
    private static function _isId(mixed $reference): bool
    {
        return is_int($reference) || (is_string($reference) && preg_match('/^\d+$/', $reference) === 1);
    }

    /**
     * Whether a reference is an empty value a checkbox group posts when nothing is ticked.
     *
     * @param mixed $reference
     * @return bool
     *
     * @author WMD Hosting
     * @since 2.3.0
     */
    private static function _isBlank(mixed $reference): bool
    {
        return $reference === null || $reference === '' || $reference === false;
    }
}
