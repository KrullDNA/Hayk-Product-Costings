(function ($) {
    'use strict';

    // Map of logical cost field -> the client's custom field name(s) to read
    // live from the edit screen (JetEngine/ACF inputs), falling back to the
    // server-provided baseline in hpcData.fields.
    var FIELD_INPUTS = {
        production_run:          ['production_run'],
        packaging_cost_per_pair: ['packaging_unit_cost'],
        labour:                  ['labour_costs'],
        facility_running_costs:  ['facility_running_costs'],
        miscellaneous_costs:     ['miscellaneous_cost']
    };

    // The plugin's own override inputs (Production & Costs metabox).
    var PLUGIN_INPUTS = {
        production_run:          '#hpc-production-run',
        packaging_cost_per_pair: '#hpc-packaging',
        labour:                  '#hpc-labour',
        facility_running_costs:  '#hpc-facility',
        miscellaneous_costs:     '#hpc-misc'
    };

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

        margin: function () {
            return (window.hpcData && hpcData.leatherMargin) ? parseFloat(hpcData.leatherMargin) || 0 : 0;
        },

        /**
         * Read a cost field: prefer a live input on the page (the client's
         * custom field), else the server baseline localized in hpcData.fields.
         */
        field: function (key) {
            // 1) Plugin override input, when filled in.
            var plugSel = PLUGIN_INPUTS[key];
            if (plugSel) {
                var $plug = $(plugSel);
                if ($plug.length && $.trim($plug.val()) !== '') {
                    var pv = parseFloat($plug.val());
                    if (!isNaN(pv)) return pv;
                }
            }
            // 2) The client's own custom field inputs on the page.
            var names = FIELD_INPUTS[key] || [];
            for (var i = 0; i < names.length; i++) {
                var $inp = $('[name="' + names[i] + '"], [name="_' + names[i] + '"]').filter('input, select, textarea').first();
                if ($inp.length) {
                    var v = parseFloat($inp.val());
                    if (!isNaN(v)) return v;
                }
            }
            if (window.hpcData && hpcData.fields && typeof hpcData.fields[key] !== 'undefined') {
                return parseFloat(hpcData.fields[key]) || 0;
            }
            return 0;
        },

        run: function () {
            return this.field('production_run');
        },

        unitInfo: function (unit) {
            if (window.hpcData && hpcData.units && hpcData.units[unit]) {
                return hpcData.units[unit];
            }
            return { singular: unit || '', plural: unit || '', units_per: 1 };
        },

        fmtNum: function (v) {
            v = parseFloat(v) || 0;
            if (Math.floor(v) === v) return String(v);
            return parseFloat(v.toFixed(4)).toString();
        },

        fmtQtyUnit: function (qty, unit) {
            qty = parseFloat(qty) || 0;
            var info = this.unitInfo(unit);
            var label = (Math.abs(qty - 1) < 1e-9) ? info.singular : info.plural;
            var out = this.fmtNum(qty) + (label ? ' ' + label : '');
            var per = parseFloat(info.units_per) || 1;
            if (per > 1 && qty > 0) {
                out += ' (' + this.fmtNum(qty * per) + ' units)';
            }
            return out;
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
                        $row.attr('data-wastage', res.data.wastage || 0);
                        $row.attr('data-area-per-unit', res.data.area_per_unit || 0);
                        $row.attr('data-area-unit', res.data.area_unit || '');
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

            // Recalculate live when either the plugin override inputs or the
            // client's custom cost fields change.
            var selectors = [];
            $.each(PLUGIN_INPUTS, function (_, sel) { selectors.push(sel); });
            $.each(FIELD_INPUTS, function (_, names) {
                $.each(names, function (_, n) {
                    selectors.push('[name="' + n + '"]');
                    selectors.push('[name="_' + n + '"]');
                });
            });
            $(document).on('input change', selectors.join(','), function () {
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
                rate: chosen.qty > 0 ? chosen.cost / chosen.qty : 0,
                apply_margin: !!chosen.apply_margin
            };
        },

        /* ── Recalculate all rows + summary ── */
        recalc: function () {
            var self = this;
            var run = this.run();
            var cur = this.currency();
            var margin = this.margin();
            var materialPerPair = 0;
            var purchasing = [];

            this.$body.find('.hpc-row').each(function () {
                var $row = $(this);
                var tiers = [];
                try { tiers = JSON.parse($row.attr('data-tiers') || '[]'); } catch (e) { tiers = []; }
                var unit = $row.attr('data-unit') || '';
                var wastage = parseFloat($row.attr('data-wastage')) || 0;
                var areaPer = parseFloat($row.attr('data-area-per-unit')) || 0;
                var areaUnit = $row.attr('data-area-unit') || '';
                var qty  = parseFloat($row.find('.hpc-field-qty').val()) || 0;
                var wasteFactor = 1 + wastage / 100;

                // Qty-per-pair unit hint (area unit in area mode, else purchase unit).
                $row.find('.hpc-qty-unit').text(areaPer > 0 ? areaUnit : self.unitInfo(unit).plural);

                var costPair = 0, tier;

                if (areaPer > 0) {
                    // Area mode: bought per unit (skins), consumed by area.
                    var grossArea = qty * wasteFactor;
                    var unitsPerPair = grossArea / areaPer;
                    tier = self.applicableTier(tiers, unitsPerPair * run);
                    if (tier) {
                        var rateA = tier.rate;
                        if (tier.apply_margin && margin > 0) { rateA = rateA * (1 + margin / 100); }
                        $row.find('.hpc-field-costmoq').val(cur + tier.cost.toFixed(2));
                        $row.find('.hpc-field-moq').val(self.fmtQtyUnit(tier.qty, unit));
                        costPair = unitsPerPair * rateA;
                        var unitsRun = Math.ceil(unitsPerPair * run - 1e-9);
                        if (unitsRun > 0) {
                            purchasing.push({ title: $row.find('.hpc-field-material option:selected').text(), qty: unitsRun, unit: unit });
                        }
                    }
                } else {
                    // Direct mode.
                    var effPerPair = qty * wasteFactor;
                    tier = self.applicableTier(tiers, effPerPair * run);
                    if (tier) {
                        var rate = tier.rate;
                        if (tier.apply_margin && margin > 0) { rate = rate * (1 + margin / 100); }
                        $row.find('.hpc-field-costmoq').val(cur + tier.cost.toFixed(2));
                        $row.find('.hpc-field-moq').val(self.fmtQtyUnit(tier.qty, unit));
                        costPair = rate * effPerPair;
                    }
                }

                materialPerPair += costPair;
                $row.find('.hpc-cell-costpair').text(costPair > 0 ? cur + costPair.toFixed(2) : '—');
            });

            $('#hpc-total-costpair').html('<strong>' + cur + materialPerPair.toFixed(2) + '</strong>');

            // Live purchasing list (area-costed materials).
            var $purch = $('#hpc-purchasing');
            if ($purch.length) {
                if (purchasing.length) {
                    var rows = purchasing.map(function (p) {
                        return '<tr><td>' + $('<span>').text(p.title).html() + '</td><td>' +
                            self.fmtNum(p.qty) + ' ' + self.unitInfo(p.unit)[p.qty === 1 ? 'singular' : 'plural'] + '</td></tr>';
                    }).join('');
                    $('#hpc-purchasing-body').html(rows);
                    $purch.show();
                } else {
                    $purch.hide();
                }
            }

            // Summary metrics from the client's cost fields.
            var packaging = this.field('packaging_cost_per_pair');
            var labour    = this.field('labour');
            var facility  = this.field('facility_running_costs');
            var misc      = this.field('miscellaneous_costs');

            var matRun  = materialPerPair * run;
            var pkgRun  = packaging * run;
            var mfg     = labour + facility;
            var full    = matRun + pkgRun + mfg + misc;
            var perPair = run > 0 ? full / run : 0;

            $('#hpc-sum-matpair').text(cur + materialPerPair.toFixed(2));
            $('#hpc-sum-matrun').text(cur + matRun.toFixed(2));
            $('#hpc-sum-pkg').text(cur + pkgRun.toFixed(2));
            $('#hpc-sum-mfg').text(cur + mfg.toFixed(2));
            $('#hpc-sum-misc').text(cur + misc.toFixed(2));
            $('#hpc-sum-full').html('<strong>' + cur + full.toFixed(2) + '</strong>');
            $('#hpc-sum-pair').html('<strong>' + cur + perPair.toFixed(2) + '</strong>');

            $('#hpc-packaging-total').text(run > 0 ? '= ' + cur + pkgRun.toFixed(2) + ' for the run' : '');
        }
    };

    $(function () { HPC.init(); });

})(jQuery);
