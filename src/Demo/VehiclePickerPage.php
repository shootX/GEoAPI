<?php

declare(strict_types=1);

namespace Carapi\Demo;

use Carapi\SiteChrome;

final class VehiclePickerPage
{
    public static function render(): void
    {
        echo SiteChrome::start(
            'Vehicle picker',
            'Choose a brand, model, year, generation, and modification.',
            false,
            'en',
            '<link rel="stylesheet" href="/assets/vehicle-picker.css?v=20261020b">'
        );
        echo SiteChrome::header('demo');
        echo <<<'HTML'
<main id="content" class="wrap ct">
<h1>Vehicle picker</h1>
<p class="lead">Choose a brand, then a model, year, generation, and modification.</p>
<form id="vehicle-picker" class="vp-grid">
<div class="fld"><label class="lbl" for="vp-brand">Brand</label><select class="inp" id="vp-brand" name="brand" disabled><option value="">Select brand</option></select></div>
<div class="fld"><label class="lbl" for="vp-model">Model</label><select class="inp" id="vp-model" name="model" disabled><option value="">Select model</option></select></div>
<div class="fld"><label class="lbl" for="vp-year">Year</label><select class="inp" id="vp-year" name="year" disabled><option value="">Select year</option></select></div>
<div class="fld"><label class="lbl" for="vp-generation">Generation</label><select class="inp" id="vp-generation" name="generation" disabled><option value="">Select generation</option></select></div>
<div class="fld"><label class="lbl" for="vp-modification">Modification</label><select class="inp" id="vp-modification" name="modification" disabled><option value="">Select modification</option></select></div>
</form>
<p class="hint" id="vp-status" aria-live="polite">Loading brands.</p>
<article class="pn vp-card" id="vp-card" hidden>
<div class="vp-photo-empty" id="vp-empty">No photo yet</div>
<img id="vp-img" class="vp-photo" alt="" hidden>
<h2 id="vp-title">Vehicle</h2>
<dl>
<div><dt>Brand</dt><dd id="vp-brand-value">—</dd></div>
<div><dt>Model</dt><dd id="vp-model-value">—</dd></div>
<div><dt>Generation</dt><dd id="vp-generation-value">—</dd></div>
<div><dt>Years</dt><dd id="vp-years-value">—</dd></div>
<div><dt>Body type</dt><dd id="vp-body-value">—</dd></div>
<div><dt>Engine</dt><dd id="vp-engine-value">—</dd></div>
<div><dt>Power</dt><dd id="vp-power-value">—</dd></div>
<div><dt>Torque</dt><dd id="vp-torque-value">—</dd></div>
<div><dt>Fuel</dt><dd id="vp-fuel-value">—</dd></div>
<div><dt>Transmission</dt><dd id="vp-transmission-value">—</dd></div>
<div><dt>Drive</dt><dd id="vp-drive-value">—</dd></div>
<div><dt>Consumption</dt><dd id="vp-consumption-value">—</dd></div>
<div><dt>Dimensions</dt><dd id="vp-dimensions-value">—</dd></div>
<div><dt>Weight</dt><dd id="vp-weight-value">—</dd></div>
<div><dt>Seats</dt><dd id="vp-seats-value">—</dd></div>
</dl>
<div class="vp-eq" id="vp-equipment">
<h2>Equipment</h2>
<details><summary>Standard</summary><div data-eq="standard"></div></details>
<details><summary>Optional</summary><div data-eq="optional"></div></details>
<details><summary>Not available</summary><div data-eq="not_available"></div></details>
</div>
</article>
</main>
HTML;
        echo SiteChrome::footer();
        echo '<script src="/assets/vehicle-picker.js?v=20261020b"></script>';
        echo SiteChrome::end();
    }
}
