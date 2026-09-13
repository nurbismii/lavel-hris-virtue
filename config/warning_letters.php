<?php

return [
    'number_code' => env('WARNING_LETTER_NUMBER_CODE', 'SP-HRD'),
    'place' => env('WARNING_LETTER_PLACE', 'Morosi'),
    'company' => env('WARNING_LETTER_COMPANY', 'PT VIRTUE DRAGON NICKEL INDUSTRY'),
    'footer' => [
        'building' => env('WARNING_LETTER_FOOTER_BUILDING', 'Indonesia Stock Exchange Building'),
        'address_line_1' => env('WARNING_LETTER_FOOTER_ADDRESS_1', 'Tower I #2802 Level 28'),
        'address_line_2' => env('WARNING_LETTER_FOOTER_ADDRESS_2', 'Jend Sudirman Kav 52-53'),
        'address_line_3' => env('WARNING_LETTER_FOOTER_ADDRESS_3', 'Jakarta 12190, Indonesia'),
        'phone' => env('WARNING_LETTER_FOOTER_PHONE', '(+62) 21 515 4408'),
        'fax' => env('WARNING_LETTER_FOOTER_FAX', '(+62) 21 515 3595'),
        'website' => env('WARNING_LETTER_FOOTER_WEBSITE', 'www.vdni.co.id'),
    ],
];
