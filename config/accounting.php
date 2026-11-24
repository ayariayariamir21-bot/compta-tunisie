<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Droit de timbre (Tunisie)
    |--------------------------------------------------------------------------
    |
    | Amount (TND, 3 decimals) applied by default to every sales invoice of a
    | newly created company. Each company can change it in its accounting
    | settings; customers flagged as exempt never carry the stamp duty.
    |
    */

    'stamp_duty_default' => env('ACCOUNTING_STAMP_DUTY_DEFAULT', '1.000'),

    /*
    |--------------------------------------------------------------------------
    | System accounts (plan comptable tunisien)
    |--------------------------------------------------------------------------
    |
    | Accounts used for fiscal postings. They are created automatically in the
    | fiscal year when missing, under the given parent account.
    |
    */

    'system_accounts' => [
        'stamp_duty' => ['code' => '4435', 'name' => 'État — droit de timbre à payer', 'type' => 'liability', 'parent' => '443'],
        'withholding_payable' => ['code' => '4434', 'name' => 'État — retenues à la source à payer', 'type' => 'liability', 'parent' => '443'],
        'withholding_receivable' => ['code' => '4421', 'name' => 'État — retenues à la source subies', 'type' => 'asset', 'parent' => '442'],
    ],

];
