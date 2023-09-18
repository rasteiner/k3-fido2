<template>
    <section class="k-passkey-section" :label="label">
      <header class="k-section-header">
        <k-headline>{{label}}</k-headline>

        <k-button-group v-if="$view.id === 'account'" :buttons="[{
          text: 'Register new',
          click: registerNew,
          icon: 'touch'
        }]" />
      </header>
      <div>
        <div v-if="passkeys.length">
          <k-items
            :items="passkeys.map(p => ({...p, options: []}))"
            :columns="{
              rpId: { label: 'Relying Party', type: 'text' },
              credentialId: { label: 'Credential ID', type: 'text' },
            }"
            layout="table"
          />
        </div>
        <div v-else>
          <k-empty>No passkeys yet.</k-empty>
        </div>
      </div>
    </section>
</template>

<script lang="ts">

import { b64ToArrayBuffer, arrayBufferToBase64 } from '../utils';

export default {
  data() {
    return {
      passkeys: [],
    }
  },

  props: {
    label: String,
  },

  async created() {
    const response = await this.load();
    this.passkeys = response.passkeys;
  },
  
  methods: {
    async registerNew() {
      const args = await this.$api.get('passkeys/create/args');

      // convert strings to ArrayBuffer
      args.publicKey.challenge = b64ToArrayBuffer(args.publicKey?.challenge);
      args.publicKey.allowCredentials = args.publicKey?.allowCredentials?.map((cred) => {
        cred.id = b64ToArrayBuffer(cred.id);
        return cred;
      });
      args.publicKey.excludeCredentials = args.publicKey?.excludeCredentials?.map((cred) => {
        cred.id = b64ToArrayBuffer(cred.id);
        return cred;
      });
      args.publicKey.user.id = b64ToArrayBuffer(args.publicKey?.user?.id);

      // request credentials
      const creds = await navigator.credentials.create(args) as PublicKeyCredential;
      const response = creds?.response as AuthenticatorAttestationResponse;

      if (response?.clientDataJSON) {
        const clientData = arrayBufferToBase64(response.clientDataJSON);
        const attestationObject = arrayBufferToBase64(response.attestationObject);

        const r = await this.$api.post('passkeys/create/process', { clientData, attestationObject });
        this.passkeys = r.passkeys;
      }
    }
  }
}
</script>