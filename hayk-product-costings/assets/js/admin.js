(function ($) {
    'use strict';

    var HPC = {
        nextIndex: 0,

        init: function () {
            this.$body = $('#hpc-materials-body');
            if (!this.$body.length) {
                return;
            }
            this.nextIndex = this.$body.find('.hpc-row').length;
            this.initSortable();
            this.initMaterialSelects();
            this.bindEvents();
            this.recalc();
        },

        currency: function () {
            return (window.hpcData && hpcData.currency) ? hpcData.currency : '$';
        },

        run: function () {
            return parseFloat($('#hpc-production-run').val()) || 0;
        },

        /* ── Sortable ── */
        initSortable: function () {
            var self = this;
            this.$body.sortable({
                handle: '.hpc-drag-handle',
                axis: 'y',
                opacity: 0.65,
                items: '> tr.hpc-row',
                update: function () {
                    self.reindexRows();
                    self.recalc();
                }
            });
        },

        /* ── Material search selects ── */
        initMaterialSelects: function () {
            this.$body.find('.hpc-field-material').each(function () {
                HPC.initSingleSelect($(this));
            });
        },

        initSingleSelect: function ($select) {
            if ($select.data('hpc-init')) return;
            $select.data('hpc-init', true);

            var $row     = $select.closest('.hpc-row');
            var $wrapper = $('<div class="hpc-material-search-wrap"></div>');
            var $input   = $('<input type="text" class="hpc-material-search" placeholder="Search materials…">');
            var $list    = $('<ul class="hpc-material-results"></ul>');
            $wrapper.append($input).append($list);
            $select.after($wrapper);
            $select.hide();

            if ($select.val()) {
                $input.val($select.find('option:selected').text());
            }

            var timer;
            $input.on('input', function () {
                clearTimeout(timer);
                var q = $(this).val();
                if (q.length < 2) { $list.empty().hide(); return; }
                timer = setTimeout(function () {
                    $.ajax({
                        url: hpcData.ajaxUrl,
                        data: { action: 'hpc_search_materials', nonce: hpcData.nonce, q: q },
                        success: function (res) {
                            $list.empty();
                            if (res.success && res.data.length) {
                                $.each(res.data, function (_, item) {
                                    $list.append($('<li></li>').text(item.text).data('id', item.id));
                                });
                                $list.show();
                            } else {
                                $list.append('<li class="hpc-no-results">No results</li>').show();
                            }
                        }
                    });
                }, 300);
            });

            $list.on('click', 'li:not(.hpc-no-results)', function () {
                var id   = $(this).data('id');
                var text = $(this).text();
                $select.html('<option value="' + id + '" selected>' + $('<span>').text(text).html() + '</option>');
                $input.val(text);
                $list.empty().hide();
                HPC.fetchMeta(id, $row);
            });

            $input.on('blur', function () {
                setTimeout(function () { $list.empty().hide(); }, 200);
            });
        },

        /* ── Fetch a material's bulk pricing ── */
        fetchMeta: function (postId, $row, done) {
            $.ajax({
                url: hpcData.ajaxUrl,
                data: { action: 'hpc_get_material_meta', nonce: hpcData.nonce, post_id: postId },
                success: function (res) {
                    if (res.success && res.data) {
                        $row.attr('data-unit', res.data.unit || '');
                        $row.attr('data-tiers', JSON.stringify(res.data.tiers || []));
                        HPC.recalc();
                    }
                    if (done) done(res);
                },
                error: function () { if (done) done(null); }
            });
        },

        /* ── Events ── */
        bindEvents: function () {
            var self = this;

            $('#hpc-add-row').on('click', function () {
                var html = wp.template('hpc-row')({ i: self.nextIndex });
                self.$body.append(html);
                var $row = self.$body.find('.hpc-row').last();
                self.initSingleSelect($row.find('.hpc-field-material'));
                self.nextIndex++;
                self.recalc();
            });

            this.$body.on('click', '.hpc-remove-row', function () {
                $(this).closest('.hpc-row').remove();
                self.reindexRows();
                self.recalc();
            });

            this.$body.on('click', '.hpc-duplicate-row', function () {
                var $orig = $(this).closest('.hpc-row');
                var $clone = $orig.clone();
                // Reset the cloned select's search wrapper so it re-initialises.
                $clone.find('.hpc-material-search-wrap').remove();
                $clone.find('.hpc-field-material').show().removeData('hpc-init');
                $orig.after($clone);
                self.reindexRows();
                self.initSingleSelect($clone.find('.hpc-field-material'));
                self.recalc();
            });

            this.$body.on('input change', '.hpc-field-qty, .hpc-field-type', function () {
                self.recalc();
            });

            $('#hpc-production-run, #hpc-packaging, #hpc-labour, #hpc-facility').on('input change', function () {
                self.recalc();
            });

            $('#hpc-refresh-meta').on('click', function () {
                self.refreshAll();
            });
        },

        refreshAll: function () {
            var self = this;
            var $rows = this.$body.find('.hpc-row');
            var pending = 0;
            $('#hpc-refresh-status').text('Refreshing…');
            $rows.each(function () {
                var $row = $(this);
                var id = parseInt($row.find('.hpc-field-material').val(), 10);
                if (id) {
                    pending++;
                    self.fetchMeta(id, $row, function () {
                        pending--;
                        if (pending <= 0) { $('#hpc-refresh-status').text('Updated.'); self.recalc(); }
                    });
                }
            });
            if (pending === 0) { $('#hpc-refresh-status').text('Nothing to refresh.'); }
        },

        reindexRows: function () {
            this.$body.find('.hpc-row').each(function (idx) {
                $(this).attr('data-index', idx);
                $(this).find('[name]').each(function () {
                    var name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/hpc_rows\[\d+\]/, 'hpc_rows[' + idx + ']'));
                    }
                });
            });
            this.nextIndex = this.$body.find('.hpc-row').length;
        },

        /**
         * Pick the applicable tier (largest MOQ <= needed, floored at smallest).
         */
        applicableTier: function (tiers, needed) {
            if (!tiers || !tiers.length) return null;
            var sorted = tiers.slice().sort(function (a, b) { return a.qty - b.qty; });
            var chosen = sorted[0];
            sorted.forEach(function (t) {
                if (t.qty <= needed + 1e-9) { chosen = t; }
            });
            return {
                qty: chosen.qty,
                cost: chosen.cost,
                rate: chosen.qty > 0 ? chosen.cost / chosen.qty : 0
            };
        },

        fmtQty: function (v) {
            v = parseFloat(v) || 0;
            if (Math.floor(v) === v) return String(v);
            return parseFloat(v.toFixed(4)).toString();
        },

        /* ── Recalculate all rows + summary ── */
        recalc: function () {
            var self = this;
            var run = this.run();
            var cur = this.currency();
            var materialPerPair = 0;

            this.$body.find('.hpc-row').each(function () {
                var $row = $(this);
                var tiers = [];
                try { tiers = JSON.parse($row.attr('data-tiers') || '[]'); } catch (e) { tiers = []; }
                var unit = $row.attr('data-unit') || '';
                var qty  = parseFloat($row.find('.hpc-field-qty').val()) || 0;
                var needed = qty * run;

                var tier = self.applicableTier(tiers, needed);
                if (tier) {
                    $row.find('.hpc-field-costmoq').val(cur + tier.cost.toFixed(2));
                    $row.find('.hpc-field-moq').val(self.fmtQty(tier.qty) + (unit ? ' ' + unit : ''));
                    var costPair = tier.rate * qty;
                    materialPerPair += costPair;
                    $row.find('.hpc-cell-costpair').text(costPair > 0 ? cur + costPair.toFixed(2) : '—');
                } else {
                    $row.find('.hpc-cell-costpair').text('—');
                }
            });

            $('#hpc-total-costpair').html('<strong>' + cur + materialPerPair.toFixed(2) + '</strong>');

            // Summary metrics.
            var packaging = parseFloat($('#hpc-packaging').val()) || 0;
            var labour    = parseFloat($('#hpc-labour').val()) || 0;
            var facility  = parseFloat($('#hpc-facility').val()) || 0;

            var matRun    = materialPerPair * run;
            var pkgRun    = packaging * run;
            var mfg       = labour + facility;
            var full      = matRun + pkgRun + mfg;
            var perPair   = run > 0 ? full / run : 0;

            $('#hpc-sum-matpair').text(cur + materialPerPair.toFixed(2));
            $('#hpc-sum-matrun').text(cur + matRun.toFixed(2));
            $('#hpc-sum-pkg').text(cur + pkgRun.toFixed(2));
            $('#hpc-sum-mfg').text(cur + mfg.toFixed(2));
            $('#hpc-sum-full').html('<strong>' + cur + full.toFixed(2) + '</strong>');
            $('#hpc-sum-pair').html('<strong>' + cur + perPair.toFixed(2) + '</strong>');

            // Packaging run-total hint next to the field.
            $('#hpc-packaging-total').text(run > 0 ? '= ' + cur + pkgRun.toFixed(2) + ' for the run' : '');
        }
    };

    $(function () { HPC.init(); });

})(jQuery);
