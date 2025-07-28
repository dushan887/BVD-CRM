<?php

declare(strict_types=1);

namespace BVD\CRM\Front\Shortcodes;

final class ClientSummary
{
    public function register(): void
    {
        add_shortcode('bvd_client_summary', [$this, 'render']);
    }

    public function render(): string
    {
        ob_start(); ?>
        <div class="bvd-crm-summary">
            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
            <table id="bvd-crm-table" class="table table-striped table-bordered" style="display:none;width:100%">
                <thead>
                    <tr>
                        <th></th>
                        <th>Client</th>
                        <th>Month/Quarter</th>
                        <th>Hours&nbsp;(spent)</th>
                        <th>Limit</th>
                        <th>Usage %</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <!-- task‑modal -->
            <div class="modal fade" id="bvdTaskModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <div class="modal-header"><h5 class="modal-title">Task details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                  <div class="modal-body"><div id="bvd-modal-body"></div></div>
                </div>
              </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
