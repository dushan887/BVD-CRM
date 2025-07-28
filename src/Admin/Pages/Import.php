<?php

declare(strict_types=1);

namespace BVD\CRM\Admin\Pages;

final class Import
{
    public static function render(): void
    {
        ?>
        <div class="wrap">
            <h1>CSV Import</h1>
            <form id="bvd-import-form" enctype="multipart/form-data">
                <input type="file" name="file" accept=".csv" required>
                <button type="submit" class="button button-primary">Upload &amp; Import</button>
                <span id="bvd-import-progress" style="margin-left:10px;"></span>
            </form>
        </div>
        <?php
    }
}
