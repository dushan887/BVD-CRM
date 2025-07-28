<?php
namespace BVD\CRM\Admin\Pages;

final class Tools{
    public static function render():void{ ?>
        <div class="wrap"><h1>BVD CRM – Tools</h1>
            <p><button id="bvd-export" class="button button-secondary">Export all data (CSV)</button></p>
            <hr>
            <p><button id="bvd-nuke" class="button button-danger">⚠ NUKE all plugin tables</button></p>
        </div><?php
    }
}
