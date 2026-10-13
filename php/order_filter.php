<?php

declare(strict_types=1);

function getOrderFilterStatus(string $status): string
{
    switch ($status) {

        case 'offerte':
        case 'bevestigd':
        case 'ingepland':
        case 'onderweg':

            return 'openstaand';

        case 'geleverd':
        case 'gefactureerd':
        case 'geannuleerd':
            return 'afgehandeld';

        default:

            return 'openstaand';
    }
}