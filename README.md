# k3-fido2
⚠️Experimental⚠️ FIDO2 / WebAuthn Login Screen for Kirby 3 


## Getting started

1. Download folder and put it into `/site/plugins/`
2. Add a `passkey` section to your user blueprint  

    **site/users/admin.yml**
    ```yml
    sections:
      passkey:
        type: passkey
        label: Passkeys
    ```
3. Log-in to the panel using your normal password
4. Navigate to your Account view and register a passkey
5. Log-out and try to log-in using the passkey. 

## Requisites 

- Current Kirby installation
- Either an HTTPS connection to the host, or use `localhost`
