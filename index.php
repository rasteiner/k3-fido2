<?php

@require_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App as Kirby;
use Kirby\Cms\User;
use rasteiner\fido2\Fido2;


Kirby::plugin('rasteiner/k3-passkeys', [
    'sections' => [
        'passkey' => [
            'computed' => [
                'passkeys' => function () {
                    // check that the model is a user
                    if ($this->model() instanceof User === false) {
                        throw new Exception('The passkey section can only be used on user models');
                    }

                    $passkeys = Fido2::getPasskeysFromUser($this->model());
                    foreach ($passkeys as &$k) {
                        $k['credentialId'] = strtoupper($k['credentialId']->getHex());
                    }
                    return $passkeys;
                },
            ]
        ]
    ],
    'api' => [
        'routes' => [
            [
                'pattern' => 'passkeys/create/args',
                'auth' => true,
                'action' => fn() => Fido2::getRegisterArgs(),
            ],
            [
                'pattern' => 'passkeys/create/process',
                'auth' => true,
                'method' => 'POST',
                'action'  => function () {
                    $input = kirby()->request()->data();

                    $passkeys = Fido2::processRegistration($input);
                    
                    return [
                        'success' => true,
                        'passkeys' => $passkeys
                    ];
                }
            ],
            [
                'pattern' => 'passkeys/get/args',
                'auth'    => false,
                'action'  => fn() => Fido2::getLoginArgs()
            ],
            [
                'pattern' => 'passkeys/get/process',
                'auth'    => false,
                'method'  => 'POST',
                'action'  => function () {
                    // wait a random amount of time to prevent timing attacks
                    // (hide the fact that the user exists)
                    usleep(random_int(50000, 300000));
                    
                    $input = kirby()->request()->data();
                    
                    return [
                        'success' => Fido2::processLogin($input)
                    ];
                }
            ],
        ]
    ]
]);
