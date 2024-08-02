<?php

// Helper function to format money into metical format
function formatMoney($amount)
{
    // Format the amount with 2 decimal places and use a comma as the decimal separator
    $formattedAmount = number_format($amount, 2, ',', '.');

    // Add the currency symbol (MT) after the amount
    return $formattedAmount . ' MT';
}

