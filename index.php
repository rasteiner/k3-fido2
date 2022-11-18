<?php

@require_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App as Kirby;
use Kirby\Cms\User;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;

function makeWebAuthn(): WebAuthn {
    $rpName = option('rasteiner.k3-passkeys.rpName', site()->title()->value());
    $rpId = option('rasteiner.k3-passkeys.rpID', kirby()->request()->url()->domain());

    // trim eventual username or password from domain 
    // which was added by Uri::domain()
    $rpId = explode('@', $rpId)[0];

    // trim port from domain 
    // Uri::domain() appends the port when non standard
    $rpId = preg_replace('/:\d+$/', '', $rpId);
    
    $webAuthn = new WebAuthn(
        $rpName,
        $rpId,
        allowedFormats: ['none'],
        useBase64UrlEncoding: true,
    );

    return $webAuthn;
}

function getPasskeysFromUser(User $user): array {
    $passkeys = [];
    try {
        $passkeys = $user->passkeys()->toData('json') ?: [];
        foreach ($passkeys as &$k) {
            $k['credentialId'] = ByteBuffer::fromBase64Url($k['credentialId']);
        }
    } catch(Exception $e) {
        $passkeys = [];
    }
    return $passkeys;
}

Kirby::plugin('rasteiner/k3-passkeys', [
    'sections' => [
        'passkey' => [
            'computed' => [
                'passkeys' => function () {
                    // check that the model is a user
                    if ($this->model() instanceof User === false) {
                        throw new Exception('The passkey section can only be used on user models');
                    }

                    $passkeys = getPasskeysFromUser($this->model());
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
                'action'  => function () {
                    $webAuthn = makeWebAuthn();

                    $user = kirby()->user();

                    $passkeys = getPasskeysFromUser($user);

                    $createArgs = $webAuthn->getCreateArgs(
                        $user->uuid(),
                        $user->email(),
                        $user->username(),
                        requireUserVerification: true,
                        crossPlatformAttachment: false,
                        excludeCredentialIds: array_column($passkeys, 'credentialId'),
                    );

                    $challenge = $webAuthn->getChallenge();
                    kirby()->session()->data()->set('passkeyChallenge', $challenge->getHex());

                    return (array)$createArgs;
                }
            ],
            [
                'pattern' => 'passkeys/create/process',
                'auth' => true,
                'method' => 'POST',
                'action'  => function () {
                    $input = kirby()->request()->data();

                    $clientData = base64_decode($input['clientData']);
                    $attestationObject = base64_decode($input['attestationObject']);
                    $challenge = ByteBuffer::fromHex(kirby()->session()->data()->pull('passkeyChallenge'));
                    
                    $webAuthn = makeWebAuthn();

                    $data = (array)$webAuthn->processCreate(
                        $clientData, 
                        $attestationObject, 
                        $challenge,
                        true,
                        false
                    );

                    $data['credentialId'] = new ByteBuffer($data['credentialId']);

                    // current passkeys
                    $user = kirby()->user();
                    $passkeys = getPasskeysFromUser($user);
                    
                    // add new passkey
                    $passkeys[] = $data;
                    
                    // save passkeys
                    $user->update([
                        'passkeys' => json_encode($passkeys)
                    ]);

                    foreach ($passkeys as &$k) {
                        $k['credentialId'] = strtoupper($k['credentialId']->getHex());
                    }

                    return [
                        'success' => true,
                        'passkeys' => $passkeys
                    ];
                }
            ],
            [
                'pattern' => 'passkeys/get/args',
                'auth'    => false,
                'action'  => function () {
                    $webAuthn = makeWebAuthn();
                    $args = $webAuthn->getGetArgs(requireUserVerification: true);
                    $challenge = $webAuthn->getChallenge();
                    kirby()->session()->data()->set('passkeyChallenge', $challenge->getHex());
                    return (array)$args;
                }
            ],
            [
                'pattern' => 'passkeys/get/process',
                'auth'    => false,
                'method'  => 'POST',
                'action'  => function () {
                    // wait a random amount of time to prevent timing attacks
                    usleep(random_int(50000, 300000));
                    $webAuthn = makeWebAuthn();

                    $input = kirby()->request()->data();
                    $email = $input['email'];
                    $response = $input['response'];

                    $userHandle = base64_decode($response['userHandle']);
                    $user = $userHandle ? kirby()->user($userHandle) : kirby()->user($email);

                    if($user) {

                        $credentialId = new ByteBuffer(base64_decode($response['id']));
                        $passkeys = getPasskeysFromUser($user);
                        
                        $passkey = null;
                        $buffer = $credentialId->getBinaryString();

                        foreach($passkeys as $p) {
                            if($p['credentialId']->getBinaryString() === $buffer) {
                                $passkey = $p;
                                break;
                            }
                        }

                        if($passkey) {
                            $challenge = ByteBuffer::fromHex(kirby()->session()->data()->pull('passkeyChallenge'));
                            $clientDataJSON = base64_decode($response['clientDataJSON']);
                            $authenticatorData = base64_decode($response['authenticatorData']);
                            $signature = base64_decode($response['signature']);

                            $success = $webAuthn->processGet(
                                $clientDataJSON,
                                $authenticatorData,
                                $signature,
                                $passkey['credentialPublicKey'],
                                $challenge,
                                requireUserVerification: true,
                            );

                            if($success) {
                                $user->loginPasswordless();
                                return [
                                    'success' => true,
                                ];
                            }
                        }                       
                    }

                    return [
                        'success' => false,
                    ];
                }
            ],
        ]
    ]
]);
