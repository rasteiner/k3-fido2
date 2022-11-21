<template>
  <form class="k-login-with-passkey" @submit.prevent="submit">
    <k-fieldset :novalidate="true" :fields="fields" v-model="user"/>
    <div v-if="resident" style="display: flex">
      <button class="rs-passkey-button" type="submit">
        <k-icon type="touch" />Login with Passkey
      </button>
    </div>

    <div class="k-login-buttons">
      <k-button
        icon="cancel"
        @click="$emit('cancel');"
        type="button"
      >Login with password</k-button>

      <k-button
        v-if="!resident"
        icon="touch"
        class="k-login-button"
        type="submit"
      >{{$t('login')}}</k-button>
    </div>
  </form>
</template>

<script lang="ts">

import { enableResidentKeys } from '../index';
import { arrayBufferToBase64, b64ToArrayBuffer } from '../utils';

export default {
  data() {
    return {
      user: { email: '' }
    };
  },
  computed: {
    resident() {
      return enableResidentKeys;
    },
    fields() {
      return this.resident ? {} : {
        email: {
          type: 'email',
          required: false,
          label: this.$t('email'),
          icon: 'user',
          autocomplete: 'email',
        }
      }
    }
  },
  methods: {
    cancel() {
      this.$emit('cancel');
    },
    async submit() {
      const getArgs = await this.$api.get('passkeys/get/args') as {
        publicKey: PublicKeyCredentialRequestOptions
      };

      getArgs.publicKey.challenge = b64ToArrayBuffer(getArgs.publicKey.challenge);

      try {

        const cred = await navigator.credentials.get( getArgs ) as PublicKeyCredential;
        const assertion = cred.response as AuthenticatorAssertionResponse;

        const response = {
          id: cred.rawId ? arrayBufferToBase64(cred.rawId) : null,
          clientDataJSON: assertion.clientDataJSON ? arrayBufferToBase64(assertion.clientDataJSON) : null,
          authenticatorData: assertion.authenticatorData ? arrayBufferToBase64(assertion.authenticatorData) : null,
          signature: assertion.signature ? arrayBufferToBase64(assertion.signature) : null,
          userHandle: assertion.userHandle ? arrayBufferToBase64(assertion.userHandle) : null
        };

        const login = await this.$api.post('passkeys/get/process', {
          email: this.user.email,
          response: response
        });

        if (login?.success) {
          this.$reload({
            globals: ["$system", "$translation"]
          });
        }

      } catch (e) {
        console.log(e);
        return;
      }
    }
  }
}

</script>