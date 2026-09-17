<?php

if (! function_exists('vnd')) {
    function vnd(int|float|null $amount): string
    {
        return number_format((int) $amount, 0, ',', '.').' ₫';
    }
}
