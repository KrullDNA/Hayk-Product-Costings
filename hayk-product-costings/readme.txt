=== Hayk Product Costings ===
Author: KrullDNA
Version: 1.0.0
Requires: WordPress + Elementor (front-end widgets). JetEngine used for the CPTs.

A shoe-product costing tool, adapted from the Apotheca cosmetic Product
Costings plugin using the same underlying logic (bulk pricing + a repeater
on Products that pulls live data from a raw-material CPT).

== What it does ==

Materials CPT (slug: `materials`)
  * "Bulk Pricing" metabox — set a purchase Unit (piece/s, m², pair/s …) and
    one or more quantity breaks. Each break is an MOQ (quantity) and the total
    Cost per MOQ. The per-unit rate (Cost ÷ MOQ) is shown live. Add multiple
    breaks for volume discounts.
  * "Where Used" metabox — every product that uses the material.

Products CPT (slug: `products`)
  * "Materials" repeater — one row per material used in the shoe. Columns:
    Material Type (free label, e.g. Leather / Lining / Sole), Material (live
    search of the Materials CPT), Cost per MOQ and MOQ (pulled from the
    material's bulk pricing), Qty per pair (entered), Cost per pair (computed).
  * "Production & Costs" metabox — Production run (pairs), Packaging cost per
    pair, Labour costs (run total), Facility running costs (run total).
  * "Cost Summary" metabox — live figures matching the front end.

Elementor widgets
  * "Materials Table" — the styled front-end table (Material Type, Image,
    Materials, Cost per MOQ, MOQ, Qty per pair, Cost per pair).
  * "Cost Metrics" — selectable figures (Production run, Full production cost,
    Single pair cost, Material cost per pair, Prod. run material cost,
    Packaging run total / per pair, Labour, Facility, Manufacturing total),
    each with a label override. Use several instances to build the cards /
    pills in the design.

== Costing model ==

  unit rate      = Cost per MOQ ÷ MOQ            (from bulk pricing)
  cost per pair  = unit rate × Qty per pair
  Material cost per pair  = Σ cost per pair
  Prod. run material cost = Material cost per pair × Production run
  Packaging run total     = Packaging cost per pair × Production run
  Full production cost    = Prod. run material cost + Packaging run total
                            + Labour + Facility running costs
  Single pair cost        = Full production cost ÷ Production run

With multiple bulk-pricing breaks, the break used for a material is the
largest MOQ that the production-run quantity (Qty per pair × Production run)
reaches, floored at the smallest MOQ. A single break always uses that break.

== Notes / assumptions ==

  * CPT slugs default to `materials` and `products`. Override with the
    `hpc_materials_cpt` / `hpc_products_cpt` filters or the matching
    HPC_MATERIALS_CPT / HPC_PRODUCTS_CPT constants if yours differ.
  * Production & Costs fields are stored by this plugin (`_hpc_*` meta). If you
    already have JetEngine/ACF fields named production_run, packaging_cost,
    labour, or facility_running_costs, the calculator falls back to them when
    the plugin field is empty.
  * The material Image uses the material post's featured image.
  * Currency symbol is set under Products → Costings Settings.

== Changelog ==

= 1.0.0 =
* Initial release. Adapted from Apotheca Product Costings for shoe production
  costing: Bulk Pricing on Materials, dynamic Materials table on Products,
  per-pair and full production-run cost calculation, Elementor front-end
  widgets.
