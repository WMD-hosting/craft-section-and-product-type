/**
 * Section + Product Type - option list filter for the field settings screens.
 *
 * Narrows one field instance's option checkboxes by name or handle. Both the
 * filter input and the lists it filters are passed in as selectors, so several
 * field settings panels can sit on the same page without interfering with each
 * other, and the same class serves all three field types.
 */

/** global: Craft */
/** global: Garnish */
(function ($) {
    'use strict';

    if (typeof Craft.SectionAndProductType === 'undefined') {
        Craft.SectionAndProductType = {};
    }

    Craft.SectionAndProductType.OptionFilter = Garnish.Base.extend({
        $input: null,
        $container: null,
        $rows: null,

        /**
         * @param {string|jQuery|HTMLElement} input The filter text input.
         * @param {string|jQuery|HTMLElement} container The element wrapping the checkbox groups to filter.
         */
        init: function (input, container) {
            this.$input = $(input);
            this.$container = $(container);

            if (!this.$input.length || !this.$container.length) {
                return;
            }

            this.$rows = this.$container.find('.checkbox-group > div');

            this.addListener(this.$input, 'input', 'updateRows');
            this.addListener(this.$input, 'keydown', 'handleKeydown');
        },

        /**
         * Keeps Return from submitting the surrounding field settings form,
         * since this input is a display filter rather than a setting.
         */
        handleKeydown: function (ev) {
            if (ev.keyCode === Garnish.RETURN_KEY) {
                ev.preventDefault();
            }
        },

        /**
         * Shows the options matching the current search text and hides the rest.
         */
        updateRows: function () {
            var needle = $.trim(this.$input.val()).toLowerCase();

            this.$rows.each(function () {
                var $row = $(this);

                if (needle === '') {
                    $row.css('display', '');
                    return;
                }

                var haystack = [
                    $row.find('label').first().text(),
                    $row.find('input[type="checkbox"]').first().data('handle') || ''
                ].join(' ').toLowerCase();

                $row.css('display', haystack.indexOf(needle) !== -1 ? '' : 'none');
            });
        }
    });
})(jQuery);
