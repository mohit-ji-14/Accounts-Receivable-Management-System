<?php

if (!defined('CURRENCY_CONFIG')) {
    define('CURRENCY_CONFIG', true);

    // Global Supported Currencies & FX Rates relative to INR (Base Currency)
    $GLOBALS['CURRENCIES'] = [
        'INR' => ['symbol' => '₹', 'name' => 'Indian Rupee', 'fx_rate' => 1.0000],
        'USD' => ['symbol' => '$', 'name' => 'US Dollar', 'fx_rate' => 85.0000],
        'EUR' => ['symbol' => '€', 'name' => 'Euro', 'fx_rate' => 92.5000],
        'GBP' => ['symbol' => '£', 'name' => 'British Pound', 'fx_rate' => 108.2000],
        'AED' => ['symbol' => 'د.إ', 'name' => 'UAE Dirham', 'fx_rate' => 23.1500]
    ];

    /**
     * Format Currency with proper symbol
     */
    function format_currency($amount, $currency_code = 'INR') {
        global $CURRENCIES;
        $code = strtoupper($currency_code);
        $symbol = $CURRENCIES[$code]['symbol'] ?? $code;
        return $symbol . number_format((float)$amount, 2);
    }

    /**
     * Convert Amount from Foreign Currency to Base Currency (INR)
     */
    function convert_to_base($amount, $currency_code = 'INR', $fx_rate = null) {
        global $CURRENCIES;
        $code = strtoupper($currency_code);
        $rate = $fx_rate ?: ($CURRENCIES[$code]['fx_rate'] ?? 1.0);
        return (float)$amount * (float)$rate;
    }
}
