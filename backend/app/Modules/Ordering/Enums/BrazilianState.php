<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Enums;

/**
 * The 27 Brazilian states (UFs). Defined by the ordering side because the order keeps the
 * destination state; the customers module (address book) and the fulfillment module (shipping
 * rates) use it through the Ordering contracts.
 */
enum BrazilianState: string
{
    case AC = 'AC';
    case AL = 'AL';
    case AM = 'AM';
    case AP = 'AP';
    case BA = 'BA';
    case CE = 'CE';
    case DF = 'DF';
    case ES = 'ES';
    case GO = 'GO';
    case MA = 'MA';
    case MG = 'MG';
    case MS = 'MS';
    case MT = 'MT';
    case PA = 'PA';
    case PB = 'PB';
    case PE = 'PE';
    case PI = 'PI';
    case PR = 'PR';
    case RJ = 'RJ';
    case RN = 'RN';
    case RO = 'RO';
    case RR = 'RR';
    case RS = 'RS';
    case SC = 'SC';
    case SE = 'SE';
    case SP = 'SP';
    case TO = 'TO';

    /** The canonical form of a typed state: no surrounding spaces, upper case (" sp " -> "SP"). */
    public static function normalize(string $value): string
    {
        return mb_strtoupper(trim($value));
    }
}
