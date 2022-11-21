<?php 

namespace rasteiner\fido2;

use Exception;
use Kirby\Cms\User;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;

class Fido2 {

    private $webAuthn;

    private function __construct() { 
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

        $this->webAuthn = $webAuthn;
    }

    /**
     * @param User $user The user to get the passkeys for, or null to get the passkeys for the current user
     * @return array An array of passkeys for the given user
     */
    public static function getPasskeysFromUser(User $user = null): array {
        if ($user === null) {
            $user = kirby()->user();
        }

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

    /**
     * Generates the WebAuthn CredentialCreationOptions for the current user
     */
    public static function getRegisterArgs() : array {
        $fido2 = new Fido2();
        $webAuthn = $fido2->webAuthn;
        
        $user = kirby()->user();

        $passkeys = self::getPasskeysFromUser($user);

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
    
    /**
     * Registers a new passkey for the current user
     * @return array The array of all passkeys for the current user
     */
    public static function processRegistration($input) : array {
        
        $clientData = base64_decode($input['clientData']);
        $attestationObject = base64_decode($input['attestationObject']);

        $challenge = ByteBuffer::fromHex(kirby()->session()->data()->pull('passkeyChallenge'));

        $fido2 = new Fido2();
        $webAuthn = $fido2->webAuthn;
       
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
        $passkeys = self::getPasskeysFromUser($user);
        
        // add new passkey
        $passkeys[] = $data;
        
        // save passkeys
        $user->update([
            'passkeys' => json_encode($passkeys)
        ]);

        foreach ($passkeys as &$k) {
            $k['credentialId'] = strtoupper($k['credentialId']->getHex());
        }

        return $passkeys;
    }

    /**
     * Generates the WebAuthn CredentialRequestOptions
     */
    public static function getLoginArgs() : array {
        $fido2 = new Fido2();
        $webAuthn = $fido2->webAuthn;
        
        $args = $webAuthn->getGetArgs(requireUserVerification: true);
        $challenge = $webAuthn->getChallenge();
        kirby()->session()->data()->set('passkeyChallenge', $challenge->getHex());
        return (array)$args;
    }

    /**
     * Verifies a passkey for the current user, logs in the user if successful
     * @return bool True if the passkey is valid
     */
    public static function processLogin($input) : bool {
        $fido2 = new Fido2();
        $webAuthn = $fido2->webAuthn;

        $input = kirby()->request()->data();
        $email = $input['email'];
        $response = $input['response'];

        $userHandle = base64_decode($response['userHandle']);
        $user = $userHandle ? kirby()->user($userHandle) : kirby()->user($email);

        if($user) {

            $credentialId = new ByteBuffer(base64_decode($response['id']));
            $passkeys = self::getPasskeysFromUser($user);
            
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
                    return true;
                }
            }                       
        }

        return false;
    }
}